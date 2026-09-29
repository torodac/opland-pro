<?php

namespace App\Services;

// Qué datos hay que sacar de un documento subido y a qué columnas van, por tabla.
//
// Antes esto estaba escrito a fuego dentro de InterpretarFacturaJob, con los campos de
// vmf_facturas metidos tanto en el prompt como en el guardado, así que la extracción solo servía
// para esa tabla. Aquí cada tabla declara sus campos y el job se limita a ejecutarlos.
//
// Añadir una tabla nueva es añadir una entrada: no hay que tocar ni el job ni la subida.
// Requisito: la tabla necesita una columna donde guardar la respuesta en bruto (texto), para
// poder revisar qué contestó Claude cuando un dato salga raro.
class ExtraccionDocumentos
{
    // tipo: 'texto' | 'numero' | 'fecha'
    //   texto  → se guarda tal cual, recortado a 'max' si se indica
    //   numero → solo se guarda si es numérico; se normaliza la coma decimal
    //   fecha  → solo se guarda si se puede interpretar; se normaliza a AAAA-MM-DD
    private const TABLAS = [

        'vmf_facturas' => [
            'columna_respuesta' => 'interpretacion',
            'titulo'            => 'factura',
            'campos' => [
                'proveedor'     => ['col' => 'proveedor',     'tipo' => 'texto',  'desc' => 'nombre del emisor de la factura (empresa o persona que emite)'],
                'factura'       => ['col' => 'factura',       'tipo' => 'texto',  'desc' => 'número o referencia de la factura'],
                'nombre'        => ['col' => 'nombre',        'tipo' => 'texto',  'max' => 255, 'desc' => 'concepto genérico que describe el servicio o producto (máx. 80 caracteres, en español)'],
                'importe_bruto' => ['col' => 'importe_bruto', 'tipo' => 'numero', 'desc' => 'base imponible total (número decimal, sin símbolo €)'],
                'iva'           => ['col' => 'iva',           'tipo' => 'numero', 'desc' => 'importe total de IVA (suma de todos los tramos de IVA, número decimal)'],
                'neto'          => ['col' => 'neto',          'tipo' => 'numero', 'desc' => 'importe total a pagar o pagado (número decimal)'],
                'importe_otros' => ['col' => 'importe_otros', 'tipo' => 'numero', 'desc' => 'cualquier otro importe que no sea base imponible ni IVA (retenciones, recargos, descuentos, etc.). Si no hay, pon 0'],
            ],
        ],

        'opland_fta_soportadas' => [
            'columna_respuesta' => 'interpretacion',
            'titulo'            => 'factura recibida',
            'campos' => [
                'fecha_emision'    => ['col' => 'fecha_emision',    'tipo' => 'fecha',  'desc' => 'fecha de emisión de la factura, en formato AAAA-MM-DD'],
                'nif_proveedor'    => ['col' => 'nif_proveedor',    'tipo' => 'texto',  'max' => 20,  'desc' => 'NIF, CIF o VAT del emisor de la factura'],
                'nombre_proveedor' => ['col' => 'nombre_proveedor', 'tipo' => 'texto',  'max' => 150, 'desc' => 'nombre o razón social del emisor'],
                'concepto'         => ['col' => 'concepto',         'tipo' => 'texto',  'max' => 200, 'desc' => 'concepto o descripción de lo facturado (en español, máx. 150 caracteres)'],
                'bi'               => ['col' => 'bi',               'tipo' => 'numero', 'desc' => 'base imponible total (número decimal, sin símbolo €)'],
                'iva'              => ['col' => 'iva',              'tipo' => 'numero', 'desc' => 'importe total de IVA (suma de todos los tramos, número decimal)'],
                'suplidos'         => ['col' => 'suplidos',         'tipo' => 'numero', 'desc' => 'importe de suplidos (gastos pagados por cuenta del cliente). Si no hay, pon 0'],
                'irpf'             => ['col' => 'irpf',             'tipo' => 'numero', 'desc' => 'importe de la retención de IRPF, como número POSITIVO. Si no hay, pon 0'],
                'total_factura'    => ['col' => 'total_factura',    'tipo' => 'numero', 'desc' => 'total de la factura antes de descontar la retención (base imponible + IVA + suplidos)'],
                'total_a_pagar'    => ['col' => 'total_a_pagar',    'tipo' => 'numero', 'desc' => 'importe final a pagar, ya descontada la retención de IRPF si la hubiera'],
            ],
        ],

    ];

    public static function definicion(string $fullTable): ?array
    {
        return self::TABLAS[$fullTable] ?? null;
    }

    public static function tieneExtraccion(string $fullTable): bool
    {
        return isset(self::TABLAS[$fullTable]);
    }

    // Construye las instrucciones a partir de los campos declarados. El armazón del texto es el
    // mismo que tenía el job antes de generalizarlo, para no cambiar la calidad de lo que
    // devuelve Claude en las tablas que ya funcionaban.
    public static function prompt(string $fullTable): string
    {
        $def = self::definicion($fullTable);
        if (!$def) return '';

        $lineas = [];
        $ejemplo = [];
        foreach ($def['campos'] as $clave => $c) {
            $lineas[] = '- "' . $clave . '": ' . $c['desc'];
            $ejemplo[$clave] = match ($c['tipo']) {
                'numero' => 0,
                'fecha'  => '2026-01-31',
                default  => '…',
            };
        }

        $listado = implode("\n", $lineas);
        $json    = json_encode($ejemplo, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Analiza este documento de {$def['titulo']} y extrae los siguientes datos. Responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional, sin markdown, sin explicaciones.

Campos a extraer:
{$listado}

Si un campo no aparece en el documento, devuelve null para ese campo. No inventes valores ni los deduzcas de otros: si no está, es null.

Ejemplo de la forma de la respuesta esperada:
{$json}
PROMPT;
    }

    // Convierte el JSON de Claude en el array de columnas a guardar, descartando lo que no
    // encaje con el tipo declarado. Un importe que llega como "1.234,56 €" se normaliza; uno que
    // llega como "no consta" se descarta en vez de guardar un 0 que parecería un dato real.
    public static function mapear(string $fullTable, array $json): array
    {
        $def = self::definicion($fullTable);
        if (!$def) return [];

        $update = [];
        foreach ($def['campos'] as $clave => $c) {
            if (!array_key_exists($clave, $json)) continue;
            $v = $json[$clave];
            if ($v === null || $v === '') continue;

            switch ($c['tipo']) {
                case 'numero':
                    $n = self::aNumero($v);
                    if ($n !== null) $update[$c['col']] = $n;
                    break;

                case 'fecha':
                    $f = self::aFecha($v);
                    if ($f !== null) $update[$c['col']] = $f;
                    break;

                default:
                    $t = trim((string) $v);
                    if ($t === '') break;
                    $update[$c['col']] = isset($c['max']) ? mb_substr($t, 0, $c['max']) : $t;
            }
        }

        return $update;
    }

    private static function aNumero($v): ?float
    {
        if (is_int($v) || is_float($v)) return (float) $v;

        $s = trim((string) $v);
        $s = str_replace(['€', ' ', "\u{00A0}"], '', $s);
        // "1.234,56" → "1234.56"; "1234.56" se queda igual.
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private static function aFecha($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') return null;

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'] as $formato) {
            $d = \DateTime::createFromFormat($formato, $s);
            if ($d && $d->format($formato) === $s) return $d->format('Y-m-d');
        }

        try {
            return (new \DateTime($s))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
