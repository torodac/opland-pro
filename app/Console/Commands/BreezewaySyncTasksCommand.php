<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BreezewaySyncTasksCommand extends Command
{
    protected $signature   = 'breezeway:sync-tasks {--desde= : Fecha YYYY-MM-DD para filtrar updated_at (por defecto: hace 3 días)} {--full : Ignora --desde y trae todo el histórico, sin filtro de fecha}';
    protected $description = 'Sincroniza tareas de housekeeping/maintenance desde Breezeway a vm_tareas_limpieza / vm_tareas_mantenimiento';

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

        $hoy   = now()->toDateString();
        if ($this->option('full')) {
            $rangoFecha = null;
        } else {
            $desde = $this->option('desde') ?: now()->subDays(3)->toDateString();
            $rangoFecha = "{$desde},{$hoy}";
        }

        $usuariosPorBreezeway = DB::table('vm_usuarios')
            ->whereNotNull('breezeway')
            ->pluck('id', 'breezeway');

        $propiedades = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))
            ->whereNotNull('breezeway_home_id')
            ->get(['id', 'nombre', 'breezeway_home_id']);

        $this->info(count($propiedades) . ' propiedades con breezeway_home_id.');

        $creadas = 0;
        $actualizadas = 0;
        $omitidas = 0;
        $imputacionesCreadas = 0;
        $pendientesVistos = []; // breezeway_id => ['nombre'=>..., 'count'=>n]
        $errores = 0;

        foreach ($propiedades as $prop) {
            try {
                $tasks = $this->fetchAllTasks($token, $prop->breezeway_home_id, $rangoFecha);
            } catch (\Throwable $e) {
                $errores++;
                Log::error("BreezewaySyncTasks: error propiedad {$prop->id} ({$prop->nombre}): " . $e->getMessage());
                usleep(250000);
                continue;
            }

            foreach ($tasks as $task) {
                $dept = $task['type_department'] ?? null;
                if (!in_array($dept, ['housekeeping', 'maintenance'], true)) {
                    continue; // inspection / safety fuera de alcance
                }

                $tableName = $dept === 'housekeeping' ? 'vm_tareas_limpieza' : 'vm_tareas_mantenimiento';

                // Resolver asignados
                $controlUserIds = [];
                $sinMapear = [];
                foreach (($task['assignments'] ?? []) as $a) {
                    $aid = $a['assignee_id'] ?? null;
                    if (!$aid) continue;
                    if (isset($usuariosPorBreezeway[$aid])) {
                        $controlUserIds[] = (int) $usuariosPorBreezeway[$aid];
                    } else {
                        $nombreAsignado = trim($a['name'] ?? "Breezeway #{$aid}");
                        $sinMapear[] = $nombreAsignado;
                        if (!isset($pendientesVistos[$aid])) {
                            $pendientesVistos[$aid] = ['nombre' => $nombreAsignado, 'count' => 0];
                        }
                        $pendientesVistos[$aid]['count']++;
                    }
                }

                $estado = null;
                if ($tableName === 'vm_tareas_limpieza') {
                    $code = $task['type_task_status']['code'] ?? null;
                    $estado = match (true) {
                        $code === 'finished'                       => 'Completada',
                        in_array($code, ['cancelled', 'canceled'])  => 'Cancelada',
                        !empty($controlUserIds)                     => 'Planificada',
                        default                                     => 'Nueva',
                    };
                }

                $descripcion = trim((string) ($task['description'] ?? ''));
                if (!empty($sinMapear)) {
                    $nota = '[Breezeway] Sin asignar en Opland: ' . implode(', ', $sinMapear);
                    $descripcion = $descripcion !== '' ? ($descripcion . "\n\n" . $nota) : $nota;
                }

                $data = [
                    'nombre'             => $task['name'] ?? ('Tarea Breezeway #' . $task['id']),
                    'descripcion'        => $descripcion !== '' ? $descripcion : null,
                    'id_propiedades'     => $prop->id,
                    'fecha_planificada'  => $task['scheduled_date'] ?? null,
                    'fecha_finalizacion' => isset($task['finished_at']) ? substr($task['finished_at'], 0, 10) : null,
                    'control_user'       => json_encode($controlUserIds),
                    'breezeway_task_id'  => $task['id'],
                    'updatedat'          => now(),
                ];
                if ($estado !== null) {
                    $data['estado'] = $estado;
                }
                if ($tableName === 'vm_tareas_limpieza') {
                    $reserva = null;
                    $extId = $task['linked_reservation']['external_reservation_id'] ?? null;
                    if ($extId) {
                        $reserva = DB::table('vm_reservas')->where('booking_id', $extId)->value('id');
                    }
                    $data['id_reservas'] = $reserva;
                }

                $existente = DB::table($tableName)->where('breezeway_task_id', $task['id'])->first(['id']);
                if ($existente) {
                    DB::table($tableName)->where('id', $existente->id)->update($data);
                    $tareaId = $existente->id;
                    $actualizadas++;
                } else {
                    $data['deleted']    = 0;
                    $data['hidden']     = 0;
                    $data['blocked']    = 0;
                    $data['createuser'] = 1;
                    $data['createdat']  = now();
                    $tareaId = DB::table($tableName)->insertGetId($data);
                    $creadas++;
                }

                // Imputaciones: solo si Breezeway ya trae tiempo total registrado
                $duracionMin = !empty($task['total_time']) ? self::parseTotalTimeMinutes($task['total_time']) : 0;
                if ($duracionMin > 0 && !empty($controlUserIds)) {
                    $tipoImputacion = $dept === 'housekeeping' ? 'limpieza' : 'mantenimiento';
                    $fechaImp = isset($task['finished_at']) ? substr($task['finished_at'], 0, 10) : ($task['scheduled_date'] ?? now()->toDateString());
                    foreach ($controlUserIds as $uid) {
                        $yaExiste = DB::table('vm_imputaciones')
                            ->where('tipo', $tipoImputacion)
                            ->where('id_tarea', $tareaId)
                            ->where('id_usuario', $uid)
                            ->where('observacion', 'LIKE', '[Breezeway]%')
                            ->exists();
                        if ($yaExiste) continue;

                        DB::table('vm_imputaciones')->insert([
                            'tipo'             => $tipoImputacion,
                            'id_tarea'         => $tareaId,
                            'id_usuario'       => $uid,
                            'duracion'         => $duracionMin,
                            'fecha_imputacion' => $fechaImp,
                            'estado'           => 'finalizada',
                            'observacion'      => '[Breezeway] Importado automáticamente (tarea #' . $task['id'] . ')',
                            'createdat'        => now(),
                        ]);
                        $imputacionesCreadas++;
                    }
                }
            }

            usleep(250000); // margen para el rate limit de Breezeway
        }

        // Segunda pasada: tareas que quedaron sin control_user por asignados sin mapear en su momento —
        // si ya se han dado de alta en Opland, se resuelven ahora sin esperar a que Breezeway "toque" la tarea de nuevo.
        [$huerfanasResueltas, $impHuerfanas] = $this->resolverTareasHuerfanas($token, $usuariosPorBreezeway->all(), $pendientesVistos);
        $imputacionesCreadas += $impHuerfanas;

        // Mantener vm_breezeway_pendientes: upsert de lo visto, borrar lo ya mapeado
        foreach ($pendientesVistos as $breezewayId => $info) {
            $existe = DB::table('vm_breezeway_pendientes')->where('breezeway_id', $breezewayId)->first();
            if ($existe) {
                DB::table('vm_breezeway_pendientes')->where('id', $existe->id)->update([
                    'num_tareas'       => $info['count'],
                    'ultima_deteccion' => now(),
                    'updatedat'        => now(),
                ]);
            } else {
                DB::table('vm_breezeway_pendientes')->insert([
                    'nombre'           => $info['nombre'],
                    'breezeway_id'     => $breezewayId,
                    'fecha_alta'       => now()->toDateString(),
                    'num_tareas'       => $info['count'],
                    'ultima_deteccion' => now(),
                    'hidden'           => 0,
                    'deleted'          => 0,
                    'createuser'       => 1,
                    'createdat'        => now(),
                    'updatedat'        => now(),
                ]);
            }
        }
        // Los que ya no aparecen sin mapear (porque su breezeway id ya está en vm_usuarios.breezeway) se limpian
        DB::table('vm_breezeway_pendientes')
            ->whereIn('breezeway_id', $usuariosPorBreezeway->keys())
            ->delete();

        Cache::forever('breezeway_sync_result', [
            'fecha'       => now()->format('d/m/Y H:i'),
            'creadas'     => $creadas,
            'actualizadas'=> $actualizadas,
            'errores'     => $errores,
        ]);

        $this->info("Resultado: {$creadas} creadas, {$actualizadas} actualizadas, {$huerfanasResueltas} huérfanas resueltas, {$imputacionesCreadas} imputaciones, {$errores} errores de propiedad.");
        Log::info("BreezewaySyncTasks: {$creadas} creadas, {$actualizadas} actualizadas, {$huerfanasResueltas} huérfanas resueltas, {$imputacionesCreadas} imputaciones, {$errores} errores.");
    }

    // Tareas que quedaron con control_user vacío por asignados sin mapear en su momento (marcadas con la
    // nota en descripcion). Se re-consultan una a una a Breezeway por si ya se puede resolver el mapeo.
    private function resolverTareasHuerfanas(string $token, array $usuariosPorBreezeway, array &$pendientesAcumulados): array
    {
        $actualizadas = 0;
        $impCreadas = 0;
        $tablas = ['vm_tareas_limpieza' => 'limpieza', 'vm_tareas_mantenimiento' => 'mantenimiento'];

        foreach ($tablas as $tableName => $tipoImputacion) {
            $huerfanas = DB::table($tableName)
                ->whereNotNull('breezeway_task_id')
                ->whereNotNull('descripcion')
                ->where('descripcion', '!=', '')
                ->whereRaw("control_user::text = '[]'")
                ->get(['id', 'breezeway_task_id', 'descripcion']);

            foreach ($huerfanas as $t) {
                $task = null;
                try {
                    $task = $this->curlJson(
                        "https://api.breezeway.io/public/inventory/v1/task/{$t->breezeway_task_id}",
                        'GET',
                        ['Authorization: JWT ' . $token]
                    );
                } catch (\Throwable $e) {
                    Log::warning("BreezewaySyncTasks: error re-consultando tarea {$t->breezeway_task_id}: " . $e->getMessage());
                }
                usleep(250000);

                if (!$task || isset($task['error'])) continue;

                $controlUserIds = [];
                $sinMapear = [];
                foreach (($task['assignments'] ?? []) as $a) {
                    $aid = $a['assignee_id'] ?? null;
                    if (!$aid) continue;
                    if (isset($usuariosPorBreezeway[$aid])) {
                        $controlUserIds[] = (int) $usuariosPorBreezeway[$aid];
                    } else {
                        $nombreAsignado = trim($a['name'] ?? "Breezeway #{$aid}");
                        $sinMapear[] = $nombreAsignado;
                        if (!isset($pendientesAcumulados[$aid])) {
                            $pendientesAcumulados[$aid] = ['nombre' => $nombreAsignado, 'count' => 0];
                        }
                        $pendientesAcumulados[$aid]['count']++;
                    }
                }

                if (empty($controlUserIds)) continue; // sigue sin poder resolverse, se deja como está

                $original = $this->stripPendienteNote($t->descripcion);
                $descripcion = $original;
                if (!empty($sinMapear)) {
                    $nota = '[Breezeway] Sin asignar en Opland: ' . implode(', ', $sinMapear);
                    $descripcion = $descripcion !== '' ? ($descripcion . "\n\n" . $nota) : $nota;
                }

                DB::table($tableName)->where('id', $t->id)->update([
                    'control_user' => json_encode($controlUserIds),
                    'descripcion'  => $descripcion !== '' ? $descripcion : null,
                    'updatedat'    => now(),
                ]);
                $actualizadas++;

                $duracionMin = !empty($task['total_time']) ? self::parseTotalTimeMinutes($task['total_time']) : 0;
                if ($duracionMin > 0) {
                    $fechaImp = isset($task['finished_at']) ? substr($task['finished_at'], 0, 10) : ($task['scheduled_date'] ?? now()->toDateString());
                    foreach ($controlUserIds as $uid) {
                        $yaExiste = DB::table('vm_imputaciones')
                            ->where('tipo', $tipoImputacion)
                            ->where('id_tarea', $t->id)
                            ->where('id_usuario', $uid)
                            ->where('observacion', 'LIKE', '[Breezeway]%')
                            ->exists();
                        if ($yaExiste) continue;

                        DB::table('vm_imputaciones')->insert([
                            'tipo'             => $tipoImputacion,
                            'id_tarea'         => $t->id,
                            'id_usuario'       => $uid,
                            'duracion'         => $duracionMin,
                            'fecha_imputacion' => $fechaImp,
                            'estado'           => 'finalizada',
                            'observacion'      => '[Breezeway] Importado automáticamente (tarea #' . $task['id'] . ')',
                            'createdat'        => now(),
                        ]);
                        $impCreadas++;
                    }
                }
            }
        }

        return [$actualizadas, $impCreadas];
    }

    private function stripPendienteNote(?string $descripcion): string
    {
        if (!$descripcion) return '';
        $marker = "\n\n[Breezeway] Sin asignar en Opland: ";
        $pos = strpos($descripcion, $marker);
        if ($pos !== false) return substr($descripcion, 0, $pos);
        if (str_starts_with($descripcion, '[Breezeway] Sin asignar en Opland: ')) return '';
        return $descripcion;
    }

    // Breezeway devuelve total_time como string "H:MM:SS" (ej. "9:06:58"), no como numero de minutos
    private static function parseTotalTimeMinutes($raw): int
    {
        if ($raw === null || $raw === '') return 0;
        if (is_numeric($raw)) return (int) round((float) $raw);
        if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', trim((string) $raw), $m)) {
            [, $h, $i, $s] = $m;
            return (int) round(((int) $h * 3600 + (int) $i * 60 + (int) $s) / 60);
        }
        return 0;
    }

    private function fetchAllTasks(string $token, int $homeId, ?string $rangoFecha): array
    {
        $all = [];
        $page = 1;
        do {
            $params = ['home_id' => $homeId, 'limit' => 100, 'page' => $page];
            if ($rangoFecha) $params['updated_at'] = $rangoFecha;
            $url = 'https://api.breezeway.io/public/inventory/v1/task/?' . http_build_query($params);
            $resp = $this->curlJson($url, 'GET', ['Authorization: JWT ' . $token]);
            if (isset($resp['error'])) {
                throw new \RuntimeException($resp['error'] . ': ' . ($resp['description'] ?? ''));
            }
            $results = $resp['results'] ?? [];
            $all = array_merge($all, $results);
            $totalPages = $resp['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

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
