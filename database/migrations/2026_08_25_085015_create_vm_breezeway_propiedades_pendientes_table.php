<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mismo patrón que vm_breezeway_pendientes (personas de Breezeway sin usuario en Opland), pero
// para propiedades: cada fila es una propiedad activa en Breezeway sin match en vm_propiedades
// (ver BreezewaySyncPropertiesCommand).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_breezeway_propiedades_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->bigInteger('breezeway_id')->unique();
            $table->date('fecha_alta');
            $table->timestamp('ultima_deteccion')->nullable();
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
        Schema::dropIfExists('vm_breezeway_propiedades_pendientes');
    }
};
