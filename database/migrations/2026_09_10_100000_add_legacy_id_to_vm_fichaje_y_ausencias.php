<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Columna de trazabilidad para la importación puntual del histórico de Hostinger
// (vacationmarbella_fichaje / vacationmarbella_ausencias). Permite reejecutar el
// comando de import de forma idempotente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
        });
        Schema::table('vm_ausencias', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });
        Schema::table('vm_ausencias', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });
    }
};
