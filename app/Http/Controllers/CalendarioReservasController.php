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

        return view('calendario-reservas', [
            'project'                => $project,
            'propiedades'            => $propiedades,
            'reservasPorPropiedad'   => $reservasPorPropiedad,
            'dias'                   => $dias,
            'breadcrumb'             => [
                ['label' => 'Calendario de reservas', 'url' => ''],
            ],
        ]);
    }
}
