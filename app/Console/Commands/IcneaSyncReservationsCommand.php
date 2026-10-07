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

    private string $apiKey;
    private string $ownerId;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey  = (string) config('services.icnea.api_key');
        $this->ownerId = (string) config('services.icnea.owner_id');
    }

    // Cuántas llamadas al servicio devolvieron respuesta utilizable. Sirve para distinguir
    // "hoy no había reservas" de "el servicio no nos contesta", que es lo que se nos escapó.
    private int $llamadas = 0;
    private int $llamadasOk = 0;

    public function handle(): void
    {
        $desde = $this->option('desde') ?? now()->subDays(30)->format('Y-m-d');
        $hastaDefault = now()->addDays(335)->format('Y-m-d'); // desde+30+335 < 366 days (API limit: max 1 year)
        $hasta = $this->option('hasta') ?? $hastaDefault;

        $this->info("Sincronizando reservas {$desde} → {$hasta}");

        $propiedades = DB::table('vm_propiedades')
            ->whereNotNull('icnea_lodging_id')
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->get(['id', 'nombre', 'icnea_lodging_id']);

        // Mapa lodging_id → vm_propiedades.id para el FK
        $propMap = $propiedades->keyBy(fn($p) => $this->ownerId . $p->icnea_lodging_id);

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
                    'id_propiedades'        => $propMap[$lodgingId]->id ?? null,
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
                    'createuser'            => 1,
                    'createdat'             => $this->date($r['booking_date'] ?? null) ?? now()->toDateString(),
                ]);
                $totalInserted++;
            }

            $this->line("  {$prop->nombre}: " . count($reservas) . ' reservas');
        }

        $this->info("{$totalInserted} reservas en tabla temporal. Comparando con vm_reservas...");

        $this->mergeIntoReservas();

        $this->info('Sincronización completada.');
        Log::info("IcneaSyncReservations: {$totalInserted} procesadas, {$desde} → {$hasta}");

        \App\Services\SaludIntegraciones::comprobar(
            'icnea:sync-reservations', $this->llamadas, $this->llamadasOk,
            'Ninguna reserva creada o modificada en Icnea está llegando a Opland.'
        );
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

        $this->llamadas++;

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

        // API returns XML on error (e.g. date range > 1 year)
        if (str_starts_with(trim($response), '<')) {
            Log::error("IcneaSyncReservations XML error ({$lodgingId}): {$response}");
            return [];
        }

        $data = json_decode($response, true);

        if (!$data) {
            Log::error("IcneaSyncReservations invalid JSON ({$lodgingId}): " . substr($response, 0, 200));
            return [];
        }

        if (isset($data['services_get_reservations_response']['error'])) {
            Log::warning("IcneaSyncReservations error ({$lodgingId}): " . $data['services_get_reservations_response']['error']);
            return [];
        }

        $this->llamadasOk++;

        return $data['services_get_reservations_response']['reservations'] ?? [];
    }

    private function mergeIntoReservas(): void
    {
        // Los campos que se COMPARAN son los mismos que se escriben abajo. Antes se comparaban
        // solo cuatro y se escribían diez: si a un huésped le cambiaba el email, el teléfono o el
        // número de adultos y nada más, el merge concluía "sin cambios" y no guardaba nada.
        $camposBase = [
            'booking_status', 'check_in_date', 'check_out_date', 'checkin_status',
            'number_of_adults', 'number_of_children', 'number_of_infants',
            'guest_name', 'guest_email', 'guest_phone',
        ];

        // Icnea puede mover una reserva de villa, y hasta ahora eso no llegaba: la propiedad se
        // escribía SOLO al insertar y después quedaba congelada para siempre. Importa porque
        // id_propiedades manda en la planilla de liquidación, las novaciones, las tareas de
        // limpieza y la devolución de fianzas: una reserva en la villa equivocada paga al
        // propietario equivocado. Caso que lo destapó: la 131282, grabada en Villa Serendi
        // cuando Icnea dice Villa Regina (2026-10-07).
        $camposPropiedad = ['icnea_lodging_id', 'id_propiedades', 'vm_propiedades_nombre'];

        $now = now()->format('Y-m-d H:i:s');

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
                    'id_propiedades'        => $temp->id_propiedades,
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
                    'trace'                 => $this->lineaTraza('booking_status', null, $temp->booking_status),
                    'icnea_updatedat'       => $temp->icnea_updatedat,
                    'createuser'            => 1,
                    'createdat'             => $temp->createdat ?? $now,
                ]);
                $nuevas++;
                continue;
            }

            // Detectar cambios
            $cambios = [];

            // La propiedad NUNCA se sobrescribe con un hueco: si el lodging que devuelve Icnea no
            // está mapeado en vm_propiedades, id_propiedades vendría a null, y quedarse sin
            // propiedad es peor que tenerla desactualizada.
            $puedeTocarPropiedad = $temp->id_propiedades !== null;
            $camposComparar = $puedeTocarPropiedad
                ? array_merge($camposBase, $camposPropiedad)
                : $camposBase;

            foreach ($camposComparar as $campo) {
                $vAnterior = $existing->$campo;
                $vNuevo    = $temp->$campo;
                if ((string) $vAnterior !== (string) $vNuevo) {
                    $cambios[] = ['campo' => $campo, 'de' => $vAnterior, 'a' => $vNuevo];
                }
            }

            if (empty($cambios)) {
                $sinCambios++;
                continue;
            }

            // Se añaden al final, separadas por saltos de línea, conservando lo que ya hubiera.
            $lineas = array_map(fn($c) => $this->lineaTraza($c['campo'], $c['de'], $c['a']), $cambios);
            $trace  = trim(($existing->trace ? $existing->trace . "\n" : '') . implode("\n", $lineas));

            $datos = [
                'booking_status'     => $temp->booking_status,
                'check_in_date'      => $temp->check_in_date,
                'check_out_date'     => $temp->check_out_date,
                'checkin_status'     => $temp->checkin_status,
                'number_of_adults'   => $temp->number_of_adults,
                'number_of_children' => $temp->number_of_children,
                'number_of_infants'  => $temp->number_of_infants,
                'guest_name'         => $temp->guest_name,
                'guest_email'        => $temp->guest_email,
                'guest_phone'        => $temp->guest_phone,
                'trace'              => $trace,
                'icnea_updatedat'    => now(),
                'updateuser'         => 1,
                'updatedat'          => $now,
            ];
            if ($puedeTocarPropiedad) {
                $datos['icnea_lodging_id']      = $temp->icnea_lodging_id;
                $datos['id_propiedades']        = $temp->id_propiedades;
                $datos['vm_propiedades_nombre'] = $temp->vm_propiedades_nombre;
            }

            DB::table('vm_reservas')->where('booking_id', $temp->booking_id)->update($datos);
            $actualizadas++;

            foreach ($cambios as $c) {
                $linea = "  CAMBIO #{$temp->booking_id} {$temp->guest_name}: {$c['campo']} '{$c['de']}' → '{$c['a']}'";
                // Un cambio de villa no es un cambio cualquiera: arrastra liquidación, limpiezas
                // y fianzas, así que además de la consola va al log, donde sí se lee.
                if ($c['campo'] === 'id_propiedades') {
                    Log::warning("IcneaSyncReservations: la reserva #{$temp->booking_id} "
                        . "({$temp->guest_name}) cambia de propiedad en Icnea: {$c['de']} → {$c['a']} "
                        . "({$temp->vm_propiedades_nombre}). Revisar liquidación y limpiezas.");
                    $linea .= '  [registrado en el log]';
                }
                $this->line($linea);
            }
        }

        $this->info("Resultado: {$nuevas} nuevas, {$actualizadas} actualizadas, {$sinCambios} sin cambios.");
        Log::info("IcneaSyncReservations merge: {$nuevas} nuevas, {$actualizadas} actualizadas, {$sinCambios} sin cambios.");
    }

    /**
     * Una línea de la traza de cambios, legible tal cual en el campo "Traza cambios" de la ficha.
     *
     * La traza se guardaba como un array JSON en una columna json, y en la ficha se veía como un
     * churro de objetos separados por comas. Pasa a ser texto con un cambio por línea (y la
     * columna, a text, que es lo que el campo declaraba desde siempre).
     */
    private function lineaTraza(string $campo, $de, $a): string
    {
        $vacio = fn($v) => ($v === null || $v === '') ? '(vacío)' : (string) $v;

        return now()->format('d/m/Y H:i') . " · {$campo}: " . $vacio($de) . ' → ' . $vacio($a);
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
