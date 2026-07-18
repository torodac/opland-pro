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

        return view('vm.informe-operativo', [
            'project'      => $project,
            'anios'        => $anios,
            'anioActual'   => $anioActual,
            'anioAnterior' => $anioAnterior,
            'categorias'   => self::MESES,
            'series'       => [
                ...$this->construirSeries($porMesAnterior, $colores, $anioAnterior, false),
                ...$this->construirSeries($porMesActual, $colores, $anioActual, true),
            ],
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
    // y (fecha_fin nula o >= inicio de mes)), agrupadas por cluster. Las propiedades sin
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
                ->selectRaw("COALESCE(cluster, 'Sin cluster') as cluster, COUNT(*) as n")
                ->groupBy('cluster')
                ->pluck('n', 'cluster');
        }

        return $porMes;
    }

    // 'Sin cluster' siempre al final de la leyenda; el resto, alfabético.
    private function asignarColores(array $porMesA, array $porMesB): array
    {
        $clusters = collect($porMesA)->flatMap(fn($c) => $c->keys())
            ->merge(collect($porMesB)->flatMap(fn($c) => $c->keys()))
            ->unique()
            ->sort(fn($a, $b) => match (true) {
                $a === 'Sin cluster' => 1,
                $b === 'Sin cluster' => -1,
                default => strcmp($a, $b),
            })
            ->values();

        return $clusters->mapWithKeys(fn($cluster, $i) => [
            $cluster => $cluster === 'Sin cluster' ? '#9ca3af' : self::PALETA[$i % count(self::PALETA)],
        ])->all();
    }

    // Devuelve una serie por cluster (para columnas apiladas por mes, con el cluster como leyenda).
    private function construirSeries(array $porMes, array $colores, int $anio, bool $esActual): array
    {
        $clusters = array_keys($colores);

        return collect($clusters)
            ->filter(fn($cluster) => collect($porMes)->contains(fn($c) => ($c[$cluster] ?? 0) > 0))
            ->map(fn($cluster) => [
                'cluster'  => $cluster,
                'anio'     => $anio,
                'esActual' => $esActual,
                'color'    => $colores[$cluster],
                'valores'  => collect(range(1, 12))->map(fn($m) => (int) ($porMes[$m][$cluster] ?? 0))->all(),
            ])
            ->values()
            ->all();
    }
}
