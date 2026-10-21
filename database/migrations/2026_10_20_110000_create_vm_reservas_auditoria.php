<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registro de auditoría del proceso de devolución de una fianza. Solo se inserta: no hay ruta ni
// código que actualice o borre filas. Cada paso (decisión, foto, tarea, correo, IBAN, edición manual)
// queda con su fecha, quién lo hizo (null si fue el proceso automático o el enlace de Filipinas) y
// un detalle en JSON.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vm_reservas_auditoria')) {
            Schema::create('vm_reservas_auditoria', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_reservas');
                $table->string('accion', 60);
                $table->text('detalle')->nullable();
                $table->unsignedBigInteger('id_usuario')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['id_reservas', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_reservas_auditoria');
    }
};
