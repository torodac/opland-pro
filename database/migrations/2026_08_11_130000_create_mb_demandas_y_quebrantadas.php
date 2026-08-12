<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expediente de demanda judicial: agrupa, por vivienda, todas sus cuotas en estado
        // "Demandada". Una vivienda solo tiene un expediente abierto a la vez -- las cuotas
        // enlazan aquí mediante mb_cuotas.id_demandas.
        Schema::create('mb_demandas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->unsignedBigInteger('id_viviendas');
            $table->decimal('importe_total', 12, 2)->default(0);
            $table->integer('num_cuotas')->default(0);
            $table->date('fecha_apertura')->nullable();
            $table->string('estado')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('blocked')->default(false);
            $table->boolean('hidden')->default(false);
            $table->boolean('deleted')->default(false);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });

        Schema::table('mb_cuotas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_demandas')->nullable()->after('estado');
        });

        // Copia plana de las cuotas en estado "Incobrable" o "Anulada" -- todas las columnas de
        // mb_cuotas, más la referencia a la cuota original.
        Schema::create('mb_cuotas_quebrantadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cuota_original');
            $table->unsignedBigInteger('id_viviendas')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('concepto')->nullable();
            $table->string('ejercicio', 9)->nullable();
            $table->string('tipo_cuota', 20)->nullable();
            $table->string('propietario')->nullable();
            $table->string('forma_pago', 20)->nullable();
            $table->decimal('importe', 12, 2)->nullable();
            $table->decimal('pendiente', 12, 2)->nullable();
            $table->date('fecha_pago')->nullable();
            $table->decimal('importe_cobrado', 12, 2)->nullable();
            $table->string('estado')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mb_cuotas_quebrantadas');

        Schema::table('mb_cuotas', function (Blueprint $table) {
            $table->dropColumn('id_demandas');
        });

        Schema::dropIfExists('mb_demandas');
    }
};
