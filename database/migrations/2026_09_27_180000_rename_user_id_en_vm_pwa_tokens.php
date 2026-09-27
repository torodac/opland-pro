<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// vm_pwa_tokens.user_id apuntaba a vm_usuarios, no a admin_users, al contrario que las otras
// dos tablas de la base de datos con una columna llamada así (sessions y admin_user_roles) y al
// contrario que la columna admin_user_id que tiene al lado. Las diecinueve tablas que apuntan a
// vm_usuarios usan id_usuario o id_usuarios; esta era la única excepción, heredada de su
// migración original de junio de 2026.
//
// Se renombra a id_usuario. En dos pasos deliberadamente: aquí se AÑADE la columna nueva con
// los datos copiados y se deja la vieja, para que las sesiones abiertas sigan funcionando
// mientras se despliega el código. La retirada de user_id va en la migración siguiente, que no
// se aplica hasta comprobar que el código nuevo funciona.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vm_pwa_tokens', 'id_usuario')) return;

        Schema::table('vm_pwa_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->nullable()->after('token');
        });

        DB::statement('UPDATE vm_pwa_tokens SET id_usuario = user_id WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vm_pwa_tokens', 'id_usuario')) return;

        // Si user_id ya no existe (se aplicó la migración siguiente), se recrea con los datos.
        if (!Schema::hasColumn('vm_pwa_tokens', 'user_id')) {
            Schema::table('vm_pwa_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('token');
            });
            DB::statement('UPDATE vm_pwa_tokens SET user_id = id_usuario WHERE id_usuario IS NOT NULL');
        }

        Schema::table('vm_pwa_tokens', function (Blueprint $table) {
            $table->dropColumn('id_usuario');
        });
    }
};
