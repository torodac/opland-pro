<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El canal de cada reserva, que Icnea devuelve y no guardabamos.
//
// Viene relleno SIEMPRE, tambien en las directas: ahi dice el nombre de la propia agencia
// ("VACATION MARBELLA" o "Web"), de modo que distingue sin ambiguedad lo propio de lo de un
// portal -- las de portal ademas traen localizador externo y comision, y las directas no.
//
// channel_booking_id es el localizador en el portal, lo unico que permite casar una reserva
// nuestra con la del canal cuando hay una discrepancia o una reclamacion.
//
// Medido sobre 733 reservas de los ultimos 120 dias: Web 29%, VACATION MARBELLA 27%,
// AirBNB iCal 25%, Booking 12%, AirBnb-Online 5%, VRBO 1%, Marriott 1%.
//
// Las dos columnas van tambien en vm_reservas_temp, que es la tabla real que usa la
// sincronizacion como area de carga antes de hacer el merge.
return new class extends Migration
{
    private const TABLAS = ['vm_reservas', 'vm_reservas_temp'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (Schema::hasColumn($tabla, 'channel')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->string('channel', 100)->nullable();
                $table->string('channel_booking_id', 60)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (!Schema::hasColumn($tabla, 'channel')) {
                continue;
            }

            Schema::table($tabla, fn(Blueprint $t) => $t->dropColumn(['channel', 'channel_booking_id']));
        }
    }
};
