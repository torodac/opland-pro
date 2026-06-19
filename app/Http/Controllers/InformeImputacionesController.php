<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformeImputacionesController extends Controller
{
    // ── Métodos públicos ──────────────────────────────────────────────────────

    public function index(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, $userId, $allUsuarios, $canSelect] = $this->resolveParams($request, $project, $user, $isAdmin);

        $data = $this->getInformeData($userId, $year, $month);

        $usuarios = $canSelect
            ? $allUsuarios
            : collect();

        return view('informe-imputaciones', array_merge($data, [
            'project'    => $project,
            'year'       => $year,
            'month'      => $month,
            'user_id'    => $userId,
            'usuarios'   => $usuarios,
            'can_select' => $canSelect,
            'breadcrumb' => [
                ['label' => 'Informe mensual', 'url' => ''],
            ],
        ]));
    }

    public function pdf(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, $userId] = $this->resolveParams($request, $project, $user, $isAdmin);

        $data = $this->getInformeData($userId, $year, $month);

        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $nombre   = str_replace(' ', '_', $data['usuario']->nombre ?? 'usuario');
        $filename = "informe_{$nombre}_{$meses[$month-1]}_{$year}.pdf";

        $pdf = Pdf::loadView('informe-imputaciones-pdf', array_merge($data, [
            'year'  => $year,
            'month' => $month,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    // ── Lógica de datos ───────────────────────────────────────────────────────

    private function resolveParams(Request $request, Project $project, $user, bool $isAdmin): array
    {
        $allUsuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol']);

        $currentVmUserId = $user->projectUserId($project);
        $canSelect       = $isAdmin;

        if ($canSelect) {
            $userId = (int) $request->input('user_id', $currentVmUserId ?? ($allUsuarios->first()->id ?? 0));
        } else {
            $userId = $currentVmUserId ?? 0;
        }

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        return [$year, $month, $userId, $allUsuarios, $canSelect];
    }

    private function getInformeData(int $userId, int $year, int $month): array
    {
        $usuario = DB::table('vm_usuarios')->where('id', $userId)->first();

        $mp  = str_pad($month, 2, '0', STR_PAD_LEFT);
        $ms  = "{$year}-{$mp}-01";
        $dim = (int) Carbon::parse($ms)->daysInMonth;
        $me  = "{$year}-{$mp}-{$dim}";

        // Fichajes del mes
        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$ms, $me])
            ->get()
            ->keyBy('fecha_fichaje');

        // Tipos de ausencia (valores fijos, sin tabla separada)
        $tiposNombres = ['Compensación','Vacaciones','Baja','Asuntos propios','Comp. festivo','Comp. horas','Absentismo'];
        $tipos = collect($tiposNombres)->mapWithKeys(fn($n) => [$n => (object)['nombre' => $n]]);

        // Ausencias del mes expandidas por día
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

        // Horas tareas por día (vm_imputaciones)
        $tareasMin = DB::table('vm_imputaciones as i')
            ->join('master_duraciones as d', 'd.id', '=', 'i.duracion')
            ->where('i.id_usuario', $userId)
            ->whereBetween('i.fecha_imputacion', [$ms, $me])
            ->groupBy('i.fecha_imputacion')
            ->select('i.fecha_imputacion', DB::raw('SUM(d.minutos) as total'))
            ->pluck('total', 'fecha_imputacion');

        // Contratos del usuario ordenados por fecha_alta
        $contratos = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja', 'horas_semana']);

        $dowLabels = ['D','L','M','X','J','V','S'];
        $dias = [];

        for ($d = 1; $d <= $dim; $d++) {
            $fecha = "{$year}-{$mp}-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            $dow   = $dowLabels[(int) date('w', strtotime($fecha))];
            $f     = $fichajes->get($fecha);
            $aus   = $ausDia[$fecha] ?? null;

            $tfMin = null;
            $pMin  = null;
            if ($f && ($f->hora_inicio ?? null) && ($f->hora_fin ?? null)) {
                $tfMin = self::hmsToMinutes($f->hora_fin) - self::hmsToMinutes($f->hora_inicio);
                if (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null)) {
                    $pMin = self::hmsToMinutes($f->pausa_fin) - self::hmsToMinutes($f->pausa_inicio);
                }
            }

            $tipoObj = ($aus && !empty($aus->tipo))
                ? (object)['nombre' => $aus->tipo] : null;

            $htMin = (int) ($tareasMin->get($fecha, 0));

            $contratoDia = null;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $fecha && (is_null($c->fecha_baja) || $c->fecha_baja >= $fecha)) {
                    $contratoDia = $c;
                    break;
                }
            }

            $isRotatorio = $f && ($f->fuera_de_turno ?? 0) == 1;
            $isFestTrab  = $f && ($f->festivo ?? 0) == 1;

            $isCompensacion = $tipoObj && self::categoriaAusencia($tipoObj->nombre) === 'C';

            $heMin           = null;
            $pausaResaltada  = false;
            if ($contratoDia && $contratoDia->horas_semana) {
                $esperadoMin    = (int) round(($contratoDia->horas_semana / 5) * 60);
                $dedPausa       = self::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                $pausaResaltada = $dedPausa > 0;

                if ($isRotatorio) {
                    $heMin = $esperadoMin;
                } elseif ($isFestTrab && $tfMin !== null) {
                    $heMin = $tfMin - $dedPausa;
                } elseif ($isCompensacion) {
                    $heMin = -$esperadoMin;
                } elseif ($tfMin !== null) {
                    $heMin = $tfMin - $esperadoMin - $dedPausa;
                }
            }

            $dias[] = [
                'num'             => $d,
                'dow'             => $dow,
                'fecha'           => $fecha,
                'entrada'         => $f ? substr($f->hora_inicio ?? '', 0, 5) : null,
                'salida'          => ($f && ($f->hora_fin ?? null)) ? substr($f->hora_fin, 0, 5) : null,
                'tf_min'          => $tfMin,
                'p_min'           => $pMin,
                'he_min'          => $heMin,
                'ht_min'          => $htMin,
                'km'              => $f ? (float) ($f->km ?? 0) : null,
                'tipo'            => $tipoObj,
                'aus'             => $aus,
                'weekend'         => in_array($dow, ['D', 'S']),
                'is_rotatorio'    => $isRotatorio,
                'is_fest_trab'    => $isFestTrab,
                'is_festivo'      => false,
                'pausa_resaltada' => $pausaResaltada,
            ];
        }

        $histExtras    = $this->calcularSaldoHistorico($userId, $contratos, $me, $tipos);
        $yearStats     = $this->getYearStats($userId, $year, $month, $tipos, $contratos);

        return [
            'usuario'          => $usuario,
            'dias'             => $dias,
            'tipos'            => $tipos,
            'dim'              => $dim,
            'year_stats'       => $yearStats,
            'hist_extras'      => $histExtras,
            'is_liquidado'     => false,
            'liquidado_fecha'  => null,
            'fecha_horas_extra'=> null,
        ];
    }

    private function calcularSaldoHistorico(int $userId, $contratos, string $hasta, $tipos): float
    {
        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->where('fecha_fichaje', '<=', $hasta)
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin',
                   'pausa_inicio', 'pausa_fin', 'fuera_de_turno', 'festivo']);

        $total = 0.0;
        foreach ($fichajes as $f) {
            $isRot  = ($f->fuera_de_turno ?? 0) == 1;
            $isFest = ($f->festivo ?? 0) == 1;
            $hasFin = !empty($f->hora_fin);

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
        }

        // Descontar días de compensación (tipo varchar)
        $compTipos = $tipos->filter(fn($t) => self::categoriaAusencia($t->nombre) === 'C')
                           ->keys()->toArray(); // claves = nombres

        if (!empty($compTipos)) {
            $compAus = DB::table('vm_ausencias')
                ->where('id_usuarios', $userId)
                ->whereIn('tipo', $compTipos)
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
        }

        return $total / 60;
    }

    private function getYearStats(int $userId, int $year, int $hastaMs, $tipos, $contratos): array
    {
        $labels = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

        $fichajesYear = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->whereBetween('fecha_fichaje', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin',
                   'pausa_inicio', 'pausa_fin', 'fuera_de_turno', 'festivo'])
            ->groupBy(fn($f) => (int) substr($f->fecha_fichaje, 5, 2));

        $stats = [];
        for ($m = 1; $m <= $hastaMs; $m++) {
            $mp = str_pad($m, 2, '0', STR_PAD_LEFT);
            $ms = "{$year}-{$mp}-01";
            $me = "{$year}-{$mp}-" . date('t', strtotime($ms));

            $ep      = 0.0;
            $en      = 0.0;
            $tCount  = 0;

            foreach (($fichajesYear[$m] ?? []) as $f) {
                $isRot  = ($f->fuera_de_turno ?? 0) == 1;
                $isFest = ($f->festivo ?? 0) == 1;
                $hasFin = !empty($f->hora_fin);

                $contratoDia = null;
                foreach ($contratos as $c) {
                    if ($c->fecha_alta <= $f->fecha_fichaje && (is_null($c->fecha_baja) || $c->fecha_baja >= $f->fecha_fichaje)) {
                        $contratoDia = $c;
                        break;
                    }
                }

                $tCount++;

                if ($contratoDia && $contratoDia->horas_semana) {
                    $esperadoMin = (int) round(($contratoDia->horas_semana / 5) * 60);
                    if ($isRot) {
                        $he = $esperadoMin;
                    } elseif ($hasFin) {
                        $tf   = self::hmsToMinutes($f->hora_fin) - self::hmsToMinutes($f->hora_inicio);
                        $pMin = (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null))
                            ? self::hmsToMinutes($f->pausa_fin) - self::hmsToMinutes($f->pausa_inicio)
                            : null;
                        $ded  = self::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                        $he   = $isFest ? $tf - $ded : $tf - $esperadoMin - $ded;
                    } else {
                        continue;
                    }
                    if ($he > 0) $ep += $he;
                    else         $en += $he;
                }
            }

            $diasCol = ['T' => $tCount, 'C' => 0, 'V' => 0, 'B' => 0, 'AA' => 0];

            $ausRaw = DB::table('vm_ausencias')
                ->where('id_usuarios', $userId)
                ->where('fecha_inicio', '<=', $me)
                ->where('fecha_fin',    '>=', $ms)
                ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
                ->get(['fecha_inicio', 'fecha_fin', 'tipo']);

            foreach ($ausRaw as $a) {
                $nombreTipo = $a->tipo ?? '';
                $cat        = self::categoriaAusencia($nombreTipo);
                if (!array_key_exists($cat, $diasCol)) continue;
                $cur = max($a->fecha_inicio, $ms);
                $lim = min($a->fecha_fin,   $me);
                while ($cur <= $lim) {
                    $diasCol[$cat]++;
                    if ($cat === 'C') {
                        foreach ($contratos as $c) {
                            if ($c->fecha_alta <= $cur && (is_null($c->fecha_baja) || $c->fecha_baja >= $cur)) {
                                $en -= (int) round(($c->horas_semana / 5) * 60);
                                break;
                            }
                        }
                    }
                    $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
                }
            }

            // Días laborables (L-V, sin festivos — no hay tabla vm_festivos)
            $lab    = 0;
            $curLab = new \DateTime($ms);
            $endLab = new \DateTime($me);
            while ($curLab <= $endLab) {
                if ((int) $curLab->format('N') <= 5) $lab++;
                $curLab->modify('+1 day');
            }

            $stats[$m] = [
                'label'      => $labels[$m - 1],
                'ep'         => $ep / 60,
                'en'         => $en / 60,
                'total'      => ($ep + $en) / 60,
                'dias_col'   => $diasCol,
                'total_dias' => array_sum($diasCol),
                'lab'        => $lab,
            ];
        }

        return $stats;
    }

    // ── Helpers estáticos ─────────────────────────────────────────────────────

    public static function fmtMin(?int $min, bool $sign = false): string
    {
        if ($min === null || $min === 0) return '';
        $neg = $min < 0;
        $abs = abs($min);
        $h   = (int) floor($abs / 60);
        $m   = $abs % 60;
        $s   = $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
        return ($neg ? '-' : ($sign ? '+' : '')) . $s;
    }

    public static function fmtHoras($h, bool $sign = false): string
    {
        if ($h === null || $h == 0) return '';
        $neg  = $h < 0;
        $abs  = abs((float) $h);
        $hrs  = (int) floor($abs);
        $mins = (int) round(($abs - $hrs) * 60);
        $s    = $hrs . 'h ' . str_pad($mins, 2, '0', STR_PAD_LEFT) . 'm';
        return ($neg ? '-' : ($sign ? '+' : '')) . $s;
    }

    private static function hmsToMinutes(string $t): int
    {
        $p = explode(':', $t);
        return (int) $p[0] * 60 + (int) ($p[1] ?? 0);
    }

    private static function pausaDeducible(?int $pMin, float $horasSemanales): int
    {
        if (!$pMin || $pMin <= 0) return 0;
        $umbral = $horasSemanales >= 40 ? 30 : 15;
        return $pMin > $umbral ? ($pMin - $umbral) : 0;
    }

    private static function categoriaAusencia(string $nombre): string
    {
        $n = mb_strtolower($nombre);
        if (str_starts_with($n, 'comp')) return 'C';
        if (str_contains($n, 'vacac'))  return 'V';
        if (str_contains($n, 'baja'))   return 'B';
        if (str_contains($n, 'asunto')) return 'AA';
        return 'otro';
    }
}
