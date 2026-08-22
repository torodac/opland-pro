<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mb_tarjetas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_viviendas');
            $table->string('codigo', 20);
            $table->integer('anio');
            $this->stdCols($table);

            $table->foreign('id_viviendas')->references('id')->on('mb_viviendas');
            // Unique en (codigo, anio), no en codigo solo: cada año se reparten tarjetas
            // nuevas y el mismo codigo de 4 digitos puede reutilizarse en otro año.
            $table->unique(['codigo', 'anio'], 'mb_tarjetas_codigo_anio_unique');
        });

        Schema::create('mb_invitados_registro', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tarjetas');
            $table->integer('personas');
            $table->date('fecha');
            $table->time('hora');
            $this->stdCols($table);

            $table->foreign('id_tarjetas')->references('id')->on('mb_tarjetas');
        });

        // Backfill: mb_viviendas.qr_codigo trae varios codigos separados por coma (mismo
        // formato que ya usa AsambleaRepartoController::vivienda()). Se siembra mb_tarjetas
        // con esos codigos vigentes hoy (anio = año actual) para no perder lo ya asignado,
        // sin tocar qr_codigo ni el flujo de reparto de asamblea.
        $anioActual = (int) now()->format('Y');
        $viviendas = DB::table('mb_viviendas')
            ->whereNotNull('qr_codigo')
            ->where('qr_codigo', '!=', '')
            ->get(['id', 'qr_codigo']);

        $vistos = [];
        $duplicados = [];
        $now = now();

        foreach ($viviendas as $v) {
            $codigos = array_filter(array_map(fn ($c) => trim($c), explode(',', $v->qr_codigo)));
            foreach ($codigos as $codigo) {
                if (isset($vistos[$codigo])) {
                    $duplicados[] = "{$codigo} (vivienda {$v->id}, ya usado por vivienda {$vistos[$codigo]})";
                    continue;
                }
                $vistos[$codigo] = $v->id;

                DB::table('mb_tarjetas')->insert([
                    'id_viviendas' => $v->id,
                    'codigo'       => $codigo,
                    'anio'         => $anioActual,
                    'hidden'       => 0,
                    'deleted'      => 0,
                    'createdat'    => $now,
                    'updatedat'    => $now,
                ]);
            }
        }

        if ($duplicados) {
            \Illuminate\Support\Facades\Log::warning(
                'Backfill mb_tarjetas: codigos duplicados omitidos: ' . implode('; ', $duplicados)
            );
        }
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
        Schema::dropIfExists('mb_invitados_registro');
        Schema::dropIfExists('mb_tarjetas');
    }
};
