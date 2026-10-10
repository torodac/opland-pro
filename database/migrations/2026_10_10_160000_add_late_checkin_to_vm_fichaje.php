<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tiempo extra dedicado a un late check-in, que Recepcion atiende fuera de su jornada.
//
// Hasta ahora ese tiempo no existia para el sistema: la unica medida de lo trabajado es
// hora_fin - hora_inicio de la unica fila diaria de vm_fichaje, de modo que atender una llegada
// de 23:00 a 00:30 despues de haber fichado la salida a las 20:00 era invisible y no generaba
// horas extra.
//
// Se guarda como tramo (inicio y fin) y no como minutos porque es lo que la persona sabe y puede
// justificar. Los minutos que acaban sumando a las horas extra los calcula
// VmHorasService::lateCheckinMin(), que ademas descuenta la parte que ya estuviera dentro del
// fichaje: contarla dos veces la pagaria dos veces.
//
// El molde conceptual es ajuste_he: minutos que se acumulan a las horas extra al margen de la
// jornada fichada.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vm_fichaje', 'late_checkin_ini')) {
            return;
        }

        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->time('late_checkin_ini')->nullable();
            $table->time('late_checkin_fin')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vm_fichaje', 'late_checkin_ini')) {
            return;
        }

        Schema::table('vm_fichaje', fn(Blueprint $t) => $t->dropColumn(['late_checkin_ini', 'late_checkin_fin']));
    }
};
