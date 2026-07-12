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

        if ($horasSemana && $horasSemana > 0) {
            [$h, $m] = array_map('intval', explode(':', substr($fichaje->hora_inicio, 0, 5)));
            $totalMinutos = $h * 60 + $m + (int) round(($horasSemana / 5) * 60);
            $horaFinMinutos = $totalMinutos % (24 * 60); // normalizar si supera medianoche
        } else {
            $horaFinMinutos = 23 * 60 + 59;
        }

        // Si la hora calculada cae entre 00:00 y 07:59, significa cambio de día → tope 23:59
        if ($horaFinMinutos < 8 * 60) {
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
