<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_imputaciones', function (Blueprint $table) {
            $table->text('observacion')->nullable();
        });
        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
        Schema::table('vm_tareas_piscinas', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }

    public function down(): void
    {
        Schema::table('vm_imputaciones', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            $table->string('estado', 20)->nullable();
        });
        Schema::table('vm_tareas_piscinas', function (Blueprint $table) {
            $table->string('estado', 20)->nullable();
        });
    }
};
