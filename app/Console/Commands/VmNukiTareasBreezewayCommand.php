<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Da de alta en Breezeway una tarea de cambio de batería para cada cerradura Nuki con la batería
// baja (mismo umbral que el aviso y la tarjeta roja). Se ejecuta al final de la importación de Nuki.
//
// Reglas:
// - Solo cerraduras con propiedad activa y con breezeway_home_id.
// - No se crea si la propiedad ya tiene una tarea de batería abierta (sincronizada).
// - Asignación según la ocupación de la propiedad:
//   · Ocupada (reserva confirmada o con huésped dentro): tarea para la coordinadora de limpieza,
//     planificada hoy, con fecha límite el checkout.
//   · No ocupada, o con checkout hoy: el próximo evento es el checkin. Si es dentro de 7 días,
//     tarea para el coordinador de recepción, con fecha límite el checkin.
//   · Sin checkin en la próxima semana (o sin reservas): tarea para el coordinador de
//     mantenimiento, planificada y con fecha límite a 2 días.
// - Breezeway solo tiene fecha de planificación (scheduled_date): en ella va la fecha límite.
//   La descripción recoge también la fecha límite y el motivo de la asignación.
// - Prioridad alta, título "[O] Cambiar batería Nuki".
//
// Con --simular no escribe nada en Breezeway: solo muestra qué crearía.
class VmNukiTareasBreezewayCommand extends Command
{
    protected $signature   = 'vm:nuki-tareas-breezeway {--simular : Muestra lo que crearía sin llamar a Breezeway para crear}';
    protected $description = 'Crea en Breezeway una tarea de cambio de batería para cada cerradura Nuki con la batería baja';

    private const ROL_COORDINADOR_LIMPIEZA = 2;
    private const ROL_COORDINADOR_MANTENIMIENTO = 5;
    private const ROL_COORDINADOR_RECEPCION = 14;
    private const TITULO = '[O] Cambiar batería Nuki';
    private const ESTADOS_ABIERTOS = ['Nueva', 'Planificada', 'Vencida'];
    private const ESTADOS_OCUPACION = ['confirmed', 'arrived'];
    private const DIAS_CHECKIN_CERCANO = 7;

    public function handle(): int
    {
        $umbral = VmSyncNukiCommand::umbral();
        $hoy = now()->startOfDay();

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
            if ($this->tieneTareaAbierta($c->id_propiedades)) {
                $omitidas++;
                continue;
            }

            $decision = $this->decidir((int) $c->id_propiedades, $hoy);
            if ($decision === null) {
                $this->error("Sin persona activa con id de Breezeway para {$c->nombre}.");
                Log::error("vm:nuki-tareas-breezeway: sin persona asignable para la propiedad {$c->id_propiedades}.");
                $fallos++;
                continue;
            }

            $linea = "{$c->nombre} ({$c->bateria} %): {$decision['asignado']->nombre}, planificada {$decision['planificada']->format('d/m')}"
                . ($decision['limite'] ? ", límite {$decision['limite']->format('d/m')}" : '');

            if ($this->option('simular')) {
                $this->line("[simular] {$linea}. {$decision['motivo']}");
                $creadas++;
                continue;
            }

            $id = $this->crearEnBreezeway($c->breezeway_home_id, $decision);
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
            $this->line("Creada tarea {$id}: {$linea}.");

            // Breezeway limita las peticiones seguidas (429): una pausa corta entre creaciones.
            usleep(1500000);
        }

        $resumen = ($this->option('simular') ? 'Simulación: ' : '') . "{$creadas} tareas, {$omitidas} omitidas, {$fallos} fallos.";
        $this->info($resumen);
        Log::info("vm:nuki-tareas-breezeway: {$resumen}");

