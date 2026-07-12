<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nombre_fiscal')->nullable();
            $table->string('nif', 15)->nullable();
            $table->string('direccion')->nullable();
            $table->string('cp', 5)->nullable();
            $table->string('poblacion')->nullable();
            $table->string('sector')->nullable();
            $table->string('direccion_postal')->nullable();
            $table->string('cp_postal', 5)->nullable();
            $table->string('poblacion_postal')->nullable();
            $table->boolean('deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
