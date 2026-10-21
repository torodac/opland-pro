<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Marca por persona para quien esta en un departamento de turnos pero no trabaja por turnos.
//
// Hasta ahora "va por turnos" se deducia solo del departamento (vm_departamentos.visible_horarios,
// ver VmHorasService::esDeptoTurno), y eso no admite excepciones: Mykola Krupa esta en
// Mantenimiento, que va por turnos, pero el suyo no se planifica en el cuadrante, asi que el
// aviso de "N dias sin horario" de su tarjeta señalaba todos los dias del mes y no habia nada
// que planificar. Un aviso que no se puede resolver deja de leerse.
//
// HOY SOLO AFECTA A ESE AVISO. Los dias de turno y de descanso se siguen contando igual, a la
// espera de decidir que mas implica no ir por turnos -- es el mismo asunto que el "Desc. Fest."
// aparcado el 06/10/2026.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vm_usuarios', 'va_por_turnos')) {
            Schema::table('vm_usuarios', function (Blueprint $table) {
                $table->boolean('va_por_turnos')->default(true);
            });
        }

        // Por defecto todos siguen como estaban; la excepcion se marca una a una.
        DB::table('vm_usuarios')->whereNull('va_por_turnos')->update(['va_por_turnos' => true]);

        $n = DB::table('vm_usuarios')
            ->where('nombre', 'Mykola Krupa')
            ->update(['va_por_turnos' => false, 'updatedat' => now()]);
        echo $n === 1
            ? '  Mykola Krupa marcado como "no va por turnos".' . PHP_EOL
            : "  AVISO - se esperaba 1 fila para Mykola Krupa y se actualizaron {$n}." . PHP_EOL;

        // Declararlo a mano y no esperar a `opland:declarar-columnas` (05:30), que lo dejaria
        // oculto: esta marca tiene que poder cambiarse desde la ficha del usuario.
        $tablaId = DB::table('admin_project_tables')
            ->where('name', 'usuarios')
            ->where('project_id', DB::table('admin_projects')->where('slug', 'vm')->value('id'))
            ->value('id');

        if ($tablaId && !DB::table('admin_table_fields')
                ->where('project_table_id', $tablaId)->where('name', 'va_por_turnos')->exists()) {
            DB::table('admin_table_fields')->insert([
                'project_table_id' => $tablaId,
                'name'      => 'va_por_turnos',
                'label'     => 'Va por turnos',
                'type'      => 'boolean',
                'order'     => 14,
                'required'  => false,
                'in_list'   => false,
                'in_form'   => true,
                'help_text' => 'Desmarcar a quien esta en un departamento de turnos pero no se planifica en el cuadrante.',
                'hidden'    => 0,
                'blocked'   => 0,
                'createuser' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_table_fields')->where('name', 'va_por_turnos')->delete();

        if (Schema::hasColumn('vm_usuarios', 'va_por_turnos')) {
            Schema::table('vm_usuarios', function (Blueprint $table) {
                $table->dropColumn('va_por_turnos');
            });
        }
    }
};
