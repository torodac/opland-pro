<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 128);
            $table->string('num_fact', 20);
            $table->string('descripcion', 255)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->foreignId('id_clientes')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('id_proyectos')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->decimal('dtototae', 10, 2)->default(0);
            $table->integer('dtototalporc')->nullable();
            $table->integer('iva')->default(21);
            $table->decimal('base_imponible', 10, 2)->nullable();
            $table->decimal('total_a_pagar', 10, 2)->nullable();
            $table->boolean('incobrable')->default(false);
            $table->string('file_documento', 512)->nullable();
            $table->boolean('deleted')->default(false);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
