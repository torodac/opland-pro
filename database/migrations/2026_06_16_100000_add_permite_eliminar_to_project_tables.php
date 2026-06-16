<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->boolean('permite_eliminar')->default(false)->after('admin_only');
        });
    }

    public function down(): void
    {
        Schema::table('project_tables', function (Blueprint $table) {
            $table->dropColumn('permite_eliminar');
        });
    }
};
