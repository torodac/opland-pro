<?php

namespace App\Services\Rodcar\Importadores;

use PhpOffice\PhpSpreadsheet\IOFactory;

class KutxaTarjetaParser
{
    // Extracto de tarjeta Kutxa: hoja "Listado", columnas fecha de operación/fecha de proceso/
    // concepto/importe/tarjeta. La columna "tarjeta" trae el número de tarjeta de cada línea,
    // por lo que un mismo fichero puede traer movimientos de varias tarjetas distintas.
    // Se ignoran las filas de resumen: "saldo anterior", "total dispuesto", "total disponible".
    private const FILAS_RESUMEN = ['saldo anterior', 'total dispuesto', 'total disponible'];

    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::createReaderForFile($path)->load($path);
        $sheet       = $spreadsheet->getSheet(0);
        $highestRow  = $sheet->getHighestRow();

        $headerRow = null;
        for ($r = 1; $r <= min(30, $highestRow); $r++) {
            $a = strtolower(trim((string) $sheet->getCell("A{$r}")->getValue()));
            $c = strtolower(trim((string) $sheet->getCell("C{$r}")->getValue()));
            if (str_starts_with($a, 'fecha de operaci') && $c === 'concepto') {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === null) {
            throw new \RuntimeException('No se encontró la cabecera esperada en el fichero de tarjeta.');
        }

        $filas = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $fecha    = trim((string) $sheet->getCell("A{$r}")->getValue());
            $concepto = trim((string) $sheet->getCell("C{$r}")->getValue());
            $importe  = $sheet->getCell("D{$r}")->getValue();
            $tarjeta  = trim((string) $sheet->getCell("E{$r}")->getValue());

            if ($concepto === '' || $importe === null || $importe === '') {
                continue;
            }
            if (in_array(strtolower($concepto), self::FILAS_RESUMEN, true)) {
                continue;
            }
            if ($fecha === '') {
                continue;
            }

            $filas[] = [
                'fecha'    => \Carbon\Carbon::createFromFormat('d/m/Y', $fecha)->toDateString(),
                'concepto' => $concepto,
                'importe'  => round((float) $importe, 2),
                'saldo'    => null,
                'tarjeta'  => $tarjeta !== '' ? $tarjeta : null,
            ];
        }

        return [
            'hojas' => [
                [
                    'sugerencia_cuenta' => null,
                    'filas'             => $filas,
                ],
            ],
            'cargo_conocido' => null,
        ];
    }
}
