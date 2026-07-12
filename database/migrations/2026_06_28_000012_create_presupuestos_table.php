<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 128);
            $table->string('num_ppto', 20);
            $table->string('descripcion', 128)->nullable();
            $table->foreignId('id_clientes')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('id_proyectos')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->date('fecha_presentacion')->nullable();
            $table->date('fecha_aprobacion')->nullable();
            $table->decimal('importe', 10, 2)->nullable();
            $table->string('file_documento', 512)->nullable();
            $table->decimal('dtototale', 10, 2)->nullable();
            $table->integer('dtototalporc')->nullable();
            $table->string('code', 11)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
