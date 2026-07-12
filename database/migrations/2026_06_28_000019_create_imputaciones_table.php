<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imputaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha_imputacion');
            // Guardado como HH:MM:SS — compatible con interval de PostgreSQL
            $table->string('duracion', 8);
            $table->boolean('no_facturable')->default(false);
            $table->foreignId('id_tareas')->nullable()->constrained('tareas')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->foreignId('id_presupuestos')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->unsignedBigInteger('control_user')->nullable();
            $table->string('factura_opland')->nullable();
            $table->string('factura_pago_interna')->nullable();
            $table->foreignId('id_fta_soportadas')->nullable()->constrained('fta_soportadas')->nullOnDelete();
            $table->string('file_imagen', 512)->nullable();
            $table->boolean('deleted')->default(false);
            $table->date('fecha_contable')->nullable();
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imputaciones');
    }
};
