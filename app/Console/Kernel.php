<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

// AVISO (2026-08-18): este archivo no tiene ningun efecto real en esta app. La programacion
// de tareas (schedule) vive en routes/console.php, via Schedule::command()/Schedule::call() --
// confirmado con `php artisan schedule:list` y con `app(Schedule::class)->events()`, que solo
// devuelven lo definido alli, nunca lo de abajo. No se ha confirmado del todo por que este
// Kernel no llega a ejecutarse (probablemente depende de como bootstrap/app.php inicializa
// la consola en esta version de Laravel), asi que en vez de borrarlo se deja todo comentado.
// Si pasadas unas semanas se confirma que de verdad no hace falta nada de esto, eliminar el
// archivo entero (o su contenido) en vez de mantenerlo comentado indefinidamente.
class Kernel extends ConsoleKernel
{
    // protected $commands = [
    //     Commands\IcneaSyncProCommand::class,
    //     Commands\VmNotificarTurno::class,
    //     Commands\BreezewaySyncTasksCommand::class,
    // ];

    // protected function schedule(Schedule $schedule): void
    // {
    //     $schedule->command('vm:notificar-turno')->everyMinute()->withoutOverlapping();
    // }

    // protected function bootstrappers(): array
    // {
    //     return parent::bootstrappers();
    // }
}
