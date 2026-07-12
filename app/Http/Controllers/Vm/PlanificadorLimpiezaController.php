<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

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
                't.id_propiedades',
                'p.nombre as propiedad', 'p.tiempo_limpieza',
            ]);

        // Siguiente checkin por propiedad (a partir de la fecha planificada)
        $propIds = $tareas->pluck('id_propiedades')->unique()->values();
        $siguientesCheckin = DB::table('vm_reservas')
            ->whereIn('id_propiedades', $propIds)
            ->where('check_in_date', '>=', $fecha)
            ->whereNotIn('booking_status', ['cancelled', 'canceled'])
            ->groupBy('id_propiedades')
            ->get([
                'id_propiedades',
                DB::raw('MIN(check_in_date) as next_checkin'),
            ])
            ->keyBy('id_propiedades');

        $usuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->whereIn('id_rol', [1, 6])
            ->orderBy('id_rol')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol']);

        // Disponibilidad del día: turno o sin entrada = disponible
        $horariosDia = DB::table('vm_horarios')
            ->where('fecha', $fecha)
            ->whereIn('id_usuario', $usuarios->pluck('id'))
            ->get(['id_usuario', 'tipo'])
            ->keyBy('id_usuario');

        $ausenciasDia = DB::table('vm_ausencias')
            ->where('deleted', 0)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin',   '>=', $fecha)
            ->whereIn('id_usuarios', $usuarios->pluck('id'))
            ->get(['id_usuarios', 'tipo'])
            ->keyBy('id_usuarios');

        $noDisponible = [];
        foreach ($usuarios as $u) {
            $a = $ausenciasDia->get($u->id);
            $h = $horariosDia->get($u->id);
            if ($a) {
                $noDisponible[$u->id] = $a->tipo;
            } elseif ($h && $h->tipo !== 'turno') {
                $noDisponible[$u->id] = ucfirst(str_replace('_', ' ', $h->tipo));
            }
        }

        $fechaCarbon = Carbon::parse($fecha);

        return view('planificador-limpieza', [
            'project'           => $project,
            'tareas'            => $tareas,
            'usuarios'          => $usuarios,
            'noDisponible'      => $noDisponible,
            'fecha'             => $fecha,
            'fechaCarbon'       => $fechaCarbon,
            'esHoy'             => $fecha === now()->toDateString(),
            'siguientesCheckin' => $siguientesCheckin,
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

    public function replanificar(Request $request, Project $project, int $id)
    {
        $fecha = $request->input('fecha');
        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return response()->json(['error' => 'Fecha invalida'], 422);
        }

        DB::table('vm_tareas_limpieza')
            ->where('id', $id)
            ->update([
                'fecha_planificada' => $fecha,
                'updatedat'         => now(),
            ]);


        return response()->json(['ok' => true, 'fecha' => $fecha]);
    }
}
