<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Hanya di PC server lokal (DB_SYNC_ENABLED=true): sinkron dua arah dengan database hosting.
        if (config('dbsync.enabled')) {
            $schedule->command('dbsync:run')
                ->cron('*/'.max(1, (int) config('dbsync.interval', 2)).' * * * *')
                ->withoutOverlapping(30)
                ->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}