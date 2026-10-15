<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dos datos que trae la informacion de alarmas de VM y que no tenian hueco propio:
//
//   - codigo_panel: el codigo que se teclea en el panel fisico. NO se cifra: a diferencia de la
//     palabra clave y de la contrasena de la app, por si solo no da acceso a nada remoto, y
//     tenerlo a la vista es justo lo que hace util la ficha cuando saltan las alarmas.
//     Si se decide que si debe ocultarse, se anade a CAMPOS_SENSIBLES del controlador.
//   - informacion_extra: texto libre tal y como venia en el Excel ("EN LA APP", "NO APP"...).
//     Se guarda aparte de observaciones para no mezclar lo importado con lo que escriba la gente.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vm_alarmas', 'codigo_panel')) {
            return;
        }

        Schema::table('vm_alarmas', function (Blueprint $table) {
            $table->string('codigo_panel', 255)->nullable();
            $table->text('informacion_extra')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vm_alarmas', 'codigo_panel')) {
            return;
        }

        Schema::table('vm_alarmas', fn(Blueprint $t) => $t->dropColumn(['codigo_panel', 'informacion_extra']));
    }
};
