<?php

namespace App\Http\Controllers\Opland;

use App\Http\Controllers\Controller;
use App\Mail\FacturasAsesoriaMail;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

// Envía a la asesoría los documentos de las facturas de opland, emitidas y recibidas, y deja
// constancia de cuándo y quién. Una factura ya enviada no se vuelve a enviar sola: el botón de
// la cabecera solo manda lo pendiente, y para reenviar una concreta está el botón de su fila.
class EnvioAsesoriaController extends Controller
{
    // Las dos tablas de facturas de opland y cómo se describe cada una en el cuerpo del correo.
    private const TABLAS = [
        'facturas'       => ['tipo' => 'emitidas',  'etiqueta' => 'Factura'],
        'fta_soportadas' => ['tipo' => 'recibidas', 'etiqueta' => 'Factura recibida'],
    ];

    // Cuántas facturas quedan por enviar. Lo usa el badge del botón: si el recuento y el envío
    // no salen del mismo sitio, el botón promete un número y manda otro (pasó con el registro de
    // prueba borrado, que el badge contaba y el envío no).
    public static function cuentaPendientes(string $full): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn($full, 'enviado_asesoria_at')) return 0;

        return DB::table($full)
            ->whereNull('enviado_asesoria_at')
            ->where('deleted', self::noBorrado($full))
            ->count();
    }

    public function pendientes(Request $request, Project $project, string $tabla)
    {
        [$full, $cfg] = $this->resolver($project, $tabla);

        $filas = DB::table($full)
            ->whereNull('enviado_asesoria_at')
            ->where('deleted', self::noBorrado($full))
            ->orderBy('id')
            ->get();

        return $this->enviar($project, $tabla, $full, $cfg, $filas, 'No hay facturas pendientes de enviar.');
    }

    public function una(Request $request, Project $project, string $tabla, int $id)
    {
        [$full, $cfg] = $this->resolver($project, $tabla);

        $filas = DB::table($full)->where('id', $id)->get();
        if ($filas->isEmpty()) abort(404, 'La factura no existe.');

        return $this->enviar($project, $tabla, $full, $cfg, $filas, 'La factura no tiene documento adjunto.');
    }

    private function enviar(Project $project, string $tabla, string $full, array $cfg, $filas, string $vacio)
    {
        $documentos = [];
        $sinFichero = [];

        foreach ($filas as $f) {
            $ruta = $f->file_documento ?? null;

            // Sin fichero no hay nada que mandar. Se listan aparte en vez de fallar en silencio:
            // 9 de las 99 facturas emitidas estaban así al implementar esto.
            if (!$ruta || !Storage::disk('public')->exists($ruta)) {
                $sinFichero[] = $f->nombre ?: ('#' . $f->id);
                continue;
            }

            $documentos[] = [
                'id'      => (int) $f->id,
                'nombre'  => basename($ruta),
                'ruta'    => $ruta,
                'detalle' => $this->detalle($tabla, $f, $cfg['etiqueta']),
            ];
        }

        if (!$documentos) {
            return back()->withErrors(['envio' => $vacio . ($sinFichero ? ' Sin documento: ' . implode(', ', $sinFichero) . '.' : '')]);
        }

        Mail::to(config('services.asesoria.email'))->send(new FacturasAsesoriaMail($cfg['tipo'], $documentos));

        DB::table($full)
            ->whereIn('id', array_column($documentos, 'id'))
            ->update([
                'enviado_asesoria_at'  => now(),
                'enviado_asesoria_por' => auth()->id(),
                'updatedat'            => now(),
            ]);

        $n   = count($documentos);
        $msg = $n === 1
            ? 'Factura enviada a la asesoría.'
            : "{$n} facturas enviadas a la asesoría en un solo correo.";

        if ($sinFichero) {
            $msg .= ' Quedan sin enviar por no tener documento: ' . implode(', ', $sinFichero) . '.';
        }

        return back()->with('success', $msg);
    }

    private function detalle(string $tabla, object $f, string $etiqueta): string
    {
        if ($tabla === 'facturas') {
            return trim($etiqueta . ' ' . ($f->num_fact ?? '') . ' — ' . ($f->nombre ?? ''), ' —');
        }

        $partes = array_filter([
            $f->nombre_proveedor ?? null,
            $f->fecha_emision ?? null,
            isset($f->total_a_pagar) ? number_format((float) $f->total_a_pagar, 2, ',', '.') . ' €' : null,
        ]);

        return $partes ? implode(' · ', $partes) : ($f->nombre ?? ('#' . $f->id));
    }

    /** @return array{0:string, 1:array} */
    private function resolver(Project $project, string $tabla): array
    {
        abort_unless($project->slug === 'opland', 404);
        abort_unless(isset(self::TABLAS[$tabla]), 404, 'Esa tabla no envía facturas a la asesoría.');
        abort_unless(auth()->user()?->canEditTable($project, $tabla), 403);

        return ['opland_' . $tabla, self::TABLAS[$tabla]];
    }

    // opland_facturas tiene 'deleted' booleano y opland_fta_soportadas lo tiene como entero.
    private static function noBorrado(string $full): bool|int
    {
        $tipo = DB::selectOne(
            'select data_type from information_schema.columns where table_name = ? and column_name = ?',
            [$full, 'deleted']
        );

        return ($tipo->data_type ?? '') === 'boolean' ? false : 0;
    }
}
