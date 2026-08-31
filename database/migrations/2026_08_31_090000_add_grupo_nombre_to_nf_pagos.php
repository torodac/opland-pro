<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // El grupo/servicio de un pago debe quedar fijado en el momento en que se genera (dato
    // histórico), no depender de seguir el vínculo vivo a nf_contratos -- si el contrato cambia
    // de grupo o se borra más adelante, el pago ya emitido no debe cambiar de aspecto.
    public function up(): void
    {
        Schema::table('nf_pagos', function (Blueprint $table) {
            $table->string('grupo_nombre', 100)->nullable()->after('id_contratos');
        });

        DB::statement('
            UPDATE nf_pagos p SET grupo_nombre = c.nombre
            FROM nf_contratos c
            WHERE c.id = p.id_contratos AND p.grupo_nombre IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('nf_pagos', function (Blueprint $table) {
            $table->dropColumn('grupo_nombre');
        });
    }
};
