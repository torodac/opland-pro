<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VmGenerateCheckoutTasksCommand extends Command
{
    protected $signature   = 'vm:generate-checkout-tasks';
    protected $description = 'Genera tareas de limpieza checkout para los próximos 7 días y actualiza las existentes';

    public function handle(): void
    {
        $hoy   = now()->toDateString();
        $hasta = now()->addDays(7)->toDateString();

        $creadas      = 0;
        $actualizadas = 0;
        $canceladas   = 0;

        // 1. Reservas con checkout en los próximos 7 días (incluyendo hoy)
        $reservas = DB::table('vm_reservas as r')
            ->join('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('p.deleted', 0)
            ->whereBetween('r.check_out_date', [$hoy, $hasta])
            ->get(['r.id', 'r.guest_name', 'r.check_out_date', 'r.booking_status', 'r.id_propiedades']);

        foreach ($reservas as $reserva) {
            $cancelada = in_array($reserva->booking_status, ['cancelled', 'canceled']);

            // Tarea activa (no cancelada) vinculada a esta reserva
            $tarea = DB::table('vm_tareas_limpieza')
                ->where('id_reservas', $reserva->id)
                ->whereNotIn('estado', ['Cancelada'])
                ->first();

            if ($cancelada) {
                // Reserva cancelada → gestionar tarea existente
                if ($tarea) {
                    $tieneAsignado = $this->tieneControlUser($tarea->control_user ?? null);
                    DB::table('vm_tareas_limpieza')->where('id', $tarea->id)->update([
                        'estado'    => $tieneAsignado ? 'Revisar' : 'Cancelada',
                        'updatedat' => now(),
                    ]);
                    $canceladas++;
                    $this->line("  CANCELADA reserva #{$reserva->id} ({$reserva->guest_name}): tarea #{$tarea->id} → " . ($tieneAsignado ? 'Revisar' : 'Cancelada'));
                }
                continue;
            }

            if (!$tarea) {
                // Crear tarea nueva
                DB::table('vm_tareas_limpieza')->insert([
                    'nombre'           => 'Limpieza checkout',
                    'Tipo'             => 'Checkout',
                    'estado'           => 'Nueva',
                    'id_propiedades'   => $reserva->id_propiedades,
                    'id_reservas'      => $reserva->id,
                    'fecha_planificada'=> $reserva->check_out_date,
                    'deleted'          => 0,
                    'hidden'           => 0,
                    'blocked'          => 0,
                    'createuser'       => 1,
                    'createdat'        => now(),
                    'updatedat'        => now(),
                ]);
                $creadas++;
                $this->line("  NUEVA tarea para reserva #{$reserva->id} ({$reserva->guest_name}) checkout {$reserva->check_out_date}");
                continue;
            }

            // Tarea existente: comprobar si ha cambiado la fecha de checkout
            if ($tarea->fecha_planificada === $reserva->check_out_date) {
                continue;
            }

            $tieneAsignado = $this->tieneControlUser($tarea->control_user ?? null);
            $update = [
                'fecha_planificada' => $reserva->check_out_date,
                'updatedat'         => now(),
            ];
            if ($tieneAsignado) {
                $update['estado'] = 'Revisar';
            }

            DB::table('vm_tareas_limpieza')->where('id', $tarea->id)->update($update);
            $actualizadas++;
            $this->line("  ACTUALIZADA tarea #{$tarea->id} ({$reserva->guest_name}): fecha {$tarea->fecha_planificada} → {$reserva->check_out_date}" . ($tieneAsignado ? ' [Revisar]' : ''));
        }

        $this->info("Resultado: {$creadas} creadas, {$actualizadas} actualizadas, {$canceladas} gestionadas por cancelación.");
        Log::info("VmGenerateCheckoutTasks: {$creadas} creadas, {$actualizadas} actualizadas, {$canceladas} cancelaciones.");
    }

    private function tieneControlUser(?string $value): bool
    {
        if ($value === null || $value === '') return false;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? count($decoded) > 0 : false;
    }
}
