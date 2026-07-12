<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tipo_caja')->constrained('tipo_caja');
            $table->string('nombre');
            $table->foreignId('id_clientes')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('id_proyectos')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->date('fecha_movimiento')->nullable();
            $table->decimal('importe', 10, 2);
            $table->foreignId('id_facturas')->nullable()->constrained('facturas')->nullOnDelete();
            $table->string('file_documento', 512)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->boolean('deleted')->default(false);
            $table->foreignId('id_fta_soportadas')->nullable()->constrained('fta_soportadas')->nullOnDelete();
            $table->foreignId('id_banco')->nullable()->constrained('banco')->nullOnDelete();
            $table->date('fecha_contable')->nullable();
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja');
    }
};
