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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->foreignId('project_table_id')->nullable()->constrained('project_tables')->nullOnDelete();
            $table->string('url')->nullable();           // para links directos sin tabla
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
