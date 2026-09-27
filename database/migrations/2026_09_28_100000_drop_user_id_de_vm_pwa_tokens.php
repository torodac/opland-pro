<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Segundo y último paso del renombrado de vm_pwa_tokens.user_id → id_usuario (ver la migración
// 2026_09_27_180000). Retira la columna vieja, que desde el despliegue del 27/09 ya no lee ni
// escribe nadie.
//
// NO APLICAR hasta haber comprobado que una sesión creada con el código nuevo rellena
// id_usuario: mientras user_id siga ahí, volver atrás es cambiar un fichero; después, no.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vm_pwa_tokens', 'user_id')) return;

        // Red de seguridad: si quedara alguna fila con user_id y sin id_usuario (una sesión
        // creada por código viejo entre migración y despliegue), se copia antes de borrar.
        DB::statement('UPDATE vm_pwa_tokens SET id_usuario = user_id WHERE id_usuario IS NULL AND user_id IS NOT NULL');

        Schema::table('vm_pwa_tokens', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('vm_pwa_tokens', 'user_id')) return;

        Schema::table('vm_pwa_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('token');
        });

        DB::statement('UPDATE vm_pwa_tokens SET user_id = id_usuario WHERE id_usuario IS NOT NULL');
    }
};
