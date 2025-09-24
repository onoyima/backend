<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        try {
            App::make(BirthdayController::class)->sendBirthdayEmails();
        } catch (\Exception $e) {
            Log::error('Birthday email sending failed: ' . $e->getMessage());
        }
    })->timezone('Africa/Lagos')->dailyAt('07:00');
    
    // Monitor overdue exeats every hour (debts created on return)
    $schedule->command('exeat:check-overdue')
        ->timezone('Africa/Lagos')
        ->hourly();
}


    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
        
        // Register the CheckOverdueExeats command
        $this->commands([
            \App\Console\Commands\CheckOverdueExeats::class,
        ]);
    }
}
