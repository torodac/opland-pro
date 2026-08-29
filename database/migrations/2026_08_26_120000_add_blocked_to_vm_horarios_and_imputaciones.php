<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_horarios', function (Blueprint $table) {
            $table->smallInteger('blocked')->default(0);
        });

        Schema::table('vm_imputaciones', function (Blueprint $table) {
            $table->smallInteger('blocked')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vm_horarios', function (Blueprint $table) {
            $table->dropColumn('blocked');
        });

        Schema::table('vm_imputaciones', function (Blueprint $table) {
            $table->dropColumn('blocked');
        });
    }
};
