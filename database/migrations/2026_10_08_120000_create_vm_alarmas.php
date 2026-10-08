<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Datos de las alarmas de cada propiedad. Es la tabla mas sensible del proyecto: lleva la palabra
// clave que se da por telefono a la central receptora y las credenciales de la app del instalador.
// De ahi tres decisiones que estan en el codigo y no en la configuracion:
//
//  1. No se guardan ni el nombre ni la direccion de la propiedad: salen de vm_propiedades por
//     id_propiedades. Copiarlos aqui crearia una segunda version de un dato que ya tiene dueno.
//  2. palabra_clave y app_password son TEXT porque van cifrados en reposo (cast "encrypted" del
//     modelo VmAlarma, clave en APP_KEY). Cifrado significa que no se pueden buscar ni ordenar
//     por ellos, y es a proposito.
//  3. Una alarma por propiedad: el indice unico sobre id_propiedades lo garantiza en la base y
//     no solo en el formulario.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_alarmas', function (Blueprint $table) {
            $table->id();

            // La propiedad es la identidad de la alarma. restrictOnDelete: borrar una propiedad
            // que tiene alarma dejaria credenciales huerfanas sin nadie a quien atribuirlas.
            $table->foreignId('id_propiedades')->unique()
                ->constrained('vm_propiedades')->restrictOnDelete();

            $table->string('empresa')->nullable();
            $table->string('titular')->nullable();
            $table->string('titular_doc', 20)->nullable();   // DNI o NIE
            $table->string('tipo_alarma')->nullable();
            $table->string('num_contrato')->nullable();

            // Los tres contactos para las llamadas de incidencias, en orden de llamada. Texto
            // libre los dos campos: el telefono puede traer prefijo, extension o un segundo
            // numero, y convertirlo a entero perderia el "+34" y los ceros a la izquierda.
            foreach ([1, 2, 3] as $n) {
                $table->string("contacto{$n}_nombre")->nullable();
                $table->string("contacto{$n}_telefono", 60)->nullable();
            }

            $table->text('palabra_clave')->nullable();       // cifrado
            $table->string('app_usuario')->nullable();
            $table->text('app_password')->nullable();        // cifrado

            $table->text('observaciones')->nullable();

            // Columnas que el no-code espera en toda tabla del proyecto.
            $table->smallInteger('blocked')->default(0);
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('deleted')->default(0);
            $table->unsignedBigInteger('createuser')->nullable();
            $table->unsignedBigInteger('updateuser')->nullable();
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });

        // El listado generico y los desplegables ref: muestran la columna "nombre". Generada, para
        // que no pueda quedarse desincronizada de la empresa que la alarma dice tener.
        DB::statement("ALTER TABLE vm_alarmas
            ADD COLUMN nombre varchar GENERATED ALWAYS AS (coalesce(empresa, '')) STORED");

        // Quien ha mirado una palabra clave o una contrasena, y cuando. Sin esto, un permiso de
        // lectura sobre la pantalla seria un permiso de lectura sin rastro.
        Schema::create('vm_alarmas_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_alarmas')->constrained('vm_alarmas')->cascadeOnDelete();
            $table->string('campo', 20);                     // palabra_clave | app_password
            $table->unsignedBigInteger('id_usuario');
            $table->timestamp('fecha');

            // La consulta de la ficha pide siempre los accesos de una alarma, los mas recientes
            // primero.
            $table->index(['id_alarmas', 'fecha'], 'vm_alarmas_accesos_alarma_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_alarmas_accesos');
        Schema::dropIfExists('vm_alarmas');
    }
};
