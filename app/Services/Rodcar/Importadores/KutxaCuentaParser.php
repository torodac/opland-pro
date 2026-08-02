<?php

namespace App\Services\Rodcar\Importadores;

use PhpOffice\PhpSpreadsheet\IOFactory;

class KutxaCuentaParser
{
    // Extracto de cuenta Kutxa: hoja "Listado", columnas fecha/concepto/fecha valor/importe/saldo.
    // El número de cuenta no aparece en el fichero -- se sugiere a partir del nombre de la hoja del libro
    // (si el usuario ha renombrado la pestaña, p.ej. "411") o queda a elección manual en el formulario.
    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::createReaderForFile($path)->load($path);
        $sheet       = $spreadsheet->getSheet(0);
        $highestRow  = $sheet->getHighestRow();

        $headerRow = null;
        for ($r = 1; $r <= min(30, $highestRow); $r++) {
            $a = strtolower(trim((string) $sheet->getCell("A{$r}")->getValue()));
            $b = strtolower(trim((string) $sheet->getCell("B{$r}")->getValue()));
            if ($a === 'fecha' && $b === 'concepto') {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === null) {
            throw new \RuntimeException('No se encontró la cabecera esperada (fecha/concepto) en el fichero.');
        }

        $filas = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $fecha = trim((string) $sheet->getCell("A{$r}")->getValue());
            if ($fecha === '') {
                continue;
            }
            $concepto = trim((string) $sheet->getCell("B{$r}")->getValue());
            $importe  = $sheet->getCell("D{$r}")->getValue();
            $saldo    = $sheet->getCell("E{$r}")->getValue();
            if ($importe === null || $importe === '') {
                continue;
            }

            $filas[] = [
                'fecha'    => \Carbon\Carbon::createFromFormat('d/m/Y', $fecha)->toDateString(),
                'concepto' => $concepto,
                'importe'  => round((float) $importe, 2),
                'saldo'    => $saldo !== null && $saldo !== '' ? round((float) $saldo, 2) : null,
                'tarjeta'  => null,
            ];
        }

        return [
            'hojas' => [
                [
                    'sugerencia_cuenta' => $sheet->getTitle() !== 'Listado' ? $sheet->getTitle() : null,
                    'filas'             => $filas,
                ],
            ],
            'cargo_conocido' => null,
        ];
    }
}
