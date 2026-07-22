<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IcneaSyncImportesCommand extends Command
{
    protected $signature   = 'icnea:sync-importes
                                {--dias=30 : Número de días hacia atrás desde hoy para filtrar por checkout}';
    protected $description = 'Sincroniza vm_reservas_importes (detail[] + channel_commission) para reservas con checkout en los últimos N días';

    private string $apiKey  = 'v$c$t$321$m$r$b';
    private string $ownerId = '1540';

    public function handle(): void
    {
        $dias  = (int) ($this->option('dias') ?? 30);
        $desde = now()->subDays($dias)->toDateString();
        $hasta = now()->toDateString();

        $this->info("Sincronizando importes para reservas con checkout {$desde} → {$hasta}");

        $reservas = DB::table('vm_reservas')
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->whereNotIn('booking_status', ['cancelled'])
            ->get(['id', 'booking_id', 'guest_name']);

        $this->info(count($reservas) . ' reservas a procesar.');

        $inserted = 0;
        $updated  = 0;
        $errors   = 0;

        foreach ($reservas as $reserva) {
            $response = $this->fetchReservation($reserva->booking_id);

            if ($response === null) {
                $this->warn("  [{$reserva->booking_id}] No se pudo obtener detalle.");
                $errors++;
                continue;
            }

            // Construir lista de líneas: detail[] + channel_commission como línea extra
            $detail = $response['detail'] ?? null;
            if (empty($detail)) {
                $lineas = [];
            } else {
                if (isset($detail['text'])) {
                    $detail = [$detail];
                }
                $lineas = array_map(fn($l) => ['text' => $l['text'] ?? '', 'import' => $l['import'] ?? 0], $detail);
            }

            $cc = isset($response['channel_commission']) && (float) $response['channel_commission'] > 0
                ? (float) $response['channel_commission']
                : null;
            if ($cc !== null) {
                $lineas[] = ['text' => 'Comisión canal', 'import' => $cc];
            }

            if (empty($lineas)) {
                $this->line("  [{$reserva->booking_id}] Sin líneas de importe.");
                continue;
            }

            foreach ($lineas as $linea) {
                $texto   = trim($linea['text']);
                $importe = (float) $linea['import'];

                if ($texto === '') {
                    continue;
                }

                // Icnea devuelve el catalan "allotjament" para el concepto de alojamiento
                if (strcasecmp($texto, 'allotjament') === 0) {
                    $texto = 'alojamiento';
                }

                $existing = DB::table('vm_reservas_importes')
                    ->where('booking_id', $reserva->booking_id)
                    ->where('texto', $texto)
                    ->first();

                if ($existing) {
                    if ((float) $existing->importe !== $importe) {
                        DB::table('vm_reservas_importes')
                            ->where('id', $existing->id)
                            ->update(['importe' => $importe, 'updatedat' => now()]);
                        $updated++;
                    }
                } else {
                    DB::table('vm_reservas_importes')->insert([
                        'id_reserva' => $reserva->id,
                        'booking_id' => $reserva->booking_id,
                        'texto'      => $texto,
                        'importe'    => $importe,
                        'createdat'  => now(),
                        'updatedat'  => now(),
                    ]);
                    $inserted++;
                }
            }

            $this->line("  [{$reserva->booking_id}] {$reserva->guest_name}: " . count($lineas) . ' líneas' . ($cc ? " · CC: {$cc}" : ''));
        }

        $this->info("Completado — insertados: {$inserted}, actualizados: {$updated}, errores: {$errors}");
    }

    private function fetchReservation(string $bookingId): ?array
    {
        $url = 'https://ws.icnea.net/services_get_reservation.aspx?' . http_build_query([
            'api_key'    => $this->apiKey,
            'owner_id'   => $this->ownerId,
            'booking_id' => $bookingId,
        ]);

        $ctx = stream_context_create(['http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        return $data['services_get_reservation_response']['reservations'] ?? null;
    }
}
