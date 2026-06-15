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
            $table = $project->slug . '_usuarios';

            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'acceso')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('acceso', 20)->default('APP y web')->after('telefono');
                });
            }

            $projectTable = $project->tables()->where('name', 'usuarios')->first();
            if ($projectTable && !$projectTable->fields()->where('name', 'acceso')->exists()) {
                $projectTable->fields()->create([
                    'name'     => 'acceso',
                    'label'    => 'Acceso',
                    'type'     => 'select',
                    'extras'   => 'opt:APP,web,APP y web,sin acceso',
                    'order'    => 50,
                    'in_list'  => true,
                    'in_form'  => true,
                    'required' => false,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (Project::all() as $project) {
            $table = $project->slug . '_usuarios';
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'acceso')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropColumn('acceso'));
            }
            $project->tables()->where('name', 'usuarios')
                ->first()?->fields()->where('name', 'acceso')->delete();
        }
    }
};
