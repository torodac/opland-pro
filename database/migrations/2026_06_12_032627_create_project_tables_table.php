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
        Schema::create('project_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');        // slug, usado como nombre de tabla dinámica
            $table->string('label');       // nombre visible
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('has_kanban')->default(false);
            $table->boolean('has_calendar')->default(false);
            $table->boolean('has_matrix')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tables');
    }
};
