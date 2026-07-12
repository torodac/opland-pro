<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fta_soportadas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha_emision')->nullable();
            $table->string('nif_proveedor')->nullable();
            $table->string('nombre_proveedor')->nullable();
            $table->string('concepto')->nullable();
            $table->decimal('bi', 10, 2)->nullable();
            $table->decimal('iva', 10, 2)->nullable();
            $table->decimal('suplidos', 10, 2)->nullable();
            $table->decimal('total_factura', 10, 2)->nullable();
            $table->decimal('irpf', 10, 2)->nullable();
            $table->decimal('total_a_pagar', 10, 2)->nullable();
            $table->string('observacion')->nullable();
            $table->string('file_documento', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fta_soportadas');
    }
};
