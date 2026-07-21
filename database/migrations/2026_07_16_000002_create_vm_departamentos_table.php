<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('vm_departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('visible_horarios')->default(false);
            $table->smallInteger('hidden')->default(0);
            $table->smallInteger('deleted')->default(0);
            $table->timestamp('createdat')->nullable();
            $table->timestamp('updatedat')->nullable();
        });

        $ahora = now();
        // visible_horarios: departamentos que trabajan por turnos y salen en el cuadrante semanal
        // (antes hardcodeado por duplicado en HorarioController y VacationmarbellaPwaController)
        $nombres = [
            // Lista real tomada de admin_table_fields (project=vm, tabla=usuarios, campo=departamento)
            'Limpieza'      => true,
            'Mantenimiento' => true,
            'Recepción'     => true,
            'Operaciones'   => true,
            'Laboral'       => false,
            'Adm/Finanzas'  => false,
            'Proyectos'     => false,
            'Expansión'     => false,
            'Reservas'      => false,
            'RRHH'          => false,
            'Proveedor ext' => false,
            'Dirección'     => false,
        ];
        foreach ($nombres as $nombre => $visibleHorarios) {
            DB::table('vm_departamentos')->insert([
                'nombre'           => $nombre,
                'visible_horarios' => $visibleHorarios,
                'hidden'           => 0,
                'deleted'          => 0,
                'createdat'        => $ahora,
                'updatedat'        => $ahora,
            ]);
        }
    }
    public function down(): void {
        Schema::dropIfExists('vm_departamentos');
    }
};
