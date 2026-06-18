<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IcneaSyncReservationsCommand extends Command
{
    protected $signature   = 'icnea:sync-reservations
                                {--desde= : Fecha inicio yyyy-MM-dd (defecto: 2026-01-01)}
                                {--hasta= : Fecha fin yyyy-MM-dd (defecto: hoy+365 días)}';
    protected $description = 'Sincroniza vm_reservas desde Icnea GET Reservations';

    private string $apiKey  = 'v$c$t$321$m$r$b';
    private string $ownerId = '1540';

    public function handle(): void
    {
        $desde = $this->option('desde') ?? '2026-01-01';
        $hasta = $this->option('hasta') ?? now()->addDays(365)->format('Y-m-d');

        $this->info("Sincronizando reservas {$desde} → {$hasta}");

        $propiedades = DB::table('vm_propiedades')
            ->whereNotNull('icnea_lodging_id')
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->get(['id', 'nombre', 'icnea_lodging_id']);

        $this->info(count($propiedades) . ' propiedades a procesar.');

        // Vaciar tabla temporal
        DB::table('vm_reservas_temp')->truncate();

        $totalInserted = 0;

        foreach ($propiedades as $prop) {
            $lodgingId = $this->ownerId . $prop->icnea_lodging_id;
            $reservas  = $this->fetchReservations($lodgingId, $desde, $hasta);

            if (empty($reservas)) {
                continue;
            }

            foreach ($reservas as $r) {
                DB::table('vm_reservas_temp')->insert([
                    'nombre'                => trim($r['guest_name'] ?? ''),
                    'icnea_lodging_id'      => $lodgingId,
                    'vm_propiedades_nombre' => $prop->nombre,
                    'booking_id'            => $r['booking_id'],
                    'booking_date'          => $this->date($r['booking_date'] ?? null),
                    'booking_status'        => $r['booking_status'] ?? null,
                    'check_in_date'         => $this->date($r['check_in_date'] ?? null),
                    'check_out_date'        => $this->date($r['check_out_date'] ?? null),
                    'number_of_adults'      => (int) ($r['number_of_adults'] ?? 0),
                    'number_of_children'    => (int) ($r['number_of_children'] ?? 0),
                    'number_of_infants'     => (int) ($r['number_of_infants'] ?? 0),
                    'guest_name'            => trim($r['guest_name'] ?? ''),
                    'guest_email'           => $r['guest_email'] ?? null,
                    'guest_phone'           => $r['guest_phone'] ?? null,
                    'guest_language'        => $r['guest_language'] ?? null,
                    'checkin_status'        => $r['checkin_status'] ?? null,
                    'icnea_updatedat'       => now(),
                    'createdat'             => now(),
                    'updatedat'             => now(),
                ]);
                $totalInserted++;
            }

            $this->line("  {$prop->nombre}: " . count($reservas) . ' reservas');
        }

        $this->info("{$totalInserted} reservas en tabla temporal. Comparando con vm_reservas...");

        $this->mergeIntoReservas();

        $this->info('Sincronización completada.');
        Log::info("IcneaSyncReservations: {$totalInserted} procesadas, {$desde} → {$hasta}");
    }

    private function fetchReservations(string $lodgingId, string $desde, string $hasta): array
    {
        $url = 'https://ws.icnea.net/services_get_reservations.aspx?' . http_build_query([
            'api_key'    => $this->apiKey,
            'owner_id'   => $this->ownerId,
            'lodging_id' => $lodgingId,
            'start_date' => $desde,
            'end_date'   => $hasta,
            'include'    => 'all',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err || !$response) {
            Log::error("IcneaSyncReservations CURL error ({$lodgingId}): {$err}");
            return [];
        }

        $data = json_decode($response, true);

        if (isset($data['services_get_reservations_response']['error'])) {
            Log::warning("IcneaSyncReservations error ({$lodgingId}): " . $data['services_get_reservations_response']['error']);
            return [];
        }

        return $data['services_get_reservations_response']['reservations'] ?? [];
    }

    private function mergeIntoReservas(): void
    {
        $camposComparar = ['booking_status', 'check_in_date', 'check_out_date', 'checkin_status'];
        $now            = now()->format('Y-m-d H:i:s');

        $nuevas      = 0;
        $actualizadas = 0;
        $sinCambios  = 0;

        $temps = DB::table('vm_reservas_temp')->get();

        foreach ($temps as $temp) {
            $existing = DB::table('vm_reservas')->where('booking_id', $temp->booking_id)->first();

            if (!$existing) {
                // Nueva reserva
                DB::table('vm_reservas')->insert([
                    'nombre'                => $temp->guest_name,
                    'icnea_lodging_id'      => $temp->icnea_lodging_id,
                    'vm_propiedades_nombre' => $temp->vm_propiedades_nombre,
                    'booking_id'            => $temp->booking_id,
                    'booking_date'          => $temp->booking_date,
                    'booking_status'        => $temp->booking_status,
                    'check_in_date'         => $temp->check_in_date,
                    'check_out_date'        => $temp->check_out_date,
                    'number_of_adults'      => $temp->number_of_adults,
                    'number_of_children'    => $temp->number_of_children,
                    'number_of_infants'     => $temp->number_of_infants,
                    'guest_name'            => $temp->guest_name,
                    'guest_email'           => $temp->guest_email,
                    'guest_phone'           => $temp->guest_phone,
                    'guest_language'        => $temp->guest_language,
                    'checkin_status'        => $temp->checkin_status,
                    'trace'                 => json_encode([['fecha' => $now, 'campo' => 'booking_status', 'de' => null, 'a' => $temp->booking_status]]),
                    'icnea_updatedat'       => $temp->icnea_updatedat,
                    'createdat'             => $now,
                    'updatedat'             => $now,
                ]);
                $nuevas++;
                continue;
            }

            // Detectar cambios
            $trace   = json_decode($existing->trace ?? '[]', true) ?? [];
            $cambios = [];

            foreach ($camposComparar as $campo) {
                $vAnterior = $existing->$campo;
                $vNuevo    = $temp->$campo;
                if ((string) $vAnterior !== (string) $vNuevo) {
                    $cambios[] = ['fecha' => $now, 'campo' => $campo, 'de' => $vAnterior, 'a' => $vNuevo];
                }
            }

            if (empty($cambios)) {
                $sinCambios++;
                continue;
            }

            $trace = array_merge($trace, $cambios);

            DB::table('vm_reservas')->where('booking_id', $temp->booking_id)->update([
                'booking_status'  => $temp->booking_status,
                'check_in_date'   => $temp->check_in_date,
                'check_out_date'  => $temp->check_out_date,
                'checkin_status'  => $temp->checkin_status,
                'number_of_adults'   => $temp->number_of_adults,
                'number_of_children' => $temp->number_of_children,
                'number_of_infants'  => $temp->number_of_infants,
                'guest_name'      => $temp->guest_name,
                'guest_email'     => $temp->guest_email,
                'guest_phone'     => $temp->guest_phone,
                'trace'           => json_encode($trace),
                'icnea_updatedat' => now(),
                'updatedat'       => $now,
            ]);
            $actualizadas++;

            foreach ($cambios as $c) {
                $this->line("  CAMBIO #{$temp->booking_id} {$temp->guest_name}: {$c['campo']} '{$c['de']}' → '{$c['a']}'");
            }
        }

        $this->info("Resultado: {$nuevas} nuevas, {$actualizadas} actualizadas, {$sinCambios} sin cambios.");
        Log::info("IcneaSyncReservations merge: {$nuevas} nuevas, {$actualizadas} actualizadas, {$sinCambios} sin cambios.");
    }

    private function date(?string $val): ?string
    {
        if (!$val || $val === '') return null;
        try {
            return \Carbon\Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
