<?php

namespace App\Services;

// Parsea el informe "Listado de recibos" que exporta el software de contabilidad (FastReport,
// formato SpreadsheetML/Excel 2003 XML -- no es un .xls ni .xlsx real). El informe agrupa las
// cuotas por vivienda: una fila con el nombre de la vivienda, una fila de cabecera de columnas,
// y luego las filas de cuota hasta la siguiente vivienda.
//
// Las columnas de cada fila de datos se detectan por el CONTENIDO de cada celda, no por su
// posición: la cabecera del informe ("Emisión", "Propietario"...) no queda siempre alineada con
// la columna real de datos (desfase de una posición comprobado en un fichero real), así que
// fiarse de la posición de la cabecera da resultados incorrectos.
class CuotasReportParser
{
    private const NS = 'urn:schemas-microsoft-com:office:spreadsheet';
    private const FORMAS_PAGO = ['Por banco', 'Despacho'];

    public function parse(string $filePath): array
    {
        $dom = new \DOMDocument();
        $dom->load($filePath);

        $worksheet = $dom->getElementsByTagNameNS(self::NS, 'Worksheet')->item(0);
        if (!$worksheet) {
            throw new \RuntimeException('No se encontró ninguna hoja (Worksheet) en el fichero.');
        }

        $table = null;
        foreach ($worksheet->childNodes as $child) {
            if ($child->localName === 'Table') { $table = $child; break; }
        }
        if (!$table) {
            throw new \RuntimeException('No se encontró la tabla de datos en el fichero.');
        }

        $rows = [];
        foreach ($table->childNodes as $child) {
            if ($child->localName === 'Row') $rows[] = $child;
        }

        $records = [];
        $currentVivienda = null;

        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            $vals = $this->rowValues($rows[$i]);
            $nonEmpty = array_filter($vals, fn($v) => $v !== null && trim((string) $v) !== '');

            $joined = implode(' ', $nonEmpty);
            if (str_contains($joined, 'Emisi') && str_contains($joined, 'Concepto')) {
                for ($back = 1; $back <= 2 && $i - $back >= 0; $back++) {
                    $prevNonEmpty = array_filter($this->rowValues($rows[$i - $back]), fn($v) => $v !== null && trim((string) $v) !== '');
                    if (!empty($prevNonEmpty)) { $currentVivienda = trim((string) reset($prevNonEmpty)); break; }
                }
                continue;
            }

            if (!$currentVivienda) continue;

            $record = $this->parseDataRow($vals, $currentVivienda);
            if ($record) $records[] = $record;
        }

        return $records;
    }

    // Reconoce una fila de cuota por su contenido: fecha (dd/mm/aaaa), dos importes con formato
    // monetario (importe y pendiente, en ese orden de columna), la forma de pago (uno de los dos
    // valores conocidos) y dos celdas de texto libre restantes (concepto y propietario, en ese
    // orden de columna). Si no aparece una fecha, no es una fila de cuota (cabecera, separador,
    // subtotal...).
    private function parseDataRow(array $vals, string $vivienda): ?array
    {
        $fecha = null;
        $dineros = []; // [idx => float] en orden de columna
        $formaPago = null;
        $textos = []; // [idx => string] en orden de columna, candidatos a concepto/propietario

        foreach ($vals as $idx => $v) {
            if ($v === null) continue;
            $v = trim($v);
            if ($v === '') continue;

            if ($fecha === null && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) {
                $fecha = "{$m[3]}-{$m[2]}-{$m[1]}";
                continue;
            }
            if (preg_match('/^-?[\d.]+,\d{2}\s*€?$/', $v)) {
                $dineros[$idx] = $this->parseMoney($v);
                continue;
            }
            if (in_array($v, self::FORMAS_PAGO, true)) {
                $formaPago = $v;
                continue;
            }
            $textos[$idx] = $v;
        }

        if ($fecha === null || count($dineros) < 2) return null;

        ksort($dineros);
        $importe   = array_shift($dineros);
        $pendiente = array_shift($dineros);

        ksort($textos);
        $concepto    = array_shift($textos);
        $propietario = array_shift($textos);
        if (!$concepto) return null;

        return [
            'vivienda_cuota_name' => $vivienda,
            'fecha_emision'       => $fecha,
            'concepto'            => $concepto,
            'propietario'         => $propietario ?? '',
            'forma_pago'          => $formaPago ?? '',
            'importe'             => $importe,
            'pendiente'           => $pendiente,
        ];
    }

    private function rowValues(\DOMElement $row): array
    {
        $vals = [];
        $cur = 1;
        foreach ($row->childNodes as $cell) {
            if ($cell->localName !== 'Cell') continue;
            $idxAttr = $cell->getAttributeNS(self::NS, 'Index');
            if ($idxAttr !== '') {
                $idx = (int) $idxAttr;
                while ($cur < $idx) { $vals[$cur] = null; $cur++; }
            }
            $data = null;
            foreach ($cell->childNodes as $child) {
                if ($child->localName === 'Data') { $data = $child->textContent; break; }
            }
            $vals[$cur] = $data;
            $cur++;
        }
        return $vals;
    }

    private function parseMoney(?string $s): ?float
    {
        if ($s === null || trim($s) === '') return null;
        $s = str_replace(['.', '€'], ['', ''], trim($s));
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? round((float) $s, 2) : null;
    }
}
