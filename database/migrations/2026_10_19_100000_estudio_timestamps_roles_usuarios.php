<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ajusta las tablas de estudio a las convenciones de las tablas de proyecto de Opland Pro:
//  - timestamps como createdat / updatedat (los listados y fichas de la plataforma los usan así)
//  - tablas especiales `roles` y `usuarios` del proyecto (estudio_roles, estudio_usuarios)
// estudio_pwa_tokens no cambia: es infraestructura de la PWA, no una tabla de proyecto.
return new class extends Migration
{
    private const TABLAS_DATOS = [
        'estudio_asignaturas',
        'estudio_temas',
        'estudio_tareas',
        'estudio_sesiones',
    ];

    public function up(): void
    {
        foreach (self::TABLAS_DATOS as $tabla) {
            if (Schema::hasColumn($tabla, 'created_at')) {
                DB::statement("ALTER TABLE {$tabla} RENAME COLUMN created_at TO createdat");
            }
            if (Schema::hasColumn($tabla, 'updated_at')) {
                DB::statement("ALTER TABLE {$tabla} RENAME COLUMN updated_at TO updatedat");
            }
        }

        if (!Schema::hasTable('estudio_roles')) {
            Schema::create('estudio_roles', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable();
                $table->string('tabla_default', 100)->nullable();
                $table->json('ver')->nullable();
                $table->json('editar')->nullable();
                $table->json('roles_supervisados')->nullable();
                $table->string('todos_registros', 20)->default('personales');
                $table->unsignedSmallInteger('hidden')->default(0);
                $table->unsignedSmallInteger('deleted')->default(0);
                $table->unsignedBigInteger('createuser')->nullable();
                $table->unsignedBigInteger('updateuser')->nullable();
                $table->timestamp('createdat')->nullable();
                $table->timestamp('updatedat')->nullable();
            });
        }

        if (!Schema::hasTable('estudio_usuarios')) {
            Schema::create('estudio_usuarios', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable();
                $table->string('alias', 50)->nullable();
                $table->string('mail')->nullable();
                $table->unsignedBigInteger('id_rol')->nullable();
                $table->string('curso', 40)->nullable();
                $table->string('acceso', 20)->default('APP y web');
                $table->unsignedBigInteger('admin_user_id')->nullable()->index();
                $table->unsignedSmallInteger('hidden')->default(0);
                $table->unsignedSmallInteger('deleted')->default(0);
                $table->unsignedBigInteger('createuser')->nullable();
                $table->unsignedBigInteger('updateuser')->nullable();
                $table->timestamp('createdat')->nullable();
                $table->timestamp('updatedat')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estudio_usuarios');
        Schema::dropIfExists('estudio_roles');

        foreach (self::TABLAS_DATOS as $tabla) {
            if (Schema::hasColumn($tabla, 'createdat')) {
                DB::statement("ALTER TABLE {$tabla} RENAME COLUMN createdat TO created_at");
            }
            if (Schema::hasColumn($tabla, 'updatedat')) {
                DB::statement("ALTER TABLE {$tabla} RENAME COLUMN updatedat TO updated_at");
            }
        }
    }
};
