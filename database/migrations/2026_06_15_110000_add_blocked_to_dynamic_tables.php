<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Project::all() as $project) {
            foreach ($project->tables as $projectTable) {
                $fullTable = $project->slug . '_' . $projectTable->name;

                if (Schema::hasTable($fullTable) && !Schema::hasColumn($fullTable, 'blocked')) {
                    Schema::table($fullTable, function (Blueprint $t) {
                        $t->tinyInteger('blocked')->default(0)->after('nombre');
                    });
                }

                if (!$projectTable->fields()->where('name', 'blocked')->exists()) {
                    $projectTable->fields()->create([
                        'name'     => 'blocked',
                        'label'    => 'Bloqueado',
                        'type'     => 'tinyint',
                        'order'    => 996,
                        'in_list'  => false,
                        'in_form'  => false,
                        'required' => false,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (Project::all() as $project) {
            foreach ($project->tables as $projectTable) {
                $fullTable = $project->slug . '_' . $projectTable->name;
                if (Schema::hasTable($fullTable) && Schema::hasColumn($fullTable, 'blocked')) {
                    Schema::table($fullTable, fn(Blueprint $t) => $t->dropColumn('blocked'));
                }
                $projectTable->fields()->where('name', 'blocked')->delete();
            }
        }
    }
};
