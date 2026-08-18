<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_informes_estado', function (Blueprint $table) {
            $table->string('paso_actual', 20)->default('rrhh')->after('en_aprobacion');
        });
    }

    public function down(): void
    {
        Schema::table('vm_informes_estado', function (Blueprint $table) {
            $table->dropColumn('paso_actual');
        });
    }
};
