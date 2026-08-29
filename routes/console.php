<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vm:notificar-turno')->everyMinute()->withoutOverlapping();

// Detecta pantallas con url propia pero sin fila en admin_project_tables -- estas quedan
// invisibles en /config/projects/{slug} aunque funcionen por URL directa (ver
// AdminAuditOrphanScreensCommand). Se registra en el log semanalmente para detectar la
// regresión sin depender de que alguien se acuerde de ejecutarlo a mano.
Schedule::command('admin:audit-orphan-screens')->weeklyOn(1, '07:00')->withoutOverlapping();

// Tarea mensual "Validar informes mensuales RRHH" -- el propio comando decide si hoy es el
// primer día laborable del mes, así que puede correr a diario sin duplicar nada.
Schedule::command('vm:generar-tarea-informes-rrhh')->dailyAt('06:30')->withoutOverlapping();

// Cruza vm_propiedades con las propiedades de Breezeway (rellena breezeway_home_id donde falte,
// mantiene vm_breezeway_propiedades_pendientes con lo que no tiene match) -- a las 07:15, después
// de icnea:sync-pro (07:00, cron de sistema) para que las propiedades dadas de alta ese mismo día
// ya puedan cruzarse sin esperar a la ejecución siguiente.
Schedule::command('breezeway:sync-properties')->dailyAt('07:15')->withoutOverlapping();

// Cierra automáticamente los fichajes del día anterior que siguen abiertos
Schedule::call(function () {
    // Se ejecuta a las 08:00 — procesa fichajes del día anterior
    $ayer = now()->subDay()->toDateString();

    $abiertos = DB::table('vm_fichaje')
        ->where('fecha_fichaje', $ayer)
        ->where('deleted', 0)
        ->whereNotNull('hora_inicio')
        ->whereNull('hora_fin')
        ->get(['id', 'control_user', 'hora_inicio']);

    if ($abiertos->isEmpty()) return;

    $auth = [
        'VAPID' => [
            'subject'    => env('VAPID_SUBJECT'),
            'publicKey'  => env('VAPID_PUBLIC_KEY'),
            'privateKey' => env('VAPID_PRIVATE_KEY'),
        ],
    ];
    $webPush = new WebPush($auth);

    foreach ($abiertos as $fichaje) {
        // hora_fin = hora_inicio + horas diarias del contrato (horas_semana / 5)
        $horasSemana = DB::table('vm_contratos')
            ->where('id_usuarios', $fichaje->control_user)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->value('horas_semana');

        [$h, $m] = array_map('intval', explode(':', substr($fichaje->hora_inicio, 0, 5)));
        $horaInicioMinutos = $h * 60 + $m;

        if ($horasSemana && $horasSemana > 0) {
            $totalMinutos = $horaInicioMinutos + (int) round(($horasSemana / 5) * 60);
            $horaFinMinutos = $totalMinutos % (24 * 60); // normalizar si supera medianoche
        } else {
            $horaFinMinutos = 23 * 60 + 59;
        }

        // El módulo de arriba solo "envuelve" (da un resultado menor que la hora de inicio) si
        // la jornada calculada de verdad cruzó la medianoche -- ese es el único caso real de
        // cambio de día, y ahí sí se aplica el tope 23:59. Antes se usaba un umbral fijo (<8:00)
        // que daba un falso positivo con contratos de pocas horas/día cuya jornada, sin cruzar
        // medianoche, terminaba igualmente antes de las 8:00 (p.ej. entrada 07:07 + 36 min/día).
        if ($horaFinMinutos < $horaInicioMinutos) {
            $horaFinMinutos = 23 * 60 + 59;
        }

        $horaFin = sprintf('%02d:%02d:00', intdiv($horaFinMinutos, 60), $horaFinMinutos % 60);

        DB::table('vm_fichaje')
            ->where('id', $fichaje->id)
            ->update(['hora_fin' => $horaFin, 'hora_fin_auto' => $horaFin, 'updatedat' => now()]);

        // Notificación push
        foreach (DB::table('vm_push_subscriptions')->where('id_usuario', $fichaje->control_user)->get() as $sub) {
            $webPush->queueNotification(
                Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth]]),
                json_encode([
                    'title' => 'Fichaje cerrado automáticamente',
                    'body'  => 'Tu jornada de ayer ha sido cerrada automáticamente. Por favor, revisa si es correcta.',
                    'url'   => '/pwa/index.html#fichaje',
                ])
            );
        }
    }

    // Cerrar pausas abiertas del día anterior
    $pausasAbiertas = DB::table('vm_fichaje')
        ->where('fecha_fichaje', $ayer)
        ->where('deleted', 0)
        ->whereNotNull('pausa_inicio')
        ->whereNull('pausa_fin')
        ->get(['id', 'control_user', 'pausa_inicio']);

    foreach ($pausasAbiertas as $fichaje) {
        $horasSemana = DB::table('vm_contratos')
            ->where('id_usuarios', $fichaje->control_user)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->value('horas_semana');

        // >= 8h diarias (40h/semana) → 30 min de pausa; menos → 15 min
        $minutosPausa = ($horasSemana && ($horasSemana / 5) >= 8) ? 30 : 15;

        [$h, $m] = array_map('intval', explode(':', substr($fichaje->pausa_inicio, 0, 5)));
        $totalMin = $h * 60 + $m + $minutosPausa;
        if ($totalMin >= 24 * 60) $totalMin = 23 * 60 + 59;
        $pausaFin = sprintf('%02d:%02d:00', intdiv($totalMin, 60), $totalMin % 60);

        DB::table('vm_fichaje')
            ->where('id', $fichaje->id)
            ->update(['pausa_fin' => $pausaFin, 'pausa_fin_auto' => $pausaFin, 'updatedat' => now()]);
    }

    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess() && in_array($report->getResponse()?->getStatusCode(), [404, 410])) {
            DB::table('vm_push_subscriptions')
                ->where('endpoint', $report->getRequest()->getUri()->__toString())
                ->delete();
        }
    }
})->dailyAt('08:00')->name('vm:cierre-fichajes')->withoutOverlapping();

// Sincroniza vm_reservas_importes (incluida "Comisión canal") para reservas con
// checkout reciente -- antes no se ejecutaba nunca de forma automática.
Schedule::command('icnea:sync-importes', ['--meses' => 6])
    ->dailyAt('06:00')
    ->withoutOverlapping();
