<?php

namespace App\Http\Controllers\Opland;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturaFormController extends Controller
{
    private function nextNumFact(): string
    {
        $year = now()->year;
        $prefix = $year . '/';

        $max = DB::table('opland_facturas')
            ->where('num_fact', 'like', $prefix . '%')
            ->pluck('num_fact')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
    }

    private function durHoras(?string $hms): float
    {
        if (!$hms) return 0;
        $parts = array_map('intval', explode(':', $hms));
        $h = $parts[0] ?? 0;
        $m = $parts[1] ?? 0;
        $s = $parts[2] ?? 0;
        return round($h + $m / 60 + $s / 3600, 2);
    }

    private function factura(int $id)
    {
        return DB::table('opland_facturas')->where('id', $id)->first();
    }

    // Crea un borrador vacio y redirige a su ficha
    public function nueva(Request $request, Project $project)
    {
        $id = DB::table('opland_facturas')->insertGetId([
            'nombre'         => 'Factura sin emitir',
            'num_fact'       => null,
            'base_imponible' => 0,
            'total_a_pagar'  => 0,
            'iva'            => 21,
            'dtototae'       => 0,
            'deleted'        => false,
            'createuser'     => auth()->id(),
            'createdat'      => now(),
            'updatedat'      => now(),
        ]);

        return redirect()->route('opland.factura_form.show', ['project' => $project->slug, 'factura' => $id]);
    }

    public function show(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);

