<?php

namespace App\Http\Controllers\Rodcar;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidacionMovimientosController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $pendientes = DB::table('rodcar_movs as m')
            ->leftJoin('rodcar_movs_tipo1 as t1', 't1.id', '=', 'm.id_movs_tipo1_propuesto')
            ->leftJoin('rodcar_movs_tipo2 as t2', 't2.id', '=', 'm.id_movs_tipo2_propuesto')
            ->whereIn('m.estado_clasificacion', ['pendiente_validacion', 'clasificado_ia_alta_confianza'])
            ->where('m.deleted', false)
            ->orderByDesc('m.clasificado_en')
            ->get([
                'm.id', 'm.fecha_operacion', 'm.nombre', 'm.importe',
                'm.estado_clasificacion', 'm.fase_clasificacion', 'm.confianza_ia', 'm.justificacion_ia',
                'm.id_movs_tipo1_propuesto', 'm.id_movs_tipo2_propuesto',
                't1.nombre as tipo1_propuesto_nombre', 't2.nombre as tipo2_propuesto_nombre',
            ]);

        $tipos1 = DB::table('rodcar_movs_tipo1')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre']);
        $tipos2 = DB::table('rodcar_movs_tipo2')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre']);

        return view('rodcar.validacion', compact('project', 'pendientes', 'tipos1', 'tipos2'));
    }

    public function validar(Request $request, Project $project, int $movimiento)
    {
        $data = $request->validate([
            'id_movs_tipo1' => 'required|integer|exists:rodcar_movs_tipo1,id',
            'id_movs_tipo2' => 'nullable|integer|exists:rodcar_movs_tipo2,id',
        ]);

        $mov = DB::table('rodcar_movs')->where('id', $movimiento)->first();
        abort_unless($mov, 404);

        DB::table('rodcar_movs')->where('id', $movimiento)->update([
            'id_movs_tipo1'        => $data['id_movs_tipo1'],
            'id_movs_tipo2'        => $data['id_movs_tipo2'] ?? null,
            'estado_clasificacion' => 'validado_manual',
            'fase_clasificacion'   => 4,
            'updatedat'            => now(),
            'updateuser'           => auth()->id(),
        ]);

        DB::table('rodcar_movs_clasificacion_log')->insert([
            'id_movs' => $movimiento, 'fase' => 4,
            'id_movs_tipo1' => $data['id_movs_tipo1'], 'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
            'confianza' => 100, 'justificacion' => 'Validado manualmente.',
            'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
        ]);

        // Feedback loop: si el concepto es reutilizable, lo guarda/actualiza en mapeos
        // para que la Fase 1 lo reconozca directamente la próxima vez.
        // ("nombre_normalizado" es una columna generada, no se puede escribir directamente.)
        $normalizado  = mb_strtoupper(trim($mov->nombre));
        $mapeoExiste = DB::table('rodcar_movs_mapeo')->where('nombre_normalizado', $normalizado)->value('id');

        if ($mapeoExiste) {
            DB::table('rodcar_movs_mapeo')->where('id', $mapeoExiste)->update([
                'id_movs_tipo1' => $data['id_movs_tipo1'],
                'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
                'updateuser'    => auth()->id(),
                'updatedat'     => now(),
            ]);
        } else {
            DB::table('rodcar_movs_mapeo')->insert([
                'nombre'        => $mov->nombre,
                'id_movs_tipo1' => $data['id_movs_tipo1'],
                'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
                'createuser'    => auth()->id(),
                'createdat'     => now(),
                'updatedat'     => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
