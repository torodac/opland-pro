<?php

namespace App\Console\Commands;

use App\Services\SaludIntegraciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Trae a vm_tareas_comentarios los comentarios que la gente escribe en Breezeway sobre las
// tareas de limpieza y mantenimiento. Es información que hoy no llega a Opland y suele ser la
// única pista de lo que pasó de verdad: material consumido, piezas a pedir, por qué algo no se
// pudo hacer.
//
// Va en una pasada propia de las 22:00 y NO dentro de breezeway:sync-tasks a propósito. Los
// comentarios no vienen en el listado de tareas ni se pueden pedir con ?include=comments
// (comprobado: responde 200 pero sin esa clave), así que son una llamada por tarea. El sync de
// tareas toca ~150 tareas por pasada y corre 13 veces al día: meterlos ahí serían ~2.000
// llamadas diarias de más, y Breezeway ya devuelve 429 cuando se le aprieta. Una vez al día,
// solo sobre las tareas del día, son unas decenas.
class BreezewaySyncComentariosCommand extends Command
{
    protected $signature = 'breezeway:sync-comentarios
                            {--desde= : Tareas planificadas o finalizadas desde esta fecha (por defecto, hoy)}
                            {--hasta= : ...y hasta esta otra (por defecto, hoy)}';

    protected $description = 'Importa los comentarios de las tareas de limpieza y mantenimiento desde Breezeway';

    private const TABLAS = [
        'limpieza'      => 'vm_tareas_limpieza',
        'mantenimiento' => 'vm_tareas_mantenimiento',
    ];

    public function handle(): void
    {
        $desde = (string) ($this->option('desde') ?: now()->toDateString());
        $hasta = (string) ($this->option('hasta') ?: now()->toDateString());

        $token = $this->autenticar();
        if (!$token) {
            Log::error('breezeway:sync-comentarios: no se pudo autenticar contra Breezeway. '
                . 'Los comentarios de las tareas de hoy no se han importado.');
            $this->error('No se pudo autenticar contra Breezeway.');
            return;
        }

        $tareas = $this->tareasDelPeriodo($desde, $hasta);
        $this->info(count($tareas) . " tarea(s) con actividad entre {$desde} y {$hasta}.");

        $llamadas = 0;
        $utiles   = 0;
        $nuevos   = 0;
        $conAlguno = 0;

        foreach ($tareas as $t) {
            $llamadas++;
            $comentarios = $this->fetchComentarios($token, (int) $t->breezeway_task_id);

            if ($comentarios === null) {
                $this->warn("  [{$t->breezeway_task_id}] no se pudieron leer los comentarios.");
                continue;
            }
            $utiles++;
            if ($comentarios) $conAlguno++;

            foreach ($comentarios as $c) {
                $texto = self::limpiarMenciones(trim((string) ($c['comment'] ?? '')));
                $bzwId = $c['id'] ?? null;
                if ($texto === '' || !$bzwId) continue;

                // insertOrIgnore + el índice único por breezeway_comment_id: la pasada vuelve a
                // ver los comentarios de siempre y solo entran los nuevos, sin un SELECT por
                // comentario y sin que dos pasadas solapadas puedan duplicar.
                $insertado = DB::table('vm_tareas_comentarios')->insertOrIgnore([
                    'nombre'               => mb_substr($texto, 0, 120),
                    'tipo'                 => $t->tipo,
                    'id_tarea'             => (int) $t->id,
                    'breezeway_comment_id' => (int) $bzwId,
                    'comentario'           => $texto,
                    'fecha'                => $c['created_at'] ?? null,
                    'deleted'              => 0,
                    'hidden'               => 0,
                    'blocked'              => 0,
                    'createuser'           => 1,
                    'createdat'            => now(),
                    'updatedat'            => now(),
                ]);
                $nuevos += $insertado;
            }

            usleep(400000); // margen para el rate limit de Breezeway
        }

        $resumen = "{$llamadas} tareas consultadas, {$conAlguno} con comentarios, {$nuevos} comentario(s) nuevo(s)";
        $this->info("Completado — {$resumen}.");
        Log::info("BreezewaySyncComentarios: {$resumen}.");

        SaludIntegraciones::comprobar(
            'breezeway:sync-comentarios', $llamadas, $utiles,
            'Los comentarios que el equipo escribe en Breezeway dejan de llegar a la ficha de la tarea.'
        );
    }

