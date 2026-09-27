<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class VmNotificarTurno extends Command
{
    protected $signature   = 'vm:notificar-turno';
    protected $description = 'Envía notificación push a usuarios cuyo turno empieza en los próximos 2 minutos';

    public function handle(): void
    {
        $ahora     = now();
        $desde     = $ahora->copy()->format('H:i:s');
        $hasta     = $ahora->copy()->addMinutes(2)->format('H:i:s');
        $hoy       = $ahora->toDateString();

        $horarios = DB::table('vm_horarios')
            ->where('fecha', $hoy)
            ->where('tipo', 'turno')
            ->whereBetween('hora_inicio', [$desde, $hasta])
            ->get();

        if ($horarios->isEmpty()) return;

        // Sin claves configuradas, WebPush lanza un "[VAPID] You must provide a subject..." que
        // desde el planificador solo se ve como "exit code 1" y no dice nada. Mejor decirlo.
        $vapid = config('services.webpush');
        if (empty($vapid['subject']) || empty($vapid['public_key']) || empty($vapid['private_key'])) {
            $this->error('Faltan las claves VAPID (services.webpush): no se envía ninguna notificación.');
            return;
        }

        $auth = [
            'VAPID' => [
                'subject'    => $vapid['subject'],
                'publicKey'  => $vapid['public_key'],
                'privateKey' => $vapid['private_key'],
            ],
        ];

        $webPush = new WebPush($auth);

        foreach ($horarios as $horario) {
            $suscripciones = DB::table('vm_push_subscriptions')
                ->where('id_usuario', $horario->id_usuario)
                ->get();

            foreach ($suscripciones as $sub) {
                $subscription = Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'keys'            => [
                        'p256dh' => $sub->p256dh,
                        'auth'   => $sub->auth,
                    ],
                ]);

                $payload = json_encode([
                    'title' => 'Es hora de fichar',
                    'body'  => 'Tu turno empieza a las ' . substr($horario->hora_inicio, 0, 5),
                    'url'   => '/pwa/index.html#fichaje',
                ]);

                $webPush->queueNotification($subscription, $payload);
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                // Suscripción expirada o inválida — la eliminamos
                if (in_array($report->getResponse()?->getStatusCode(), [404, 410])) {
                    DB::table('vm_push_subscriptions')
                        ->where('endpoint', $report->getRequest()->getUri()->__toString())
                        ->delete();
                }
            }
        }
    }
}
