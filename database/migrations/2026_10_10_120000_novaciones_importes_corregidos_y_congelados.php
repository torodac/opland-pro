<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dos cosas en las novaciones, que resultaron ser la misma.
//
// 1. Poder usar un importe distinto del que manda Icnea, SIN perder el de Icnea. No vale corregir
//    la columna "importe": el cron icnea:sync-importes corre cada dia a las 06:00 con ventana de
//    seis meses y la sobrescribe en cuanto difiere de lo que diga Icnea. Por eso la correccion va
//    en una columna propia, que el cron ni mira -- sus insert/update enumeran columnas.
//
// 2. Guardar con que se calculo cada novacion. Hasta ahora solo quedaban las dos cifras finales
//    (base_propietario y base_calculo); las lineas, los toggles y el IVA no se guardaban en
//    ninguna parte, asi que una novacion no era reproducible NI se podia detectar que Icnea
//    hubiera cambiado algo despues: calcularTotalesNovacion() suma esas dos cifras ya
//    materializadas, de modo que comparar el snapshot contra "lo actual" comparaba lo mismo
//    consigo mismo y la tarea "Revision Novacion" no saltaba nunca por este motivo.
//
// Las columnas *_novado son la foto del momento de novar; importe_novacion es la correccion
// manual. Son conceptos distintos y conviven: el efectivo que se usa es
// COALESCE(importe_novacion, importe), y la foto guarda cuanto valia ese efectivo al novar.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vm_reservas_importes', 'importe_novacion')) {
            Schema::table('vm_reservas_importes', function (Blueprint $table) {
                // La correccion manual. NULL = sin corregir, manda el importe de Icnea.
                $table->decimal('importe_novacion', 10, 2)->nullable()->after('importe');
                // Quien y cuando. Esta tabla decide cuanto se liquida a un propietario y no tiene
                // ni createuser ni updateuser, asi que la trazabilidad va aqui.
                $table->unsignedBigInteger('importe_novacion_user')->nullable()->after('importe_novacion');
                $table->timestamp('importe_novacion_at')->nullable()->after('importe_novacion_user');
                // La foto al novar. NULL = esta linea no estaba cuando se novo.
                $table->decimal('importe_novado', 10, 2)->nullable()->after('importe_novacion_at');
                $table->smallInteger('propietario_novado')->nullable()->after('importe_novado');
            });
        }

        if (!Schema::hasColumn('vm_reservas', 'novacion_iva_pct')) {
            Schema::table('vm_reservas', function (Blueprint $table) {
                // El IVA era un input de pantalla con 10% por defecto y no se guardaba, asi que al
                // reabrir una reserva ya novada volvia a 10 y podia cambiar las cifras sin que
                // nadie lo notara.
                $table->decimal('novacion_iva_pct', 5, 2)->nullable();
                $table->decimal('novacion_porc_honorarios', 5, 2)->nullable();
                $table->timestamp('novacion_at')->nullable();
                $table->unsignedBigInteger('novacion_user')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vm_reservas_importes', 'importe_novacion')) {
            Schema::table('vm_reservas_importes', fn(Blueprint $t) => $t->dropColumn([
                'importe_novacion', 'importe_novacion_user', 'importe_novacion_at',
                'importe_novado', 'propietario_novado',
            ]));
        }

        if (Schema::hasColumn('vm_reservas', 'novacion_iva_pct')) {
            Schema::table('vm_reservas', fn(Blueprint $t) => $t->dropColumn([
                'novacion_iva_pct', 'novacion_porc_honorarios', 'novacion_at', 'novacion_user',
            ]));
        }
    }
};
