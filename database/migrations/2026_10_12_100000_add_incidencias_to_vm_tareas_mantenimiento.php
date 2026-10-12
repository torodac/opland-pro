<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Las "incidencias reportadas" de Breezeway (reported_issue) son tareas como cualquier otra en su
// API, pero significan algo distinto: alguien que estaba haciendo una limpieza encontro un
// desperfecto y lo anoto. Son la evidencia que hace falta para decidir una fianza, asi que hay
// que poder distinguirlas y saber de que limpieza salieron.
//
// El vinculo a la limpieza existe en Breezeway (parent_task_id) pero NO viaja por la API REST que
// usamos: solo por webhooks. Mientras tanto se deduce, y por eso se guarda aparte si el vinculo
// es deducido -- el dia que lleguen los webhooks hay que saber cuales sustituir.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vm_tareas_mantenimiento', 'reported_issue')) {
            return;
        }

        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            // Smallint 0/1 como el resto de banderas de estas tablas (blocked, hidden, deleted).
            $table->smallInteger('reported_issue')->default(0);
            // inspection | maintenance | safety. Las de inspection acaban en esta misma tabla, asi
            // que sin esto se perderia de donde vino cada una.
            $table->string('departamento_breezeway', 40)->nullable();
            // La hora de Breezeway, no la de nuestra importacion: createdat guarda cuando la
            // trajimos (horas despues), y con esa no se puede deducir a que limpieza pertenece.
            $table->timestamp('breezeway_createdat')->nullable();
            // La limpieza que la origino.
            $table->unsignedBigInteger('id_tareas_limpieza')->nullable();
            $table->smallInteger('vinculo_deducido')->default(0);
        });

        // La ficha de la fianza busca las incidencias de un puñado de limpiezas; sin indice son
        // tres mil y pico filas en cada carga de pantalla.
        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            $table->index('id_tareas_limpieza', 'vm_tareas_mant_id_limpieza_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vm_tareas_mantenimiento', 'reported_issue')) {
            return;
        }

        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            $table->dropIndex('vm_tareas_mant_id_limpieza_idx');
            $table->dropColumn([
                'reported_issue', 'departamento_breezeway', 'breezeway_createdat',
                'id_tareas_limpieza', 'vinculo_deducido',
            ]);
        });
    }
};
