<?php
namespace App\Http\Controllers\Rodcar;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TesoreriaController extends Controller
{
    private function tipoExpr(): string
    {
        return "CASE
            WHEN t2.nombre IN ('Capital','Compra','Venta','Capital inicial','PP_Silvia','PP_Tomás') THEN 'Inversión'
            WHEN t2.nombre ILIKE '%Inv%'                                                            THEN 'Inversión'
            WHEN t1.nombre = 'Ahorro'                                                               THEN 'Inversión'
            ELSE 'Gastos'
        END";
    }

    private function titularExpr(): string
    {
        return "CASE
            WHEN t1.nombre = 'FICTICIO'                                                   THEN 'Ficticio'
            WHEN t1.nombre = 'Silvia'        AND t2.nombre = 'Regularización'             THEN 'Sil. Regul.'
            WHEN t1.nombre = 'Silvia'                                                      THEN 'Gasto Silvia'
            WHEN t1.nombre IN ('Inversiones','PERSONAL') AND t2.nombre = 'Regularización' THEN 'Tom. Regul.'
            WHEN t1.nombre IN ('Inversiones','PERSONAL')                                   THEN 'Gasto Tomás'
            WHEN t1.nombre = 'Traspaso'                                                    THEN 'Traspaso'
            WHEN t1.nombre = 'DM Invest'                                                   THEN 'Dr Moliner'
            WHEN t1.nombre = 'AA Invest'                                                   THEN 'Aben al Abbar'
            WHEN t1.nombre = 'HM Invest'                                                   THEN 'Hermanos Machado'
            WHEN t1.nombre = 'Regularización'                                              THEN 'Regularización'
            ELSE 'Común'
        END";
    }

