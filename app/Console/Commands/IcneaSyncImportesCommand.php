<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IcneaSyncImportesCommand extends Command
{
    protected $signature   = 'icnea:sync-importes
                                {--meses=6 : Número de meses hacia atrás (además del actual) para filtrar por checkout}';
    protected $description = 'Sincroniza vm_reservas_importes (detail[] + channel_commission) para reservas con checkout en los últimos N meses, igual que el botón Sincronizar de Novaciones';

    private string $apiKey  = 'v$c$t$321$m$r$b';
    private string $ownerId = '1540';

    // Líneas que gestiona la propia Novaciones a mano y que Icnea nunca devuelve --
    // nunca se marcan como obsoletas aunque no aparezcan en la respuesta de la API.
    private array $textosProtegidos = ['Management Fee', 'Comisión Bancos'];

    public function handle(): void
    {
        $meses = (int) ($this->option('meses') ?? 6);
        $desde = now()->subMonths($meses)->startOfMonth()->toDateString();
        $hasta = now()->endOfMonth()->toDateString();

        $this->info("Sincronizando importes para reservas con checkout {$desde} → {$hasta}");

        $reservas = DB::table('vm_reservas')
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->whereNotIn('booking_status', ['cancelled'])
            ->get(['id', 'booking_id', 'guest_name']);

        $this->info(count($reservas) . ' reservas a procesar.');

        $procesadas = 0;
        $insertadas = 0;
        $actualizadas = 0;
        $borradas = 0;
        $errores = 0;

        foreach ($reservas as $reserva) {
            $response = $this->fetchReservation($reserva->booking_id);

            if ($response === null) {
                $this->warn("  [{$reserva->booking_id}] No se pudo obtener detalle.");
                $errores++;
                continue;
            }

            // Construir líneas: detail[] (traduciendo el catalán) + channel_commission como línea extra.
            $detail = $response['detail'] ?? null;
            $lineas = [];
            if (!empty($detail)) {
                if (isset($detail['text'])) {
                    $detail = [$detail];
                }
                foreach ($detail as $linea) {
                    $texto = trim($linea['text'] ?? '');
                    if (strcasecmp($texto, 'allotjament') === 0) {
                        $texto = 'alojamiento';
                    }
                    if ($texto !== '') {
                        $lineas[$texto] = (float) ($linea['import'] ?? 0);
                    }
                }
            }

            $cc = isset($response['channel_commission']) && (float) $response['channel_commission'] > 0
                ? (float) $response['channel_commission']
                : null;
            if ($cc !== null) {
                $lineas['Comisión canal'] = $cc;
            }

            if (empty($lineas)) {
                $this->line("  [{$reserva->booking_id}] Sin líneas de importe.");
                continue;
            }

            // Marcar como obsoletas (deleted=1) las líneas que ya no vienen en la respuesta
            // de Icnea, salvo las protegidas -- igual que reconciliarMes() en Novaciones.
            $actuales = DB::table('vm_reservas_importes')
                ->where('booking_id', $reserva->booking_id)
                ->where('deleted', 0)
                ->get(['id', 'texto']);

            foreach ($actuales as $fila) {
                if (in_array($fila->texto, $this->textosProtegidos, true)) {
                    continue;
                }
                if (!array_key_exists($fila->texto, $lineas)) {
                    DB::table('vm_reservas_importes')->where('id', $fila->id)->update([
                        'deleted'   => 1,
                        'updatedat' => now(),
                    ]);
                    $borradas++;
                }
            }

            foreach ($lineas as $texto => $importe) {
                $existente = DB::table('vm_reservas_importes')
                    ->where('booking_id', $reserva->booking_id)
                    ->where('texto', $texto)
                    ->first();

                if ($existente) {
                    if ((float) $existente->importe !== $importe || $existente->deleted) {
                        DB::table('vm_reservas_importes')->where('id', $existente->id)->update([
                            'importe'   => $importe,
                            'deleted'   => 0,
                            'updatedat' => now(),
                        ]);
                        $actualizadas++;
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
                    $insertadas++;
                }
            }

            $procesadas++;
            $this->line("  [{$reserva->booking_id}] {$reserva->guest_name}: " . count($lineas) . ' líneas' . ($cc ? " · CC: {$cc}" : ''));
        }

        $this->info("Completado — procesadas: {$procesadas}, insertadas: {$insertadas}, actualizadas: {$actualizadas}, marcadas obsoletas: {$borradas}, errores: {$errores}");
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
