<?php

namespace App\Services\Rodcar\Importadores;

class MediolanumTarjetaParser
{
    // Liquidación mensual de tarjeta Mediolanum (PDF). Se extrae el texto con pdftotext -layout
    // (las columnas quedan alineadas con espacios, lo que permite separarlas de forma fiable) y se
    // parsea línea a línea. Además del detalle de compras, el PDF trae el importe y la fecha de
    // cargo exactos ("(1) El citado importe le será cargado el día ...") -- un dato muy fiable para
    // encontrar automáticamente el movimiento de cargo correspondiente en rodcar_movs.
    public function parse(string $path): array
    {
        $texto = shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($texto === null || trim($texto) === '') {
            throw new \RuntimeException('No se pudo extraer texto del PDF.');
        }

        $lineas = explode("\n", $texto);
        $filas  = [];
        $tarjeta = null;
        $cargoFecha = null;
        $cargoImporte = null;
        $dentroDetalle = false;

        foreach ($lineas as $linea) {
            if (preg_match('/FACTURAS DEL PER[IÍ]ODO/iu', $linea)) {
                $dentroDetalle = true;
                continue;
            }
            if (preg_match('/Total tarjeta n[uú]mero\s+([\d.X]+)/i', $linea, $m)) {
                $tarjeta = $m[1];
                $dentroDetalle = false;
            }
            if (preg_match('/cargado el d[ií]a\s+(\d{1,2}\/\d{1,2}\/\d{2})/iu', $linea, $m)) {
                $cargoFecha = $this->parseFecha($m[1]);
            }
            if (preg_match('/CUOTA DEL MES.*?([\d.,]+)\s*EUR/i', $linea, $m)) {
                $cargoImporte = $this->parseImporte($m[1]);
            }

            if (!$dentroDetalle || !preg_match('/^\s*(\d{1,2}\/\d{1,2}\/\d{2})\s+(.+)$/', $linea, $m)) {
                continue;
            }
            $fecha = $this->parseFecha($m[1]);
            $resto = trim($m[2]);
            $partes = preg_split('/\s{2,}/', $resto);
            if (count($partes) < 2) {
                continue;
            }
            $importeStr = end($partes);
            if (!preg_match('/^[\d.,]+-?$/', $importeStr)) {
                continue;
            }
            $establecimiento = $partes[0];
            $localidad       = $partes[1] ?? '';
            $importeAbs      = $this->parseImporte($importeStr);
            $esDevolucion    = $this->esDevolucion($importeStr);

            $filas[] = [
                'fecha'    => $fecha,
                'concepto' => trim($establecimiento . ($localidad !== $importeStr ? ' - ' . $localidad : '')),
                'importe'  => $esDevolucion ? $importeAbs : -$importeAbs,
                'saldo'    => null,
                'tarjeta'  => $tarjeta,
            ];
        }

        return [
            'hojas' => [
                [
                    'sugerencia_cuenta' => null,
                    'filas'             => $filas,
                ],
            ],
            'cargo_conocido' => ($cargoFecha && $cargoImporte !== null)
                ? ['fecha' => $cargoFecha, 'importe_total' => $cargoImporte]
                : null,
        ];
    }

    private function esDevolucion(string $importeStr): bool
    {
        return str_ends_with(trim($importeStr), '-');
    }

    private function parseImporte(string $raw): float
    {
        $raw = rtrim(trim($raw), '-');
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
        return (float) $raw;
    }

    private function parseFecha(string $raw): string
    {
        [$d, $m, $y] = explode('/', $raw);
        $y = strlen($y) === 2 ? '20' . $y : $y;
        return \Carbon\Carbon::createFromFormat('d/m/Y', str_pad($d, 2, '0', STR_PAD_LEFT) . '/' . str_pad($m, 2, '0', STR_PAD_LEFT) . '/' . $y)->toDateString();
    }
}
