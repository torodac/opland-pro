<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->string('table_type', 20)->default('hechos')->after('admin_only');
        });
    }

    public function down(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->dropColumn('table_type');
        });
    }
};
