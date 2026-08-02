<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClasificacionGastosController extends Controller
{
    public function index(Project $project)
    {
        $pendientes = DB::table('mb_gastos')
            ->whereNull('id_gastos_cuentas')
            ->where('deleted', 0)
            ->orderBy('fecha_emision')
            ->get(['id', 'nombre', 'fecha_emision', 'importe_neto']);

        $ultimosClasificados = DB::table('mb_gastos as g')
            ->join('mb_gastos_cuentas as c', 'c.id', '=', 'g.id_gastos_cuentas')
            ->leftJoin('mb_gastos_agrupacion as a', 'a.id', '=', 'c.id_gastos_agrupacion')
            ->where('g.deleted', 0)
            ->whereNotNull('g.id_gastos_cuentas')
            ->orderByDesc('g.updatedat')
            ->limit(5)
            ->get(['g.id', 'g.nombre', 'g.fecha_emision', 'g.importe_neto', 'g.updatedat', 'c.cuenta', 'c.nombre as cuenta_nombre', 'a.nombre as agrupacion_nombre']);

        $grupos = DB::table('mb_gastos_agrupacion')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $cuentasPorGrupo = DB::table('mb_gastos_cuentas')
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->whereIn('id_gastos_agrupacion', $grupos->pluck('id'))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'cuenta', 'id_gastos_agrupacion'])
            ->groupBy('id_gastos_agrupacion');

        return view('mb.clasificacion-gastos', compact('project', 'pendientes', 'ultimosClasificados', 'grupos', 'cuentasPorGrupo'));
    }

    public function clasificar(Request $request, Project $project, int $gasto)
    {
        $data = $request->validate([
            'id_gastos_cuentas' => 'required|integer|exists:mb_gastos_cuentas,id',
        ]);

        $afectados = DB::table('mb_gastos')->where('id', $gasto)->where('deleted', 0)->update([
            'id_gastos_cuentas' => $data['id_gastos_cuentas'],
            'updatedat'         => now(),
            'updateuser'        => auth()->id(),
        ]);

        abort_if($afectados === 0, 404);

        return response()->json(['ok' => true]);
    }

    public function deshacer(Request $request, Project $project, int $gasto)
    {
        $afectados = DB::table('mb_gastos')->where('id', $gasto)->where('deleted', 0)->update([
            'id_gastos_cuentas' => null,
            'updatedat'         => now(),
            'updateuser'        => auth()->id(),
        ]);

        abort_if($afectados === 0, 404);

        return response()->json(['ok' => true]);
    }
}
