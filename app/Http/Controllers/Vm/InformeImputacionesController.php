<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\VmHorasService;
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

        $hoy = date('Y-m-d');
        $contratosUsuario = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja']);
        $sinContrato = $contratosUsuario->isNotEmpty()
            && $contratosUsuario->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
            && $contratosUsuario->every(fn($c) => $c->fecha_alta <= $hoy);
        $fechaFinContrato = $sinContrato
            ? $contratosUsuario->sortByDesc('fecha_baja')->first()?->fecha_baja
            : null;

        $usuarios = $canSelect
            ? $allUsuarios
            : collect();

        return view('informe-imputaciones', array_merge($data, [
            'project'            => $project,
            'year'               => $year,
            'month'              => $month,
            'user_id'            => $userId,
            'usuarios'           => $usuarios,
            'can_select'         => $canSelect,
            'sin_contrato'       => $sinContrato,
            'fecha_fin_contrato' => $fechaFinContrato,
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

        $hoy = date('Y-m-d');
        $contratosUsuario = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja']);
        $sinContrato = $contratosUsuario->isNotEmpty()
            && $contratosUsuario->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
            && $contratosUsuario->every(fn($c) => $c->fecha_alta <= $hoy);
        $fechaFinContrato = $sinContrato
            ? $contratosUsuario->sortByDesc('fecha_baja')->first()?->fecha_baja
            : null;

        $pdf = Pdf::loadView('informe-imputaciones-pdf', array_merge($data, [
            'year'             => $year,
            'month'            => $month,
            'sin_contrato'     => $sinContrato,
            'fecha_fin_contrato' => $fechaFinContrato,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function pdfTodos(Request $request, Project $project)
    {
        ini_set('memory_limit', '512M');
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        if (!$isAdmin) abort(403);

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        $allUsuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $hoy = date('Y-m-d');
        $pages = [];
        foreach ($allUsuarios as $u) {
            $data = $this->getInformeData($u->id, $year, $month);

            $contratosU = DB::table('vm_contratos')
                ->where('id_usuarios', $u->id)
                ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
                ->orderBy('fecha_alta')
                ->get(['fecha_alta', 'fecha_baja']);
            $sinContrato = $contratosU->isNotEmpty()
                && $contratosU->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
                && $contratosU->every(fn($c) => $c->fecha_alta <= $hoy);
            $fechaFinContrato = $sinContrato
                ? $contratosU->sortByDesc('fecha_baja')->first()?->fecha_baja
                : null;

            $pages[] = view('informe-imputaciones-pdf', array_merge($data, [
                'year'               => $year,
                'month'              => $month,
                'sin_contrato'       => $sinContrato,
                'fecha_fin_contrato' => $fechaFinContrato,
            ]))->render();
        }

        $html = '';
        foreach ($pages as $i => $page) {
            $style = $i > 0 ? ' style="page-break-before:always"' : '';
            $html .= "<div{$style}>{$page}</div>";
        }
        $filename = "informes_{$meses[$month-1]}_{$year}.pdf";

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
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
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;
        $canSelect       = $isAdmin || in_array((int) $authRol, [3, 11]); // Dirección general, Director RRHH

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
        $sede    = $usuario->sede ?? '';

        $mp  = str_pad($month, 2, '0', STR_PAD_LEFT);
        $ms  = "{$year}-{$mp}-01";
        $dim = (int) Carbon::parse($ms)->daysInMonth;
        $me  = "{$year}-{$mp}-{$dim}";

        $festivosDia = VmHorasService::festivosSet($sede, $ms, $me);

        // Fichajes del mes
        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$ms, $me])
            ->get(['fecha_fichaje','hora_inicio','hora_fin','pausa_inicio','pausa_fin',
                   'fuera_de_turno','festivo','km','ajuste_he','ajuste_he_motivo'])
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

        // Horas tareas por día (vm_imputaciones): suma de duracion (minutos) registrada en cada imputación
        $tareasMin = DB::table('vm_imputaciones')
            ->where('id_usuario', $userId)
            ->whereBetween('fecha_imputacion', [$ms, $me])
            ->groupBy('fecha_imputacion')
            ->select('fecha_imputacion', DB::raw('SUM(duracion) as total'))
            ->pluck('total', 'fecha_imputacion');

        // Horarios del mes (descanso, etc.)
        $horariosRaw = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->whereBetween('fecha', [$ms, $me])
            ->get(['fecha', 'tipo']);
        $horarioDia = $horariosRaw->keyBy('fecha');

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
            $hor   = $horarioDia->get($fecha);

            $tfMin = null;
            $pMin  = null;
            if ($f && ($f->hora_inicio ?? null) && ($f->hora_fin ?? null)) {
                $tfMin = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                if (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null)) {
                    $pMin = VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio);
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
            $isFestivo   = isset($festivosDia[$fecha]);

            $isCompensacion = $tipoObj && VmHorasService::categoriaAusencia($tipoObj->nombre) === 'C';

            $heMin = VmHorasService::calcularHeDia(
                $tfMin, $pMin, $tipoObj?->nombre ?? null, $contratoDia,
                $isFestivo, $isRotatorio, $isFestTrab, (bool) $f,
                $hor && $hor->tipo === 'descanso',
                (int) ($f?->ajuste_he ?? 0)
            );
            $pausaResaltada = $contratoDia && $pMin !== null
                && VmHorasService::pausaDeducible($pMin, (float) $contratoDia->horas_semana) > 0;

            $dias[] = [
                'num'             => $d,
                'dow'             => $dow,
                'fecha'           => $fecha,
                'entrada'         => $f ? substr($f->hora_inicio ?? '', 0, 5) : null,
                'salida'          => ($f && ($f->hora_fin ?? null)) ? substr($f->hora_fin, 0, 5) : null,
                'tf_min'          => $tfMin,
                'p_min'           => $pMin,
                'he_min'          => $heMin,
                'ajuste_he'       => (int) ($f?->ajuste_he ?? 0),
                'ht_min'          => $htMin,
                'km'              => $f ? (float) ($f->km ?? 0) : null,
                'tipo'            => $tipoObj,
                'aus'             => $aus,
                'weekend'         => in_array($dow, ['D', 'S']),
                'is_rotatorio'    => $isRotatorio,
                'is_fest_trab'    => $isFestTrab,
                'is_festivo'      => $isFestivo,
                'pausa_resaltada' => $pausaResaltada,
                'horario_tipo'    => $hor ? $hor->tipo : null,
            ];
        }

        $histExtras    = $this->calcularSaldoHistorico($userId, $contratos, $me, $tipos, $sede);
        $yearStats     = $this->getYearStats($userId, $year, $month, $tipos, $contratos, $sede);
        $saldoPrevYear = $this->calcularSaldoHistorico($userId, $contratos, ($year - 1) . '-12-31', $tipos, $sede);

        return [
            'usuario'          => $usuario,
            'dias'             => $dias,
            'ajustes_anio'     => DB::table('vm_fichaje')
                ->where('control_user', $userId)
                ->where('deleted', 0)
                ->where('ajuste_he', '!=', 0)
                ->whereBetween('fecha_fichaje', ["{$year}-01-01", "{$year}-12-31"])
                ->orderBy('fecha_fichaje')
                ->get(['id', 'fecha_fichaje', 'ajuste_he', 'ajuste_he_motivo']),
            'tipos'            => $tipos,
            'dim'              => $dim,
            'year_stats'       => $yearStats,
            'hist_extras'      => $histExtras,
            'saldo_prev_year'  => $saldoPrevYear,
            'is_liquidado'     => false,
            'liquidado_fecha'  => null,
            'fecha_horas_extra'=> null,
        ];
    }

    private function calcularSaldoHistorico(int $userId, $contratos, string $hasta, $tipos, string $sede = ''): float
    {
        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->where('fecha_fichaje', '<=', $hasta)
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin',
                   'pausa_inicio', 'pausa_fin', 'fuera_de_turno', 'festivo', 'ajuste_he']);

        // Festivos hasta la fecha para bono +8h
        $festivosHist = VmHorasService::festivosSet($sede, '2000-01-01', $hasta);

        // Horarios descanso del usuario (para bono festivo sin fichaje)
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
                $tf   = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                $pMin = (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null))
                    ? VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio)
                    : null;
                $ded   = VmHorasService::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                $total += $isFest ? $tf - $ded : $tf - $esperadoMin - $ded;
            }
            if ($isFestivo) $total += 480;
            $total += (int) ($f->ajuste_he ?? 0);
        }

        // Bono festivo por días de descanso en festivo (sin fichaje)
        foreach ($festivosHist as $fDate => $_) {
            if (!isset($descansosDias[$fDate])) continue;
            // Comprobar que no hay fichaje ese día (ya contado arriba)
            $tieneF = $fichajes->contains('fecha_fichaje', $fDate);
            if ($tieneF) continue;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $fDate && (is_null($c->fecha_baja) || $c->fecha_baja >= $fDate)) {
                    $total += 480;
                    break;
                }
            }
        }

        // Descontar días de compensación (tipo varchar)
        $compTipos = $tipos->filter(fn($t) => VmHorasService::categoriaAusencia($t->nombre) === 'C')
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

    private function getYearStats(int $userId, int $year, int $hastaMs, $tipos, $contratos, string $sede = ''): array
    {
        $labels = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

        $fichajesYear = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->whereBetween('fecha_fichaje', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin',
                   'pausa_inicio', 'pausa_fin', 'fuera_de_turno', 'festivo', 'ajuste_he'])
            ->groupBy(fn($f) => (int) substr($f->fecha_fichaje, 5, 2));

        $festivosYear = VmHorasService::festivosSet($sede, "{$year}-01-01", "{$year}-12-31");

        $descansosDias = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->where('tipo', 'descanso')
            ->whereBetween('fecha', ["{$year}-01-01", "{$year}-12-31"])
            ->pluck('fecha')->flip()->all();

        $stats = [];
        for ($m = 1; $m <= $hastaMs; $m++) {
            $mp = str_pad($m, 2, '0', STR_PAD_LEFT);
            $ms = "{$year}-{$mp}-01";
            $me = "{$year}-{$mp}-" . date('t', strtotime($ms));

            $ep      = 0.0;
            $en      = 0.0;
            $tCount  = 0;
            $fichajesFechas = [];

            foreach (($fichajesYear[$m] ?? []) as $f) {
                $isRot  = ($f->fuera_de_turno ?? 0) == 1;
                $isFest = ($f->festivo ?? 0) == 1;
                $hasFin = !empty($f->hora_fin);
                $isFestivo = isset($festivosYear[$f->fecha_fichaje]);
                $fichajesFechas[$f->fecha_fichaje] = true;

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
                        $tf   = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                        $pMin = (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null))
                            ? VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio)
                            : null;
                        $ded  = VmHorasService::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                        $he   = $isFest ? $tf - $ded : $tf - $esperadoMin - $ded;
                    } else {
                        continue;
                    }
                    if ($isFestivo) $he += 480;
                    $ajMin = (int) ($f->ajuste_he ?? 0);
                    $he += $ajMin;
                    if ($he > 0) $ep += $he;
                    else         $en += $he;
                }
            }

            // Bono festivo por descanso en festivo sin fichaje
            foreach ($festivosYear as $fDate => $_) {
                if ($fDate < $ms || $fDate > $me) continue;
                if (isset($fichajesFechas[$fDate])) continue; // ya contado
                if (!isset($descansosDias[$fDate])) continue;
                foreach ($contratos as $c) {
                    if ($c->fecha_alta <= $fDate && (is_null($c->fecha_baja) || $c->fecha_baja >= $fDate)) {
                        $ep += 480;
                        break;
                    }
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
                $cat        = VmHorasService::categoriaAusencia($nombreTipo);
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

            $hasAjuste = collect($fichajesYear[$m] ?? [])->contains(fn($f) => (int)($f->ajuste_he ?? 0) !== 0);
            $stats[$m] = [
                'label'      => $labels[$m - 1],
                'ep'         => $ep / 60,
                'en'         => $en / 60,
                'total'      => ($ep + $en) / 60,
                'has_ajuste' => $hasAjuste,
                'dias_col'   => $diasCol,
                'total_dias' => array_sum($diasCol),
                'lab'        => $lab,
            ];
        }

        return $stats;
    }

    // ── Helpers de formato (vista) ────────────────────────────────────────────

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
}
