<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_nominas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->date('mes');
            $table->decimal('devengado', 10, 2);
            $table->decimal('liquido', 10, 2);
            $table->decimal('coste_total', 10, 2);
            $table->smallInteger('deleted')->default(0);
            $table->timestamp('createdat')->useCurrent();
            $table->timestamp('updatedat')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['id_usuario', 'mes']);
            $table->foreign('id_usuario')->references('id')->on('vm_usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_nominas');
    }
};
