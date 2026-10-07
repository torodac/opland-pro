<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El circuito de firmas pasa a servir a dos informes: el mensual de imputaciones y el de
// kilómetros. Las dos tablas tenían la clave única sin nada que dijera de qué informe era cada
// fila, así que no podían albergar dos circuitos a la vez.
//
// Se añade 'informe' con default 'mensual', de modo que todo lo ya firmado queda marcado como
// mensual sin tocar una sola fila a mano.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_informes_estado', function (Blueprint $table) {
            $table->string('informe', 10)->default('mensual');
        });
        Schema::table('vm_informes_estado', function (Blueprint $table) {
            $table->dropUnique(['id_usuario', 'anio', 'mes']);
            $table->unique(['id_usuario', 'anio', 'mes', 'informe']);
        });

        Schema::table('vm_informes_aprobaciones', function (Blueprint $table) {
            $table->string('informe', 10)->default('mensual');
        });
        Schema::table('vm_informes_aprobaciones', function (Blueprint $table) {
            $table->dropUnique(['id_usuario', 'anio', 'mes', 'step']);
            $table->unique(['id_usuario', 'anio', 'mes', 'informe', 'step']);
        });

        // vm_informes_ediciones_log se queda como está a propósito: lo que registra es la edición
        // de un fichaje, no de un informe, y esa edición afecta a los dos por igual.
    }

    public function down(): void
    {
        Schema::table('vm_informes_aprobaciones', function (Blueprint $table) {
            // Por el nombre y no por las columnas: el que deriva Laravel pasa de los 63
            // caracteres que admite Postgres y queda truncado en "..._uniqu", así que buscarlo
            // por columnas no lo encontraría.
            $table->dropUnique('vm_informes_aprobaciones_id_usuario_anio_mes_informe_step_uniqu');
            $table->unique(['id_usuario', 'anio', 'mes', 'step']);
            $table->dropColumn('informe');
        });

        Schema::table('vm_informes_estado', function (Blueprint $table) {
            $table->dropUnique(['id_usuario', 'anio', 'mes', 'informe']);
            $table->unique(['id_usuario', 'anio', 'mes']);
            $table->dropColumn('informe');
        });
    }
};
