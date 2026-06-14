<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

/**
 * Lee el Excel y devuelve cabeceras + primeras filas para preview.
 * No escribe en base de datos.
 */
class TablaFromExcelImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public Collection $rows;
    public array $rawHeadings = [];

    public function collection(Collection $rows): void
    {
        $this->rows = $rows->take(5); // solo primeras 5 filas para preview
    }

    public function headingRow(): int
    {
        return 1;
    }

    // Devuelve los tipos inferidos a partir de los datos
    public function inferTypes(): array
    {
        if (!isset($this->rows) || $this->rows->isEmpty()) return [];

        $headings = array_keys($this->rows->first()->toArray());
        $types    = [];

        foreach ($headings as $heading) {
            $values = $this->rows->pluck($heading)->filter()->values();
            $types[$heading] = $this->inferType($values);
        }

        return $types;
    }

    private function inferType(Collection $values): string
    {
        if ($values->isEmpty()) return 'string';

        $allBool = $values->every(fn($v) => in_array(strtolower((string) $v), ['0','1','sí','si','no','true','false']));
        if ($allBool) return 'tinyint';

        $allInt = $values->every(fn($v) => preg_match('/^\d+$/', (string) $v));
        if ($allInt) return 'int';

        $allDecimal = $values->every(fn($v) => preg_match('/^\d+([.,]\d+)?$/', (string) $v));
        if ($allDecimal) return 'decimal';

        $allDate = $values->every(function ($v) {
            try {
                return (bool) \Carbon\Carbon::parse((string) $v);
            } catch (\Exception) {
                return false;
            }
        });
        if ($allDate && $values->every(fn($v) => preg_match('/\d{2,4}[-\/]\d{2}[-\/]\d{2,4}/', (string) $v))) {
            return 'fecha';
        }

        $allEmail = $values->every(fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL));
        if ($allEmail) return 'email';

        $allLong = $values->every(fn($v) => strlen((string) $v) > 100);
        if ($allLong) return 'text';

        return 'string';
    }
}
