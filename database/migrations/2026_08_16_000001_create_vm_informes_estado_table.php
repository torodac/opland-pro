<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vm_informes_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->boolean('en_aprobacion')->default(false);
            $table->unsignedBigInteger('marcado_por')->nullable();
            $table->timestamp('marcado_at')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();

            $table->unique(['id_usuario', 'anio', 'mes']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('vm_informes_estado');
    }
};