    public function index(Request $request, Project $project)
    {
        $years = DB::select("
            SELECT DISTINCT EXTRACT(YEAR FROM fecha_operacion)::int AS y
            FROM (
                SELECT fecha_operacion FROM rodcar_movs        WHERE deleted=false AND fecha_operacion IS NOT NULL
                UNION ALL
                SELECT fecha_operacion FROM rodcar_movs_detalle WHERE deleted=false AND fecha_operacion IS NOT NULL
            ) t
            ORDER BY y DESC
        ");
        $years   = array_column($years, 'y');
        $year    = (int) $request->get('year', $years[0] ?? date('Y'));
        $titular = $request->get('titular');

        $titularExpr = $this->titularExpr();
        $tipoExpr    = $this->tipoExpr();
        $tipo        = $request->get('tipo');

        $filters = [];
        $params  = [$year, $year];
        if ($titular) { $filters[] = "({$titularExpr}) = ?"; $params[] = $titular; }
        if ($tipo)    { $filters[] = "({$tipoExpr}) = ?";    $params[] = $tipo;    }
        $extraWhere = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

        $rows = DB::select("
            SELECT
                t1.ingreso,
                COALESCE(t0.nombre, 'Sin categoría') AS categoria,
                COALESCE(t0.orden, 999)               AS orden_cat,
                t1.nombre                              AS tipo,
                EXTRACT(MONTH FROM src.fecha_operacion)::int AS mes,
                SUM(src.importe)                       AS total
            FROM (
                SELECT fecha_operacion, importe,
                       COALESCE(id_movs_tipo1, id_movs_tipo1_propuesto) AS id_movs_tipo1,
                       COALESCE(id_movs_tipo2, id_movs_tipo2_propuesto) AS id_movs_tipo2
                FROM rodcar_movs
                WHERE deleted=false AND hidden=0 AND fecha_operacion IS NOT NULL
                  AND EXTRACT(YEAR FROM fecha_operacion) = ?
                  AND COALESCE(id_movs_tipo1, id_movs_tipo1_propuesto) IS NOT NULL
                  AND COALESCE(id_movs_tipo1, id_movs_tipo1_propuesto) != 48
                  AND NOT EXISTS (
                      SELECT 1 FROM rodcar_movs_detalle d
                      WHERE d.id_movs = rodcar_movs.id AND d.deleted=false
                  )
                UNION ALL
                SELECT d.fecha_operacion, d.importe,
                       COALESCE(d.id_movs_tipo1, d.id_movs_tipo1_propuesto) AS id_movs_tipo1,
                       COALESCE(d.id_movs_tipo2, d.id_movs_tipo2_propuesto) AS id_movs_tipo2
                FROM rodcar_movs_detalle d
                WHERE d.deleted=false AND d.hidden=0 AND d.fecha_operacion IS NOT NULL
                  AND EXTRACT(YEAR FROM d.fecha_operacion) = ?
                  AND COALESCE(d.id_movs_tipo1, d.id_movs_tipo1_propuesto) IS NOT NULL
                  AND COALESCE(d.id_movs_tipo1, d.id_movs_tipo1_propuesto) != 48
            ) src
            JOIN rodcar_movs_tipo1 t1 ON t1.id = src.id_movs_tipo1
            LEFT JOIN rodcar_movs_tipo0 t0 ON t0.id = t1.id_movs_tipo0
            LEFT JOIN rodcar_movs_tipo2 t2 ON t2.id = src.id_movs_tipo2
            {$extraWhere}
            GROUP BY t1.ingreso, t0.nombre, t0.orden, t1.nombre, mes
            ORDER BY t1.ingreso DESC, COALESCE(t0.orden,999), t0.nombre, t1.nombre, mes
        ", $params);

        // Titulares disponibles (sin Ficticio, ya excluido)
        $titulares = DB::select("
            SELECT DISTINCT ({$titularExpr}) AS titular
            FROM (
                SELECT COALESCE(id_movs_tipo1, id_movs_tipo1_propuesto) AS id_movs_tipo1,
                       COALESCE(id_movs_tipo2, id_movs_tipo2_propuesto) AS id_movs_tipo2
                FROM rodcar_movs
                WHERE deleted=false AND hidden=0
                  AND COALESCE(id_movs_tipo1, id_movs_tipo1_propuesto) IS NOT NULL
                  AND COALESCE(id_movs_tipo1, id_movs_tipo1_propuesto) != 48
                  AND NOT EXISTS (SELECT 1 FROM rodcar_movs_detalle d WHERE d.id_movs=rodcar_movs.id AND d.deleted=false)
                UNION ALL
                SELECT COALESCE(d.id_movs_tipo1, d.id_movs_tipo1_propuesto) AS id_movs_tipo1,
                       COALESCE(d.id_movs_tipo2, d.id_movs_tipo2_propuesto) AS id_movs_tipo2
                FROM rodcar_movs_detalle d
                WHERE d.deleted=false AND d.hidden=0
                  AND COALESCE(d.id_movs_tipo1, d.id_movs_tipo1_propuesto) IS NOT NULL
                  AND COALESCE(d.id_movs_tipo1, d.id_movs_tipo1_propuesto) != 48
            ) src
            JOIN rodcar_movs_tipo1 t1 ON t1.id = src.id_movs_tipo1
            LEFT JOIN rodcar_movs_tipo2 t2 ON t2.id = src.id_movs_tipo2
            ORDER BY titular
        ");
        $titulares = array_column($titulares, 'titular');

        // Estructura: [ig][cat][tipo][mes] = total
        $meses     = range(1, 12);
        $jerarquia = ['ingreso' => [], 'gasto' => []];
        $ordenCats = ['ingreso' => [], 'gasto' => []];

        foreach ($rows as $row) {
            $ig   = $row->ingreso ? 'ingreso' : 'gasto';
            $cat  = $row->categoria;
            $tipo = $row->tipo;
            $mes  = (int) $row->mes;

            if (!isset($jerarquia[$ig][$cat])) {
                $jerarquia[$ig][$cat] = [];
                $ordenCats[$ig][$cat] = (int) $row->orden_cat;
            }
            if (!isset($jerarquia[$ig][$cat][$tipo])) {
                $jerarquia[$ig][$cat][$tipo] = array_fill_keys($meses, 0.0);
            }
            $jerarquia[$ig][$cat][$tipo][$mes] = (float) $row->total;
        }

        foreach (['ingreso', 'gasto'] as $ig) {
            uksort($jerarquia[$ig], fn($a, $b) =>
                ($ordenCats[$ig][$a] ?? 999) <=> ($ordenCats[$ig][$b] ?? 999)
                ?: $a <=> $b
            );
        }

        $tiposFilter = ['Inversión', 'Gastos'];

        // KPI pendientes: movimientos sin tipo confirmado (con o sin propuesto)
        $pendientesStats = DB::selectOne("
            SELECT
                COUNT(*)                                                   AS n,
                COALESCE(SUM(importe) FILTER (WHERE importe < 0), 0)      AS neg,
                COALESCE(SUM(importe) FILTER (WHERE importe > 0), 0)      AS pos
            FROM (
                SELECT importe FROM rodcar_movs
                WHERE deleted=false AND hidden=0 AND id_movs_tipo1 IS NULL
                  AND EXTRACT(YEAR FROM fecha_operacion) = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM rodcar_movs_detalle d
                      WHERE d.id_movs = rodcar_movs.id AND d.deleted=false
                  )
                UNION ALL
                SELECT d.importe FROM rodcar_movs_detalle d
                WHERE d.deleted=false AND d.hidden=0 AND d.id_movs_tipo1 IS NULL
                  AND EXTRACT(YEAR FROM d.fecha_operacion) = ?
            ) src
        ", [$year, $year]);

        return view('rodcar.tesoreria', compact('project', 'year', 'years', 'jerarquia', 'titular', 'titulares', 'tipo', 'tiposFilter', 'pendientesStats'));
    }
}
