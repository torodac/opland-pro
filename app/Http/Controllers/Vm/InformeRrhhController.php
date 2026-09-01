<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Informe RRHH: costes laborales, rotación y evolución de plantilla, con datos reales.
// No existe un concepto de "presupuesto laboral" en la base de datos -- por decisión explícita
// del usuario, el presupuesto se calcula a partir de vm_contratos.salario_base (ya es el bruto
// anual, ver "Salario bruto (€/año)" en usuario.blade.php) + los bonus teóricos de vm_bonus; el
// coste real sale de vm_nominas.coste_total (importado desde el resumen contable en PDF).
//
// Limitación aceptada: vm_usuarios.id_departamento es el departamento ACTUAL de la persona, no
// está historizado por contrato -- los meses pasados de "plantilla por departamento" agrupan a
// cada persona en su departamento de hoy, no en el que tuviera entonces.
class InformeRrhhController extends Controller
{
    // 1=Limpieza, 2=Mantenimiento (mismo criterio que CostesLaboralesController::index()); el
    // resto de departamentos se agregan como "SSCC".
    private const DEPTO_LIMPIEZA      = 1;
    private const DEPTO_MANTENIMIENTO = 2;

    // Solo Dirección general (id_rol=3) y Director RRHH (id_rol=11) -- mismo criterio de roles
    // ya usado para otras pantallas sensibles (ver ROLES_SIN_LIMITE en FichajeController, o el
    // resumen de todos los informes mensuales). Un admin del proyecto siempre tiene acceso.
    private function authorize(Project $project): void
    {
        $user = auth()->user();
        if ($user->isProjectAdmin($project)) return;

        $currentVmUserId = $user->projectUserId($project);
        $authRol = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;

        abort_unless(in_array((int) $authRol, [3, 11], true), 403, 'No tienes permiso para acceder a esta sección.');
    }

    public function index(Request $request, Project $project)
    {
        $this->authorize($project);

        // El informe se ancla al último mes con nómina importada (no al mes natural en curso):
        // así "mes actual" en todo el informe significa "el último mes con datos reales", no un
        // mes que todavía puede estar vacío por retraso en la importación.
        $ultimaNomina   = DB::table('vm_nominas')->where('deleted', 0)->max('mes');
        $fechaRef       = $ultimaNomina ? Carbon::parse($ultimaNomina)->endOfMonth() : Carbon::today();
        $anio      = $fechaRef->year;
        $mesActual = $fechaRef->month;
        $meses     = range(1, $mesActual);
        $mesesEs   = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        $usuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->get(['id', 'nombre', 'cargo', 'id_departamento'])
            ->keyBy('id');

        $departamentos = DB::table('vm_departamentos')
            ->where('deleted', 0)
            ->get(['id', 'nombre'])
            ->keyBy('id');

        $grupoDepto = function (?int $idDepto) {
            if ($idDepto === self::DEPTO_LIMPIEZA) return 'limpieza';
            if ($idDepto === self::DEPTO_MANTENIMIENTO) return 'mantenimiento';
            return 'sscc';
        };

        $contratosPorUsuario = DB::table('vm_contratos')
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['id_usuarios', 'fecha_alta', 'fecha_baja', 'salario_base', 'horas_semana'])
            ->groupBy('id_usuarios');

        $bonusTodos = DB::table('vm_bonus')->where('deleted', 0)
            ->get(['alcance', 'id_referencia', 'meses', 'importe', 'fecha_inicio', 'fecha_fin']);

        $nominasAnio = DB::table('vm_nominas')
            ->where('deleted', 0)
            ->whereBetween('mes', ["{$anio}-01-01", "{$anio}-12-31"])
            ->get(['id_usuario', 'mes', 'coste_total']);
        $nominasPorUsuario = $nominasAnio->groupBy('id_usuario');
        $nominasPorMes = $nominasAnio->groupBy(fn($n) => (int) substr($n->mes, 5, 2))
            ->map(fn($grupo) => $grupo->sum('coste_total'));

