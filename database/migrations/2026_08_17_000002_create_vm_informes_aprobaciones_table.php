<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_informes_aprobaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->string('step', 20); // rrhh | coordinador | trabajador | direccion
            $table->unsignedBigInteger('aprobado_por');
            $table->char('content_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('aprobado_at');
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
            $table->unique(['id_usuario', 'anio', 'mes', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_informes_aprobaciones');
    }
};
