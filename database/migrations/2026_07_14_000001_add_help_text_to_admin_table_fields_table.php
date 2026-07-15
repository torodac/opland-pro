<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_table_fields', function (Blueprint $table) {
            $table->text('help_text')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('admin_table_fields', function (Blueprint $table) {
            $table->dropColumn('help_text');
        });
    }
};
