<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Proyecto "estudio": tablas físicas de la app de estudio (asignaturas → temas → tareas, y sesiones
// de tiempo). Las tablas de datos siguen las columnas base del resto de proyectos (deleted, hidden,
// createuser, updateuser). Todo dato se filtra por admin_user_id: cada usuario solo ve lo suyo.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('estudio_pwa_tokens')) {
            Schema::create('estudio_pwa_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->unsignedBigInteger('admin_user_id');
                $table->string('device', 255)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('estudio_asignaturas')) {
            Schema::create('estudio_asignaturas', function (Blueprint $table) {
                $this->base($table);
                $table->string('nombre', 120);
                $table->string('curso', 40);
                $table->string('color', 20)->nullable();
                $table->unsignedBigInteger('admin_user_id')->index();
            });
        }

        if (!Schema::hasTable('estudio_temas')) {
            Schema::create('estudio_temas', function (Blueprint $table) {
                $this->base($table);
                $table->string('nombre', 200);
                $table->unsignedBigInteger('id_asignatura')->index();
                $table->unsignedBigInteger('admin_user_id')->index();
            });
        }

        if (!Schema::hasTable('estudio_tareas')) {
            Schema::create('estudio_tareas', function (Blueprint $table) {
                $this->base($table);
                $table->string('nombre', 200);
                $table->unsignedBigInteger('id_tema')->index();
                $table->string('estado', 20)->default('Pendiente'); // Pendiente | En curso | Estudiado
                $table->unsignedInteger('tiempo_estimado_min')->nullable();
                $table->unsignedBigInteger('admin_user_id')->index();
            });
        }

        if (!Schema::hasTable('estudio_sesiones')) {
            Schema::create('estudio_sesiones', function (Blueprint $table) {
                $this->base($table);
                $table->date('fecha');
                $table->timestamp('inicio')->nullable();  // solo sesiones de cronómetro
                $table->timestamp('fin')->nullable();     // null = cronómetro en marcha
                $table->unsignedInteger('minutos')->nullable();
                $table->string('origen', 20)->default('manual'); // cronometro | manual
                $table->unsignedBigInteger('id_asignatura')->index();
                $table->unsignedBigInteger('id_tarea')->index();
                $table->unsignedBigInteger('admin_user_id')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estudio_sesiones');
        Schema::dropIfExists('estudio_tareas');
        Schema::dropIfExists('estudio_temas');
        Schema::dropIfExists('estudio_asignaturas');
        Schema::dropIfExists('estudio_pwa_tokens');
    }

    private function base(Blueprint $table): void
    {
        $table->id();
        // tinyint, igual que el resto de tablas de proyecto (las consultas usan deleted = 0)
        $table->unsignedTinyInteger('deleted')->default(0);
        $table->unsignedTinyInteger('hidden')->default(0);
        $table->unsignedBigInteger('createuser')->nullable();
        $table->unsignedBigInteger('updateuser')->nullable();
        $table->timestamps();
    }
};
