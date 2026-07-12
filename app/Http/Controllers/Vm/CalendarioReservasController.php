<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarioReservasController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $dias        = max(14, min(60, (int) $request->input('dias', 30)));
        $desdeRaw    = $request->input('desde', now()->toDateString());
        try { $desdeCarbon = \Carbon\Carbon::parse($desdeRaw); }
        catch (\Exception $e) { $desdeCarbon = now(); }
        $desde = $desdeCarbon->toDateString();
        $hasta = $desdeCarbon->copy()->addDays($dias)->toDateString();
        $salidas = $request->input('salidas'); // 'hoy' | 'manana' | null

        $baseQuery = DB::table('vm_reservas as r')
            ->join('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('p.deleted', 0)
            ->whereNotNull('p.icnea_code')
            ->whereNotIn('r.booking_status', ['cancelled', 'canceled']);

        // Conteos para los stats
        $salidasHoy    = (clone $baseQuery)->whereDate('r.check_out_date', now()->toDateString())->count();
        $salidasManana = (clone $baseQuery)->whereDate('r.check_out_date', now()->addDay()->toDateString())->count();

        // Filtro por salidas si se activa el stat
        $reservasQuery = (clone $baseQuery)
            ->where('r.check_out_date', '>=', $desde)
            ->where('r.check_in_date', '<=', $hasta)
            ->orderBy('p.nombre')
            ->orderBy('r.check_in_date');

        if ($salidas === 'hoy') {
            $propsFiltradas = (clone $baseQuery)
                ->whereDate('r.check_out_date', now()->toDateString())
                ->pluck('p.nombre')->unique();
            $reservasQuery->whereIn('p.nombre', $propsFiltradas);
        } elseif ($salidas === 'manana') {
            $propsFiltradas = (clone $baseQuery)
                ->whereDate('r.check_out_date', now()->addDay()->toDateString())
                ->pluck('p.nombre')->unique();
            $reservasQuery->whereIn('p.nombre', $propsFiltradas);
        }

        $reservas = $reservasQuery->get(['p.nombre as propiedad', 'r.id', 'r.guest_name', 'r.check_in_date', 'r.check_out_date', 'r.booking_status']);

        $propiedades = $reservas->pluck('propiedad')->unique()->sort()->values();

        $reservasPorPropiedad = $reservas->groupBy('propiedad');

        // Tareas de limpieza planificadas en el período
        $limpieza = DB::table('vm_tareas_limpieza as t')
            ->join('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->whereBetween('t.fecha_planificada', [$desde, $hasta])
            ->get(['p.nombre as propiedad', 't.id', 't.nombre', 't.fecha_planificada', 't.Tipo as tipo'])
            ->map(fn($r) => (object) array_merge((array) $r, ['categoria' => 'limpieza']));

        // Tareas de mantenimiento planificadas en el período
        $mantenimiento = DB::table('vm_tareas_mantenimiento as t')
            ->join('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->whereBetween('t.fecha_planificada', [$desde, $hasta])
            ->get(['p.nombre as propiedad', 't.id', 't.nombre', 't.fecha_planificada', 't.Tipo as tipo'])
            ->map(fn($r) => (object) array_merge((array) $r, ['categoria' => 'mantenimiento']));

        $tareasPorPropiedad = $limpieza->concat($mantenimiento)
            ->groupBy('propiedad')
            ->map(fn($ts) => $ts->groupBy('fecha_planificada'));

        return view('calendario-reservas', [
            'project'                => $project,
            'desde'                  => $desde,
            'propiedades'            => $propiedades,
            'reservasPorPropiedad'   => $reservasPorPropiedad,
            'tareasPorPropiedad'     => $tareasPorPropiedad,
            'dias'                   => $dias,
            'salidasHoy'             => $salidasHoy,
            'salidasManana'          => $salidasManana,
            'salidasFiltro'          => $salidas,
            'breadcrumb'             => [
                ['label' => 'Calendario de reservas', 'url' => ''],
            ],
        ]);
    }
}
