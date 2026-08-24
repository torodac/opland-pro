<?php

namespace App\Console\Commands;

use App\Services\VmHorasService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Genera "Validar informes mensuales RRHH" en vm_tareas_sscc el primer día laborable de cada
// mes, para el rol Director RRHH (vm_roles.id=11). Se ejecuta a diario (ver routes/console.php)
// pero solo actúa el día que toca, y es idempotente por mes (no duplica si ya existe).
class VmGenerarTareaInformesRrhhCommand extends Command
{
    protected $signature   = 'vm:generar-tarea-informes-rrhh';
    protected $description = 'Crea la tarea mensual "Validar informes mensuales RRHH" el primer día laborable del mes';

    private const ROL_DIRECTOR_RRHH = 11;
    private const DEPARTAMENTO_RRHH = 10;
    private const TIPO_TAREA        = 'Validación informes RRHH';

    public function handle(): int
    {
        $hoy = Carbon::today();

        $primerLaborable = $this->primerDiaLaborable($hoy->year, $hoy->month);
        if (!$hoy->isSameDay($primerLaborable)) {
            return self::SUCCESS; // hoy no toca, no hacer nada
        }

        $yaExiste = DB::table('vm_tareas_sscc')
            ->where('Tipo', self::TIPO_TAREA)
            ->whereBetween('fecha_planificada', [$hoy->copy()->startOfMonth()->toDateString(), $hoy->copy()->endOfMonth()->toDateString()])
            ->where('deleted', 0)
            ->exists();

        if ($yaExiste) {
            $this->info('Ya existe la tarea de este mes, no se duplica.');
            return self::SUCCESS;
        }

        $controlUsers = DB::table('vm_usuarios')
            ->where('id_rol', self::ROL_DIRECTOR_RRHH)
            ->where('deleted', 0)
            ->pluck('id')
            ->all();

        $ahora = now();
        $id = DB::table('vm_tareas_sscc')->insertGetId([
            'nombre'            => 'Validar informes mensuales RRHH',
            'descripcion'       => 'Revisar y validar los informes mensuales del mes anterior pendientes del paso RRHH.',
            'Tipo'              => self::TIPO_TAREA,
            'estado'            => 'Nueva',
            'fecha_planificada' => $hoy->toDateString(),
            'id_rol'            => self::ROL_DIRECTOR_RRHH,
            'id_departamento'   => self::DEPARTAMENTO_RRHH,
            'control_user'      => json_encode($controlUsers),
            'hidden'            => 0,
            'deleted'           => 0,
            'blocked'           => 0,
            'createuser'        => auth()->id(),
            'createdat'         => $ahora,
            'updatedat'         => $ahora,
        ]);

        $this->info("Tarea creada (id={$id}) para el {$hoy->toDateString()}.");
        return self::SUCCESS;
    }

    // Primer día del mes que no cae en fin de semana ni en un festivo de empresa (vm_festivos
    // sin sede, es decir válido para todas las sedes -- mismo criterio que VmHorasService).
    private function primerDiaLaborable(int $year, int $month): Carbon
    {
        $inicio = Carbon::create($year, $month, 1);
        $fin    = $inicio->copy()->endOfMonth();
        $festivos = VmHorasService::festivosSet('', $inicio->toDateString(), $fin->toDateString());

        for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
            if ($d->isWeekend()) continue;
            if (isset($festivos[$d->toDateString()])) continue;
            return $d;
        }

        return $inicio; // fallback defensivo, no debería llegar aquí en un mes normal
    }
}
