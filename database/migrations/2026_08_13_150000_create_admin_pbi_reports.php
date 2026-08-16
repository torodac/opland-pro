<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Equivalente moderno de admin_pbi_tables (legacy): un informe de Power BI embebido por
// proyecto. Sustituye el flujo legacy de token de un solo uso (admin_pbi_access/admin_pbi_log)
// por autenticacion normal via 'auth' + 'project.access', ya que aqui el usuario ya tiene
// sesion (el legacy lo necesitaba para poder servir el iframe de forma stateless).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_pbi_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_proyectos')->constrained('admin_projects')->cascadeOnDelete();
            $table->string('reportid');
            // Filtros fijos aplicados siempre al embeber (igual que el legacy): array de
            // {"tabla":"...", "columna":"...", "valor": "..."} -> report.updateFilters() al cargar.
            $table->json('filtros')->nullable();
            $table->string('reportpage')->nullable();
            $table->boolean('page_navigation')->default(true);
            $table->boolean('filters_visible')->default(false);
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('deleted')->default(0);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_pbi_reports');
    }
};
