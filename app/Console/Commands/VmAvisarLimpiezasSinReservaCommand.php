<?php

namespace App\Console\Commands;

use App\Http\Controllers\Vm\DevFianzasController;
use App\Mail\VmLimpiezasSinReservaMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Avisa de las limpiezas de salida que se han quedado sin reserva asociada.
//
// El vinculo lo pone Breezeway al crear la tarea. Cuando no lo pone, el sync deduce lo que puede
// (propiedad + checkout a +-1 dia, una unica candidata) y lo que queda llega aqui. Hasta ahora
// esto solo se descubria cuando alguien abria una ficha de fianza y la veia en blanco.
//
// Se acota a la ventana de Dev. fianzas: avisar de limpiezas de hace meses, cuya fianza ya esta
// cerrada, convertiria el correo en ruido que se aprende a ignorar.
class VmAvisarLimpiezasSinReservaCommand extends Command
{
    protected $signature = 'vm:avisar-limpiezas-sin-reserva
                            {--a= : Destinatario, por defecto el responsable de Opland}
                            {--forzar : Envia el correo aunque no haya nada que avisar (para probarlo)}';

    protected $description = 'Avisa por correo de las limpiezas de salida que no tienen reserva asociada';

    private const DESTINO_POR_DEFECTO = 'trodriguez@opland.es';

    public function handle(): int
    {
        $tareas = $this->sinReserva();
        $ambiguas = $this->ambiguas();

        if ($tareas->isEmpty() && !$this->option('forzar')) {
            $this->info('Ninguna limpieza de salida sin reserva en la ventana de fianzas. No se envia correo.');
            return self::SUCCESS;
        }

        $destino = (string) ($this->option('a') ?: self::DESTINO_POR_DEFECTO);

        try {
            Mail::to($destino)->send(new VmLimpiezasSinReservaMail($tareas, $ambiguas));
        } catch (\Throwable $e) {
            // Que el aviso falle en silencio seria lo peor de los dos mundos: ni correo ni rastro.
            Log::error('vm:avisar-limpiezas-sin-reserva: no se pudo enviar el correo. ' . $e->getMessage());
            $this->error('No se pudo enviar: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Aviso enviado a {$destino}: {$tareas->count()} sin reserva, {$ambiguas} ambigua(s).");
        Log::info("vm:avisar-limpiezas-sin-reserva: {$tareas->count()} sin reserva, {$ambiguas} ambiguas, enviado a {$destino}.");

        return self::SUCCESS;
    }

    /** Limpiezas de salida sin reserva, en la misma ventana que mira Dev. fianzas. */
    private function sinReserva()
    {
        return DB::table('vm_tareas_limpieza as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where(fn($q) => $q->where('t.deleted', 0)->orWhereNull('t.deleted'))
            ->whereIn('t.Tipo', DevFianzasController::TIPOS_SALIDA)
            ->whereNotIn(DB::raw("COALESCE(t.estado, '')"), DevFianzasController::ESTADOS_EXCLUIDOS)
            ->whereNull('t.id_reservas')
            ->whereNotNull('t.breezeway_task_id')
            ->where('t.fecha_planificada', '>', DevFianzasController::ANTES_DE)
            ->orderByDesc('t.fecha_planificada')
            ->get(['t.id', 't.nombre', 't.fecha_planificada', 't.breezeway_task_id', 'p.nombre as propiedad']);
    }

    /**
     * Cuantas tienen MAS de una reserva candidata.
     *
     * Van en el correo solo como recuento: elegir entre varias seria decidir una fianza con la
     * evidencia de otra estancia, asi que no se eligen ni se sugieren.
     */
    private function ambiguas(): int
    {
        return (int) DB::table('vm_tareas_limpieza as t')
            ->where(fn($q) => $q->where('t.deleted', 0)->orWhereNull('t.deleted'))
            ->whereIn('t.Tipo', DevFianzasController::TIPOS_SALIDA)
            ->whereNotIn(DB::raw("COALESCE(t.estado, '')"), DevFianzasController::ESTADOS_EXCLUIDOS)
            ->whereNull('t.id_reservas')
            ->whereNotNull('t.fecha_planificada')
            ->where('t.fecha_planificada', '>', DevFianzasController::ANTES_DE)
            ->whereRaw("(SELECT count(*) FROM vm_reservas r
                          WHERE r.id_propiedades = t.id_propiedades
                            AND COALESCE(r.deleted, 0) = 0
                            AND r.booking_status <> 'cancelled'
                            AND r.check_out_date BETWEEN t.fecha_planificada - 1 AND t.fecha_planificada + 1) > 1")
            ->count();
    }
}
