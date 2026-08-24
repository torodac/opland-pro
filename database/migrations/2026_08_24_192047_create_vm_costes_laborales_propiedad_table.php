<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_costes_laborales_propiedad', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->string('tipo', 20); // 'limpieza' | 'mantenimiento'
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_propiedades');
            $table->decimal('coste', 10, 2);
            // minutos: propios del trabajador en esa propiedad ese mes -- null cuando el
            // reparto viene del peso agregado del grupo (origen='peso_grupo'), porque en ese
            // caso el trabajador no tiene minutos propios de los que partir.
            $table->integer('minutos')->nullable();
            $table->string('origen', 20); // 'propio' | 'peso_grupo'
            $table->decimal('nomina_coste_total', 10, 2); // coste_total de la nómina de ese mes (referencia/auditoría)
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('deleted')->default(0);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->timestamp('createdat')->useCurrent();
            $table->timestamp('updatedat')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['anio', 'mes', 'tipo', 'id_usuario', 'id_propiedades'], 'vm_clp_unico');
            $table->foreign('id_usuario')->references('id')->on('vm_usuarios');
            $table->foreign('id_propiedades')->references('id')->on('vm_propiedades');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_costes_laborales_propiedad');
    }
};
