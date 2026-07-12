<?php

namespace App\Jobs;

use App\Services\ClaudeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $registro = DB::table($this->fullTable)->find($this->id);
        if (!$registro) return;

        $absolutePath = Storage::disk('public')->path($this->storagePath);
        if (!file_exists($absolutePath)) return;

        $ext       = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mediaType = match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/pdf',
        };

        $base64 = base64_encode(file_get_contents($absolutePath));

        $prompt = <<<PROMPT
Analiza este documento de factura y extrae los siguientes datos. Responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional, sin markdown, sin explicaciones.

Campos a extraer:
- "proveedor": nombre del emisor de la factura (empresa o persona que emite)
- "factura": número o referencia de la factura
- "nombre": concepto genérico que describe el servicio o producto (máx. 80 caracteres, en español)
- "importe_bruto": base imponible total (número decimal, sin símbolo €)
- "iva": importe total de IVA (suma de todos los tramos de IVA, número decimal)
- "neto": importe total a pagar o pagado (número decimal)
- "importe_otros": cualquier otro importe que no sea base imponible ni IVA (retenciones, recargos, descuentos, etc.). Si no hay, pon 0.

Si un campo no aparece en el documento, devuelve null para ese campo.

Ejemplo de respuesta esperada:
{"proveedor":"Empresa S.L.","factura":"F-2024-001","nombre":"Servicios de limpieza","importe_bruto":1000.00,"iva":210.00,"neto":1210.00,"importe_otros":0}
PROMPT;

        $claude = new ClaudeService();
        $raw    = $claude->interpretarDocumento($base64, $mediaType, $prompt, 512);

        // Intentar parsear el JSON de la respuesta
        $json = $this->extractJson($raw);
        if (!$json) return;

        $update = ['interpretacion' => $raw, 'updatedat' => now()];

        foreach (['proveedor', 'factura', 'importe_bruto', 'iva', 'neto', 'importe_otros'] as $campo) {
            if (isset($json[$campo]) && $json[$campo] !== null) {
                $update[$campo] = $json[$campo];
            }
        }

        // El concepto va al campo nombre
        if (!empty($json['nombre'])) {
            $update['nombre'] = mb_substr($json['nombre'], 0, 255);
        }

        DB::table($this->fullTable)->where('id', $this->id)->update($update);
    }

    private function extractJson(string $text): ?array
    {
        // Buscar el primer bloque JSON en la respuesta
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false) return null;

        $jsonStr = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($jsonStr, true);

        return is_array($decoded) ? $decoded : null;
    }
}
