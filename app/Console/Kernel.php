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
        // Resumen de la mañana por notificación push (requiere el cron de "schedule:run" en el servidor)
        $schedule->command('vandu:push-resumen')
            ->dailyAt(config('vandu.push.resumen_hora', '08:50'))
            ->timezone(config('vandu.zona_horaria'))
            ->withoutOverlapping();

        // Latido: deja constancia de que el cron corre, para mostrarlo en Notificaciones
        $schedule->call(fn () => \Illuminate\Support\Facades\Cache::forever('vandu.cron.latido', now()->timestamp))
            ->everyMinute()->name('vandu-latido');
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
