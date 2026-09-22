<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Publicación del cuadrante por semana. Solo se guarda fila para las semanas que alguien ha
// tocado; sin fila, la semana se considera publicada, así que la tabla nace vacía y nada cambia
// hasta que se oculte la primera.
//
// La semana se guarda en formato ISO (anio = "o" de PHP, semana = "W"), el mismo que usa la PWA
// en sus parámetros YYYY-Www, para no tener que traducir entre formatos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_horarios_publicacion', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->smallInteger('semana');
            $table->boolean('publicado')->default(true);
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();

            $table->unique(['anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_horarios_publicacion');
    }
};
