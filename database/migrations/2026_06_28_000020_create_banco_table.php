<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banco', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_contable')->nullable();
            $table->date('fecha_valor')->nullable();
            $table->string('codigo', 10)->nullable();
            $table->string('nombre_banco')->nullable();
            $table->string('beneficiario', 155)->nullable();
            $table->string('observaciones')->nullable();
            $table->decimal('importe', 10, 2)->nullable();
            $table->decimal('saldo', 10, 2)->nullable();
            $table->string('oficina', 20)->nullable();
            $table->string('remesa')->nullable();
            $table->string('nombre');
            $table->boolean('deleted')->default(false);
            $table->integer('orden')->nullable();
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco');
    }
};