        // Bonus (vm_bonus.meses = "1,6,12", números de mes) aplicables a un usuario en un mes
        // concreto del año en curso -- mismo criterio de alcance que VmUsuarioController.php
        // (usuario->id, usuario->cargo, id de departamento -- id_referencia guarda ids reales
        // para usuario/departamento desde la migración 2026_09_01_100000, texto libre solo para
        // cargo), evaluado aquí para todos los usuarios/meses en vez de uno solo.
        $bonusAplicables = function ($usuario, int $mes) use ($bonusTodos, $anio) {
            $inicioMes = Carbon::create($anio, $mes, 1);
            $finMes    = $inicioMes->copy()->endOfMonth();
            return $bonusTodos->filter(function ($b) use ($usuario, $mes, $inicioMes, $finMes) {
                $coincideAlcance =
                    ($b->alcance === 'usuario' && $b->id_referencia === (string) $usuario->id) ||
                    ($b->alcance === 'cargo' && $b->id_referencia === $usuario->cargo) ||
                    ($b->alcance === 'departamento' && $usuario->id_departamento !== null && $b->id_referencia === (string) $usuario->id_departamento);
                if (!$coincideAlcance) return false;
                if (!in_array($mes, array_map('intval', explode(',', $b->meses)), true)) return false;
                if ($b->fecha_inicio && $finMes->lt(Carbon::parse($b->fecha_inicio))) return false;
                if ($b->fecha_fin && $inicioMes->gt(Carbon::parse($b->fecha_fin))) return false;
                return true;
            });
        };

