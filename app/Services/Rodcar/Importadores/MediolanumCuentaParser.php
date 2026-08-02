<?php

namespace App\Services\Rodcar\Importadores;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MediolanumCuentaParser
{
    // Extracto de cuenta Mediolanum: puede traer varias cuentas, una por hoja (Hoja1, Hoja2...).
    // Cada hoja trae su propio número de cuenta en la fila de título ("Consulta de extracto de la
    // cuenta" | nº cuenta | ... | "Saldo Inicial (EUR):" | | saldo). Columnas de movimientos:
    // Fecha Operación / Concepto / Fecha Valor / Pagos / Ingresos / Saldo (fechas como nº de serie Excel).
    public function parse(string $path): array
    {
        $spreadsheet = IOFactory::createReaderForFile($path)->load($path);

        $hojas = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestRow = $sheet->getHighestRow();

            $headerRow  = null;
            $numeroCta  = null;
            for ($r = 1; $r <= min(30, $highestRow); $r++) {
                $a = trim((string) $sheet->getCell("A{$r}")->getValue());
                $b = trim((string) $sheet->getCell("B{$r}")->getValue());
                if (str_starts_with($a, 'Consulta de extracto')) {
                    $numeroCta = $b !== '' ? $b : null;
                }
                if (str_starts_with($a, 'Fecha Operaci') && str_starts_with($b, 'Concepto')) {
                    $headerRow = $r;
                    break;
                }
            }
            if ($headerRow === null) {
                continue; // hoja vacía o sin datos
            }

            $filas = [];
            for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
                $fechaRaw = $sheet->getCell("A{$r}")->getValue();
                $concepto = trim((string) $sheet->getCell("B{$r}")->getValue());
                $pagos    = $sheet->getCell("D{$r}")->getValue();
                $ingresos = $sheet->getCell("E{$r}")->getValue();
                $saldo    = $sheet->getCell("F{$r}")->getValue();

                if ($fechaRaw === null || $fechaRaw === '' || $concepto === '') {
                    continue;
                }

                $fecha = is_numeric($fechaRaw)
                    ? ExcelDate::excelToDateTimeObject($fechaRaw)->format('Y-m-d')
                    : \Carbon\Carbon::parse($fechaRaw)->toDateString();

                $importe = (float) ($ingresos ?: 0) - (float) ($pagos ?: 0);
                if ($importe === 0.0 && ($pagos === null || $pagos === '') && ($ingresos === null || $ingresos === '')) {
                    continue;
                }

                $filas[] = [
                    'fecha'    => $fecha,
                    'concepto' => $concepto,
                    'importe'  => round($importe, 2),
                    'saldo'    => $saldo !== null && $saldo !== '' ? round((float) $saldo, 2) : null,
                    'tarjeta'  => null,
                ];
            }

            if (!empty($filas)) {
                $hojas[] = [
                    'sugerencia_cuenta' => $numeroCta ?? ($sheet->getTitle() !== 'Hoja1' ? $sheet->getTitle() : null),
                    'filas'             => $filas,
                ];
            }
        }

        return ['hojas' => $hojas, 'cargo_conocido' => null];
    }
}
