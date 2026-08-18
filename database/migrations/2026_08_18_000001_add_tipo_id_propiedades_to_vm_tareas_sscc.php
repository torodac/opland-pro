<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_tareas_sscc', function (Blueprint $table) {
            $table->string('Tipo')->nullable()->after('nombre');
            $table->unsignedBigInteger('id_propiedades')->nullable()->after('id_departamento');
        });
    }

    public function down(): void
    {
        Schema::table('vm_tareas_sscc', function (Blueprint $table) {
            $table->dropColumn(['Tipo', 'id_propiedades']);
        });
    }
};