        $contratoActivoEn = function ($contratos, string $fecha) {
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $fecha && (!$c->fecha_baja || $c->fecha_baja >= $fecha)) {
                    return $c;
                }
            }
            return null;
        };

        // ── Series mensuales: coste real/presupuesto, plantilla por grupo, altas/bajas ────────
        $costeReal = $costePresu = $altas = $bajas = [];
        $plantillaPorGrupo = ['sscc' => [], 'mantenimiento' => [], 'limpieza' => []];

        foreach ($meses as $mes) {
            $finMes = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
            $presuMes = 0.0;
            $conteoGrupo = ['sscc' => 0, 'mantenimiento' => 0, 'limpieza' => 0];

            foreach ($usuarios as $uid => $u) {
                $contratos = $contratosPorUsuario[$uid] ?? collect();
                $contratoActivo = $contratoActivoEn($contratos, $finMes);
                if (!$contratoActivo) continue;

                $conteoGrupo[$grupoDepto($u->id_departamento)]++;
                $presuMes += ((float) $contratoActivo->salario_base / 12)
                    + $bonusAplicables($u, $mes)->sum('importe');
            }

            $realMes = (float) ($nominasPorMes[$mes] ?? 0);

            $costeReal[] = round($realMes / 1000, 1);
            $costePresu[] = round($presuMes / 1000, 1);
            foreach ($conteoGrupo as $g => $n) $plantillaPorGrupo[$g][] = $n;
        }

        // Altas = primer fecha_alta (de todos sus contratos) cae en el mes; bajas = último
        // fecha_baja cae en el mes y no hay contrato posterior que lo cubra (no es un hueco entre
        // contratos, es una baja real).
        foreach ($meses as $mes) {
            $altaCount = $bajaCount = 0;
            foreach ($contratosPorUsuario as $contratos) {
                $primero = $contratos->first();
                $ultimo  = $contratos->last();
                if ($primero && (int) substr($primero->fecha_alta, 5, 2) === $mes && (int) substr($primero->fecha_alta, 0, 4) === $anio) {
                    $altaCount++;
                }
                if ($ultimo && $ultimo->fecha_baja
                    && (int) substr($ultimo->fecha_baja, 5, 2) === $mes
                    && (int) substr($ultimo->fecha_baja, 0, 4) === $anio) {
                    $bajaCount++;
                }
            }
            $altas[] = $altaCount;
            $bajas[] = $bajaCount;
        }

        // ── KPIs ───────────────────────────────────────────────────────────────────────────────
        $plantillaActual = 0;
        $antiguedadSumDias = 0;
        foreach ($usuarios as $uid => $u) {
            $contratos = $contratosPorUsuario[$uid] ?? collect();
            if ($contratoActivoEn($contratos, $fechaRef->toDateString())) {
                $plantillaActual++;
                $antiguedadSumDias += Carbon::parse($contratos->first()->fecha_alta)->diffInDays($fechaRef);
            }
        }
        $antiguedadMediaAnios = $plantillaActual > 0 ? round($antiguedadSumDias / $plantillaActual / 365, 1) : 0;

        // Rotación anualizada: bajas de los últimos 12 meses / plantilla media de los últimos 12 meses.
        $hace12 = $fechaRef->copy()->subMonths(12);
        $bajas12m = 0;
        $sumaPlantillaMensual = 0;
        foreach ($contratosPorUsuario as $contratos) {
            $ultimo = $contratos->last();
            if ($ultimo && $ultimo->fecha_baja && Carbon::parse($ultimo->fecha_baja)->gte($hace12)) {
                $bajas12m++;
            }
        }
        for ($i = 0; $i < 12; $i++) {
            $fin = $fechaRef->copy()->subMonths($i)->endOfMonth()->toDateString();
            $sumaPlantillaMensual += $usuarios->filter(fn($u, $uid) => $contratoActivoEn($contratosPorUsuario[$uid] ?? collect(), $fin))->count();
        }
        $plantillaMedia12m = $sumaPlantillaMensual / 12;
        $rotacionAnualizada = $plantillaMedia12m > 0 ? round($bajas12m / $plantillaMedia12m * 100) : 0;

        $costeRealMesActual = $costeReal[count($costeReal) - 1] ?? 0;
        $costePresuMesActual = $costePresu[count($costePresu) - 1] ?? 0;
        $desviacionMesActual = $costePresuMesActual > 0
            ? round((($costeRealMesActual - $costePresuMesActual) / $costePresuMesActual) * 100)
            : 0;
        $costeMedioEmpleado = $plantillaActual > 0 ? round($costeRealMesActual * 1000 / $plantillaActual) : 0;

        // ── Coste acumulado por departamento (año en curso, agrupado a 3) ─────────────────────
        $costeDeptoAcumulado = ['sscc' => 0.0, 'mantenimiento' => 0.0, 'limpieza' => 0.0];
        foreach ($usuarios as $uid => $u) {
            $total = ($nominasPorUsuario[$uid] ?? collect())->sum('coste_total');
            $costeDeptoAcumulado[$grupoDepto($u->id_departamento)] += $total;
        }

        // ── Matriz de coste laboral por persona (agrupada por departamento REAL, no por los 3
        // grupos de los gráficos) ─────────────────────────────────────────────────────────────
        $matriz = [];
        foreach ($usuarios as $uid => $u) {
            $contratos = $contratosPorUsuario[$uid] ?? collect();
            $contratoActivo = $contratoActivoEn($contratos, $fechaRef->toDateString());
            if (!$contratoActivo) continue; // solo plantilla activa hoy

            $plusDpto = $plusPuesto = $plusPersonal = 0.0;
            foreach ($meses as $mes) {
                // Un bonus solo cuenta en los meses en que la persona ya estaba contratada --
                // si empezó en junio, un bonus de "todos los meses" no debe sumar enero-mayo.
                $finDeMes = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
                if (!$contratoActivoEn($contratos, $finDeMes)) continue;
                foreach ($bonusAplicables($u, $mes) as $b) {
                    if ($b->alcance === 'departamento') $plusDpto += (float) $b->importe;
                    elseif ($b->alcance === 'cargo') $plusPuesto += (float) $b->importe;
                    else $plusPersonal += (float) $b->importe;
                }
            }
            $salarioYtd = (float) $contratoActivo->salario_base * ($mesActual / 12);
            $presupuesto = $salarioYtd + $plusDpto + $plusPuesto + $plusPersonal;
            $realYtd = ($nominasPorUsuario[$uid] ?? collect())->sum('coste_total');

            $deptoNombre = $departamentos[$u->id_departamento]->nombre ?? 'Sin departamento';
            $matriz[$deptoNombre][] = [
                'puesto'       => $u->nombre . ($u->cargo ? " ({$u->cargo})" : ''),
                'anual'        => round($salarioYtd),
                'plusDpto'     => round($plusDpto),
                'plusPuesto'   => round($plusPuesto),
                'plusPersonal' => round($plusPersonal),
                'total'        => round($realYtd),
                'presupuesto'  => round($presupuesto),
            ];
        }

        $plantillaMatriz = [];
        foreach ($matriz as $nombre => $empleados) {
            $sampleOf = count($empleados);
            $sampled = $sampleOf > 10;
            $lista = $sampled ? array_slice($empleados, 0, 5) : $empleados;
            $plantillaMatriz[] = [
                'name'            => $nombre,
                'deptAnual'       => array_sum(array_column($empleados, 'anual')),
                'deptPlusDpto'    => array_sum(array_column($empleados, 'plusDpto')),
                'deptPlusPuesto'  => array_sum(array_column($empleados, 'plusPuesto')),
                'deptPlusPersonal'=> array_sum(array_column($empleados, 'plusPersonal')),
                'deptTotal'       => array_sum(array_column($empleados, 'total')),
                'deptPresupuesto' => array_sum(array_column($empleados, 'presupuesto')),
                'sampled'         => $sampled,
                'sampleOf'        => $sampleOf,
                'empleados'       => $lista,
            ];
        }
        usort($plantillaMatriz, fn($a, $b) => $b['deptTotal'] <=> $a['deptTotal']);

        // Mínimo de plantilla del año (para el KPI "Plantilla actual"), sumando los 3 grupos.
        $totalPorMes = [];
        foreach ($meses as $i => $mes) {
            $totalPorMes[$mes] = $plantillaPorGrupo['sscc'][$i] + $plantillaPorGrupo['mantenimiento'][$i] + $plantillaPorGrupo['limpieza'][$i];
        }
        $mesMinimo = array_keys($totalPorMes, min($totalPorMes))[0] ?? $mesActual;
        $plantillaMinima = $totalPorMes[$mesMinimo] ?? $plantillaActual;

        $statsCards = [
            [
                'label' => "Plantilla actual ({$mesesEs[$mesActual]})",
                'value' => (string) $plantillaActual,
                'delta' => $plantillaActual > $plantillaMinima
                    ? sprintf('+%d vs. mínimo del año (%s: %d)', $plantillaActual - $plantillaMinima, $mesesEs[$mesMinimo], $plantillaMinima)
                    : 'Sin variación relevante en el año',
                'cls' => $plantillaActual > $plantillaMinima ? 'up' : '',
            ],
            [
                'label' => "Coste laboral ({$mesesEs[$mesActual]})",
                'value' => number_format($costeRealMesActual * 1000, 0, ',', '.') . ' €',
                'delta' => $desviacionMesActual >= 0
                    ? "+{$desviacionMesActual}% sobre presupuesto (" . number_format($costePresuMesActual * 1000, 0, ',', '.') . ' €)'
                    : "{$desviacionMesActual}% bajo presupuesto (" . number_format($costePresuMesActual * 1000, 0, ',', '.') . ' €)',
                'cls' => $desviacionMesActual > 5 ? 'warn' : '',
            ],
            [
                'label' => 'Coste medio / empleado·mes',
                'value' => number_format($costeMedioEmpleado, 0, ',', '.') . ' €',
                'delta' => "Media de {$mesesEs[$mesActual]} {$anio}",
                'cls' => '',
            ],
            [
                'label' => 'Rotación anualizada',
                'value' => "{$rotacionAnualizada}%",
                'delta' => 'Bajas de los últimos 12 meses sobre la plantilla media',
                'cls' => $rotacionAnualizada >= 50 ? 'warn' : '',
            ],
            [
                'label' => 'Antigüedad media',
                'value' => number_format($antiguedadMediaAnios, 1, ',', '.') . ' años',
                'delta' => "De la plantilla activa en {$mesesEs[$mesActual]}",
                'cls' => '',
            ],
        ];

        return view('vm.informe-rrhh', [
            'project'    => $project,
            'anio'       => $anio,
            'mesesLabels' => array_map(fn($m) => $mesesEs[$m], $meses),
            'costeReal'  => $costeReal,
            'costePresu' => $costePresu,
            'altas'      => $altas,
            'bajas'      => $bajas,
            'plantillaSscc'          => $plantillaPorGrupo['sscc'],
            'plantillaMantenimiento' => $plantillaPorGrupo['mantenimiento'],
            'plantillaLimpieza'      => $plantillaPorGrupo['limpieza'],
            'costeDeptoAcumulado'    => array_map(fn($v) => round($v / 1000), $costeDeptoAcumulado),
            'plantillaMatriz' => $plantillaMatriz,
            'statsCards' => $statsCards,
            'breadcrumb' => [
                ['label' => 'Informe RRHH', 'url' => ''],
            ],
        ]);
    }
}
