<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_tareas_limpieza', function (Blueprint $t) {
            $t->string('estado', 20)->nullable()->after('Tipo');
            $t->unsignedBigInteger('id_reservas')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('vm_tareas_limpieza', function (Blueprint $t) {
            $t->dropColumn(['estado', 'id_reservas']);
        });
    }
};
