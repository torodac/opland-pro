<?php

namespace App\Console\Commands;

use App\Services\VmHorasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Pasada puntual (no recurrente) para aplicar al histórico el ajuste de la hora de salida a la
// jornada de contrato que, desde 2026-09-17, se aplica al cerrar cada fichaje (ver 3.79 en
// DOC_TECNICO.md y VmHorasService::ajustarHoraFinAlContrato). Los fichajes anteriores conservan
// las horas extra "irreales" que motivaron el cambio, y esto las regulariza.
//
// Es idempotente: un fichaje ya ajustado tiene desvío 0 y el helper lo devuelve intacto, así que
// volver a ejecutarla no desplaza nada.
class VmAjustarFichajesRetroactivoCommand extends Command
{
    protected $signature = 'vm:ajustar-fichajes-retroactivo
                            {--desde=2026-08-01 : Fecha de corte (incluida)}
                            {--hasta= : Fecha final (incluida); por defecto, hoy}
                            {--apply : Persiste los cambios; sin esta opción solo muestra el resumen}';

    protected $description = 'Aplica al histórico el ajuste de la hora de salida a la jornada de contrato';

    public function handle(): int
    {
        $desde = $this->option('desde');
        $hasta = $this->option('hasta') ?: now()->toDateString();
        $apply = (bool) $this->option('apply');

        $fichajes = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->whereNotNull('hora_fin')
            ->whereBetween('fecha_fichaje', [$desde, $hasta])
            ->orderBy('fecha_fichaje')
            ->get(['id', 'control_user', 'fecha_fichaje', 'hora_inicio', 'hora_fin', 'pausa_inicio', 'pausa_fin']);

        $nombres = DB::table('vm_usuarios')->pluck('nombre', 'id')->all();

        $resumen = [];   // [usuario][mes] => ['n' => int, 'min' => int]
        $cambios = [];   // [id fichaje] => nueva hora_fin
        foreach ($fichajes as $f) {
            $nueva = VmHorasService::ajustarHoraFinAlContrato(
                (int) $f->control_user,
                (string) $f->fecha_fichaje,
                $f->hora_inicio,
                $f->hora_fin,
                $f->pausa_inicio,
                $f->pausa_fin
            );
            if ($nueva === $f->hora_fin) continue;

            // El ajuste lleva el desvío neto de la jornada a cero, así que la variación de horas
            // extra de ese día es exactamente los minutos que se mueve la salida.
            $delta = VmHorasService::hmsToMinutes($nueva) - VmHorasService::hmsToMinutes($f->hora_fin);
            $mes   = substr((string) $f->fecha_fichaje, 0, 7);
            $nom   = $nombres[$f->control_user] ?? "Usuario {$f->control_user}";

            $resumen[$nom][$mes]['n']   = ($resumen[$nom][$mes]['n']   ?? 0) + 1;
            $resumen[$nom][$mes]['min'] = ($resumen[$nom][$mes]['min'] ?? 0) + $delta;
            $cambios[$f->id] = $nueva;
        }

        $this->info("Fichajes revisados ({$desde} a {$hasta}): " . $fichajes->count());
        $this->info('Fichajes que cambian: ' . count($cambios));
        $this->newLine();

        ksort($resumen);
        $filas = [];
        $totalMin = 0;
        foreach ($resumen as $nom => $meses) {
            ksort($meses);
            foreach ($meses as $mes => $d) {
                $filas[] = [$nom, $mes, $d['n'], $this->fmt($d['min'])];
                $totalMin += $d['min'];
            }
        }
        if ($filas) {
            $this->table(['Trabajador', 'Mes', 'Fichajes', 'Horas extra'], $filas);
            $this->info('Variación total de horas extra: ' . $this->fmt($totalMin));
        } else {
            $this->warn('Ningún fichaje cambia en ese rango.');
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('[DRY-RUN] No se ha escrito nada. Añade --apply para persistirlo.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $id => $nueva) {
                DB::table('vm_fichaje')->where('id', $id)->update([
                    'hora_fin'  => $nueva,
                    'updatedat' => now(),
                ]);
            }
        });
        $this->newLine();
        $this->info('Aplicado: ' . count($cambios) . ' fichajes actualizados.');

        return self::SUCCESS;
    }

    // Minutos con signo -> "+1h 05m" / "-12m"
    private function fmt(int $min): string
    {
        $signo = $min < 0 ? '-' : '+';
        $abs   = abs($min);
        return $abs >= 60
            ? sprintf('%s%dh %02dm', $signo, intdiv($abs, 60), $abs % 60)
            : sprintf('%s%dm', $signo, $abs);
    }
}
