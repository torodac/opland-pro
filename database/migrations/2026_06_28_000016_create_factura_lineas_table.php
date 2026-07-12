<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_lineas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('id_facturas')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('id_conceptos')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->decimal('precio', 10, 2)->nullable();
            $table->decimal('descuentoe', 10, 2)->nullable();
            $table->integer('descuentoporc')->nullable();
            $table->boolean('deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_lineas');
    }
};
