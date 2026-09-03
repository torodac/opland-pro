<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CostesLaboralesPropiedadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostesLaboralesController extends Controller
{
    private function authorize(Project $project): void
    {
        // Mismo gate que el resto de informes financieros/operativos de vm (RRHH/Dirección
        // no tienen por qué ver el reparto de coste laboral por propiedad; es un informe de
        // gestión de Operaciones/Dirección general/Contabilidad).
        $user  = auth()->user();
        $rolId = (int) (DB::table('vm_usuarios')->where('admin_user_id', $user->id)->value('id_rol') ?? 0);
        abort_unless($user->isProjectAdmin($project) || in_array($rolId, [3, 10, 7], true), 403);
    }

    public function index(Request $request, Project $project)
    {
        $this->authorize($project);

        // Por defecto (sin anio/mes en la URL) se ancla al último mes con nómina importada, no al
        // mes natural de hoy: la nómina va con retraso (importación manual desde el resumen
        // contable), así que el mes actual normalmente no tiene nada que repartir todavía y el
        // botón "Calcular este mes" parecía no hacer nada (calculaba 0 € por falta de datos).
        if ($request->filled('anio') || $request->filled('mes')) {
            $anio = (int) ($request->input('anio') ?: now()->year);
            $mes  = (int) ($request->input('mes') ?: now()->month);
        } else {
            $ultimaNomina = DB::table('vm_nominas')->where('deleted', 0)->max('mes');
            $fechaDefault = $ultimaNomina ? Carbon::parse($ultimaNomina) : now();
            $anio = $fechaDefault->year;
            $mes  = $fechaDefault->month;
        }

        $inicioMes = Carbon::create($anio, $mes, 1);
        $finMes    = $inicioMes->copy()->endOfMonth();

        // Todas las propiedades con importe ese mes -- incluidas las ya de baja o sin
        // fecha_inicio (p.ej. ALMACEN), que antes quedaban fuera por filtrarse contra el
        // listado de "propiedades activas" y hacían que esta tabla no cuadrase con la de
        // trabajadores (ver conversación: 2.467,51 € de diferencia en marzo 2026).
        // minutos: solo cuenta el reparto por minutos propios (origen='propio'); las filas de
        // fallback por peso de grupo no tienen minutos reales de esa propiedad (minutos=null,
        // SUM los ignora), así que las horas mostradas son siempre horas de trabajo real.
        $costes = DB::table('vm_costes_laborales_propiedad as c')
            ->join('vm_propiedades as p', 'p.id', '=', 'c.id_propiedades')
            ->where('c.anio', $anio)->where('c.mes', $mes)->where('c.deleted', 0)
            ->selectRaw('c.id_propiedades, p.nombre as propiedad, c.tipo, sum(c.coste) as coste, sum(c.minutos) as minutos')
            ->groupBy('c.id_propiedades', 'p.nombre', 'c.tipo')
            ->get()
            ->groupBy('id_propiedades');

        $filasListado = $costes->map(function ($deEsta) {
            $filaLimpieza = $deEsta->firstWhere('tipo', 'limpieza');
            $filaMantenimiento = $deEsta->firstWhere('tipo', 'mantenimiento');
            $limpieza = round((float) ($filaLimpieza->coste ?? 0), 2);
            $mantenimiento = round((float) ($filaMantenimiento->coste ?? 0), 2);
            return (object) [
                'propiedad'          => $deEsta->first()->propiedad,
                'limpieza'           => $limpieza,
                'mantenimiento'      => $mantenimiento,
                'total'              => round($limpieza + $mantenimiento, 2),
                'horas_limpieza'     => round(((float) ($filaLimpieza->minutos ?? 0)) / 60, 1),
                'horas_mantenimiento'=> round(((float) ($filaMantenimiento->minutos ?? 0)) / 60, 1),
            ];
        })->sortBy('propiedad')->values();

        $totales = (object) [
            'limpieza'            => round($filasListado->sum('limpieza'), 2),
            'mantenimiento'       => round($filasListado->sum('mantenimiento'), 2),
            'total'               => round($filasListado->sum('total'), 2),
            'horas_limpieza'      => round($filasListado->sum('horas_limpieza'), 1),
            'horas_mantenimiento' => round($filasListado->sum('horas_mantenimiento'), 1),
        ];

        // Minutos imputados por trabajador ese mes, en el tipo de imputación que corresponde a
        // su propio departamento (Limpieza=1 -> tipo 'limpieza', Mantenimiento=2 -> tipo
        // 'mantenimiento') -- mismo criterio de "minutos propios" que usa
        // CostesLaboralesPropiedadService para el reparto.
        $tipoPorDepartamento = [1 => 'limpieza', 2 => 'mantenimiento'];
        $imputacionesDelMes = DB::table('vm_imputaciones')
            ->whereIn('tipo', ['limpieza', 'mantenimiento'])
            ->whereBetween('fecha_imputacion', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->groupBy('id_usuario', 'tipo')
            ->selectRaw('id_usuario, tipo, sum(duracion) as minutos')
            ->get();
        $minutosPorUsuarioTipo = $imputacionesDelMes->groupBy('id_usuario');

        // Horas imputadas por tipo, directas de vm_imputaciones -- independientes de si la
        // nómina de ese mes ya se ha importado o no. $totalesTrabajadores (más abajo) solo
        // incluye a quien ya tiene nómina ese mes (join con vm_nominas), así que si la nómina va
        // con retraso (normal: julio/agosto ya tienen horas fichadas pero aún no nómina) sus
        // horas quedaban en 0 aunque el trabajo ya estuviera registrado.
        $horasImputadasPorTipoMes = $imputacionesDelMes->groupBy('tipo')
            ->map(fn($grupo) => round($grupo->sum('minutos') / 60, 1));

        // Minutos fichados por trabajador ese mes (vm_fichaje.control_user = vm_usuarios.id):
        // (hora_fin - hora_inicio) menos la pausa si está registrada, sumado por trabajador --
        // mismo cálculo que usa DashboardController para comparar fichaje contra imputaciones.
        // Solo fichajes con hora_fin (los abiertos no aportan horas todavía).
        $minutosFichadosPorUsuario = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->whereNotNull('control_user')
            ->whereNotNull('hora_inicio')
            ->whereNotNull('hora_fin')
            ->groupBy('control_user')
            ->selectRaw("control_user, SUM(
                EXTRACT(EPOCH FROM (hora_fin - hora_inicio)) / 60
                - CASE WHEN pausa_inicio IS NOT NULL AND pausa_fin IS NOT NULL
                       THEN EXTRACT(EPOCH FROM (pausa_fin - pausa_inicio)) / 60 ELSE 0 END
            ) as minutos")
            ->pluck('minutos', 'control_user');

        // Mismos trabajadores que reparte CostesLaboralesPropiedadService (departamentos
        // Limpieza=1/Mantenimiento=2), pero sin desglosar por propiedad -- su coste_total de
        // nómina de ese mes es directamente su fila (cada trabajador es de un único
        // departamento, así que solo una de las dos columnas tiene importe).
        $mesNomina = $inicioMes->toDateString();
        $filasTrabajadores = DB::table('vm_nominas as n')
            ->join('vm_usuarios as u', 'u.id', '=', 'n.id_usuario')
            ->whereIn('u.id_departamento', [1, 2])
            ->where('n.mes', $mesNomina)->where('n.deleted', 0)
            ->orderBy('u.nombre')
            ->get(['u.id as id_usuario', 'u.nombre', 'u.id_departamento', 'n.coste_total'])
            ->map(function ($n) use ($minutosPorUsuarioTipo, $tipoPorDepartamento, $minutosFichadosPorUsuario) {
                $tipo = $tipoPorDepartamento[(int) $n->id_departamento];
                $minutos = (int) ($minutosPorUsuarioTipo->get($n->id_usuario, collect())->firstWhere('tipo', $tipo)->minutos ?? 0);
                $horas = round($minutos / 60, 1);
                $horasFichadas = round(((float) ($minutosFichadosPorUsuario[$n->id_usuario] ?? 0)) / 60, 1);
                $costeTotal = round((float) $n->coste_total, 2);
                return (object) [
                    'nombre'         => $n->nombre,
                    'limpieza'       => (int) $n->id_departamento === 1 ? $costeTotal : 0,
                    'mantenimiento'  => (int) $n->id_departamento === 2 ? $costeTotal : 0,
                    'total'          => $costeTotal,
                    'horas'          => $horas,
                    'horas_limpieza'      => (int) $n->id_departamento === 1 ? $horas : 0,
                    'horas_mantenimiento' => (int) $n->id_departamento === 2 ? $horas : 0,
                    'horas_fichadas' => $horasFichadas,
                    'coste_hora'     => $horas > 0 ? round($costeTotal / $horas, 2) : null,
                ];
            });

        $totalHorasTrabajadores = round($filasTrabajadores->sum('horas'), 1);
        $totalesTrabajadores = (object) [
            'limpieza'       => round($filasTrabajadores->sum('limpieza'), 2),
            'mantenimiento'  => round($filasTrabajadores->sum('mantenimiento'), 2),
            'total'          => round($filasTrabajadores->sum('total'), 2),
            'horas'          => $totalHorasTrabajadores,
            'horas_limpieza'      => round($filasTrabajadores->sum('horas_limpieza'), 1),
            'horas_mantenimiento' => round($filasTrabajadores->sum('horas_mantenimiento'), 1),
            'horas_fichadas' => round($filasTrabajadores->sum('horas_fichadas'), 1),
            'coste_hora'     => $totalHorasTrabajadores > 0 ? round($filasTrabajadores->sum('total') / $totalHorasTrabajadores, 2) : null,
        ];

        $hayCalculo = DB::table('vm_costes_laborales_propiedad')
            ->where('anio', $anio)->where('mes', $mes)->where('deleted', 0)->exists();

        // "¿Hace falta recalcular?": importe de nómina y horas imputadas ACTUALES (de
        // $totalesTrabajadores, siempre al día) frente a lo que quedó guardado en el último
        // reparto ($totales, de vm_costes_laborales_propiedad) -- si la variación supera el 2% (o
        // no hay nada repartido todavía), se marca para avisar de que conviene recalcular.
        $variacion = function (float $actual, float $repartido) {
            if (!$repartido) return $actual != 0.0 ? 100.0 : 0.0;
            return (($actual - $repartido) / $repartido) * 100;
        };
        $statsRecalculo = [
            ['label' => 'Nómina Limpieza',      'actual' => $totalesTrabajadores->limpieza,           'repartido' => $totales->limpieza,           'unidad' => '€'],
            ['label' => 'Nómina Mantenimiento', 'actual' => $totalesTrabajadores->mantenimiento,      'repartido' => $totales->mantenimiento,      'unidad' => '€'],
            ['label' => 'Horas Limpieza',       'actual' => (float) ($horasImputadasPorTipoMes['limpieza'] ?? 0),      'repartido' => $totales->horas_limpieza,     'unidad' => 'h'],
            ['label' => 'Horas Mantenimiento',  'actual' => (float) ($horasImputadasPorTipoMes['mantenimiento'] ?? 0), 'repartido' => $totales->horas_mantenimiento,'unidad' => 'h'],
        ];
        foreach ($statsRecalculo as &$s) {
            $s['variacion'] = $hayCalculo ? $variacion($s['actual'], $s['repartido']) : null;
            $s['fueraDeRango'] = $s['variacion'] !== null && abs($s['variacion']) > 2;
        }
        unset($s);

        $mesLabel = $inicioMes->translatedFormat('F Y');
        $anterior = Carbon::create($anio, $mes, 1)->subMonth();
        $siguiente = Carbon::create($anio, $mes, 1)->addMonth();
        $urlAnterior  = request()->fullUrlWithQuery(['anio' => $anterior->year, 'mes' => $anterior->month]);
        $urlSiguiente = request()->fullUrlWithQuery(['anio' => $siguiente->year, 'mes' => $siguiente->month]);

        return view('vm.costes-laborales', [
            'project'      => $project,
            'anio'         => $anio,
            'mes'          => $mes,
            'mesLabel'     => $mesLabel,
            'urlAnterior'  => $urlAnterior,
            'urlSiguiente' => $urlSiguiente,
            'filas'               => $filasListado,
            'totales'             => $totales,
            'statsRecalculo'      => $statsRecalculo,
            'filasTrabajadores'   => $filasTrabajadores,
            'totalesTrabajadores' => $totalesTrabajadores,
            'tieneCalculo' => $hayCalculo,
            'breadcrumb'   => [
                ['label' => 'Coste laboral por propiedad', 'url' => ''],
            ],
        ]);
    }

    public function recalcular(Request $request, Project $project, CostesLaboralesPropiedadService $service)
    {
        $this->authorize($project);

        $anio = (int) $request->input('anio');
        $mes  = (int) $request->input('mes');
        if ($anio < 2020 || $mes < 1 || $mes > 12) {
            return response()->json(['error' => 'Año/mes inválido.'], 422);
        }

        $resumen = $service->recalcular($anio, $mes);

        return response()->json(['ok' => true, 'resumen' => $resumen]);
    }
}
