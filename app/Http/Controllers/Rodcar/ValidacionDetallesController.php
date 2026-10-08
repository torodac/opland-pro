<?php
namespace App\Http\Controllers\Rodcar;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidacionDetallesController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $rows = DB::select("
            SELECT
                m.id,
                m.fecha_operacion,
                m.nombre,
                m.importe,
                COUNT(d.id)      AS n_det,
                SUM(d.importe)   AS sum_det,
                m.importe - SUM(d.importe) AS diferencia
            FROM rodcar_movs m
            JOIN rodcar_movs_tipo1 t ON t.id = m.id_movs_tipo1
            JOIN rodcar_movs_detalle d ON d.id_movs = m.id AND d.deleted = false
            WHERE t.id = 48
              AND m.deleted = false
            GROUP BY m.id, m.fecha_operacion, m.nombre, m.importe
            ORDER BY ABS(m.importe - SUM(d.importe)) DESC, m.fecha_operacion DESC
        ");

        return view('rodcar.validacion-detalles', compact('project', 'rows'));
    }

    public function detalles(Request $request, Project $project, int $id)
    {
        $mov = DB::table('rodcar_movs')->where('id', $id)->first();
        abort_if(!$mov, 404);

        $detalles = DB::select("
            SELECT d.id, d.fecha_operacion, d.nombre, d.importe,
                   t1.nombre AS tipo1, t0.nombre AS tipo0
            FROM rodcar_movs_detalle d
            LEFT JOIN rodcar_movs_tipo1 t1 ON t1.id = d.id_movs_tipo1
            LEFT JOIN rodcar_movs_tipo0 t0 ON t0.id = t1.id_movs_tipo0
            WHERE d.id_movs = ? AND d.deleted = false
            ORDER BY d.fecha_operacion, d.id
        ", [$id]);

        return response()->json([
            'mov'      => $mov,
            'detalles' => $detalles,
        ]);
    }
}
