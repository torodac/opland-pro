<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('presupuesto_lineas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('id_presupuestos')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->decimal('precio', 10, 2)->nullable();
            $table->decimal('descuentoe', 10, 2)->nullable();
            $table->foreignId('id_conceptos')->nullable()->constrained('conceptos')->nullOnDelete();
            $table->integer('descuentoporc')->nullable();
            $table->boolean('deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_lineas');
    }
};
