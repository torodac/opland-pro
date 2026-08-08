<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de parametrización: cada texto de concepto (tal cual aparece en el informe de
        // cuotas) mapeado a su ejercicio y tipo de cuota. Se siembra desde el histórico real de
        // mb_cuotas y se completa via el flujo de importación cuando aparece un concepto nuevo.
        Schema::create('mb_cuotas_mapeo', function (Blueprint $table) {
            $table->id();
            $table->string('concepto')->unique();
            $table->string('ejercicio', 9);
            $table->string('tipo_cuota', 20);
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });

        // Copia de mb_cuotas para probar el pipeline de carga sin tocar la tabla en producción.
        Schema::create('mb_cuotas_provisional', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_viviendas')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_emision');
            $table->string('concepto');
            $table->string('ejercicio', 9);
            $table->string('tipo_cuota', 20);
            $table->string('propietario')->nullable();
            $table->string('forma_pago', 20)->nullable();
            $table->decimal('importe', 12, 2);
            $table->decimal('pendiente', 12, 2);
            $table->date('fecha_pago')->nullable();
            $table->decimal('importe_cobrado', 12, 2)->nullable();
            $table->string('estado')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });

        // Histórico de cambios de "pendiente" detectados en cada carga de fichero.
        Schema::create('mb_cuotas_pendiente_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cuota');
            $table->decimal('pendiente_anterior', 12, 2);
            $table->decimal('pendiente_nuevo', 12, 2);
            $table->date('fecha_carga');
            $table->timestamp('createdat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mb_cuotas_pendiente_historico');
        Schema::dropIfExists('mb_cuotas_provisional');
        Schema::dropIfExists('mb_cuotas_mapeo');
    }
};
