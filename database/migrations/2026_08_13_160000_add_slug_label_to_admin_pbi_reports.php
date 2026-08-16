<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Un proyecto puede tener varios informes de Power BI (p.ej. vm: Operaciones/Gerencia/
// Gobernanta, cada uno restringido a roles distintos en el legacy) -- 'slug' identifica cada
// informe dentro de un proyecto y forma parte de la URL; 'label' es el texto del enlace del
// sidebar cuando hay mas de uno.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_pbi_reports', function (Blueprint $table) {
            $table->string('slug')->default('default')->after('id_proyectos');
            $table->string('label')->default('Power BI')->after('slug');
        });
        DB::statement('CREATE UNIQUE INDEX admin_pbi_reports_proyecto_slug_unique ON admin_pbi_reports (id_proyectos, slug) WHERE deleted = 0');
    }

    public function down(): void
    {
        Schema::table('admin_pbi_reports', function (Blueprint $table) {
            $table->dropColumn(['slug', 'label']);
        });
    }
};
