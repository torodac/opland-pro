<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HorarioController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        $rolesEditPast = [3, 11]; // Dirección general, Director RRHH
        $canEditPast = $isAdmin;
        if (!$canEditPast) {
            $vmRole = $user->getProjectRolePublic($project);
            $canEditPast = $vmRole && in_array((int) $vmRole->id, $rolesEditPast);
        }

        $weekStart = $request->filled('semana')
            ? Carbon::parse($request->input('semana'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $dates = collect();
        for ($i = 0; $i < 7; $i++) {
            $dates->push($weekStart->copy()->addDays($i));
        }

        $deptosVisiblesHorarios = DB::table('vm_departamentos')
            ->where('deleted', 0)
            ->where('visible_horarios', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $allUsuarios = DB::table('vm_usuarios as u')
            ->leftJoin('vm_departamentos as d', 'd.id', '=', 'u.id_departamento')
            ->where('u.deleted', 0)
            ->orderBy('d.nombre')->orderBy('u.nombre')
            ->select('u.*', 'd.nombre as departamento_nombre')
            ->get();

        $deptIdsPermitidos = $deptosVisiblesHorarios->pluck('id')->all();

        $usuariosFiltrados = $allUsuarios->filter(
            fn($u) => in_array($u->id_departamento, $deptIdsPermitidos)
        );

        $usuariosByDept = $usuariosFiltrados->groupBy('id_departamento');

        $departamentos = $deptosVisiblesHorarios->filter(
            fn($d) => $usuariosByDept->has($d->id)
        )->values();

        $horariosRaw = DB::table('vm_horarios')
            ->whereBetween('fecha', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        $horariosMap = [];
        foreach ($horariosRaw as $h) {
            $horariosMap[$h->id_usuario . '_' . $h->fecha] = $h;
        }

        $ausenciasRaw = DB::table('vm_ausencias')
            ->where('deleted', 0)
            ->where('fecha_inicio', '<=', $weekEnd->toDateString())
            ->where('fecha_fin',   '>=', $weekStart->toDateString())
            ->get();

        $ausenciasMap = [];
        foreach ($ausenciasRaw as $aus) {
            $cur = Carbon::parse($aus->fecha_inicio);
            $fin = Carbon::parse($aus->fecha_fin);
            while ($cur->lte($fin)) {
                $ds = $cur->toDateString();
                if ($ds >= $weekStart->toDateString() && $ds <= $weekEnd->toDateString()) {
                    $ausenciasMap[$aus->id_usuarios][$ds] = $aus->tipo;
                }
                $cur->addDay();
            }
        }

        $festivosMap = DB::table('vm_festivos')
            ->where('deleted', 0)
            ->whereBetween('fecha_fecha', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->pluck('nombre', 'fecha_fecha')
            ->toArray();

        return view('horario', [
            'project'        => $project,
            'isAdmin'        => $isAdmin,
            'canEditPast'    => $canEditPast,
            'weekStart'      => $weekStart,
            'weekEnd'        => $weekEnd,
            'dates'          => $dates,
            'departamentos'  => $departamentos,   // Collection de {id, nombre}
            'usuariosByDept' => $usuariosByDept,  // Keyed by id_departamento
            'horariosMap'    => $horariosMap,
            'ausenciasMap'   => $ausenciasMap,
            'festivosMap'    => $festivosMap,
            'prevWeek'       => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek'       => $weekStart->copy()->addWeek()->toDateString(),
            'breadcrumb'     => [['label' => 'Horarios', 'url' => '']],
        ]);
    }

    public function listado(Request $request, Project $project)
    {
        return app(\App\Http\Controllers\ListadoController::class)
            ->index($request, $project, 'horarios');
    }

    public function store(Request $request, Project $project)
    {
        $entries = $request->input('entries', []);

        foreach ($entries as $entry) {
            DB::table('vm_horarios')->upsert([
                'id_usuario'  => (int) $entry['id_usuario'],
                'fecha'       => $entry['fecha'],
                'tipo'        => $entry['tipo'],
                'hora_inicio' => $entry['hora_inicio'] ?: null,
                'hora_fin'    => $entry['hora_fin'] ?: null,
                'updatedat'   => now(),
                'createdat'   => now(),
            ], ['id_usuario', 'fecha'], ['tipo', 'hora_inicio', 'hora_fin', 'updatedat']);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Project $project)
    {
        $entries = $request->input('entries', []);

        foreach ($entries as $entry) {
            DB::table('vm_horarios')
                ->where('id_usuario', (int) $entry['id_usuario'])
                ->where('fecha', $entry['fecha'])
                ->delete();
        }

        return response()->json(['ok' => true]);
    }
}
