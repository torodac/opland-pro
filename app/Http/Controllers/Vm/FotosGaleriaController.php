<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FotosGaleriaController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $qNombre = trim((string) $request->input('q_nombre', ''));
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $idPropiedad = $request->input('id_propiedades');

        $query = DB::table('vm_fotos as f')
            ->leftJoin('vm_tareas_limpieza as tl', 'tl.id', '=', 'f.id_tareas_limpieza')
            ->leftJoin('vm_tareas_mantenimiento as tm', 'tm.id', '=', 'f.id_tareas_mantenimiento')
            ->leftJoin('vm_tareas_piscinas as tp', 'tp.id', '=', 'f.id_tareas_piscinas')
            ->leftJoin('vm_propiedades as p', function ($join) {
                $join->on('p.id', '=', DB::raw('COALESCE(tl.id_propiedades, tm.id_propiedades, tp.id_propiedades)'));
            })
            ->where('f.deleted', 0)
            ->selectRaw("
                f.id, f.file_foto,
                COALESCE(tl.nombre, tm.nombre, tp.nombre) as tarea_nombre,
                COALESCE(tl.fecha_planificada, tm.fecha_planificada, tp.fecha_planificada) as fecha_planificada,
                COALESCE(tl.id_propiedades, tm.id_propiedades, tp.id_propiedades) as id_propiedades,
                p.nombre as propiedad_nombre,
                CASE
                    WHEN f.id_tareas_limpieza IS NOT NULL THEN 'limpieza'
                    WHEN f.id_tareas_mantenimiento IS NOT NULL THEN 'mantenimiento'
                    WHEN f.id_tareas_piscinas IS NOT NULL THEN 'piscina'
                    ELSE NULL
                END as tarea_tipo,
                COALESCE(f.id_tareas_limpieza, f.id_tareas_mantenimiento, f.id_tareas_piscinas) as tarea_id
            ")
            ->when($qNombre !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('tl.nombre', 'ilike', "%{$qNombre}%")
                ->orWhere('tm.nombre', 'ilike', "%{$qNombre}%")
                ->orWhere('tp.nombre', 'ilike', "%{$qNombre}%")
            ))
            ->when($fechaDesde, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('tl.fecha_planificada', '>=', $fechaDesde)
                ->orWhere('tm.fecha_planificada', '>=', $fechaDesde)
                ->orWhere('tp.fecha_planificada', '>=', $fechaDesde)
            ))
            ->when($fechaHasta, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('tl.fecha_planificada', '<=', $fechaHasta)
                ->orWhere('tm.fecha_planificada', '<=', $fechaHasta)
                ->orWhere('tp.fecha_planificada', '<=', $fechaHasta)
            ))
            ->when($idPropiedad, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('tl.id_propiedades', $idPropiedad)
                ->orWhere('tm.id_propiedades', $idPropiedad)
                ->orWhere('tp.id_propiedades', $idPropiedad)
            ))
            ->orderByRaw('COALESCE(tl.fecha_planificada, tm.fecha_planificada, tp.fecha_planificada) DESC NULLS LAST')
            ->orderByDesc('f.id');

        $fotos = $query->paginate(48)->withQueryString();

        $propiedades = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('vm.fotos-galeria', [
            'project' => $project,
            'fotos' => $fotos,
            'propiedades' => $propiedades,
            'qNombre' => $qNombre,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'idPropiedad' => $idPropiedad,
        ]);
    }
}
