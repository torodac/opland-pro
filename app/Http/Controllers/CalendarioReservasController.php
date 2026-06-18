<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarioReservasController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $dias  = max(14, min(60, (int) $request->input('dias', 30)));
        $desde = now()->toDateString();
        $hasta = now()->addDays($dias)->toDateString();

        $reservas = DB::table('vm_reservas as r')
            ->join('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('p.deleted', 0)
            ->whereNotNull('p.icnea_code')
            ->whereNotIn('r.booking_status', ['cancelled', 'canceled'])
            ->where('r.check_out_date', '>=', $desde)
            ->where('r.check_in_date', '<=', $hasta)
            ->orderBy('p.nombre')
            ->orderBy('r.check_in_date')
            ->get(['p.nombre as propiedad', 'r.id', 'r.guest_name', 'r.check_in_date', 'r.check_out_date', 'r.booking_status']);

        $propiedades = $reservas->pluck('propiedad')->unique()->sort()->values();

        $reservasPorPropiedad = $reservas->groupBy('propiedad');

        // Tareas de limpieza planificadas en el período
        $limpieza = DB::table('vm_tareas_limpieza as t')
            ->join('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->whereBetween('t.fecha_planificada', [$desde, $hasta])
            ->get(['p.nombre as propiedad', 't.id', 't.fecha_planificada', 't.Tipo as tipo'])
            ->map(fn($r) => (object) array_merge((array) $r, ['categoria' => 'limpieza']));

        // Tareas de mantenimiento planificadas en el período
        $mantenimiento = DB::table('vm_tareas_mantenimiento as t')
            ->join('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->whereBetween('t.fecha_planificada', [$desde, $hasta])
            ->get(['p.nombre as propiedad', 't.id', 't.fecha_planificada', 't.Tipo as tipo'])
            ->map(fn($r) => (object) array_merge((array) $r, ['categoria' => 'mantenimiento']));

        $tareasPorPropiedad = $limpieza->concat($mantenimiento)
            ->groupBy('propiedad')
            ->map(fn($ts) => $ts->groupBy('fecha_planificada'));

        return view('calendario-reservas', [
            'project'                => $project,
            'propiedades'            => $propiedades,
            'reservasPorPropiedad'   => $reservasPorPropiedad,
            'tareasPorPropiedad'     => $tareasPorPropiedad,
            'dias'                   => $dias,
            'breadcrumb'             => [
                ['label' => 'Calendario de reservas', 'url' => ''],
            ],
        ]);
    }
}
