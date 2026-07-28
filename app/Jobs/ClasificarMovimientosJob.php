<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class ClasificarMovimientosJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 3600;
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'movimientos-clasificar-rodcar';
    }

    public function handle(): void
    {
        Artisan::call('movimientos:clasificar');
    }
}