        $clientes = DB::table('opland_clientes')->where('deleted', false)->orderBy('nombre')
            ->get(['id', 'nombre', 'nombre_fiscal', 'nif', 'direccion', 'cp', 'poblacion']);
        $proyectos = DB::table('opland_proyectos')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre', 'id_clientes']);
        $conceptos = DB::table('opland_conceptos')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);

        return view('opland.factura-form', compact('project', 'f', 'clientes', 'proyectos', 'conceptos'));
    }

    // Estado completo de la factura (cabecera + lineas + imputaciones del proyecto) en JSON, para pintar/refrescar el tablero
    public function estado(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);

        $lineas = DB::table('opland_factura_lineas')
            ->where('id_facturas', $factura)
            ->where('deleted', false)
            ->orderBy('id')
            ->get(['id', 'nombre', 'precio', 'descuentoporc', 'descuentoe', 'id_conceptos']);

        $lineaIds = $lineas->pluck('id');

        $imputacionesAsignadas = DB::table('opland_imputaciones')
            ->whereIn('id_factura_lineas', $lineaIds)
            ->where('deleted', false)
            ->get(['id', 'nombre', 'fecha_imputacion', 'duracion', 'id_factura_lineas']);

        $lineasOut = $lineas->map(function ($l) use ($imputacionesAsignadas) {
            $imps = $imputacionesAsignadas->where('id_factura_lineas', $l->id)->values();
            return [
                'id'            => $l->id,
                'nombre'        => $l->nombre,
                'precio'        => (float) $l->precio,
                'descuentoporc' => $l->descuentoporc,
                'descuentoe'    => (float) $l->descuentoe,
                'id_conceptos'  => $l->id_conceptos,
                'horas'         => round($imps->sum(fn ($i) => $this->durHoras($i->duracion)), 2),
                'imputaciones'  => $imps->map(fn ($i) => [
                    'id' => $i->id, 'nombre' => $i->nombre, 'fecha' => $i->fecha_imputacion,
                    'horas' => $this->durHoras($i->duracion),
                ])->values(),
            ];
        })->values();

        // Imputaciones facturables sin facturar, del mismo proyecto que la factura, no asignadas todavia a ninguna linea
        $pool = collect();
        if ($f->id_proyectos) {
            $pool = DB::table('opland_imputaciones as i')
                ->join('opland_tareas as t', 't.id', '=', 'i.id_tareas')
                ->where('t.id_proyectos', $f->id_proyectos)
                ->where('i.deleted', false)
                ->where('i.no_facturable', false)
                ->where(function ($q) { $q->whereNull('i.factura_opland')->orWhere('i.factura_opland', ''); })
                ->whereNull('i.id_factura_lineas')
                ->orderBy('i.fecha_imputacion')
                ->get(['i.id', 'i.nombre', 'i.fecha_imputacion', 'i.duracion', 't.nombre as tarea_nombre'])
                ->map(fn ($i) => ['id' => $i->id, 'nombre' => $i->nombre, 'tarea_nombre' => $i->tarea_nombre, 'fecha' => $i->fecha_imputacion, 'horas' => $this->durHoras($i->duracion)]);
        }

        $base = $lineasOut->sum(fn ($l) => $l['precio'] - $l['descuentoe']);
        $dtoFactura = (float) ($f->dtototae ?? 0);
        $baseNeta = $base - $dtoFactura;
        $iva = $baseNeta * (($f->iva ?? 21) / 100);
        $total = $baseNeta + $iva;

        return response()->json([
            'factura' => [
                'id' => $f->id, 'nombre' => $f->nombre, 'num_fact' => $f->num_fact,
                'num_fact_preview' => $f->num_fact ?? $this->nextNumFact(),
                'emitida' => !is_null($f->num_fact),
                'descripcion' => $f->descripcion, 'fecha_emision' => $f->fecha_emision,
                'id_clientes' => $f->id_clientes, 'id_proyectos' => $f->id_proyectos,
                'iva' => $f->iva, 'dtototae' => (float) ($f->dtototae ?? 0),
            ],
            'lineas' => $lineasOut,
            'imputaciones_pool' => $pool->values(),
            'totales' => ['base' => round($base, 2), 'dto_factura' => round($dtoFactura, 2), 'base_neta' => round($baseNeta, 2), 'iva' => round($iva, 2), 'total' => round($total, 2)],
        ]);
    }

    public function updateHeader(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);
        abort_if($f->num_fact, 422, 'Esta factura ya ha sido emitida, no se puede modificar la cabecera.');

        $data = $request->validate([
            'id_clientes'  => 'nullable|integer|exists:opland_clientes,id',
            'id_proyectos' => 'nullable|integer|exists:opland_proyectos,id',
            'descripcion'  => 'nullable|string|max:255',
            'dtototae'     => 'nullable|numeric',
        ]);

        // al cambiar de proyecto, las imputaciones ya arrastradas de otro proyecto dejan de tener sentido: se sueltan
        if (array_key_exists('id_proyectos', $data) && $data['id_proyectos'] != $f->id_proyectos) {
            $lineaIds = DB::table('opland_factura_lineas')->where('id_facturas', $factura)->pluck('id');
            DB::table('opland_imputaciones')->whereIn('id_factura_lineas', $lineaIds)->update(['id_factura_lineas' => null]);
        }

        DB::table('opland_facturas')->where('id', $factura)->update($data + ['updatedat' => now(), 'updateuser' => auth()->id()]);

        return response()->json(['ok' => true]);
    }

    public function addLinea(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);
        abort_if($f->num_fact, 422, 'Factura ya emitida.');

        $id = DB::table('opland_factura_lineas')->insertGetId([
            'nombre' => 'Nueva línea', 'id_facturas' => $factura, 'precio' => 0,
            'descuentoporc' => 0, 'descuentoe' => 0, 'deleted' => false,
            'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function updateLinea(Request $request, Project $project, int $factura, int $linea)
    {
        $l = DB::table('opland_factura_lineas')->where('id', $linea)->where('id_facturas', $factura)->first();
        abort_unless($l, 404);

        $data = $request->validate([
            'nombre'        => 'sometimes|string|max:255',
            'precio'        => 'sometimes|numeric',
            'descuentoporc' => 'sometimes|nullable|numeric|min:0|max:100',
            'descuentoe'    => 'sometimes|nullable|numeric|min:0',
            'id_conceptos'  => 'sometimes|nullable|integer|exists:opland_conceptos,id',
        ]);

        $precio = $data['precio'] ?? $l->precio;

        // dto% y dto€ estan enlazados: el que llega en la peticion manda y recalcula el otro
        if (array_key_exists('descuentoporc', $data) && !array_key_exists('descuentoe', $data)) {
            $data['descuentoe'] = round($precio * ($data['descuentoporc'] ?? 0) / 100, 2);
        } elseif (array_key_exists('descuentoe', $data) && !array_key_exists('descuentoporc', $data)) {
            $data['descuentoporc'] = $precio > 0 ? (int) round(($data['descuentoe'] ?? 0) / $precio * 100) : 0;
        }

        DB::table('opland_factura_lineas')->where('id', $linea)->update($data + ['updatedat' => now(), 'updateuser' => auth()->id()]);

        return response()->json(['ok' => true]);
    }

    public function removeLinea(Request $request, Project $project, int $factura, int $linea)
    {
        DB::table('opland_imputaciones')->where('id_factura_lineas', $linea)->update(['id_factura_lineas' => null]);
        DB::table('opland_factura_lineas')->where('id', $linea)->where('id_facturas', $factura)->delete();

        return response()->json(['ok' => true]);
    }

    // Arrastrar una imputacion a una linea
    public function attachImputacion(Request $request, Project $project, int $factura, int $linea)
    {
        $data = $request->validate(['imputacion_id' => 'required|integer']);

        $linea_ok = DB::table('opland_factura_lineas')->where('id', $linea)->where('id_facturas', $factura)->exists();
        abort_unless($linea_ok, 404);

        DB::table('opland_imputaciones')->where('id', $data['imputacion_id'])->update(['id_factura_lineas' => $linea]);

        return response()->json(['ok' => true]);
    }

    public function detachImputacion(Request $request, Project $project, int $imputacion)
    {
        DB::table('opland_imputaciones')->where('id', $imputacion)->update(['id_factura_lineas' => null]);

        return response()->json(['ok' => true]);
    }

    // Duplica cabecera + lineas (sin imputaciones asociadas), en borrador
    public function duplicar(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);

        $newId = DB::table('opland_facturas')->insertGetId([
            'nombre' => $f->nombre . ' (copia)', 'num_fact' => null, 'descripcion' => $f->descripcion,
            'id_clientes' => $f->id_clientes, 'id_proyectos' => $f->id_proyectos,
            'dtototae' => $f->dtototae, 'iva' => $f->iva,
            'base_imponible' => 0, 'total_a_pagar' => 0, 'deleted' => false,
            'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
        ]);

        $lineas = DB::table('opland_factura_lineas')->where('id_facturas', $factura)->where('deleted', false)->get();
        foreach ($lineas as $l) {
            DB::table('opland_factura_lineas')->insert([
                'nombre' => $l->nombre, 'id_facturas' => $newId, 'id_conceptos' => $l->id_conceptos,
                'precio' => $l->precio, 'descuentoporc' => $l->descuentoporc, 'descuentoe' => $l->descuentoe,
                'deleted' => false, 'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
            ]);
        }

        return response()->json(['ok' => true, 'id' => $newId]);
    }

    // Fija el numero de factura definitivo y marca las imputaciones incluidas como facturadas
    public function emitir(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);
        abort_if($f->num_fact, 422, 'Esta factura ya estaba emitida.');
        abort_unless($f->id_clientes, 422, 'Falta seleccionar el cliente.');

        $lineas = DB::table('opland_factura_lineas')->where('id_facturas', $factura)->where('deleted', false)->get();
        abort_if($lineas->isEmpty(), 422, 'La factura no tiene líneas.');

        $numFact = $this->nextNumFact();

        $base = $lineas->sum(fn ($l) => $l->precio - $l->descuentoe);
        $dtoFactura = (float) ($f->dtototae ?? 0);
        $baseNeta = $base - $dtoFactura;
        $total = $baseNeta * (1 + ($f->iva ?? 21) / 100);

        DB::table('opland_facturas')->where('id', $factura)->update([
            'num_fact' => $numFact,
            'fecha_emision' => $f->fecha_emision ?? now()->toDateString(),
            'base_imponible' => round($baseNeta, 2),
            'total_a_pagar' => round($total, 2),
            'updatedat' => now(),
            'updateuser' => auth()->id(),
        ]);

        DB::table('opland_imputaciones')
            ->whereIn('id_factura_lineas', $lineas->pluck('id'))
            ->update(['factura_opland' => $numFact, 'updatedat' => now(), 'updateuser' => auth()->id()]);

        return response()->json(['ok' => true, 'num_fact' => $numFact]);
    }

    // Genera el PDF del documento (borrador o emitida) para descargar desde la previsualización
    public function pdf(Request $request, Project $project, int $factura)
    {
        $f = $this->factura($factura);
        abort_unless($f, 404);

        $cliente = $f->id_clientes ? DB::table('opland_clientes')->where('id', $f->id_clientes)->first() : null;

        $lineas = DB::table('opland_factura_lineas')
            ->where('id_facturas', $factura)->where('deleted', false)->orderBy('id')->get();

        $base = $lineas->sum(fn ($l) => $l->precio - $l->descuentoe);
        $dtoFactura = (float) ($f->dtototae ?? 0);
        $baseNeta = $base - $dtoFactura;
        $iva = $baseNeta * (($f->iva ?? 21) / 100);
        $total = $baseNeta + $iva;
        $numFact = $f->num_fact ?? $this->nextNumFact();

        $logoPath = public_path('projects/opland/logo-factura-blanco.png');
        $logoB64 = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('opland.factura-pdf', [
            'f' => $f, 'cliente' => $cliente, 'lineas' => $lineas,
            'numFact' => $numFact, 'base' => $base, 'baseNeta' => $baseNeta, 'iva' => $iva, 'total' => $total,
            'logoB64' => $logoB64,
        ])->setPaper('a4', 'portrait');

        $prefijo = is_null($f->num_fact) ? 'prefactura_' : 'factura_';
        $nombre = $prefijo . str_replace('/', '-', $numFact) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}
