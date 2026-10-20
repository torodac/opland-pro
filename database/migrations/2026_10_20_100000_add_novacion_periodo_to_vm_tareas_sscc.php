<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Una novación no tiene id propio: es propiedad + mes. Para que una tarea SSCC apunte a su
// novación se guarda el periodo (YYYY-MM) junto a id_propiedades, que ya existe. La tarea de una
// fianza enlaza con su reserva por id_reservas, así que no hace falta nada más para ella.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vm_tareas_sscc', 'novacion_periodo')) {
            Schema::table('vm_tareas_sscc', function (Blueprint $table) {
                $table->string('novacion_periodo', 7)->nullable();
                $table->index(['id_propiedades', 'novacion_periodo'], 'vm_tareas_sscc_novacion_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vm_tareas_sscc', 'novacion_periodo')) {
            Schema::table('vm_tareas_sscc', function (Blueprint $table) {
                $table->dropIndex('vm_tareas_sscc_novacion_idx');
                $table->dropColumn('novacion_periodo');
            });
        }
    }
};
