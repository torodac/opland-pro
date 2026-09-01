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
// Dos fechas de referencia distintas, porque no llegan a la vez: la plantilla (altas/bajas,
// antigüedad, rotación) se puede saber al día de hoy con solo vm_contratos, pero el coste real
// depende de que la nómina del mes ya se haya importado -- suele ir 1-2 meses por detrás de hoy.
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

    // "Ppto bruto" = salario_base/12 + bonus teóricos (lo que hasta ahora era todo el
    // "presupuesto"). "Presupuesto" pasa a ser ese bruto + una carga fija del 30% (aproximando el
    // coste de empresa -SS, etc.- que sí lleva vm_nominas.coste_total, para que sea comparable
    // con el coste real). Decisión explícita del usuario, aplicada a todo el informe.
    private const FACTOR_CARGAS_SOCIALES = 1.30;

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

        $hoy    = Carbon::today();
        $anio   = $hoy->year;
        $mesHoy = $hoy->month;
        $mesesEs = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        // El coste (nómina) se ancla al último mes importado, no al mes natural en curso: puede
        // llevar 1-2 meses de retraso respecto a hoy. Si ese último mes cae en un año anterior
        // (p. ej. en enero, antes de importar la primera nómina del año), no hay coste que mostrar
        // este año todavía.
        $ultimaNomina = DB::table('vm_nominas')->where('deleted', 0)->max('mes');
        $fechaCostes  = $ultimaNomina ? Carbon::parse($ultimaNomina)->endOfMonth() : $hoy->copy()->endOfMonth();
        $mesCostes    = $fechaCostes->year === $anio ? $fechaCostes->month : 0;

        $mesesPlantilla = range(1, $mesHoy);
        $mesesCostes    = $mesCostes > 0 ? range(1, $mesCostes) : [];

        // Sin filtro por deleted: alguien archivado (vm_usuarios.deleted=1) puede seguir teniendo
        // meses reales de plantilla/coste este año (p. ej. causó baja en mayo) -- excluirlo de
        // partida infrarrepresentaría el histórico de plantilla y coste de los meses en que sí
        // estuvo. El flag deleted se usa solo para estilo (gris) en la matriz, no para filtrar.
        $usuarios = DB::table('vm_usuarios')
            ->get(['id', 'nombre', 'cargo', 'id_departamento', 'deleted'])
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

        // ── Plantilla por grupo / altas / bajas: hasta hoy (dato de contrato, sin retraso) ───────
        $plantillaPorGrupo = ['sscc' => [], 'mantenimiento' => [], 'limpieza' => []];
        foreach ($mesesPlantilla as $mes) {
            $finMes = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
            $conteoGrupo = ['sscc' => 0, 'mantenimiento' => 0, 'limpieza' => 0];
            foreach ($usuarios as $uid => $u) {
                $contratos = $contratosPorUsuario[$uid] ?? collect();
                if (!$contratoActivoEn($contratos, $finMes)) continue;
                $conteoGrupo[$grupoDepto($u->id_departamento)]++;
            }
            foreach ($conteoGrupo as $g => $n) $plantillaPorGrupo[$g][] = $n;
        }

        // Altas = primer fecha_alta (de todos sus contratos) cae en el mes; bajas = último
        // fecha_baja cae en el mes y no hay contrato posterior que lo cubra (no es un hueco entre
        // contratos, es una baja real). También hasta hoy, no hasta el mes de coste.
        $altas = $bajas = [];
        foreach ($mesesPlantilla as $mes) {
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

        // Plantilla al cierre del año anterior (punto de partida de la cascada) y su evolución mes
        // a mes, para comparar la evolución de este año con la del año pasado en el mismo gráfico.
        $finAnioAnterior = Carbon::create($anio - 1, 12, 31)->toDateString();
        $plantillaInicioAnio = $usuarios->filter(fn($u, $uid) => $contratoActivoEn($contratosPorUsuario[$uid] ?? collect(), $finAnioAnterior))->count();
        $plantillaAnioAnterior = [];
        foreach ($mesesPlantilla as $mes) {
            $finMes = Carbon::create($anio - 1, $mes, 1)->endOfMonth()->toDateString();
            $plantillaAnioAnterior[] = $usuarios->filter(fn($u, $uid) => $contratoActivoEn($contratosPorUsuario[$uid] ?? collect(), $finMes))->count();
        }

        // ── Coste real/presupuesto: solo hasta el último mes con nómina importada ────────────────
        $costeReal = $costePresu = [];
        foreach ($mesesCostes as $mes) {
            $finMes = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
            $presuMes = 0.0;
            foreach ($usuarios as $uid => $u) {
                $contratos = $contratosPorUsuario[$uid] ?? collect();
                $contratoActivo = $contratoActivoEn($contratos, $finMes);
                if (!$contratoActivo) continue;
                $presuMes += ((float) $contratoActivo->salario_base / 12) + $bonusAplicables($u, $mes)->sum('importe');
            }
            $realMes = (float) ($nominasPorMes[$mes] ?? 0);
            $costeReal[] = round($realMes / 1000, 1);
            $costePresu[] = round($presuMes * self::FACTOR_CARGAS_SOCIALES / 1000, 1);
        }

        // ── KPIs de plantilla: a fecha de hoy ─────────────────────────────────────────────────────
        $plantillaActual = 0;
        $antiguedadSumDias = 0;
        foreach ($usuarios as $uid => $u) {
            $contratos = $contratosPorUsuario[$uid] ?? collect();
            if ($contratoActivoEn($contratos, $hoy->toDateString())) {
                $plantillaActual++;
                $antiguedadSumDias += Carbon::parse($contratos->first()->fecha_alta)->diffInDays($hoy);
            }
        }
        $antiguedadMediaAnios = $plantillaActual > 0 ? round($antiguedadSumDias / $plantillaActual / 365, 1) : 0;

        // Rotación anualizada: bajas de los últimos 12 meses / plantilla media de los últimos 12
        // meses, ambos hasta hoy.
        $hace12 = $hoy->copy()->subMonths(12);
        $bajas12m = 0;
        $sumaPlantillaMensual = 0;
        foreach ($contratosPorUsuario as $contratos) {
            $ultimo = $contratos->last();
            if ($ultimo && $ultimo->fecha_baja && Carbon::parse($ultimo->fecha_baja)->gte($hace12)) {
                $bajas12m++;
            }
        }
        for ($i = 0; $i < 12; $i++) {
            $fin = $hoy->copy()->subMonths($i)->endOfMonth()->toDateString();
            $sumaPlantillaMensual += $usuarios->filter(fn($u, $uid) => $contratoActivoEn($contratosPorUsuario[$uid] ?? collect(), $fin))->count();
        }
        $plantillaMedia12m = $sumaPlantillaMensual / 12;
        $rotacionAnualizada = $plantillaMedia12m > 0 ? round($bajas12m / $plantillaMedia12m * 100) : 0;

        // ── KPIs de coste: al último mes con nómina ───────────────────────────────────────────────
        $costeRealMesActual = $costeReal[count($costeReal) - 1] ?? 0;
        $costePresuMesActual = $costePresu[count($costePresu) - 1] ?? 0;
        $desviacionMesActual = $costePresuMesActual > 0
            ? round((($costeRealMesActual - $costePresuMesActual) / $costePresuMesActual) * 100)
            : 0;
        $plantillaEnFechaCostes = $mesCostes > 0
            ? $usuarios->filter(fn($u, $uid) => $contratoActivoEn($contratosPorUsuario[$uid] ?? collect(), $fechaCostes->toDateString()))->count()
            : 0;
        $costeMedioEmpleado = $plantillaEnFechaCostes > 0 ? round($costeRealMesActual * 1000 / $plantillaEnFechaCostes) : 0;

        // ── Coste acumulado por departamento (año en curso, agrupado a 3) ─────────────────────
        $costeDeptoAcumulado = ['sscc' => 0.0, 'mantenimiento' => 0.0, 'limpieza' => 0.0];
        foreach ($usuarios as $uid => $u) {
            $total = ($nominasPorUsuario[$uid] ?? collect())->sum('coste_total');
            $costeDeptoAcumulado[$grupoDepto($u->id_departamento)] += $total;
        }

        // ── Matriz de coste laboral por persona (agrupada por departamento REAL, no por los 3
        // grupos de los gráficos). Incluye también a quien causó baja este año o fue archivado
        // (deleted=1), siempre que tenga contrato o nómina relevante en el año -- su coste real ya
        // pagado no debe desaparecer del informe solo porque hoy ya no esté en plantilla. ───────
        $matriz = [];
        foreach ($usuarios as $uid => $u) {
            $contratos = $contratosPorUsuario[$uid] ?? collect();
            if ($contratos->isEmpty()) continue;

            $contratoActivo = $mesCostes > 0 ? $contratoActivoEn($contratos, $fechaCostes->toDateString()) : null;
            $contratoRelevante = $contratoActivo ?? $contratos->last();

            // Meses (dentro de la ventana de coste, hasta el último mes con nómina) en que la
            // persona tuvo contrato activo -- así el salario prorrateado y los bonus solo cuentan
            // los meses realmente trabajados este año (no todo el año si empezó o se fue a mitad).
            $mesesEmpleado = [];
            foreach ($mesesCostes as $mes) {
                $finDeMes = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
                if ($contratoActivoEn($contratos, $finDeMes)) $mesesEmpleado[] = $mes;
            }

            $realYtd = ($nominasPorUsuario[$uid] ?? collect())->sum('coste_total');
            if (empty($mesesEmpleado) && $realYtd == 0) continue; // sin actividad relevante este año

            $plusDpto = $plusPuesto = $plusPersonal = 0.0;
            foreach ($mesesEmpleado as $mes) {
                foreach ($bonusAplicables($u, $mes) as $b) {
                    if ($b->alcance === 'departamento') $plusDpto += (float) $b->importe;
                    elseif ($b->alcance === 'cargo') $plusPuesto += (float) $b->importe;
                    else $plusPersonal += (float) $b->importe;
                }
            }
            $salarioYtd = (float) ($contratoRelevante->salario_base ?? 0) * (count($mesesEmpleado) / 12);

            // Redondeo por componente y luego suma de los ya redondeados, para que lo que se ve
            // en pantalla (Salario + Bonus...) sume exactamente el Ppto bruto mostrado. El
            // Presupuesto (con el que se compara el coste real) es ese bruto + la carga del 30%.
            $salarioR = round($salarioYtd);
            $plusDptoR = round($plusDpto);
            $plusPuestoR = round($plusPuesto);
            $plusPersonalR = round($plusPersonal);
            $pptoBrutoR = $salarioR + $plusDptoR + $plusPuestoR + $plusPersonalR;
            $presupuestoR = round($pptoBrutoR * self::FACTOR_CARGAS_SOCIALES);

            // Alta/baja de la persona (no del contrato "relevante" para el salario): el alta más
            // antigua de todos sus contratos, y la baja del contrato con el alta más reciente (que
            // puede seguir sin baja si continúa en activo).
            $primerContrato = $contratos->first();
            $ultimoContrato = $contratos->last();

            $deptoNombre = $departamentos[$u->id_departamento]->nombre ?? 'Sin departamento';
            $matriz[$deptoNombre][] = [
                'id'           => $uid,
                'nombre'       => $u->nombre,
                'cargo'        => $u->cargo,
                'deleted'      => (int) ($u->deleted ?? 0) === 1,
                'fechaAlta'    => $primerContrato->fecha_alta ? Carbon::parse($primerContrato->fecha_alta)->format('d/m/y') : null,
                'fechaBaja'    => $ultimoContrato->fecha_baja ? Carbon::parse($ultimoContrato->fecha_baja)->format('d/m/y') : null,
                'anual'        => $salarioR,
                'plusDpto'     => $plusDptoR,
                'plusPuesto'   => $plusPuestoR,
                'plusPersonal' => $plusPersonalR,
                'total'        => round($realYtd),
                'pptoBruto'    => $pptoBrutoR,
                'presupuesto'  => $presupuestoR,
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
                'deptPptoBruto'   => array_sum(array_column($empleados, 'pptoBruto')),
                'deptPresupuesto' => array_sum(array_column($empleados, 'presupuesto')),
                'sampled'         => $sampled,
                'sampleOf'        => $sampleOf,
                'empleados'       => $lista,
            ];
        }
        usort($plantillaMatriz, fn($a, $b) => $b['deptTotal'] <=> $a['deptTotal']);

        // Mínimo de plantilla del año (para el KPI "Plantilla actual"), sumando los 3 grupos.
        $totalPorMes = [];
        foreach ($mesesPlantilla as $i => $mes) {
            $totalPorMes[$mes] = $plantillaPorGrupo['sscc'][$i] + $plantillaPorGrupo['mantenimiento'][$i] + $plantillaPorGrupo['limpieza'][$i];
        }
        $mesMinimo = array_keys($totalPorMes, min($totalPorMes))[0] ?? $mesHoy;
        $plantillaMinima = $totalPorMes[$mesMinimo] ?? $plantillaActual;

        $etiquetaMesCostes = $mesCostes > 0 ? $mesesEs[$mesCostes] : '—';

        $statsCards = [
            [
                'label' => 'Plantilla actual (hoy)',
                'value' => (string) $plantillaActual,
                'delta' => $plantillaActual > $plantillaMinima
                    ? sprintf('+%d vs. mínimo del año (%s: %d)', $plantillaActual - $plantillaMinima, $mesesEs[$mesMinimo], $plantillaMinima)
                    : 'Sin variación relevante en el año',
                'cls' => $plantillaActual > $plantillaMinima ? 'up' : '',
            ],
            [
                'label' => "Coste laboral ({$etiquetaMesCostes})",
                'value' => number_format($costeRealMesActual * 1000, 0, ',', '.') . ' €',
                'delta' => $desviacionMesActual >= 0
                    ? "+{$desviacionMesActual}% sobre presupuesto (" . number_format($costePresuMesActual * 1000, 0, ',', '.') . ' €)'
                    : "{$desviacionMesActual}% bajo presupuesto (" . number_format($costePresuMesActual * 1000, 0, ',', '.') . ' €)',
                'cls' => $desviacionMesActual > 5 ? 'warn' : '',
            ],
            [
                'label' => "Coste medio / empleado·mes ({$etiquetaMesCostes})",
                'value' => number_format($costeMedioEmpleado, 0, ',', '.') . ' €',
                'delta' => "Sobre la plantilla de {$etiquetaMesCostes}",
                'cls' => '',
            ],
            [
                'label' => 'Rotación anualizada',
                'value' => "{$rotacionAnualizada}%",
                'delta' => 'Bajas de los últimos 12 meses hasta hoy sobre la plantilla media',
                'cls' => $rotacionAnualizada >= 50 ? 'warn' : '',
            ],
            [
                'label' => 'Antigüedad media',
                'value' => number_format($antiguedadMediaAnios, 1, ',', '.') . ' años',
                'delta' => 'De la plantilla activa hoy',
                'cls' => '',
            ],
        ];

        // Plantilla de URL a la ficha de usuario (con un id de relleno a sustituir en el cliente),
        // para poder enlazar cada fila de la matriz sin llamar a route() una vez por persona.
        $usuarioFormUrlTemplate = str_replace(
            '999999999',
            '__ID__',
            route('vm.usuario_form', ['project' => $project->slug, 'id' => 999999999])
        );

        return view('vm.informe-rrhh', [
            'project'    => $project,
            'anio'       => $anio,
            'etiquetaMesCostes' => $etiquetaMesCostes,
            'usuarioFormUrlTemplate' => $usuarioFormUrlTemplate,
            'mesesLabels'       => array_map(fn($m) => $mesesEs[$m], $mesesPlantilla),
            'mesesLabelsCostes' => array_map(fn($m) => $mesesEs[$m], $mesesCostes),
            'costeReal'  => $costeReal,
            'costePresu' => $costePresu,
            'altas'      => $altas,
            'bajas'      => $bajas,
            'plantillaInicioAnio'   => $plantillaInicioAnio,
            'plantillaAnioAnterior' => $plantillaAnioAnterior,
            'anioAnterior'          => $anio - 1,
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
