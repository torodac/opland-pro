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
            $table->json('tab_tables')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->dropColumn('tab_tables');
        });
    }
};
