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

    // Departamento "de turnos" (vm_departamentos.visible_horarios): determina si los descansos
    // vienen del horario (vm_horarios) o son sábado/domingo por defecto (sin id_departamento o
    // departamento sin visible_horarios, se asume NO de turnos).
    public static function esDeptoTurno(int $userId): bool
    {
        return (bool) DB::table('vm_usuarios')
            ->join('vm_departamentos', 'vm_departamentos.id', '=', 'vm_usuarios.id_departamento')
            ->where('vm_usuarios.id', $userId)
            ->value('vm_departamentos.visible_horarios');
    }

    // Descanso real de un día concreto: para personal de turnos, el que marque su horario
    // (vm_horarios.tipo = 'descanso'); para el resto, sábado y domingo por defecto (no tienen
    // horario, su semana laboral son los días entre semana).
    public static function esDescansoEfectivo(string $fecha, ?string $horarioTipo, bool $esTurno): bool
    {
        if ($esTurno) {
            return $horarioTipo === 'descanso';
        }
        return (int) date('N', strtotime($fecha)) >= 6; // 6=sábado, 7=domingo (ISO-8601)
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
        bool $isFestTrab,
        bool $hasFichaje,
        bool $isDescanso,
        int $ajusteMin = 0,
        bool $esTurno = false
    ): ?int {
        $isCompensacion = $tipoAusencia && self::categoriaAusencia($tipoAusencia) === 'C';

        $heMin = null;
        if ($contrato && $contrato->horas_semana) {
            $esperadoMin = (int) round(($contrato->horas_semana / 5) * 60);
            $dedPausa    = self::pausaDeducible($pMin, (float) $contrato->horas_semana);

            if ($isFestTrab && $tfMin !== null) {
                // Festivo trabajado: la extra es siempre la jornada diaria del contrato, no el
                // tiempo realmente fichado ese día.
                $heMin = $esperadoMin;
            } elseif ($isCompensacion) {
                $heMin = -$esperadoMin;
            } elseif ($tfMin !== null) {
                $heMin = $tfMin - $esperadoMin - $dedPausa;
            }

            // Bono: trabajar un festivo o un día de descanso (real horario si es de turnos, o
            // sábado/domingo si no) cuenta siempre como extra completo -- la resta de esperado en
            // la rama normal + este bono dan el resultado correcto por cancelación algebraica, sin
            // necesitar una rama propia. Sin fichaje, solo si coinciden festivo Y descanso a la vez
            // (recuperar un festivo que cae en tu día libre) -- y solo para departamentos con
            // horario visible (vm_departamentos.visible_horarios): para el resto, un festivo que
            // cae en fin de semana no genera nada, ya que su descanso por defecto ya es ese mismo
            // fin de semana sin que exista un horario real detrás.
            $bono = $hasFichaje
                ? ($isFestivo || $isDescanso) && !$isFestTrab
                : ($esTurno && $isFestivo && $isDescanso);
            if ($bono) {
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
        $esTurno = self::esDeptoTurno($userId);

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
                isset($festivosDia[$fecha]) && (bool) $f,
                (bool) $f,
                self::esDescansoEfectivo($fecha, $hor->tipo ?? null, $esTurno),
                (int) ($f->ajuste_he ?? 0),
                $esTurno
            );

            $cur->modify('+1 day');
        }

        return $result;
    }

    // ── Saldo histórico acumulado ────────────────────────────────────────────

    /**
     * Saldo de horas extra acumulado hasta una fecha, para toda la vida laboral del usuario --
     * misma lógica que el "Σ horas extra" del informe mensual (antes duplicada allí).
     *
     * Devuelve ['total' => saldo histórico real (horas), 'dias_fest' => (horas de "Trab. fest."
     * menos las compensadas con "Comp. festivo") / horas de contrato, en días con 1 decimal --
     * puede salir negativo si se ha compensado de más, o fraccionario si no es un día completo.
     * 'horas_resto' => suma de horas extra de los días "Trabajo" + "Trab. desc." (no festivo)]
     */
    public static function saldoAcumuladoHoras(int $userId, string $hasta): array
    {
        $usuario = DB::table('vm_usuarios')->where('id', $userId)->first();
        $sede    = $usuario->sede ?? '';
        $esTurno = self::esDeptoTurno($userId);

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
                   'pausa_inicio', 'pausa_fin', 'ajuste_he']);

        $festivosHist = self::festivosSet($sede, '2000-01-01', $hasta);

        $descansosDias = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->where('tipo', 'descanso')
            ->where('fecha', '<=', $hasta)
            ->pluck('fecha')->flip()->all();

        $esDescanso = fn(string $fecha) => $esTurno
            ? isset($descansosDias[$fecha])
            : ((int) date('N', strtotime($fecha)) >= 6); // 6=sábado, 7=domingo

        $total      = 0.0;
        $festMin    = 0; // horas extra (con ajuste) de los días "Trab. fest." trabajados
        $trabajoMin = 0; // horas extra (con ajuste) de los días "Trabajo"/"Trab. desc."
        foreach ($fichajes as $f) {
            $hasFin = !empty($f->hora_fin);
            $isFestivo   = isset($festivosHist[$f->fecha_fichaje]);
            // Festivo trabajado = el día es festivo según vm_festivos, ya no depende del
            // checkbox manual vm_fichaje.festivo (sustituido por completo).
            $isFest = $isFestivo;
            $isDescansoEf = $esDescanso($f->fecha_fichaje);

            $contratoDia = null;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $f->fecha_fichaje && (is_null($c->fecha_baja) || $c->fecha_baja >= $f->fecha_fichaje)) {
                    $contratoDia = $c;
                    break;
                }
            }
            if (!$contratoDia || !$contratoDia->horas_semana) continue;

            $esperadoMin = (int) round(($contratoDia->horas_semana / 5) * 60);
            $diaMin = 0;
            if ($hasFin) {
                $tf   = self::hmsToMinutes($f->hora_fin) - self::hmsToMinutes($f->hora_inicio);
                $pMin = (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null))
                    ? self::hmsToMinutes($f->pausa_fin) - self::hmsToMinutes($f->pausa_inicio)
                    : null;
                $ded   = self::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                // Festivo trabajado: la extra es siempre la jornada diaria del contrato, no el
                // tiempo realmente fichado ese día (mismo criterio que calcularHeDia()).
                $diaMin = $isFest ? $esperadoMin : $tf - $esperadoMin - $ded;
            }
            // El bono es para festivos o descansos SIN trabajar (fichaje.festivo=false) o sin
            // fichaje (bloque de más abajo) -- si ya se trabajó, todo lo trabajado cuenta como
            // extra en la rama de arriba, y sumar el bono aquí sería contarlo dos veces. El bono
            // son las horas de contrato del día, no un fijo de 8h para todos.
            if (($isFestivo || $isDescansoEf) && !$isFest) $diaMin += $esperadoMin;

            $diaTotal = $diaMin + (int) ($f->ajuste_he ?? 0);

            // Desglose (misma clasificación que la pill "Tipo" del informe mensual): festivo
            // trabajado real o marcado manualmente -> "Trab. fest."; el resto de días fichados
            // (normal o descanso trabajado) -> bloque "Trabajo"/"Trab. desc.".
            $trabajaFestivo = $hasFin && ($isFest || $isFestivo);
            if ($trabajaFestivo) {
                $festMin += $diaTotal;
            } elseif ($hasFin) {
                $trabajoMin += $diaTotal;
            }

            $total += $diaTotal;
        }

        // Bono festivo por días de descanso en festivo (sin fichaje) -- las horas de contrato del
        // día, no un fijo de 8h (relevante para contratos con jornada diaria distinta de 8h).
        // Solo para departamentos con horario visible (visible_horarios): para el resto, un
        // festivo en fin de semana no genera nada (su descanso por defecto ya es ese fin de
        // semana, sin un horario real detrás).
        if ($esTurno) {
            foreach ($festivosHist as $fDate => $_) {
                if (!$esDescanso($fDate)) continue;
                $tieneF = $fichajes->contains('fecha_fichaje', $fDate);
                if ($tieneF) continue;
                foreach ($contratos as $c) {
                    if ($c->fecha_alta <= $fDate && (is_null($c->fecha_baja) || $c->fecha_baja >= $fDate)) {
                        $total += (int) round(($c->horas_semana / 5) * 60);
                        break;
                    }
                }
            }
        }

        // Descontar días de compensación (cualquier tipo de ausencia de categoría 'C')
        $compAus = DB::table('vm_ausencias')
            ->where('id_usuarios', $userId)
            ->where('tipo', 'ilike', 'comp%')
            ->where('fecha_fin', '<=', $hasta)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->get(['fecha_inicio', 'fecha_fin', 'tipo']);

        $compFestMin = 0; // horas descontadas específicamente por "Comp. festivo"

        foreach ($compAus as $a) {
            $cur = $a->fecha_inicio;
            $lim = min($a->fecha_fin, $hasta);
            while ($cur <= $lim) {
                foreach ($contratos as $c) {
                    if ($c->fecha_alta <= $cur && (is_null($c->fecha_baja) || $c->fecha_baja >= $cur)) {
                        $ded = (int) round(($c->horas_semana / 5) * 60);
                        $total -= $ded;
                        if ($a->tipo === 'Comp. festivo') $compFestMin += $ded;
                        break;
                    }
                }
                $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
            }
        }

        // Días de contrato de referencia para expresar el saldo de festivos en "días": el vigente
        // a la fecha del corte, o el último conocido si a esa fecha no hay ninguno activo.
        $contratoRef = null;
        foreach ($contratos as $c) {
            if ($c->fecha_alta <= $hasta && (is_null($c->fecha_baja) || $c->fecha_baja >= $hasta)) {
                $contratoRef = $c;
                break;
            }
        }
        $contratoRef ??= $contratos->last();
        $esperadoRefMin = ($contratoRef && $contratoRef->horas_semana)
            ? (int) round(($contratoRef->horas_semana / 5) * 60)
            : 0;

        $diasFest = $esperadoRefMin > 0 ? round(($festMin - $compFestMin) / $esperadoRefMin, 1) : 0.0;

        return [
            'total'       => $total / 60,
            'dias_fest'   => $diasFest,
            'horas_resto' => $trabajoMin / 60,
        ];
    }

}
