<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Copia a vm_cerraduras_accesos las autorizaciones de cada cerradura (quién puede abrir, con qué
// código y desde cuándo).
//
// SOLO LECTURA contra Nuki: GET /smartlock/{id}/auth. Nunca crea, cambia ni borra nada en Nuki.
//
// Una llamada que falla NO borra las autorizaciones guardadas de esa cerradura: solo se borran las
// que Nuki confirma que ya no existen, así que un fallo de red no deja la pantalla vacía.
class VmSyncNukiAccesosCommand extends Command
{
    protected $signature   = 'vm:sync-nuki-accesos';
    protected $description = 'Copia a vm_cerraduras_accesos las autorizaciones de cada cerradura Nuki (solo lectura)';

    public function handle(): int
    {
        $token = (string) config('services.nuki.token');
        if ($token === '') {
            $this->error('Falta NUKI_API_TOKEN en el .env.');
            Log::error('vm:sync-nuki-accesos: falta NUKI_API_TOKEN.');
            return self::FAILURE;
        }

        $cerraduras = DB::table('vm_cerraduras')->pluck('nuki_smartlock_id');
        $ahora = now();
        $guardadas = 0;
        $fallos = 0;

        foreach ($cerraduras as $smartlockId) {
            [$code, $auths] = $this->get("https://api.nuki.io/smartlock/{$smartlockId}/auth", $token);
            if ($code !== 200 || !is_array($auths)) {
                $fallos++;
                Log::warning("vm:sync-nuki-accesos: cerradura {$smartlockId} HTTP {$code}, se conservan sus accesos.");
                continue;
            }

            $vistos = [];
            foreach ($auths as $a) {
                $authId = $a['authId'] ?? null;
                if (!$authId) {
                    continue;
                }

                $tipo = match ((int) ($a['type'] ?? -1)) {
                    13      => 'codigo',
                    0       => 'app',
                    3       => 'teclado',
                    default => 'otro',
                };

                DB::table('vm_cerraduras_accesos')->updateOrInsert(
                    ['nuki_smartlock_id' => $smartlockId, 'nuki_auth_id' => $authId],
                    [
                        'nombre'         => trim((string) ($a['name'] ?? '')) ?: null,
                        'tipo'           => $tipo,
                        'codigo'         => $tipo === 'codigo' && isset($a['code']) ? Crypt::encryptString((string) $a['code']) : null,
                        'habilitado'     => (bool) ($a['enabled'] ?? false),
                        'fecha_desde'    => $this->fecha($a['allowedFromDate'] ?? null),
                        'fecha_hasta'    => $this->fecha($a['allowedUntilDate'] ?? null),
                        'creado_nuki_at' => $this->fecha($a['creationDate'] ?? null),
                        'ultimo_uso_at'  => $this->fecha($a['lastActiveDate'] ?? null),
                        'leido_at'       => $ahora,
                        'updated_at'     => $ahora,
                    ]
                );

                $vistos[] = $authId;
                $guardadas++;
            }

            DB::table('vm_cerraduras_accesos')
                ->where('nuki_smartlock_id', $smartlockId)
                ->whereNotIn('nuki_auth_id', $vistos)
                ->delete();
        }

        $this->info("{$guardadas} autorizaciones guardadas de " . $cerraduras->count() . " cerraduras, {$fallos} fallos.");
        Log::info("vm:sync-nuki-accesos: {$guardadas} autorizaciones, {$fallos} fallos.");

        return $fallos === $cerraduras->count() && $cerraduras->count() > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fecha(?string $valor): ?Carbon
    {
        if (!$valor) {
            return null;
        }
        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
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
