<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vm_tareas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('deleted')->default(0);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
            $table->string('Tipo')->nullable();
            $table->date('fecha_planificada')->nullable();
            $table->date('fecha_finalizacion')->nullable();
            $table->time('duracion')->nullable();
            $table->json('control_user')->nullable();
            $table->smallInteger('blocked')->default(0);
            $table->unsignedBigInteger('master_duraciones')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('comentario')->nullable();
            $table->unsignedBigInteger('propiedad')->nullable();
            $table->unsignedBigInteger('id_propiedades')->nullable();
            $table->string('estado')->nullable();
            $table->unsignedBigInteger('id_reservas')->nullable();
            $table->time('tiempo')->nullable();
            $table->unsignedBigInteger('id_departamento')->nullable();
        });
    }
    public function down(): void {
        Schema::dropIfExists('vm_tareas');
    }
};
