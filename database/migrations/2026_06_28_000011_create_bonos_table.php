<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bonos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('control_user')->nullable();
            $table->integer('horas')->nullable();
            $table->date('fecha_contratacion')->nullable();
            $table->foreignId('id_clientes')->constrained('clientes');
            $table->boolean('deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonos');
    }
};
