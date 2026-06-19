<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanificadorLimpiezaController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $fecha = $request->input('fecha');
        $fecha = ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
            ? Carbon::parse($fecha)->toDateString()
            : now()->toDateString();

        $tareas = DB::table('vm_tareas_limpieza as t')
            ->join('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $fecha)
            ->orderBy('p.nombre')
            ->get([
                't.id', 't.nombre', 't.Tipo as tipo', 't.estado', 't.control_user',
                'p.nombre as propiedad', 'p.tiempo_limpieza',
            ]);

        $usuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->whereIn('id_rol', [1, 6])
            ->orderBy('id_rol')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol']);

        $fechaCarbon = Carbon::parse($fecha);

        return view('planificador-limpieza', [
            'project'    => $project,
            'tareas'     => $tareas,
            'usuarios'   => $usuarios,
            'fecha'      => $fecha,
            'fechaCarbon'=> $fechaCarbon,
            'esHoy'      => $fecha === now()->toDateString(),
            'breadcrumb' => [
                ['label' => 'Tareas limpieza', 'url' => route('listado', [$project->slug, 'tareas_limpieza'])],
                ['label' => 'Planificador del día', 'url' => ''],
            ],
        ]);
    }

    public function assign(Request $request, Project $project, int $id)
    {
        $cleaners = array_values((array) $request->input('cleaners', []));

        DB::table('vm_tareas_limpieza')
            ->where('id', $id)
            ->update([
                'control_user' => json_encode($cleaners),
                'updatedat'    => now(),
            ]);

        return response()->json(['ok' => true]);
    }
}
