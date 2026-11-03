<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Business Analytics: refresh current + previous month daily.
        $schedule->command('analytics:sync --months=2')->dailyAt('02:15')->withoutOverlapping();
        $schedule->command('analytics:sync-daily --days=14')->dailyAt('02:45')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
