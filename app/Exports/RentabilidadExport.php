<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Excel de la pestaña "Rentabilidad" de /vm/informe-financiero -- mismas columnas y orden que la
// tabla en pantalla. $filas es el array ya calculado por
// InformeFinancieroController::rentabilidadPorPropiedad().
class RentabilidadExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(private array $filas)
    {
    }

    public function array(): array
    {
        return array_map(fn($f) => [
            $f['propiedad'],
            $f['ingresos'],
            $f['beneficio'],
            $f['margen'],
            $f['dias_reservados'],
            $f['adr'],
            $f['ocupacion'],
            $f['revpar'],
        ], $this->filas);
    }

    public function headings(): array
    {
        return ['Propiedad', 'Ingresos', 'Beneficio', 'Margen %', 'Días ocupados', 'ADR', '% Ocupación', 'RevPAR'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
