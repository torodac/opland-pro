<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registro de las tareas de cambio de batería que Opland crea en Breezeway desde el sync de Nuki.
//
// Sirve para no duplicar: la tarea recién creada tarda en volver a Opland (breezeway:sync-tasks
// corre por horas), así que mirar solo vm_tareas_mantenimiento no basta para saber si ya se pidió.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vm_nuki_tareas_breezeway')) {
            return;
        }

        Schema::create('vm_nuki_tareas_breezeway', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nuki_smartlock_id');
            $table->unsignedBigInteger('id_propiedades');
            $table->unsignedBigInteger('breezeway_task_id')->unique();
            $table->timestamp('creada_at');
            $table->timestamps();

            $table->index(['id_propiedades', 'creada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_nuki_tareas_breezeway');
    }
};
