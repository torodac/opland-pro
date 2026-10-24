<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Trae de Nuki el estado de las cerraduras a vm_cerraduras.
//
// SOLO LECTURA contra Nuki: este comando nunca abre, cierra ni modifica nada. Son puertas de
// viviendas con huespedes dentro, y el token que usa podria hacerlo.
//
// Se guarda un espejo local en vez de llamar a Nuki desde el dashboard porque son 49 cerraduras
// y casi un segundo de espera: cada pintada de pantalla seria una llamada a un tercero.
class VmSyncNukiCommand extends Command
{
    protected $signature   = 'vm:sync-nuki';
    protected $description = 'Lee de Nuki el estado de las cerraduras (bateria) y lo guarda en vm_cerraduras';

    public function handle(): int
    {
        $token = (string) config('services.nuki.token');
        if ($token === '') {
            $this->error('Falta NUKI_API_TOKEN en el .env.');
            Log::error('vm:sync-nuki: falta NUKI_API_TOKEN.');
            return self::FAILURE;
        }

        [$code, $locks] = $this->get('https://api.nuki.io/smartlock', $token);
        if ($code !== 200 || !is_array($locks)) {
            $this->error("Nuki ha respondido HTTP {$code}.");
            Log::error("vm:sync-nuki: Nuki ha respondido HTTP {$code}.");
            return self::FAILURE;
        }

        // Las propiedades, para intentar atar cada cerradura a la suya por el nombre. Nuki no
        // conoce nuestros ids, asi que es lo unico de que se dispone.
        $propiedades = DB::table('vm_propiedades')
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->get(['id', 'nombre']);

        $ahora = now();
        $guardadas = 0;
        $atadas = 0;

        foreach ($locks as $l) {
            $id = $l['smartlockId'] ?? null;
            if (!$id) {
                continue;
            }

            $propiedad = $this->propiedadDe((string) ($l['name'] ?? ''), $propiedades);

            $datos = [
                'nombre'          => trim((string) ($l['name'] ?? '')) ?: null,
                'bateria'         => $l['state']['batteryCharge'] ?? null,
                'bateria_critica' => (bool) ($l['state']['batteryCritical'] ?? false),
                'leido_at'        => $ahora,
                'updated_at'      => $ahora,
                'created_at'      => $ahora,
            ];

            // La propiedad NO se escribe en cada pasada: solo se rellena si esta vacia.
            //
            // Esto se deduce de un nombre, y hay cerraduras que no se pueden deducir y se atan a
            // mano. Si el sync reescribiera el campo, la pasada de la manana borraria ese trabajo
            // todas las noches sin que nadie se enterara.
            $actual = DB::table('vm_cerraduras')->where('nuki_smartlock_id', $id)->value('id_propiedades');
            if ($actual === null && $propiedad !== null) {
                $datos['id_propiedades'] = $propiedad;
                $atadas++;   // atadas EN ESTA PASADA, no el total que ya tienen propiedad
            }

            // Último evento de la cerradura: cuándo y quién. Una llamada por cerradura, de solo lectura.
            $ultimo = $this->ultimoEvento((int) $id, $token);
            if ($ultimo !== null) {
                $datos['ultimo_uso_at'] = $ultimo['at'];
                $datos['ultimo_uso_quien'] = $ultimo['quien'];
            }

            DB::table('vm_cerraduras')->updateOrInsert(['nuki_smartlock_id' => $id], $datos);
            $guardadas++;
        }

        $bajas = DB::table('vm_cerraduras')->whereNotNull('bateria')
            ->where('bateria', '<=', self::umbral())->count();

        $conProp = DB::table('vm_cerraduras')->whereNotNull('id_propiedades')->count();
        $this->info("{$guardadas} cerraduras guardadas, {$atadas} atadas ahora ({$conProp} con propiedad en total), {$bajas} con la bateria baja.");
        Log::info("vm:sync-nuki: {$guardadas} cerraduras, {$atadas} con propiedad, {$bajas} con bateria baja.");

        return self::SUCCESS;
    }

    /**
     * El último evento registrado en la cerradura: la fecha y quién (o cómo) la accionó.
     * Null si no hay eventos o si Nuki no responde.
     */
    private function ultimoEvento(int $smartlockId, string $token): ?array
    {
        [$code, $log] = $this->get("https://api.nuki.io/smartlock/{$smartlockId}/log?limit=1", $token);
        if ($code !== 200 || !is_array($log) || empty($log[0]['date'])) {
            return null;
        }

        $evento = $log[0];
        $quien = trim((string) ($evento['name'] ?? '')) ?: match ((int) ($evento['trigger'] ?? -1)) {
            1       => 'Botón de la cerradura',
            2       => 'Bluetooth / app',
            3       => 'Llave física',
            4       => 'Automático',
            255     => 'Web / teclado',
            default => 'Sin identificar',
        };

        return [
            'at'    => Carbon::parse($evento['date']),
            'quien' => $quien,
        ];
    }

    /** Porcentaje a partir del cual se avisa. En el .env para poder subirlo o bajarlo sin desplegar. */
    public static function umbral(): int
    {
        return (int) config('services.nuki.bateria_minima', 30);
    }

    /**
     * La propiedad a la que pertenece una cerradura, deducida del nombre.
     *
     * Nuki no conoce nuestros ids: lo unico comun es el nombre, y no coincide literalmente
     * ("Guadalmina 829" frente a "VILLA MOSS-GUADALMINA 539"). Se compara normalizado y solo se
     * acepta cuando hay UNA sola candidata; con varias se deja sin atar, porque el aviso vale
     * igual sin propiedad y atarla mal mandaria a alguien a la casa equivocada.
     */
    private function propiedadDe(string $nombreCerradura, $propiedades): ?int
    {
        $n = $this->normalizar($nombreCerradura);
        if ($n === '') {
            return null;
        }

        $cands = [];
        foreach ($propiedades as $p) {
            $pn = $this->normalizar((string) $p->nombre);
            if ($pn === '') {
                continue;
            }
            if ($pn === $n || str_contains($pn, $n) || str_contains($n, $pn)) {
                $cands[] = (int) $p->id;
            }
        }

        return count($cands) === 1 ? $cands[0] : null;
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $s)));
    }

    private function get(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$code, json_decode((string) $body, true)];
    }
}
