<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // envia recordatorios de pago cada dia a las 8:00 AM hora Mexico
        $schedule->command('recordatorios:pago')->dailyAt('08:00');

        // actualiza estados de creditos segun fechas y pagos
        // se corre cada hora para que el cambio no tarde todo el dia en reflejarse
        $schedule->command('creditos:actualizar-estados')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
