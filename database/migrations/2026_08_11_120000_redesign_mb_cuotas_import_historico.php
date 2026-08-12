<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dato en bruto: una fila por cada cuota y cada fecha de exportación de fichero cargado.
        // Nunca se pierde -- permite reconstruir el estado actual y el histórico de cambios aunque
        // los ficheros se carguen en cualquier orden (incluido backfill de ficheros de años antiguos).
        Schema::create('mb_cuotas_exportaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_viviendas');
            $table->string('concepto');
            $table->date('fecha_emision');
            $table->date('fecha_exportacion');
            $table->string('ejercicio', 9);
            $table->string('tipo_cuota', 20);
            $table->string('propietario')->nullable();
            $table->string('forma_pago', 20)->nullable();
            $table->decimal('importe', 12, 2);
            $table->decimal('pendiente', 12, 2);
            $table->decimal('importe_cobrado', 12, 2)->nullable();
            $table->string('estado')->nullable();
            $table->string('fichero_origen')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
            $table->index(['id_viviendas', 'fecha_emision']);
            $table->index('fecha_exportacion');
        });

        Schema::table('mb_cuotas_provisional', function (Blueprint $table) {
            $table->renameColumn('fecha_pago', 'fecha_cobro');
        });
        Schema::table('mb_cuotas_provisional', function (Blueprint $table) {
            $table->date('fecha_exportacion')->nullable()->after('estado');
        });

        Schema::rename('mb_cuotas_pendiente_historico', 'mb_cuotas_estado_historico');

        Schema::table('mb_cuotas_estado_historico', function (Blueprint $table) {
            $table->renameColumn('fecha_carga', 'fecha_exportacion');
        });
        Schema::table('mb_cuotas_estado_historico', function (Blueprint $table) {
            $table->string('estado_anterior')->nullable()->after('id_cuota');
            $table->string('estado_nuevo')->nullable()->after('estado_anterior');
            $table->string('fichero_origen')->nullable()->after('pendiente_nuevo');
        });
    }

    public function down(): void
    {
        Schema::table('mb_cuotas_estado_historico', function (Blueprint $table) {
            $table->dropColumn(['estado_anterior', 'estado_nuevo', 'fichero_origen']);
        });
        Schema::table('mb_cuotas_estado_historico', function (Blueprint $table) {
            $table->renameColumn('fecha_exportacion', 'fecha_carga');
        });
        Schema::rename('mb_cuotas_estado_historico', 'mb_cuotas_pendiente_historico');

        Schema::table('mb_cuotas_provisional', function (Blueprint $table) {
            $table->dropColumn('fecha_exportacion');
        });
        Schema::table('mb_cuotas_provisional', function (Blueprint $table) {
            $table->renameColumn('fecha_cobro', 'fecha_pago');
        });

        Schema::dropIfExists('mb_cuotas_exportaciones');
    }
};
