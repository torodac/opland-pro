<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_pwa_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('app', 50)->default('vm');
            $table->string('device', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // Añadir file_foto y FKs de mantenimiento/piscinas a vm_fotos
        Schema::table('vm_fotos', function (Blueprint $table) {
            $table->string('file_foto', 512)->nullable()->after('nombre');
            $table->unsignedBigInteger('id_tareas_mantenimiento')->nullable()->after('id_tareas_limpieza');
            $table->unsignedBigInteger('id_tareas_piscinas')->nullable()->after('id_tareas_mantenimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_pwa_tokens');
        Schema::table('vm_fotos', function (Blueprint $table) {
            $table->dropColumn(['file_foto', 'id_tareas_mantenimiento', 'id_tareas_piscinas']);
        });
    }
};
