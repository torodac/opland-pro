<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Da de alta en Breezeway una tarea de cambio de batería para cada cerradura Nuki con la batería
// baja (mismo umbral que el aviso y la tarjeta roja). Se ejecuta al final de la importación de Nuki.
//
// Reglas:
// - Solo cerraduras con propiedad activa y con breezeway_home_id.
// - No se crea si la propiedad ya tiene una tarea de batería abierta (sincronizada) ni si se creó
//   una en los últimos 7 días (la sincronización tarda en traerla de vuelta).
// - Asignada al coordinador de mantenimiento activo de menor id.
// - Fecha límite a 2 días, prioridad alta, título "[O] Cambiar batería Nuki".
//
// Con --simular no escribe nada en Breezeway: solo muestra qué crearía.
class VmNukiTareasBreezewayCommand extends Command
{
    protected $signature   = 'vm:nuki-tareas-breezeway {--simular : Muestra lo que crearía sin llamar a Breezeway para crear}';
    protected $description = 'Crea en Breezeway una tarea de cambio de batería para cada cerradura Nuki con la batería baja';

    private const ROL_COORDINADOR_MANTENIMIENTO = 5;
    private const TITULO = '[O] Cambiar batería Nuki';
    private const ESTADOS_ABIERTOS = ['Nueva', 'Planificada', 'Vencida'];

    public function handle(): int
    {
        $umbral = VmSyncNukiCommand::umbral();

        $asignado = DB::table('vm_usuarios')
            ->where('id_rol', self::ROL_COORDINADOR_MANTENIMIENTO)
            ->where('deleted', 0)->where('blocked', 0)->whereNull('fecha_baja')
            ->whereNotNull('breezeway')
            ->orderBy('id')
            ->first(['id', 'nombre', 'breezeway']);

        if (!$asignado) {
            $this->error('No hay ningún coordinador de mantenimiento activo con id de Breezeway.');
            Log::error('vm:nuki-tareas-breezeway: no hay coordinador de mantenimiento activo con id de Breezeway.');
            return self::FAILURE;
        }

        // Las cerraduras con batería baja que tienen propiedad activa con id de Breezeway.
        $cerraduras = DB::table('vm_cerraduras as c')
            ->join('vm_propiedades as p', 'p.id', '=', 'c.id_propiedades')
            ->whereNotNull('c.bateria')
            ->where('c.bateria', '<=', $umbral)
            ->where(fn($q) => $q->where('p.deleted', 0)->orWhereNull('p.deleted'))
            ->whereNotNull('p.breezeway_home_id')
            ->orderBy('p.nombre')
            ->get(['c.nuki_smartlock_id', 'c.id_propiedades', 'c.bateria', 'p.nombre', 'p.breezeway_home_id']);

        $creadas = 0;
        $omitidas = 0;
        $fallos = 0;

        foreach ($cerraduras as $c) {
            if ($this->tieneTareaAbierta($c->id_propiedades) || $this->creadaRecientemente($c->id_propiedades)) {
                $omitidas++;
                continue;
            }

            if ($this->option('simular')) {
                $this->line("[simular] {$c->nombre} ({$c->bateria} %): crearía la tarea para {$asignado->nombre}.");
                $creadas++;
                continue;
            }

            $id = $this->crearEnBreezeway($c->breezeway_home_id, $asignado->breezeway);
            if ($id === null) {
                $fallos++;
                continue;
            }

            DB::table('vm_nuki_tareas_breezeway')->insert([
                'nuki_smartlock_id' => $c->nuki_smartlock_id,
                'id_propiedades'    => $c->id_propiedades,
                'breezeway_task_id' => $id,
                'creada_at'         => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $creadas++;
            $this->line("Creada tarea {$id} para {$c->nombre} ({$c->bateria} %).");

            // Breezeway limita las peticiones seguidas (429): una pausa corta entre creaciones.
            usleep(1500000);
        }

        $resumen = ($this->option('simular') ? 'Simulación: ' : '') . "{$creadas} tareas, {$omitidas} omitidas, {$fallos} fallos.";
        $this->info($resumen);
        Log::info("vm:nuki-tareas-breezeway: {$resumen}");

        return $fallos > 0 && $creadas === 0 ? self::FAILURE : self::SUCCESS;
    }

    // Hay una tarea de batería de Nuki abierta y ya sincronizada en la propiedad.
    private function tieneTareaAbierta(int $idPropiedades): bool
    {
        return DB::table('vm_tareas_mantenimiento')
            ->where('id_propiedades', $idPropiedades)
            ->whereIn('estado', self::ESTADOS_ABIERTOS)
            ->whereRaw("nombre ~* 'nuki'")
            ->whereRaw("nombre ~* '(bater|pila)'")
            ->exists();
    }

    // Ya pedimos una en los últimos 7 días, aunque todavía no haya vuelto a Opland.
    private function creadaRecientemente(int $idPropiedades): bool
    {
        return DB::table('vm_nuki_tareas_breezeway')
            ->where('id_propiedades', $idPropiedades)
            ->where('creada_at', '>=', now()->subDays(7))
            ->exists();
    }

    // Devuelve el id de la tarea creada, o null si Breezeway no la ha aceptado.
    private function crearEnBreezeway(int $homeId, int $asignadoBreezeway): ?int
    {
        $token = $this->token();
        if ($token === null) {
            return null;
        }

        [$code, $tarea] = $this->post('https://api.breezeway.io/public/inventory/v1/task', [
            'home_id'          => $homeId,
            'name'             => self::TITULO,
            'description'      => 'Creada desde Opland',
            'type_department'  => 'maintenance',
            'type_priority'    => 'high',
            'scheduled_date'   => now()->addDays(2)->toDateString(),
            'assignments'      => [$asignadoBreezeway],
        ], $token);

        if ($code !== 201 || empty($tarea['id'])) {
            $this->error("Breezeway ha respondido HTTP {$code} para la propiedad de home {$homeId}.");
            Log::error("vm:nuki-tareas-breezeway: HTTP {$code} creando tarea para home {$homeId}.");
            return null;
        }

        return (int) $tarea['id'];
    }

    private function token(): ?string
    {
        [$code, $auth] = $this->post('https://api.breezeway.io/public/auth/v1/', [
            'client_id'     => (string) config('services.breezeway.client_id'),
            'client_secret' => (string) config('services.breezeway.client_secret'),
        ]);

        if ($code !== 200 || empty($auth['access_token'])) {
            $this->error("Breezeway no ha autenticado (HTTP {$code}).");
            Log::error("vm:nuki-tareas-breezeway: no se pudo autenticar en Breezeway (HTTP {$code}).");
            return null;
        }

        return $auth['access_token'];
    }

    private function post(string $url, array $data, ?string $token = null): array
    {
        $cabeceras = ['Content-Type: application/json'];
        if ($token !== null) {
            $cabeceras[] = 'Authorization: JWT ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$code, json_decode((string) $body, true) ?: []];
    }
}
