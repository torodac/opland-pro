<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// La retencion de una fianza pasa de ser un importe y un motivo sueltos en vm_reservas a
// construirse desde la evidencia: cada foto lleva su concepto y su importe, y el total sale de
// sumarlos. fianza_retencion se mantiene con la suma, porque es lo que consulta el listado.
//
// Y el estado de la fianza pasa de dos valores a cinco. Solo se ALMACENAN los tres que nacen de
// una accion de una persona (Pte. devolucion, Pte. retencion) o del cierre de su tarea (Devuelta);
// "Vigente" y "Pte. evaluacion" se derivan al pintar, de la fecha de checkout y de si hay
// decision. Guardarlos obligaria a un proceso nocturno que los fuera cambiando, y el dia que
// fallara habria estados mintiendo sobre dinero de un huesped.
return new class extends Migration
{
    private const ESTADO_PTE_DEVOLUCION = 'Pte. devolución';

    public function up(): void
    {
        // ── Retencion por foto ───────────────────────────────────────────────
        if (!Schema::hasColumn('vm_fotos', 'retencion_concepto')) {
            Schema::table('vm_fotos', function (Blueprint $table) {
                $table->string('retencion_concepto', 255)->nullable();
                // decimal, no float: son euros que se le descuentan a alguien.
                $table->decimal('retencion_importe', 10, 2)->nullable();
            });
        }

        // ── La tarea SSCC tiene que saber que fianza cierra ──────────────────
        // Sin esto no hay forma de pasar a "Devuelta": la tabla no referencia la reserva.
        if (!Schema::hasColumn('vm_tareas_sscc', 'id_reservas')) {
            Schema::table('vm_tareas_sscc', function (Blueprint $table) {
                $table->unsignedBigInteger('id_reservas')->nullable();
                $table->index('id_reservas', 'vm_tareas_sscc_id_reservas_idx');
            });
        }

        // ── A quien se mando la tarea ────────────────────────────────────────
        if (!Schema::hasColumn('vm_reservas', 'fianza_departamento')) {
            Schema::table('vm_reservas', function (Blueprint $table) {
                // Se guarda el id de departamento elegido en la ficha (Recepcion 3 /
                // Adm-Finanzas 6), no el literal: el nombre del departamento puede cambiar.
                $table->unsignedBigInteger('fianza_departamento')->nullable();
            });
        }

        // ── Las decisiones ya tomadas, al vocabulario nuevo ──────────────────
        // Son 2 filas con "Aprobada devolucion". Conformidad dada = pendiente de devolver.
        DB::table('vm_reservas')
            ->where('estado_fianza', 'Aprobada devolución')
            ->update(['estado_fianza' => self::ESTADO_PTE_DEVOLUCION]);

        DB::table('vm_reservas')
            ->where('estado_fianza', 'Aplicar retención')
            ->update(['estado_fianza' => 'Pte. retención']);
    }

    public function down(): void
    {
        DB::table('vm_reservas')
            ->where('estado_fianza', self::ESTADO_PTE_DEVOLUCION)
            ->update(['estado_fianza' => 'Aprobada devolución']);

        DB::table('vm_reservas')
            ->where('estado_fianza', 'Pte. retención')
            ->update(['estado_fianza' => 'Aplicar retención']);

        if (Schema::hasColumn('vm_reservas', 'fianza_departamento')) {
            Schema::table('vm_reservas', fn(Blueprint $t) => $t->dropColumn('fianza_departamento'));
        }

        if (Schema::hasColumn('vm_tareas_sscc', 'id_reservas')) {
            Schema::table('vm_tareas_sscc', function (Blueprint $table) {
                $table->dropIndex('vm_tareas_sscc_id_reservas_idx');
                $table->dropColumn('id_reservas');
            });
        }

        if (Schema::hasColumn('vm_fotos', 'retencion_concepto')) {
            Schema::table('vm_fotos', fn(Blueprint $t) => $t->dropColumn([
                'retencion_concepto', 'retencion_importe',
            ]));
        }
    }
};