    /**
     * Breezeway codifica las menciones dentro del texto como "{{463056,Saida Moussi}}", que es su
     * formato interno y no se puede leer. Se convierte a "@Saida Moussi": se pierde el id, que no
     * sirve para nada aquí, y se conserva a quién se menciona.
     *
     * Ojo: el mencionado NO es el autor -- la API no dice quién escribe cada comentario.
     */
    public static function limpiarMenciones(string $texto): string
    {
        $texto = preg_replace('/\{\{\s*\d+\s*,\s*([^}]+?)\s*\}\}/u', '@$1 ', $texto);

        return trim(preg_replace('/[ 	]{2,}/', ' ', (string) $texto));
    }

    /** Tareas de los dos tipos con actividad en el periodo, ya sea planificadas o finalizadas. */
    private function tareasDelPeriodo(string $desde, string $hasta): array
    {
        $todas = [];
        foreach (self::TABLAS as $tipo => $tabla) {
            $filas = DB::table($tabla)
                ->whereNotNull('breezeway_task_id')
                ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
                ->where(function ($q) use ($desde, $hasta) {
                    $q->whereBetween('fecha_planificada', [$desde, $hasta])
                      ->orWhereBetween('fecha_finalizacion', [$desde, $hasta]);
                })
                ->get(['id', 'breezeway_task_id']);

            foreach ($filas as $f) {
                $f->tipo = $tipo;
                $todas[] = $f;
            }
        }

        return $todas;
    }

    /**
     * GET /task/{id}/comments. Devuelve la lista, o null si la llamada falló -- que es distinto
     * de una lista vacía (tarea sin comentarios), y la diferencia es justo lo que mira
     * SaludIntegraciones para saber si el proceso está trayendo algo.
     *
     * La respuesta es un array JSON PLANO, sin el envoltorio results/total_pages que sí tiene el
     * listado de tareas: no se puede reutilizar el patrón paginado de BreezewaySyncTasks.
     */
    private function fetchComentarios(string $token, int $taskId): ?array
    {
        [$code, $json] = $this->curlJson(
            "https://api.breezeway.io/public/inventory/v1/task/{$taskId}/comments",
            ['Authorization: JWT ' . $token]
        );

        if ($code !== 200 || !is_array($json)) return null;

        return array_values(array_filter($json, 'is_array'));
    }

    /**
     * El endpoint de autenticación devuelve 429 cuando se le piden varios tokens seguidos, y
     * esta pasada convive con el sync de tareas de las 20:00. Se reintenta espaciado en vez de
     * darse por vencida al primer intento.
     */
    private function autenticar(): ?string
    {
        foreach ([0, 15, 45] as $espera) {
            if ($espera) sleep($espera);

            [$code, $json] = $this->curlJson('https://api.breezeway.io/public/auth/v1/', [], [
                'client_id'     => (string) config('services.breezeway.client_id'),
                'client_secret' => (string) config('services.breezeway.client_secret'),
            ]);

            if ($code === 200 && !empty($json['access_token'])) {
                return (string) $json['access_token'];
            }
            $this->warn("  autenticación: HTTP {$code}, se reintenta.");
        }

        return null;
    }

    /** @return array{0:int, 1:?array} [código HTTP, json decodificado] */
    private function curlJson(string $url, array $headers = [], ?array $body = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$code, json_decode((string) $resp, true)];
    }
}
