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
                $heMin = ($heMin ?? 0) + 480;
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
}
