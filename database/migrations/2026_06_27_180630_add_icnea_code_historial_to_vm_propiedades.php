<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_propiedades', function (Blueprint $table) {
            $table->text('icnea_code_historial')->nullable()->after('icnea_code');
        });
    }

    public function down(): void
    {
        Schema::table('vm_propiedades', function (Blueprint $table) {
            $table->dropColumn('icnea_code_historial');
        });
    }
};
