<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class VmHorasService
{
    // ── Helpers compartidos ──────────────────────────────────────────────────

    public static function hmsToMinutes(string $t): int
    {
        $p = explode(':', $t);
        return (int) $p[0] * 60 + (int) ($p[1] ?? 0);
    }

    public static function pausaDeducible(?int $pMin, float $horasSemanales): int
    {
        if (!$pMin || $pMin <= 0) return 0;
        $umbral = $horasSemanales >= 40 ? 30 : 15;
        return $pMin > $umbral ? ($pMin - $umbral) : 0;
    }

    public static function categoriaAusencia(string $nombre): string
    {
        $n = mb_strtolower($nombre);
        if (str_starts_with($n, 'comp')) return 'C';
        if (str_contains($n, 'vacac'))  return 'V';
        if (str_contains($n, 'baja'))   return 'B';
        if (str_contains($n, 'asunto')) return 'AA';
        return 'otro';
    }

    public static function festivosSet(string $sede, string $desde, string $hasta): array
    {
        $q = DB::table('vm_festivos')
            ->where('deleted', 0)
            ->whereBetween('fecha_fecha', [$desde, $hasta]);
        if ($sede) {
            $q->where(fn($w) => $w->whereNull('sede')->orWhere('sede', '')->orWhere('sede', $sede));
        }
        return $q->pluck('fecha_fecha')->map(fn($d) => (string) $d)->flip()->all();
    }

    // ── Cálculo HE diario (lógica compartida) ────────────────────────────────

    /**
     * Calcula horas extra (en minutos) para un día concreto.
     * Es la fuente de verdad única: tanto InformeImputacionesController
     * como VmUsuarioController deben delegar aquí.
     */
    public static function calcularHeDia(
        ?int $tfMin,
        ?int $pMin,
        ?string $tipoAusencia,
        ?object $contrato,
        bool $isFestivo,
        bool $isRotatorio,
        bool $isFestTrab,
        bool $hasFichaje,
        bool $isDescanso,
        int $ajusteMin = 0
    ): ?int {
        $isCompensacion = $tipoAusencia && self::categoriaAusencia($tipoAusencia) === 'C';

        $heMin = null;
        if ($contrato && $contrato->horas_semana) {
            $esperadoMin = (int) round(($contrato->horas_semana / 5) * 60);
            $dedPausa    = self::pausaDeducible($pMin, (float) $contrato->horas_semana);

            if ($isRotatorio) {
                $heMin = $esperadoMin;
            } elseif ($isFestTrab && $tfMin !== null) {
                $heMin = $tfMin - $dedPausa;
            } elseif ($isCompensacion) {
                $heMin = -$esperadoMin;
            } elseif ($tfMin !== null) {
                $heMin = $tfMin - $esperadoMin - $dedPausa;
            }

            if ($isFestivo && !$isFestTrab && !$isRotatorio && ($hasFichaje || $isDescanso)) {
                $heMin = ($heMin ?? 0) + $esperadoMin;
            }
        }

        if ($heMin !== null && $ajusteMin !== 0) {
            $heMin += $ajusteMin;
        }

        return $heMin;
    }

    // ── Cálculo anual para ficha de usuario ──────────────────────────────────

    /**
     * Devuelve mapa fecha → he_min para todo un año.
     * Hace 6 queries totales (no por mes) para ser eficiente.
     *
     * @return array<string, int|null>
     */
    public static function calcularAnio(int $userId, int $year): array
    {
        $usuario = DB::table('vm_usuarios')->where('id', $userId)->first();
        $sede    = $usuario->sede ?? '';

        $ms = "{$year}-01-01";
        $me = "{$year}-12-31";

        $festivosDia = self::festivosSet($sede, $ms, $me);

        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$ms, $me])
            ->get()
            ->keyBy('fecha_fichaje');

        $ausenciasRaw = DB::table('vm_ausencias')
            ->where('id_usuarios', $userId)
            ->where('fecha_inicio', '<=', $me)
            ->where('fecha_fin',    '>=', $ms)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->get();

        $ausDia = [];
        foreach ($ausenciasRaw as $a) {
            $cur = max($a->fecha_inicio, $ms);
            $lim = min($a->fecha_fin,   $me);
            while ($cur <= $lim) {
                $ausDia[$cur] = $a;
                $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
            }
        }

        $horarioDia = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->whereBetween('fecha', [$ms, $me])
            ->get(['fecha', 'tipo'])
            ->keyBy('fecha');

        $contratos = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja', 'horas_semana']);

        $result = [];
        $cur    = new \DateTime($ms);
        $end    = new \DateTime($me);

        while ($cur <= $end) {
            $fecha = $cur->format('Y-m-d');
            $f     = $fichajes->get($fecha);
            $aus   = $ausDia[$fecha] ?? null;
            $hor   = $horarioDia->get($fecha);

            $tfMin = null;
            $pMin  = null;
            if ($f && ($f->hora_inicio ?? null) && ($f->hora_fin ?? null)) {
                $tfMin = self::hmsToMinutes($f->hora_fin) - self::hmsToMinutes($f->hora_inicio);
                if (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null)) {
                    $pMin = self::hmsToMinutes($f->pausa_fin) - self::hmsToMinutes($f->pausa_inicio);
                }
            }

            $contratoDia = null;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $fecha && (is_null($c->fecha_baja) || $c->fecha_baja >= $fecha)) {
                    $contratoDia = $c;
                    break;
                }
            }

            $result[$fecha] = self::calcularHeDia(
                $tfMin,
                $pMin,
                $aus->tipo ?? null,
                $contratoDia,
                isset($festivosDia[$fecha]),
                $f && ($f->fuera_de_turno ?? 0) == 1,
                $f && ($f->festivo ?? 0) == 1,
                (bool) $f,
                $hor && $hor->tipo === 'descanso',
                (int) ($f->ajuste_he ?? 0)
            );

            $cur->modify('+1 day');
        }

        return $result;
    }

    // ── Saldo histórico acumulado ────────────────────────────────────────────

    /**
     * Saldo de horas extra acumulado (en horas) hasta una fecha, para toda la
     * vida laboral del usuario -- misma lógica que el "Σ horas extra" del
     * informe mensual (antes duplicada en InformeImputacionesController).
     */
    public static function saldoAcumuladoHoras(int $userId, string $hasta): float
    {
        $usuario = DB::table('vm_usuarios')->where('id', $userId)->first();
        $sede    = $usuario->sede ?? '';

        $contratos = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja', 'horas_semana']);

        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->where('fecha_fichaje', '<=', $hasta)
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin',
                   'pausa_inicio', 'pausa_fin', 'fuera_de_turno', 'festivo', 'ajuste_he']);

        $festivosHist = self::festivosSet($sede, '2000-01-01', $hasta);

        $descansosDias = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->where('tipo', 'descanso')
            ->where('fecha', '<=', $hasta)
            ->pluck('fecha')->flip()->all();

        $total = 0.0;
        foreach ($fichajes as $f) {
            $isRot  = ($f->fuera_de_turno ?? 0) == 1;
            $isFest = ($f->festivo ?? 0) == 1;
            $hasFin = !empty($f->hora_fin);
            $isFestivo = isset($festivosHist[$f->fecha_fichaje]);

            $contratoDia = null;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $f->fecha_fichaje && (is_null($c->fecha_baja) || $c->fecha_baja >= $f->fecha_fichaje)) {
                    $contratoDia = $c;
                    break;
                }
            }
            if (!$contratoDia || !$contratoDia->horas_semana) continue;

            $esperadoMin = (int) round(($contratoDia->horas_semana / 5) * 60);
            if ($isRot) {
                $total += $esperadoMin;
            } elseif ($hasFin) {
                $tf   = self::hmsToMinutes($f->hora_fin) - self::hmsToMinutes($f->hora_inicio);
                $pMin = (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null))
                    ? self::hmsToMinutes($f->pausa_fin) - self::hmsToMinutes($f->pausa_inicio)
                    : null;
                $ded   = self::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                $total += $isFest ? $tf - $ded : $tf - $esperadoMin - $ded;
            }
            // El bono es para festivos SIN trabajar (fichaje.festivo=false) o sin fichaje
            // (bloque de descanso, más abajo) -- si ya se trabajó el festivo, todo lo trabajado
            // cuenta como extra en la rama de arriba, y sumar el bono aquí sería contarlo dos veces.
            // El bono son las horas de contrato del día, no un fijo de 8h para todos.
            if ($isFestivo && !$isFest && !$isRot) $total += $esperadoMin;
            $total += (int) ($f->ajuste_he ?? 0);
        }

        // Bono festivo por días de descanso en festivo (sin fichaje) -- las horas de contrato del
        // día, no un fijo de 8h (relevante para contratos con jornada diaria distinta de 8h).
        foreach ($festivosHist as $fDate => $_) {
            if (!isset($descansosDias[$fDate])) continue;
            $tieneF = $fichajes->contains('fecha_fichaje', $fDate);
            if ($tieneF) continue;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $fDate && (is_null($c->fecha_baja) || $c->fecha_baja >= $fDate)) {
                    $total += (int) round(($c->horas_semana / 5) * 60);
                    break;
                }
            }
        }

        // Descontar días de compensación (cualquier tipo de ausencia de categoría 'C')
        $compAus = DB::table('vm_ausencias')
            ->where('id_usuarios', $userId)
            ->where('tipo', 'ilike', 'comp%')
            ->where('fecha_fin', '<=', $hasta)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->get(['fecha_inicio', 'fecha_fin']);

        foreach ($compAus as $a) {
            $cur = $a->fecha_inicio;
            $lim = min($a->fecha_fin, $hasta);
            while ($cur <= $lim) {
                foreach ($contratos as $c) {
                    if ($c->fecha_alta <= $cur && (is_null($c->fecha_baja) || $c->fecha_baja >= $cur)) {
                        $total -= (int) round(($c->horas_semana / 5) * 60);
                        break;
                    }
                }
                $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
            }
        }

        return $total / 60;
    }

    // Nº de días fichados como festivo trabajado (vm_fichaje.festivo = true), acumulado histórico.
    public static function festivosTrabajadosCount(int $userId): int
    {
        return DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->where('festivo', true)
            ->count();
    }
}
