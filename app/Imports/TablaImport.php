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
        private string $dupMode
    ) {
        $this->buildRefCache();
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
                            ->update(array_merge($rowData, ['updatedat' => $now]));
                        $this->updated++;
                        continue;
                    }
                }
            }

            DB::table($fullTable)->insert(array_merge($rowData, [
                'createdat' => $now,
                'updatedat' => $now,
                'deleted'   => 0,
                'hidden'    => 0,
            ]));
            $this->inserted++;
        }
    }
}
