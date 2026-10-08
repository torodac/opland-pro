<?php
namespace App\Http\Controllers\Rodcar;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    private function formatFecha(string $f): string
    {
        $meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        preg_match('/^(\d+)-(\d+)-(\d+)/', $f, $m);
        return ($meses[(int)$m[2]] ?? $m[2]) . ' ' . $m[3];
    }

    public function index(Request $request, Project $project)
    {
        $rows = DB::table('rodcar_balance')
            ->where('deleted', 0)
            ->whereNotNull('grupo')
            ->orderByRaw("TO_DATE(SUBSTRING(fecha_fecha, 1, 10), 'DD-MM-YYYY')")
            ->get(['id', 'nombre', 'valor', 'fecha_fecha', 'grupo']);

        $fechas = $rows->pluck('fecha_fecha')->unique()->sortBy(function ($f) {
            preg_match('/^(\d+)-(\d+)-(\d+)/', $f, $m);
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        })->values();

        $chartData = [];
        foreach ($fechas as $fecha) {
            $pr = $rows->where('fecha_fecha', $fecha);
            $ac = (float) $pr->where('grupo', 'activo_corto')->sum('valor');
            $am = (float) $pr->where('grupo', 'activo_medio')->sum('valor');
            $al = (float) $pr->where('grupo', 'activo_largo')->sum('valor');
            $pa = (float) $pr->where('grupo', 'pasivo')->sum('valor');
            $detalles = [];
            foreach ($pr as $row) {
                $detalles[$row->nombre] = (float) $row->valor;
            }
            $chartData[] = [
                'fecha'        => $fecha,
                'label'        => $this->formatFecha($fecha),
                'activo_corto' => $ac,
                'activo_medio' => $am,
                'activo_largo' => $al,
                'activo_total' => $ac + $am + $al,
                'pasivo'       => $pa,
                'patrimonio'   => $ac + $am + $al - $pa,
                'detalles'     => $detalles,
            ];
        }

        $current = collect($chartData)->last() ?? [];

        // PostgreSQL: ORDER BY expression must appear in SELECT list when using DISTINCT
        $items = DB::table('rodcar_balance')
            ->where('deleted', 0)
            ->whereNotNull('grupo')
            ->selectRaw("nombre, grupo, CASE grupo WHEN 'activo_corto' THEN 1 WHEN 'activo_medio' THEN 2 WHEN 'activo_largo' THEN 3 ELSE 4 END AS grupo_order")
            ->distinct()
            ->orderByRaw('grupo_order')
            ->orderBy('nombre')
            ->get();

        return view('rodcar.balance', compact('project', 'chartData', 'current', 'items'));
    }

    public function store(Request $request, Project $project)
    {
        $request->validate([
            'fecha'   => 'required|date_format:Y-m',
            'valores' => 'required|array',
        ]);

        [$year, $month] = explode('-', $request->fecha);
        $fechaStr = sprintf('01-%02d-%s 12:00', (int)$month, $year);

        $user = auth()->id();
        $now  = now();

        foreach ($request->valores as $nombre => $raw) {
            $valor = ($raw === '' || $raw === null) ? null : (float) str_replace(',', '.', $raw);
            if ($valor === null) continue;

            $grupo = DB::table('rodcar_balance')
                ->where('nombre', $nombre)
                ->whereNotNull('grupo')
                ->value('grupo') ?? 'activo_corto';

            $existing = DB::table('rodcar_balance')
                ->where('nombre', $nombre)
                ->where('fecha_fecha', $fechaStr)
                ->where('deleted', 0)
                ->first();

            if ($existing) {
                DB::table('rodcar_balance')->where('id', $existing->id)->update([
                    'valor'      => $valor,
                    'updateuser' => $user,
                    'updatedat'  => $now,
                ]);
            } else {
                DB::table('rodcar_balance')->insert([
                    'nombre'      => $nombre,
                    'grupo'       => $grupo,
                    'valor'       => $valor,
                    'fecha_fecha' => $fechaStr,
                    'blocked'     => 0,
                    'hidden'      => 0,
                    'deleted'     => 0,
                    'createuser'  => $user,
                    'updateuser'  => $user,
                    'createdat'   => $now,
                    'updatedat'   => $now,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function loadFecha(Request $request, Project $project)
    {
        $fecha = $request->query('fecha');
        $rows  = DB::table('rodcar_balance')
            ->where('fecha_fecha', $fecha)
            ->where('deleted', 0)
            ->get(['nombre', 'valor']);

        return response()->json($rows->pluck('valor', 'nombre'));
    }
}
