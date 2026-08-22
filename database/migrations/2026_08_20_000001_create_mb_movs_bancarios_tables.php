<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mb_movs_cuenta', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('iban')->nullable();
            $table->string('numero_cuenta', 20)->nullable();
            $this->stdCols($table);
        });

        Schema::create('mb_movs_mapeo', function (Blueprint $table) {
            $table->id();
            $table->string('patron');
            $table->string('tipo_match', 20); // contiene | comienza | es_igual
            $table->boolean('case_sensitive')->default(true);
            // Sin FK real a mb_gastos_cuentas: esa tabla pertenece a un owner de BD distinto
            // (postgres) y el rol de la app no tiene permiso REFERENCES sobre ella. Se enlaza
            // igualmente vía el motor no-code (extras 'ref:gastos_cuentas'), sin constraint.
            $table->unsignedBigInteger('id_gastos_cuentas')->nullable();
            $table->integer('orden')->default(0);
            $this->stdCols($table);
        });

        Schema::create('mb_movs_import_lote', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_movs_cuenta');
            $table->string('fichero');
            $table->timestamp('fecha_importacion')->useCurrent();
            $table->integer('filas_importadas')->default(0);
            $table->integer('filas_duplicadas')->default(0);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->timestamp('createdat')->useCurrent();

            $table->foreign('id_movs_cuenta')->references('id')->on('mb_movs_cuenta');
        });

        Schema::create('mb_movs_bancarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_movs_cuenta');
            $table->unsignedBigInteger('id_lote')->nullable();
            $table->date('fecha');
            $table->date('fecha_valor')->nullable();
            $table->string('movimiento');
            $table->string('mas_datos')->nullable();
            $table->decimal('importe', 10, 2);
            $table->decimal('saldo', 10, 2)->nullable();
            $table->unsignedBigInteger('id_movs_mapeo')->nullable();
            $table->unsignedBigInteger('id_gastos_cuentas')->nullable();
            $this->stdCols($table);

            $table->foreign('id_movs_cuenta')->references('id')->on('mb_movs_cuenta');
            $table->foreign('id_lote')->references('id')->on('mb_movs_import_lote')->nullOnDelete();
            $table->foreign('id_movs_mapeo')->references('id')->on('mb_movs_mapeo')->nullOnDelete();
            // Sin FK a mb_gastos_cuentas -- ver nota en mb_movs_mapeo más arriba.
            // Clave natural para detectar duplicados de un mismo extracto re-importado (el
            // fichero del banco no trae un identificador único de movimiento).
            $table->unique(['id_movs_cuenta', 'fecha', 'movimiento', 'importe', 'saldo'], 'mb_movs_bancarios_natural_key');
        });
    }

    private function stdCols(Blueprint $table): void
    {
        $table->unsignedBigInteger('createuser')->nullable();
        $table->unsignedBigInteger('updateuser')->nullable();
        $table->timestamp('createdat')->useCurrent();
        $table->timestamp('updatedat')->useCurrent();
        $table->smallInteger('hidden')->default(0);
        $table->boolean('deleted')->default(false);
    }

    public function down(): void
    {
        Schema::dropIfExists('mb_movs_bancarios');
        Schema::dropIfExists('mb_movs_import_lote');
        Schema::dropIfExists('mb_movs_mapeo');
        Schema::dropIfExists('mb_movs_cuenta');
    }
};
