<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// vm_contratos no tenía forma de expresar cuántos días/turnos a la semana trabaja una persona --
// VmHorasService asumía siempre 5 (jornada partida habitual) al calcular horas esperadas
// (horas_semana/5). Caso real que lo destapó: Mykola Krupa, contrato de 3h/semana trabajadas en
// un único turno semanal -- con el /5 fijo, se le contaban +2,4h de "extra" cada turno aunque
// cumplía su contrato exacto. Default 5 para no alterar el cálculo de nadie más.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_contratos', function (Blueprint $table) {
            $table->smallInteger('dias_semana')->default(5)->after('horas_semana');
        });
    }

    public function down(): void
    {
        Schema::table('vm_contratos', function (Blueprint $table) {
            $table->dropColumn('dias_semana');
        });
    }
};
