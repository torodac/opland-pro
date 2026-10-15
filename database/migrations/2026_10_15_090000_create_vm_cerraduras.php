<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Espejo local de las cerraduras Nuki.
//
// Existe para no llamar a la API de Nuki en cada carga del dashboard: son 49 cerraduras, casi un
// segundo de espera, y cada pintada de pantalla seria una llamada a un tercero. El comando
// vm:sync-nuki la refresca una vez al dia y el dashboard lee de aqui.
//
// No se guarda nada que abra una puerta: solo identificador, nombre y bateria.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vm_cerraduras')) {
            return;
        }

        Schema::create('vm_cerraduras', function (Blueprint $table) {
            $table->id();
            // El id de Nuki es un entero grande propio suyo, no nuestro.
            $table->unsignedBigInteger('nuki_smartlock_id')->unique();
            $table->string('nombre', 255)->nullable();
            $table->unsignedSmallInteger('bateria')->nullable();
            $table->boolean('bateria_critica')->default(false);
            // La propiedad, cuando el nombre de la cerradura permite deducirla. Nullable porque
            // no todas casan, y una cerradura sin propiedad sigue siendo util para avisar.
            $table->unsignedBigInteger('id_propiedades')->nullable();
            // Cuando lo leimos de Nuki: un aviso de bateria basado en un dato de hace una semana
            // no vale nada, y asi se puede decir en pantalla.
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->index('bateria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_cerraduras');
    }
};
