<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->string('nombre_formula')->nullable()->after('tab_tables');
            $table->boolean('nombre_ocultar_ficha')->default(true)->after('nombre_formula');
            $table->boolean('nombre_ocultar_listado')->default(true)->after('nombre_ocultar_ficha');
        });
    }

    public function down(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->dropColumn(['nombre_formula', 'nombre_ocultar_ficha', 'nombre_ocultar_listado']);
        });
    }
};
