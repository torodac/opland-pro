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
        Schema::create('table_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_table_id')->constrained('project_tables')->cascadeOnDelete();
            $table->string('name');        // nombre de columna en la tabla dinámica
            $table->string('label');       // nombre visible
            $table->string('type');        // text, int, decimal, fecha_, id_, file_, tinyint, smallint, email, telefono, password, time
            $table->integer('order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('in_list')->default(true);   // mostrar en listado
            $table->boolean('in_form')->default(true);   // mostrar en formulario
            $table->string('extras')->nullable();        // opt:val1,val2 | autocalc | etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_fields');
    }
};