        return $fallos > 0 && $creadas === 0 ? self::FAILURE : self::SUCCESS;
    }

    // Decide a quién se asigna la tarea, según la ocupación de la propiedad y su próximo evento.
    // Devuelve null si no hay nadie activo con id de Breezeway para el rol que toca.
    private function decidir(int $idPropiedades, Carbon $hoy): ?array
    {
        $ocupada = DB::table('vm_reservas')
            ->where('id_propiedades', $idPropiedades)
            ->where('deleted', 0)
            ->whereIn('booking_status', self::ESTADOS_OCUPACION)
            ->whereDate('check_in_date', '<=', $hoy)
            ->whereDate('check_out_date', '>', $hoy)
            ->orderByDesc('check_in_date')
            ->first(['check_out_date']);

        if ($ocupada) {
            $checkout = Carbon::parse($ocupada->check_out_date);
            $asignado = $this->persona(self::ROL_COORDINADOR_LIMPIEZA);
            return $asignado === null ? null : [
                'asignado'    => $asignado,
                'planificada' => $checkout,
                'limite'      => $checkout,
                'motivo'      => "Ocupada hasta el checkout del {$checkout->format('d/m/Y')}; el siguiente evento es la limpieza de salida, así que va a limpieza.",
            ];
        }

        $checkin = DB::table('vm_reservas')
            ->where('id_propiedades', $idPropiedades)
            ->where('deleted', 0)
            ->where('booking_status', 'confirmed')
            ->whereDate('check_in_date', '>=', $hoy)
            ->orderBy('check_in_date')
            ->first(['check_in_date']);

        if ($checkin) {
            $fechaCheckin = Carbon::parse($checkin->check_in_date);
            $dias = $hoy->diffInDays($fechaCheckin, false);

            if ($dias <= self::DIAS_CHECKIN_CERCANO) {
                $asignado = $this->persona(self::ROL_COORDINADOR_RECEPCION);
                return $asignado === null ? null : [
                    'asignado'    => $asignado,
                    'planificada' => $fechaCheckin,
                    'limite'      => $fechaCheckin,
                    'motivo'      => "No ocupada; el siguiente evento es el checkin del {$fechaCheckin->format('d/m/Y')}, dentro de la semana, así que va a recepción.",
                ];
            }
        }

        // Sin checkin en la próxima semana: mantenimiento, con el plazo habitual de 2 días.
        $asignado = $this->persona(self::ROL_COORDINADOR_MANTENIMIENTO);
        $limite = $hoy->copy()->addDays(2);
        return $asignado === null ? null : [
            'asignado'    => $asignado,
            'planificada' => $limite,
            'limite'      => $limite,
            'motivo'      => 'No ocupada y sin checkin en la próxima semana, así que va a mantenimiento.',
        ];
    }

    // La persona activa de ese rol con menor id y con id de Breezeway.
    private function persona(int $rol): ?object
    {
        return DB::table('vm_usuarios')
            ->where('id_rol', $rol)
            ->where('deleted', 0)->where('blocked', 0)->whereNull('fecha_baja')
            ->whereNotNull('breezeway')
            ->orderBy('id')
            ->first(['id', 'nombre', 'breezeway']);
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

    // Devuelve el id de la tarea creada, o null si Breezeway no la ha aceptado.
    private function crearEnBreezeway(int $homeId, array $decision): ?int
    {
        $token = $this->token();
        if ($token === null) {
            return null;
        }

        $limite = $decision['limite'] ? $decision['limite']->format('d/m/Y') : 'sin fecha';

        [$code, $tarea] = $this->post('https://api.breezeway.io/public/inventory/v1/task', [
            'home_id'          => $homeId,
            'name'             => self::TITULO,
            'description'      => "Creada desde Opland. Fecha límite: {$limite}. Asignación: {$decision['motivo']}",
            'type_department'  => 'maintenance',
            'type_priority'    => 'high',
            'scheduled_date'   => $decision['planificada']->toDateString(),
            'assignments'      => [(int) $decision['asignado']->breezeway],
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
