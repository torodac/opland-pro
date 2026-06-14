<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TablaExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    private Collection $campos;
    private array $fkOptions   = [];  // [fieldName => [id => nombre]]
    private array $usuariosMap = [];  // [id => nombre]

    public function __construct(
        private Project $project,
        private ProjectTable $projectTable,
        private Collection $registros,
        ?Collection $campos = null,
        private bool $includeId = false
    ) {
        $this->campos = $campos ?? $projectTable->fields->where('in_list', true)->values();
        $this->loadLookups();
    }

    private function loadLookups(): void
    {
        // FK desplegables
        foreach ($this->campos as $campo) {
            $refTable = $campo->getRefTable();
            if (!$refTable) continue;
            $fullRef = $this->project->slug . '_' . $refTable;
            $this->fkOptions[$campo->name] = DB::table($fullRef)
                ->where('deleted', 0)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray();
        }

        // Usuarios del proyecto (para multiusuario / control_user)
        $usuariosTable = $this->project->slug . '_usuarios';
        if (Schema::hasTable($usuariosTable)) {
            $this->usuariosMap = DB::table($usuariosTable)
                ->pluck('nombre', 'id')
                ->toArray();
        }
    }

    public function collection(): Collection
    {
        return $this->registros;
    }

    public function headings(): array
    {
        $heads = $this->campos->pluck('label')->toArray();
        return $this->includeId ? array_merge(['ID'], $heads) : $heads;
    }

    public function map($row): array
    {
        $cells = $this->campos->map(function ($campo) use ($row) {
            $valor = $row->{$campo->name} ?? null;

            return match ($campo->type) {
                'tinyint' => $valor ? 'Sí' : 'No',

                'fecha' => $valor
                    ? \Carbon\Carbon::parse($valor)->format('d-m-Y')
                    : '',

                'desplegable', 'id' => $valor
                    ? ($this->fkOptions[$campo->name][$valor] ?? $valor)
                    : '',

                'multiusuario', 'multitabla' => implode(', ',
                    array_map(
                        fn($id) => $this->usuariosMap[$id] ?? "#{$id}",
                        json_decode($valor ?: '[]', true) ?? []
                    )
                ),

                default => $valor ?? '',
            };
        })->toArray();

        return $this->includeId ? array_merge([$row->id ?? ''], $cells) : $cells;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
