<?php

namespace App\Jobs;

use App\Services\ClaudeService;
use App\Services\ExtraccionDocumentos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Lee un documento subido y rellena con Claude los campos del registro.
//
// Qué campos se extraen y a qué columnas van lo declara ExtraccionDocumentos, una tabla a la vez:
// antes estaba aquí dentro, con los campos de vmf_facturas escritos a fuego, y por eso solo
// servía para esa tabla.
class InterpretarFacturaJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly string $fullTable,
        public readonly int    $id,
        public readonly string $storagePath,
    ) {}

    public function handle(): void
    {
        $def = ExtraccionDocumentos::definicion($this->fullTable);
        if (!$def) {
            Log::warning("InterpretarFacturaJob: {$this->fullTable} no tiene extracción declarada.");
            return;
        }

        $registro = DB::table($this->fullTable)->find($this->id);
        if (!$registro) return;

        $absolutePath = Storage::disk('public')->path($this->storagePath);
        if (!file_exists($absolutePath)) {
            Log::warning("InterpretarFacturaJob: no existe el fichero {$this->storagePath}.");
            return;
        }

        $ext       = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mediaType = match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/pdf',
        };

        $base64 = base64_encode(file_get_contents($absolutePath));

        $claude = new ClaudeService();
        $raw    = $claude->interpretarDocumento(
            $base64, $mediaType, ExtraccionDocumentos::prompt($this->fullTable), 1024
        );

        // La respuesta en bruto se guarda siempre, salga bien o mal el parseo: es lo único que
        // permite entender después por qué un importe salió raro.
        $update = [$def['columna_respuesta'] => $raw, 'updatedat' => now()];

        $json = $this->extractJson($raw);
        if ($json) {
            $update = array_merge($update, ExtraccionDocumentos::mapear($this->fullTable, $json));
        } else {
            Log::warning("InterpretarFacturaJob: respuesta no parseable para {$this->fullTable}#{$this->id}.");
        }

        DB::table($this->fullTable)->where('id', $this->id)->update($update);
    }

    private function extractJson(string $text): ?array
    {
        // Busca el primer bloque JSON de la respuesta.
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false) return null;

        $jsonStr = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($jsonStr, true);

        return is_array($decoded) ? $decoded : null;
    }
}
