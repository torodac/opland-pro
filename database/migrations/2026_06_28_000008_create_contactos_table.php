<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('puesto')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('mail')->nullable();
            $table->foreignId('id_clientes')->nullable()->constrained('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
