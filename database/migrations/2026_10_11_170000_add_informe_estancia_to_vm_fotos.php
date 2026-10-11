<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marca de que fotos de la limpieza de salida se van a usar en el informe de la estancia que se
// entrega al propietario.
//
// Es una decision de una persona mirando las fotos, no algo deducible: por eso se guarda en vez
// de calcularse. Smallint 0/1 como el resto de banderas de esta tabla (blocked, hidden, deleted).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vm_fotos', 'informe_estancia')) {
            return;
        }

        Schema::table('vm_fotos', function (Blueprint $table) {
            $table->smallInteger('informe_estancia')->default(0);
            // Quien la marco y cuando: la foto acaba en un documento que ve el propietario.
            $table->unsignedBigInteger('informe_estancia_user')->nullable();
            $table->timestamp('informe_estancia_at')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vm_fotos', 'informe_estancia')) {
            return;
        }

        Schema::table('vm_fotos', fn(Blueprint $t) => $t->dropColumn([
            'informe_estancia', 'informe_estancia_user', 'informe_estancia_at',
        ]));
    }
};
