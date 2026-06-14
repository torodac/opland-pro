<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;
use App\Models\ProjectTable;

return new class extends Migration
{
    public function up(): void
    {
        // Añade los table_fields de ver/editar a tablas de roles ya existentes
        foreach (Project::all() as $project) {
            $rolesTable = $project->tables()->where('name', 'roles')->first();
            if (!$rolesTable) continue;

            $toAdd = [
                ['name' => 'ver',    'label' => 'Puede ver',    'type' => 'multitabla', 'order' => 30],
                ['name' => 'editar', 'label' => 'Puede editar', 'type' => 'multitabla', 'order' => 40],
            ];

            foreach ($toAdd as $f) {
                if ($rolesTable->fields()->where('name', $f['name'])->exists()) continue;

                $rolesTable->fields()->create(array_merge($f, [
                    'in_list' => false, 'in_form' => true, 'required' => false,
                ]));
            }
        }
    }

    public function down(): void
    {
        foreach (Project::all() as $project) {
            $rolesTable = $project->tables()->where('name', 'roles')->first();
            if (!$rolesTable) continue;

            $rolesTable->fields()->whereIn('name', ['ver', 'editar'])->delete();
        }
    }
};
