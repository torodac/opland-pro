<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vm_informes_ediciones_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->string('tabla', 50);
            $table->string('accion', 20);
            $table->unsignedBigInteger('id_registro')->nullable();
            $table->unsignedBigInteger('editado_por')->nullable();
            $table->timestamp('editado_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('origen', 20)->default('backoffice');
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();

            $table->index(['id_usuario', 'anio', 'mes']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('vm_informes_ediciones_log');
    }
};
