<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Columna de texto redundante con id_departamento (FK real a vm_departamentos, que ya es
    // la fuente de verdad usada por VmHorasService::esDeptoTurno() y el resto de la app). Se
    // eliminaba a mano en la ficha genérica sin sincronizarse nunca con id_departamento.
    public function up(): void
    {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->dropColumn('departamento');
        });
    }

    public function down(): void
    {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->string('departamento')->nullable();
        });
    }
};
