<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marca de envío a la asesoría, en las dos tablas de facturas de opland: las emitidas
// (opland_facturas) y las recibidas (opland_fta_soportadas).
//
// Se guarda la fecha y quién lo mandó, no un simple sí/no: ante una discusión con la asesoría
// sobre si una factura se envió o no, la fecha es lo que resuelve.
return new class extends Migration
{
    private const TABLAS = ['opland_facturas', 'opland_fta_soportadas'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (Schema::hasColumn($tabla, 'enviado_asesoria_at')) continue;

            Schema::table($tabla, function (Blueprint $table) {
                $table->timestamp('enviado_asesoria_at')->nullable();
                $table->unsignedBigInteger('enviado_asesoria_por')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (!Schema::hasColumn($tabla, 'enviado_asesoria_at')) continue;

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['enviado_asesoria_at', 'enviado_asesoria_por']);
            });
        }
    }
};
