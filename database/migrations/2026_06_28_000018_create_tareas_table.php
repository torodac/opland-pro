<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 512);
            $table->text('comentario')->nullable();
            // nullable: algunas tareas antiguas tienen id_responsable=0
            $table->unsignedBigInteger('id_responsable')->nullable();
            $table->foreignId('id_prioridad')->nullable()->constrained('prioridades')->nullOnDelete();
            $table->foreignId('id_estado')->nullable()->constrained('estados')->nullOnDelete();
            $table->foreignId('id_proyectos')->constrained('proyectos');
            $table->date('fecha_aproximada')->nullable();
            $table->decimal('horas_estimadas', 10, 2)->default(0);
            $table->string('file_captura', 512)->nullable();
            $table->string('tags_etiquetas', 1024)->nullable();
            $table->foreignId('id_bonos')->nullable()->constrained('bonos')->nullOnDelete();
            $table->foreignId('id_facturas')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('id_tipo_tarea')->nullable()->constrained('tipo_tarea')->nullOnDelete();
            $table->boolean('deleted')->default(false);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
