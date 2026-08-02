<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropietariosController extends Controller
{
    private function baseQuery(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        return DB::table('mb_propietarios as p')
            ->where('p.deleted', 0)
            ->when($q !== '', fn ($qq) => $qq->where(fn ($sub) => $sub
                ->where('p.nombre', 'ilike', '%' . $q . '%')
                ->orWhereExists(fn ($ex) => $ex->selectRaw('1')
                    ->from('mb_propietarios_historico as ph')
                    ->join('mb_viviendas as v', 'v.id', '=', 'ph.id_viviendas')
                    ->whereColumn('ph.id_propietarios', 'p.id')
                    ->where('ph.deleted', 0)
                    ->where('v.nombre', 'ilike', '%' . $q . '%'))
            ))
            ->select([
                'p.id', 'p.nombre',
                DB::raw("(SELECT COUNT(*) FROM mb_propietarios_historico ph
                          WHERE ph.id_propietarios = p.id AND ph.deleted = 0 AND ph.fecha_hasta IS NULL) as viviendas_actuales"),
                DB::raw("(SELECT COUNT(*) FROM mb_propietarios_historico ph
                          WHERE ph.id_propietarios = p.id AND ph.deleted = 0) as viviendas_total"),
            ]);
    }

    public function index(Request $request, Project $project)
    {
        $propietarios = $this->baseQuery($request)->orderBy('p.nombre')->get();

        $sortField = $request->input('sort');
        $sortDir   = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        if ($sortField && in_array($sortField, ['nombre', 'viviendas_actuales', 'viviendas_total'])) {
            $propietarios = $sortDir === 'asc'
                ? $propietarios->sortBy($sortField)->values()
                : $propietarios->sortByDesc($sortField)->values();
        }

        $viviendasPorPropietario = DB::table('mb_propietarios_historico as ph')
            ->join('mb_viviendas as v', 'v.id', '=', 'ph.id_viviendas')
            ->whereIn('ph.id_propietarios', $propietarios->pluck('id'))
            ->where('ph.deleted', 0)
            ->orderByRaw('(ph.fecha_hasta IS NULL) DESC, ph.fecha_desde DESC')
            ->get(['ph.id_propietarios', 'v.id as id_vivienda', 'v.nombre as vivienda_nombre', 'ph.fecha_desde', 'ph.fecha_hasta'])
            ->groupBy('id_propietarios');

        return view('mb.propietarios-listado', [
            'project' => $project,
            'propietarios' => $propietarios,
            'viviendasPorPropietario' => $viviendasPorPropietario,
            'q' => $request->input('q', ''),
            'sortField' => $sortField,
            'sortDir' => $sortDir,
        ]);
    }
}
