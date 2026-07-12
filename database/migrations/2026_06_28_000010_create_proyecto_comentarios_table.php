<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proyecto_comentarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('id_proyectos')->constrained('proyectos')->cascadeOnDelete();
            $table->text('comentario')->nullable();
            $table->string('file_fichero', 512)->nullable();
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_comentarios');
    }
};
