<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_tareas_limpieza', function (Blueprint $table) {
            $table->boolean('validado')->default(false)->after('deleted');
            $table->bigInteger('validado_por')->nullable()->after('validado');
        });

        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            $table->boolean('validado')->default(false)->after('deleted');
            $table->bigInteger('validado_por')->nullable()->after('validado');
        });

        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->bigInteger('validado_por')->nullable()->after('validado');
        });
    }

    public function down(): void
    {
        Schema::table('vm_tareas_limpieza', function (Blueprint $table) {
            $table->dropColumn(['validado', 'validado_por']);
        });

        Schema::table('vm_tareas_mantenimiento', function (Blueprint $table) {
            $table->dropColumn(['validado', 'validado_por']);
        });

        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->dropColumn('validado_por');
        });
    }
};
