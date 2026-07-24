<?php

namespace App\Http\Controllers\Opland;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConciliacionController extends Controller
{
    // Ejercicio = año natural, p.ej. "2026" = 2026-01-01 .. 2026-12-31
    private function ejercicioActual(): string
    {
        return (string) now()->year;
    }

    private function yearBounds(string $ejercicio): array
    {
        $y = (int) $ejercicio;
        return ["{$y}-01-01", "{$y}-12-31"];
    }

    public function index(Request $request, Project $project)
    {
        $ejercicio = $request->input('ejercicio', $this->ejercicioActual());
        if (!preg_match('/^\d{4}$/', $ejercicio)) {
            $ejercicio = $this->ejercicioActual();
        }
        [$desde, $hasta] = $this->yearBounds($ejercicio);

        $facturas = DB::table('opland_facturas as f')
            ->leftJoin('opland_clientes as c', 'c.id', '=', 'f.id_clientes')
            ->where('f.deleted', false)
            ->whereBetween('f.fecha_emision', [$desde, $hasta])
            ->orderBy('f.fecha_emision')
            ->get(['f.id', 'f.num_fact', 'f.fecha_emision', 'f.total_a_pagar', 'c.nombre as cliente']);

        $caja = DB::table('opland_caja')
            ->where('deleted', false)
            ->whereBetween('fecha_movimiento', [$desde, $hasta])
            ->orderBy('fecha_movimiento')
            ->get(['id', 'nombre', 'fecha_movimiento', 'importe', 'id_facturas', 'id_banco']);

        $banco = DB::table('opland_banco')
            ->where('deleted', false)
            ->whereBetween('fecha_contable', [$desde, $hasta])
            ->orderBy('fecha_contable')
            ->get(['id', 'nombre', 'fecha_contable', 'importe']);

        $tiposCaja = DB::table('opland_tipo_caja')->orderBy('id')->get(['id', 'nombre']);

        return view('opland.conciliacion', compact('project', 'facturas', 'caja', 'banco', 'ejercicio', 'tiposCaja'));
    }

    // Vincula una fila de caja a una factura o a un movimiento de banco (rellena caja.id_facturas / caja.id_banco).
    public function vincular(Request $request, Project $project)
    {
        $data = $request->validate([
            'caja_id' => 'required|integer',
            'campo'   => 'required|in:id_facturas,id_banco',
            'valor_id'=> 'required|integer',
        ]);

        $tabla = $data['campo'] === 'id_facturas' ? 'opland_facturas' : 'opland_banco';
        abort_unless(DB::table($tabla)->where('id', $data['valor_id'])->exists(), 404);
        abort_unless(DB::table('opland_caja')->where('id', $data['caja_id'])->exists(), 404);

        DB::table('opland_caja')->where('id', $data['caja_id'])->update([
            $data['campo'] => $data['valor_id'],
            'updatedat'    => now(),
            'updateuser'   => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    // Quita el vinculo de una fila de caja (pone a NULL id_facturas o id_banco).
    public function desvincular(Request $request, Project $project)
    {
        $data = $request->validate([
            'caja_id' => 'required|integer',
            'campo'   => 'required|in:id_facturas,id_banco',
        ]);

        DB::table('opland_caja')->where('id', $data['caja_id'])->update([
            $data['campo'] => null,
            'updatedat'    => now(),
            'updateuser'   => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    // Crea una fila nueva en caja a partir de un movimiento de banco soltado en la zona de "nuevo movimiento".
    public function crearDesdeBanco(Request $request, Project $project)
    {
        $data = $request->validate([
            'banco_id'     => 'required|integer',
            'nombre'       => 'required|string|max:255',
            'id_tipo_caja' => 'required|integer',
        ]);

        $banco = DB::table('opland_banco')->where('id', $data['banco_id'])->first();
        abort_unless($banco, 404);

        $id = DB::table('opland_caja')->insertGetId([
            'nombre'           => $data['nombre'],
            'id_tipo_caja'     => $data['id_tipo_caja'],
            'fecha_movimiento' => $banco->fecha_contable,
            'fecha_contable'   => $banco->fecha_contable,
            'importe'          => $banco->importe,
            'id_banco'         => $banco->id,
            'deleted'          => false,
            'createuser'       => auth()->id(),
            'createdat'        => now(),
            'updatedat'        => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
