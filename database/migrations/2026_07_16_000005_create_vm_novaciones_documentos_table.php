<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vm_novaciones_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_propiedades');
            $table->integer('year');
            $table->integer('month');
            $table->date('fecha_novacion'); // fin de mes, para casar con vm_novaciones_gastos
            $table->unsignedBigInteger('createuser')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->decimal('importe_propietario', 12, 2)->default(0);
            $table->decimal('importe_vm', 12, 2)->default(0);
            $table->decimal('total_gastos', 12, 2)->default(0);
            $table->string('pdf_path')->nullable();
            $table->smallInteger('deleted')->default(0);

            $table->index(['id_propiedades', 'year', 'month']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('vm_novaciones_documentos');
    }
};
