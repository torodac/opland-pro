<?php

namespace App\Imports;

use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class TablaImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $errors   = [];
    public int   $inserted = 0;
    public int   $updated  = 0;
    public int   $skipped  = 0;

    // Cache de lookups texto→id por campo: ['campo' => ['nombre' => id]]
    private array $refCache = [];

    public function __construct(
        private Project $project,
        private ProjectTable $projectTable,
        private array $keyFields,
        private string $dupMode,
        private int $userId = 0,
        private bool $skipErrors = false
    ) {
        $this->buildRefCache();
    }

    // Convierte dd/mm/aaaa [hh:MM[:ss]] → aaaa-mm-dd [hh:MM:ss]
    private function normalizeDateValue(string $val, string $type): string
    {
        // Formato dd/mm/aaaa hh:MM[:ss]
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $val, $m)) {
            $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            $time = sprintf('%02d:%02d:%02d', $m[4], $m[5], $m[6] ?? 0);
            return $type === 'fecha' ? $date : "{$date} {$time}";
        }
        // Formato dd/mm/aaaa
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return $val;
    }

    // Pre-carga los mapas nombre→id para todos los campos desplegable
    private function buildRefCache(): void
    {
        foreach ($this->projectTable->fields as $field) {
            $refTable = $field->getRefTable();
            if (!$refTable) continue;

            $fullRef = $this->project->slug . '_' . $refTable;
            $this->refCache[$field->name] = DB::table($fullRef)
                ->where('deleted', 0)
                ->pluck('id', 'nombre')
                ->toArray();
        }
    }

    public function collection(Collection $rows): void
    {
        $fullTable    = $this->projectTable->getFullTableName();
        $allowedNames = $this->projectTable->fields->pluck('name')->toArray();
        $fieldTypes   = $this->projectTable->fields->pluck('type', 'name')->toArray();
        $now          = now();

        foreach ($rows as $row) {
            $rowData = [];

            foreach ($allowedNames as $name) {
                if (!isset($row[$name])) continue;
                $valor = $row[$name] === '' ? null : $row[$name];

                // Si el campo tiene lookup y el valor no es numérico, resolver texto→id
                if ($valor !== null && isset($this->refCache[$name]) && !is_numeric($valor)) {
                    $valor = $this->refCache[$name][trim((string) $valor)] ?? null;
                }

                // Normalizar fechas y timestamps en formato dd/mm/aaaa [hh:MM]
                if ($valor !== null && in_array($fieldTypes[$name] ?? '', ['fecha', 'timestamp'])) {
                    $valor = $this->normalizeDateValue((string) $valor, $fieldTypes[$name]);
                }

                $rowData[$name] = $valor;
            }

            if (empty($rowData)) {
                $this->skipped++;
                continue;
            }

            // Deduplicación
            $hasKeys = !empty($this->keyFields) && collect($this->keyFields)->every(fn($k) => isset($rowData[$k]));
            if ($hasKeys) {
                $q = DB::table($fullTable)->where('deleted', 0);
                foreach ($this->keyFields as $k) {
                    $q->where($k, $rowData[$k]);
                }
                $exists = $q->first();

                if ($exists) {
                    if ($this->dupMode === 'skip') {
                        $this->skipped++;
                        continue;
                    }
                    if ($this->dupMode === 'update') {
                        DB::table($fullTable)
                            ->where('id', $exists->id)
                            ->update(array_merge($rowData, ['updatedat' => $now, 'updateuser' => $this->userId]));
                        $this->updated++;
                        continue;
                    }
                }
            }

            try {
                DB::table($fullTable)->insert(array_merge($rowData, [
                    'createdat'  => $now,
                    'updatedat'  => $now,
                    'createuser' => $this->userId,
                    'updateuser' => $this->userId,
                    'deleted'    => 0,
                    'hidden'     => 0,
                ]));
                $this->inserted++;
            } catch (\Exception $e) {
                if ($this->skipErrors) {
                    $this->skipped++;
                } else {
                    throw $e;
                }
            }
        }
    }
}
