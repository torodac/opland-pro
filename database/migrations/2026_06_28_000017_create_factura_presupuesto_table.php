<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_presupuesto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('id_facturas')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('id_presupuestos')->nullable()->constrained('presupuestos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_presupuesto');
    }
};
