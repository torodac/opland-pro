<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal no está instalado (necesario para Schema::table()->change()), así que
        // el cambio de tipo de columna en Postgres se hace con SQL directo.
        DB::statement('ALTER TABLE vm_propiedades ALTER COLUMN tiempo_limpieza TYPE numeric(10,2)');

        DB::table('admin_table_fields')
            ->whereIn('project_table_id', function ($q) {
                $q->select('id')->from('admin_project_tables')
                    ->where('name', 'propiedades')
                    ->whereIn('project_id', function ($q2) {
                        $q2->select('id')->from('admin_projects')->where('slug', 'vm');
                    });
            })
            ->where('name', 'tiempo_limpieza')
            ->update(['type' => 'decimal']);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vm_propiedades ALTER COLUMN tiempo_limpieza TYPE integer USING ROUND(tiempo_limpieza)');

        DB::table('admin_table_fields')
            ->whereIn('project_table_id', function ($q) {
                $q->select('id')->from('admin_project_tables')
                    ->where('name', 'propiedades')
                    ->whereIn('project_id', function ($q2) {
                        $q2->select('id')->from('admin_projects')->where('slug', 'vm');
                    });
            })
            ->where('name', 'tiempo_limpieza')
            ->update(['type' => 'int']);
    }
};
