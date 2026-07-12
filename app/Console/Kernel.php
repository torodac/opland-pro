<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\IcneaSyncProCommand::class,
        Commands\VmNotificarTurno::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('vm:notificar-turno')->everyMinute()->withoutOverlapping();
    }

    protected function bootstrappers(): array
    {
        return parent::bootstrappers();
    }
}
