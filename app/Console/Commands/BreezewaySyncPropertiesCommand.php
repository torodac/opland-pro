<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Cruza vm_propiedades con las propiedades reales de Breezeway (GET /inventory/v1/property),
// para dos cosas:
// 1. Rellenar vm_propiedades.breezeway_home_id donde falte -- el emparejamiento original
//    (2026-07-09) se hizo a mano una sola vez por el número que precede al nombre en Breezeway
//    ("1297 · villa status" -> icnea_lodging_id=1297); este comando automatiza ese mismo cruce
//    para las propiedades que se den de alta después (via icnea:sync-pro) sin tocarlo a mano.
// 2. Mantener vm_breezeway_propiedades_pendientes con las propiedades de Breezeway que no tienen
//    ningún match en Opland -- mismo patrón que vm_breezeway_pendientes (personas), mismo tipo de
//    aviso en el listado.
//
// Nunca sobrescribe un breezeway_home_id ya distinto del que resolvería este cruce (ver
// $conflictos) -- solo rellena huecos, cualquier discrepancia real queda para revisión manual.
class BreezewaySyncPropertiesCommand extends Command
{
    protected $signature   = 'breezeway:sync-properties';
    protected $description = 'Cruza vm_propiedades.icnea_lodging_id con las propiedades de Breezeway: rellena breezeway_home_id donde falte y mantiene vm_breezeway_propiedades_pendientes';

    private string $clientId;
    private string $clientSecret;

    public function handle(): void
    {
        $this->clientId     = (string) env('BREEZEWAY_CLIENT_ID');
        $this->clientSecret = (string) env('BREEZEWAY_CLIENT_SECRET');

        $token = $this->authenticate();
        if (!$token) {
            $this->error('No se pudo autenticar contra Breezeway.');
            return;
        }

        $propiedadesBreezeway = $this->fetchAllProperties($token);
        $activas = array_filter($propiedadesBreezeway, fn($p) => ($p['status'] ?? '') === 'active');
        $this->info(count($activas) . ' propiedades activas recibidas de Breezeway.');

        $porLodgingId = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->whereNotNull('icnea_lodging_id')
            ->get(['id', 'icnea_lodging_id', 'breezeway_home_id'])
            ->keyBy(fn($p) => (int) $p->icnea_lodging_id);

        // Todo lo que YA tiene breezeway_home_id informado, sea cual sea el motivo (el cruce por
        // número, o un caso especial resuelto a mano como ALMACÉN, que no tiene icnea_lodging_id)
        // -- estos quedan fuera del todo, ni se tocan ni se listan como pendientes.
        $yaMapeados = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->whereNotNull('breezeway_home_id')
            ->pluck('breezeway_home_id')
            ->map(fn($id) => (int) $id)
            ->flip();

        $rellenados = 0;
        $conflictos = [];
        $pendientesVistos = [];
        $ahora = now();

        foreach ($activas as $bp) {
            $breezewayId = (int) $bp['id'];
            if (isset($yaMapeados[$breezewayId])) continue; // ya resuelto, de la forma que sea

            $lodgingId = $this->extraerLodgingId($bp['name'] ?? '');
            $vm = $lodgingId !== null ? ($porLodgingId[$lodgingId] ?? null) : null;

            if (!$vm) {
                $pendientesVistos[$breezewayId] = $bp['name'] ?? "#{$breezewayId}";
                continue;
            }

            if ($vm->breezeway_home_id === null) {
                DB::table('vm_propiedades')->where('id', $vm->id)->update([
                    'breezeway_home_id' => $breezewayId,
                    'updatedat'         => $ahora,
                ]);
                $rellenados++;
                $this->line("  RELLENADO: [{$lodgingId}] {$bp['name']} -> vm_propiedades#{$vm->id}");
            } else {
                // La propiedad ya tiene OTRO breezeway_home_id distinto del que da este cruce --
                // no se pisa, se avisa para revisar a mano.
                $conflictos[] = "{$bp['name']} (Breezeway #{$breezewayId}) vs vm_propiedades#{$vm->id} ya tiene #{$vm->breezeway_home_id}";
            }
        }

        // Upsert de vm_breezeway_propiedades_pendientes: igual que vm_breezeway_pendientes
        // (personas) -- se actualiza lo visto, se borra lo que ya no aparece sin match (porque
        // se resolvió, o porque Breezeway ya no lo devuelve como activo).
        foreach ($pendientesVistos as $breezewayId => $nombre) {
            $existe = DB::table('vm_breezeway_propiedades_pendientes')->where('breezeway_id', $breezewayId)->first();
            if ($existe) {
                DB::table('vm_breezeway_propiedades_pendientes')->where('id', $existe->id)->update([
                    'nombre'           => $nombre,
                    'ultima_deteccion' => $ahora,
                    'updatedat'        => $ahora,
                ]);
            } else {
                DB::table('vm_breezeway_propiedades_pendientes')->insert([
                    'nombre'           => $nombre,
                    'breezeway_id'     => $breezewayId,
                    'fecha_alta'       => $ahora->toDateString(),
                    'ultima_deteccion' => $ahora,
                    'deleted'          => 0,
                    'hidden'           => 0,
                    'createdat'        => $ahora,
                    'updatedat'        => $ahora,
                ]);
                $this->line("  SIN MATCH EN OPLAND: {$nombre} (Breezeway #{$breezewayId})");
            }
        }
        DB::table('vm_breezeway_propiedades_pendientes')
            ->whereNotIn('breezeway_id', array_keys($pendientesVistos))
            ->delete();

        $this->info("Rellenados: {$rellenados}. Sin match en Opland: " . count($pendientesVistos) . '.');
        if (!empty($conflictos)) {
            $this->warn('Conflictos (no se han tocado, revisar a mano):');
            foreach ($conflictos as $c) $this->warn("  - {$c}");
        }
    }

    // "1297 · villa status" -> 1297. Si el nombre no empieza por un número (p.ej. "ALMACÉN"),
    // no hay forma de cruzarlo por este camino -- se queda como pendiente de resolver a mano,
    // igual que se hizo originalmente con ALMACÉN.
    private function extraerLodgingId(string $nombre): ?int
    {
        return preg_match('/^\s*(\d+)\s*·/u', $nombre, $m) ? (int) $m[1] : null;
    }

    private function fetchAllProperties(string $token): array
    {
        $all = [];
        $page = 1;
        do {
            $url = 'https://api.breezeway.io/public/inventory/v1/property?' . http_build_query(['limit' => 100, 'page' => $page]);
            $resp = $this->curlJson($url, 'GET', ['Authorization: JWT ' . $token]);
            $results = $resp['results'] ?? [];
            $all = array_merge($all, $results);
            $page++;
        } while (!empty($results));

        return $all;
    }

    private function authenticate(): ?string
    {
        $resp = $this->curlJson('https://api.breezeway.io/public/auth/v1/', 'POST', [], [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
        return $resp['access_token'] ?? null;
    }

    private function curlJson(string $url, string $method = 'GET', array $headers = [], ?array $body = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("CURL error: {$err}");
        }

        return json_decode($resp, true) ?? [];
    }
}
