<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Monitor overdue exeats every hour (debts created on return)
        $schedule->command('exeat:check-overdue')
            ->hourly();

        // Automatically expire overdue exeat requests every hour
        $schedule->command('exeat:expire-overdue')
            ->hourly()
            ->withoutOverlapping();

        // Process queued emails/notifications every minute
        $schedule->command('queue:work --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();
    }


    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
