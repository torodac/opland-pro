<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// En un TPV físico se crean dos tareas (Recepción y Dirección de Operaciones). Cada una apunta a su
// gemela. Al cerrar una, la otra se marca como borrada (deleted = 1).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vm_tareas_sscc', 'id_tarea_hermana')) {
            Schema::table('vm_tareas_sscc', function (Blueprint $table) {
                $table->unsignedBigInteger('id_tarea_hermana')->nullable();
                $table->index('id_tarea_hermana', 'vm_tareas_sscc_hermana_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vm_tareas_sscc', 'id_tarea_hermana')) {
            Schema::table('vm_tareas_sscc', function (Blueprint $table) {
                $table->dropIndex('vm_tareas_sscc_hermana_idx');
                $table->dropColumn('id_tarea_hermana');
            });
        }
    }
};
