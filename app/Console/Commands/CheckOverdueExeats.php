<?php

namespace App\Console\Commands;

use App\Models\ExeatRequest;
use App\Models\StudentExeatDebt;
use App\Models\Student;
use App\Services\ExeatNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueExeats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exeat:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue exeat requests (monitoring only - debts created on return)';
    
    /**
     * The exeat notification service.
     *
     * @var \App\Services\ExeatNotificationService
     */
    protected $notificationService;
    
    /**
     * Create a new command instance.
     *
     * @param \App\Services\ExeatNotificationService $notificationService
     * @return void
     */
    public function __construct(ExeatNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue exeat requests...');
        
        // Get all exeat requests that have passed their return date
        // and student hasn't completed the return process (not completed, rejected, or cancelled)
        $overdueExeats = ExeatRequest::where('return_date', '<', Carbon::now()->toDateString())
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->where('is_expired', false)
            ->get();
            
        $this->info("Found {$overdueExeats->count()} overdue exeat requests.");
        
        $baseDebtAmount = 10000; // 10,000 Naira base fee for every 24 hours
        
        foreach ($overdueExeats as $exeat) {
            $returnDate = Carbon::parse($exeat->return_date);
            $now = Carbon::now();
            
            // Calculate hours overdue for monitoring
            $hoursOverdue = $returnDate->diffInHours($now);
            $daysOverdue = ceil($hoursOverdue / 24);
            $potentialDebtAmount = $daysOverdue * $baseDebtAmount;
            
            // Only log overdue students for monitoring (no debt creation)
            $this->info("Overdue student monitoring - Exeat #{$exeat->id}, Student #{$exeat->student_id}: {$hoursOverdue} hours overdue (Potential debt: ₦{$potentialDebtAmount})");
            
            // Log for admin monitoring
            Log::info('Overdue student detected', [
                'exeat_id' => $exeat->id,
                'student_id' => $exeat->student_id,
                'hours_overdue' => $hoursOverdue,
                'potential_debt' => $potentialDebtAmount,
                'status' => $exeat->status
            ]);
        }
        
        $this->info('Overdue exeat check completed.');
        
        return 0;
    }
}