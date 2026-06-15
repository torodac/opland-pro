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
    public Collection $rows;       // primeras 5 filas para preview
    public Collection $allRows;    // todas las filas para inferencia de tipos
    public array $rawHeadings = [];

    public function collection(Collection $rows): void
    {
        $this->allRows = $rows;
        $this->rows    = $rows->take(5);
    }

    public function headingRow(): int
    {
        return 1;
    }

    // Devuelve los tipos inferidos a partir de los datos
    public function inferTypes(): array
    {
        $source = isset($this->allRows) && $this->allRows->isNotEmpty()
            ? $this->allRows
            : $this->rows;

        if (!isset($source) || $source->isEmpty()) return [];

        $headings = array_keys($source->first()->toArray());
        $types    = [];

        foreach ($headings as $heading) {
            $values = $source->pluck($heading)->filter()->values();
            $types[$heading] = $this->inferType($values);
        }

        return $types;
    }

    /**
     * Valida todas las filas contra los tipos seleccionados.
     * Devuelve array de errores: [['row' => N, 'col' => 'nombre', 'value' => '...', 'error' => '...']]
     */
    public function validateAgainstTypes(array $fieldNames, array $fieldTypes): array
    {
        $source = isset($this->allRows) && $this->allRows->isNotEmpty() ? $this->allRows : $this->rows;
        $errors = [];

        foreach ($source as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // fila Excel (cabecera es fila 1)
            $arr = $row->toArray();

            foreach ($fieldNames as $colKey => $fieldName) {
                $type = $fieldTypes[$colKey] ?? 'string';
                // Buscamos el valor por la clave original del Excel (puede diferir de fieldName)
                $headings = array_keys($arr);
                $excelKey = $headings[$colKey] ?? null;
                $raw = $excelKey !== null ? ($arr[$excelKey] ?? null) : null;

                if ($raw === null || $raw === '') continue;
                $val = (string) $raw;

                $error = match($type) {
                    'int'     => (!preg_match('/^-?\d+$/', $val) || (int)$val > 2147483647 || (int)$val < -2147483648)
                                    ? 'No es un entero válido (rango ±2.147.483.647)' : null,
                    'tinyint' => (!in_array(strtolower($val), ['0','1','sí','si','no','true','false']))
                                    ? "Solo acepta 0/1 (valor: «{$val}»)" : null,
                    'decimal' => (!preg_match('/^-?\d+([.,]\d+)?$/', $val))
                                    ? 'No es un número decimal válido' : null,
                    'fecha'     => (!preg_match('/^(\d{1,2}\/\d{1,2}\/\d{4}|\d{2,4}[-]\d{2}[-]\d{2,4})$/', $val))
                                    ? 'Formato de fecha no reconocido (esperado dd/mm/aaaa o aaaa-mm-dd)' : null,
                    'timestamp' => (!preg_match('/^(\d{1,2}\/\d{1,2}\/\d{4}( \d{1,2}:\d{2}(:\d{2})?)?|\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?)$/', $val))
                                    ? 'Formato de timestamp no reconocido (esperado dd/mm/aaaa hh:MM o aaaa-mm-dd hh:MM:ss)' : null,
                    'email'   => (!filter_var($val, FILTER_VALIDATE_EMAIL))
                                    ? 'No es un email válido' : null,
                    'string'  => (mb_strlen($val) > 255)
                                    ? 'Supera 255 caracteres (' . mb_strlen($val) . ')' : null,
                    default   => null,
                };

                if ($error !== null) {
                    $errors[] = [
                        'row'   => $rowNum,
                        'col'   => $fieldName ?: ($excelKey ?? $colKey),
                        'value' => mb_strlen($val) > 60 ? mb_substr($val, 0, 60) . '…' : $val,
                        'error' => $error,
                    ];
                }
            }
        }

        return $errors;
    }

    private function inferType(Collection $values): string
    {
        if ($values->isEmpty()) return 'string';

        $allBool = $values->every(fn($v) => in_array(strtolower((string) $v), ['0','1','sí','si','no','true','false']));
        if ($allBool) return 'tinyint';

        $allInt = $values->every(fn($v) => preg_match('/^\d+$/', (string) $v));
        if ($allInt) {
            // Si algún valor supera el rango de integer de PostgreSQL, usar string
            // (teléfonos, IDs largos, etc. no son operables como entero)
            $maxVal = $values->max(fn($v) => (int) $v);
            return $maxVal > 2147483647 ? 'string' : 'int';
        }

        $allDecimal = $values->every(fn($v) => preg_match('/^\d+([.,]\d+)?$/', (string) $v));
        if ($allDecimal) return 'decimal';

        // Timestamp: dd/mm/aaaa hh:MM o aaaa-mm-dd hh:MM:ss
        $allTimestamp = $values->every(fn($v) => preg_match(
            '/^(\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2}|\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2})/',
            (string) $v
        ));
        if ($allTimestamp) return 'timestamp';

        // Fecha: dd/mm/aaaa o aaaa-mm-dd
        $allDate = $values->every(fn($v) => preg_match(
            '/^(\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2})$/',
            (string) $v
        ));
        if ($allDate) return 'fecha';

        $allEmail = $values->every(fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL));
        if ($allEmail) return 'email';

        $allLong = $values->every(fn($v) => strlen((string) $v) > 100);
        if ($allLong) return 'text';

        return 'string';
    }
}
