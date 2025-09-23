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
    protected $description = 'Check for overdue exeat requests and calculate debt fees';
    
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
        
        // Get all approved exeat requests that have passed their return date
        // and don't have a security sign-in record
        $overdueExeats = ExeatRequest::where('status', 'approved')
            ->where('return_date', '<', Carbon::now()->toDateString())
            ->whereDoesntHave('securitySignouts', function ($query) {
                $query->where('type', 'sign_in');
            })
            ->get();
            
        $this->info("Found {$overdueExeats->count()} overdue exeat requests.");
        
        $baseDebtAmount = 10000; // 10,000 Naira base fee for every 24 hours
        
        foreach ($overdueExeats as $exeat) {
            $returnDate = Carbon::parse($exeat->return_date);
            $now = Carbon::now();
            
            // Calculate hours overdue
            $hoursOverdue = $returnDate->diffInHours($now);
            
            // Calculate debt amount - only using daily charges
            // If less than 24 hours, count as 1 day
            $daysOverdue = ceil($hoursOverdue / 24); // Using ceil to count partial days as full days
            $totalDebtAmount = $daysOverdue * $baseDebtAmount;
            
            // Check if a debt record already exists for this exeat
            $existingDebt = StudentExeatDebt::where('exeat_request_id', $exeat->id)
                ->where('payment_status', '!=', 'cleared')
                ->first();
            
            $isNewDebt = false;
                
            if ($existingDebt) {
                // Update existing debt record
                $existingDebt->amount = $totalDebtAmount;
                $existingDebt->overdue_hours = $hoursOverdue;
                $existingDebt->save();
                
                $this->info("Updated debt for exeat #{$exeat->id}, student #{$exeat->student_id}: {$totalDebtAmount} Naira ({$hoursOverdue} hours overdue)");
            } else {
                // Create new debt record
                StudentExeatDebt::create([
                    'student_id' => $exeat->student_id,
                    'exeat_request_id' => $exeat->id,
                    'amount' => $totalDebtAmount,
                    'overdue_hours' => $hoursOverdue,
                    'payment_status' => 'unpaid',
                ]);
                
                $isNewDebt = true;
                $this->info("Created new debt for exeat #{$exeat->id}, student #{$exeat->student_id}: {$totalDebtAmount} Naira ({$hoursOverdue} hours overdue)");
            }
            
            // Send notification to student if this is a new debt
            if ($isNewDebt) {
                try {
                    $student = Student::find($exeat->student_id);
                    if ($student) {
                        $this->notificationService->sendDebtNotification($student, $exeat, $totalDebtAmount);
                        $this->info("Sent debt notification to student #{$exeat->student_id}");
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send debt notification', [
                        'student_id' => $exeat->student_id,
                        'exeat_id' => $exeat->id,
                        'error' => $e->getMessage()
                    ]);
                    $this->error("Failed to send debt notification: {$e->getMessage()}");
                }
            }
        }
        
        $this->info('Overdue exeat check completed.');
        
        return 0;
    }
}