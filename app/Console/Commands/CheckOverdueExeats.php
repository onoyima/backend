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
    protected $description = 'Check for overdue exeat requests from students who have left campus and automatically complete them to allow new applications';

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
        // and student has left campus (passed security signout) but hasn't completed the return process
        // Only consider exeats overdue if student has actually left campus
        $overdueExeats = ExeatRequest::where('return_date', '<', Carbon::now()->toDateString())
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->where('is_expired', false)
            ->whereIn('status', ['security_signin', 'hostel_signin']) // Only students who have left campus
            ->get();

        $this->info("Found {$overdueExeats->count()} overdue exeat requests from students who have left campus.");

        $baseDebtAmount = 10000; // 10,000 Naira base fee for every 24 hours
        $completedCount = 0;

        foreach ($overdueExeats as $exeat) {
            $returnDate = Carbon::parse($exeat->return_date);
            $now = Carbon::now();

            // Calculate days overdue using exact 24-hour periods at 11:59 PM
            $daysOverdue = $this->calculateDaysOverdue($returnDate, $now);
            $potentialDebtAmount = $daysOverdue * $baseDebtAmount;

            $this->info("Processing overdue exeat - ID: #{$exeat->id}, Student: #{$exeat->student_id}, Days overdue: {$daysOverdue}, Potential debt: ₦{$potentialDebtAmount}");

            // Auto-complete overdue exeats to allow students to apply for new ones
            $originalStatus = $exeat->status;
            $exeat->update([
                'status' => 'completed',
                'is_expired' => true,
                'expired_at' => now()
            ]);

            $completedCount++;

            // Log the automatic completion
            Log::info('Overdue exeat automatically completed', [
                'exeat_id' => $exeat->id,
                'student_id' => $exeat->student_id,
                'original_status' => $originalStatus,
                'days_overdue' => $daysOverdue,
                'potential_debt' => $potentialDebtAmount,
                'completed_at' => now()->toDateTimeString()
            ]);

            $this->line("  ✓ Completed overdue exeat #{$exeat->id} - Student can now apply for new exeats");
        }

        $this->info("Overdue exeat check completed. {$completedCount} overdue exeats were automatically completed.");

        return 0;
    }

    /**
     * Calculate days overdue using exact 24-hour periods at 11:59 PM
     * 
     * @param \Carbon\Carbon $returnDate
     * @param \Carbon\Carbon $currentTime
     * @return int
     */
    private function calculateDaysOverdue(\Carbon\Carbon $returnDate, \Carbon\Carbon $currentTime): int
    {
        // Set return date to 11:59 PM of the expected return date
        $returnDateEnd = $returnDate->copy()->setTime(23, 59, 59);

        // If current time is before or at 11:59 PM of return date, no debt
        if ($currentTime->lte($returnDateEnd)) {
            return 0;
        }

        // Calculate full 24-hour periods after 11:59 PM of return date
        $daysPassed = 0;
        $currentCheckDate = $returnDateEnd->copy();

        while ($currentCheckDate->lt($currentTime)) {
            $currentCheckDate->addDay()->setTime(23, 59, 59);
            $daysPassed++;
        }

        return $daysPassed;
    }
}