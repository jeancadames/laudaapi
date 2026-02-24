<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('dgii:tokens:refresh')
            ->everyMinute()              // luego lo cambiamos a hourly si quieres
            ->withoutOverlapping(10);
    }

    protected function commands(): void
    {
        // ✅ Carga todos los comandos en app/Console/Commands
        $this->load(app_path('Console/Commands'));

        require base_path('routes/console.php');
    }
}