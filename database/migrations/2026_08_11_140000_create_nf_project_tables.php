<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nf_genero', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $this->stdCols($table);
        });

        Schema::create('nf_tipo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $this->stdCols($table);
        });

        Schema::create('nf_tipo_gasto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $this->stdCols($table);
        });

        Schema::create('nf_estado_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $this->stdCols($table);
        });

        Schema::create('nf_forma_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $this->stdCols($table);
        });

        Schema::create('nf_estado_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $this->stdCols($table);
        });

        Schema::create('nf_grupo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->unsignedBigInteger('id_tipo')->nullable();
            $this->stdCols($table);
        });

        Schema::create('nf_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('telefono')->nullable();
            $table->string('dni')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('id_genero')->nullable();
            $table->string('email')->nullable();
            $table->integer('cp')->nullable();
            $table->string('direccion')->nullable();
            $table->string('poblacion')->nullable();
            $table->string('file_foto')->nullable();
            $this->stdCols($table);
        });

        Schema::create('nf_contratos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_clientes')->nullable();
            $table->unsignedBigInteger('id_tipo')->nullable();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('importe', 8, 2)->nullable();
            $table->boolean('dia1')->default(false);
            $table->boolean('dia2')->default(false);
            $table->text('descripcion')->nullable();
            $table->string('nombre')->nullable();
            $this->stdCols($table);
        });

        Schema::create('nf_objetivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->date('fecha_objetivo')->nullable();
            $table->decimal('objetivo', 8, 2)->nullable();
            $table->string('nombre')->nullable();
            $this->stdCols($table);
        });

        Schema::create('nf_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_clientes')->nullable();
            $table->date('fecha_pago')->nullable();
            $table->date('fecha_pagado')->nullable();
            $table->unsignedBigInteger('id_contratos')->nullable();
            $table->decimal('cantidad', 8, 2)->nullable();
            $table->unsignedBigInteger('id_estado_pagos')->nullable();
            $table->unsignedBigInteger('id_forma_pago')->nullable();
            $table->string('nombre')->nullable();
            $this->stdCols($table);
        });

        Schema::create('nf_gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tipo_gasto')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_gasto')->nullable();
            $table->decimal('importe', 8, 2)->nullable();
            $this->stdCols($table);
        });

        Schema::create('nf_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('num_documento')->nullable();
            $table->string('file_documento')->nullable();
            $table->unsignedBigInteger('id_estado_documento')->nullable();
            $table->date('fecha_envio')->nullable();
            $table->unsignedBigInteger('id_clientes')->nullable();
            $this->stdCols($table);
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
        Schema::dropIfExists('nf_documentos');
        Schema::dropIfExists('nf_gastos');
        Schema::dropIfExists('nf_pagos');
        Schema::dropIfExists('nf_objetivos');
        Schema::dropIfExists('nf_contratos');
        Schema::dropIfExists('nf_clientes');
        Schema::dropIfExists('nf_grupo');
        Schema::dropIfExists('nf_estado_documento');
        Schema::dropIfExists('nf_forma_pago');
        Schema::dropIfExists('nf_estado_pagos');
        Schema::dropIfExists('nf_tipo_gasto');
        Schema::dropIfExists('nf_tipo');
        Schema::dropIfExists('nf_genero');
    }
};
