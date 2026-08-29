<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InformeOperativoController extends Controller
{
    private const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    private const PALETA = ['#f97316', '#1d4ed8', '#15803d', '#9333ea', '#e11d48', '#0891b2'];

    public function index(Request $request, Project $project)
    {
        $anios = $this->aniosDisponibles();
        $anioActual   = (int) ($request->input('anio') ?: ($anios->first() ?? now()->year));
        $anioAnterior = $anioActual - 1;

        $porMesActual   = $this->propiedadesPorMes($anioActual);
        $porMesAnterior = $this->propiedadesPorMes($anioAnterior);

        // Mismo color base por cluster en los dos años (el año anterior se pinta a menor
        // opacidad en el JS) — la paleta se asigna sobre la unión de clusters de ambos años,
        // para que un cluster que solo aparezca en uno de los dos siga teniendo un color estable.
        $colores = $this->asignarColores($porMesActual, $porMesAnterior);

        $series = [
            ...$this->construirSeries($porMesAnterior, $colores, $anioAnterior, false),
            ...$this->construirSeries($porMesActual, $colores, $anioActual, true),
        ];

        $totalesActual   = $this->totalesPorMes($porMesActual);
        $totalesAnterior = $this->totalesPorMes($porMesAnterior);

        return view('vm.informe-operativo', [
            'project'       => $project,
            'anios'         => $anios,
            'anioActual'    => $anioActual,
            'anioAnterior'  => $anioAnterior,
            'categorias'    => self::MESES,
            'series'        => $series,
            'seriesPercent' => $this->seriesAPorcentaje($series, [
                $anioActual   => $totalesActual,
                $anioAnterior => $totalesAnterior,
            ]),
            'filas' => $this->construirFilasTabla($colores, $porMesActual, $porMesAnterior, $totalesActual, $totalesAnterior),
        ]);
    }

    // Rango de años seleccionables: desde el primer fecha_inicio real hasta el año actual.
    private function aniosDisponibles()
    {
        $minAnio = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->whereNotNull('fecha_inicio')
            ->min(DB::raw('EXTRACT(year FROM fecha_inicio)'));

        $desde = $minAnio ? (int) $minAnio : now()->year;
        $hasta = max(now()->year, $desde);

        return collect(range($hasta, $desde))->values();
    }

    // Para cada mes del año dado, cuenta propiedades activas ese mes (fecha_inicio <= fin de mes
    // y (fecha_fin nula o >= inicio de mes)), agrupadas por tipo_renta. Las propiedades sin
    // fecha_inicio no se pueden ubicar en ningún mes concreto y quedan fuera del recuento.
    private function propiedadesPorMes(int $anio): array
    {
        $porMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $inicioMes = Carbon::create($anio, $m, 1)->toDateString();
            $finMes    = Carbon::create($anio, $m, 1)->endOfMonth()->toDateString();

            $porMes[$m] = DB::table('vm_propiedades')
                ->where('deleted', 0)
                ->whereNotNull('fecha_inicio')
                ->where('fecha_inicio', '<=', $finMes)
                ->where(fn($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $inicioMes))
                ->selectRaw("COALESCE(NULLIF(tipo_renta, ''), 'Sin tipo') as tipo_renta, COUNT(*) as n")
                ->groupBy('tipo_renta')
                ->pluck('n', 'tipo_renta');
        }

        return $porMes;
    }

    // 'Sin tipo' siempre al final de la leyenda; el resto, alfabético.
    private function asignarColores(array $porMesA, array $porMesB): array
    {
        $tipos = collect($porMesA)->flatMap(fn($c) => $c->keys())
            ->merge(collect($porMesB)->flatMap(fn($c) => $c->keys()))
            ->unique()
            ->sort(fn($a, $b) => match (true) {
                $a === 'Sin tipo' => 1,
                $b === 'Sin tipo' => -1,
                default => strcmp($a, $b),
            })
            ->values();

        return $tipos->mapWithKeys(fn($tipo, $i) => [
            $tipo => $tipo === 'Sin tipo' ? '#9ca3af' : self::PALETA[$i % count(self::PALETA)],
        ])->all();
    }

    // Devuelve una serie por tipo_renta (para columnas apiladas por mes, con el tipo_renta como leyenda).
    private function construirSeries(array $porMes, array $colores, int $anio, bool $esActual): array
    {
        $tipos = array_keys($colores);

        return collect($tipos)
            ->filter(fn($tipo) => collect($porMes)->contains(fn($c) => ($c[$tipo] ?? 0) > 0))
            ->map(fn($tipo) => [
                'tipoRenta' => $tipo,
                'anio'      => $anio,
                'esActual'  => $esActual,
                'color'     => $colores[$tipo],
                'valores'   => collect(range(1, 12))->map(fn($m) => (int) ($porMes[$m][$tipo] ?? 0))->all(),
            ])
            ->values()
            ->all();
    }

    // Total de propiedades (todos los tipos) por mes, para calcular porcentajes sobre el 100%.
    private function totalesPorMes(array $porMes): array
    {
        return collect(range(1, 12))->mapWithKeys(fn($m) => [$m => $porMes[$m]->sum()])->all();
    }

    // Misma forma que $series, pero con cada valor mensual expresado como % sobre el total de
    // ese mes y ese año (para la vista de columnas apiladas al 100%).
    private function seriesAPorcentaje(array $series, array $totalesPorAnio): array
    {
        return collect($series)->map(function ($s) use ($totalesPorAnio) {
            $totales = $totalesPorAnio[$s['anio']] ?? [];
            $s['valores'] = collect($s['valores'])->map(function ($v, $i) use ($totales) {
                $total = $totales[$i + 1] ?? 0;
                return $total > 0 ? (int) round($v / $total * 100) : 0;
            })->all();
            return $s;
        })->all();
    }

    // Una fila por tipo_renta con los valores del año seleccionado y del anterior (para mostrarlos
    // como "actual (anterior)" en el pie de la gráfica), en unidades y en % sobre el total del mes.
    private function construirFilasTabla(array $colores, array $porMesActual, array $porMesAnterior, array $totalesActual, array $totalesAnterior): array
    {
        return collect(array_keys($colores))
            ->filter(fn($tipo) => collect($porMesActual)->contains(fn($c) => ($c[$tipo] ?? 0) > 0)
                || collect($porMesAnterior)->contains(fn($c) => ($c[$tipo] ?? 0) > 0))
            ->map(fn($tipo) => [
                'tipo'        => $tipo,
                'color'       => $colores[$tipo],
                'actual'      => collect(range(1, 12))->map(fn($m) => (int) ($porMesActual[$m][$tipo] ?? 0))->all(),
                'anterior'    => collect(range(1, 12))->map(fn($m) => (int) ($porMesAnterior[$m][$tipo] ?? 0))->all(),
                'actualPct'   => collect(range(1, 12))->map(fn($m) => $totalesActual[$m] > 0 ? (int) round(($porMesActual[$m][$tipo] ?? 0) / $totalesActual[$m] * 100) : 0)->all(),
                'anteriorPct' => collect(range(1, 12))->map(fn($m) => $totalesAnterior[$m] > 0 ? (int) round(($porMesAnterior[$m][$tipo] ?? 0) / $totalesAnterior[$m] * 100) : 0)->all(),
            ])
            ->values()
            ->all();
    }
}
