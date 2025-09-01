<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExeatRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExpireOverdueExeatRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exeat:expire-overdue {--dry-run : Show what would be expired without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically expire exeat requests that have passed their return date and have not reached security signin stage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $today = Carbon::today();
        
        $this->info('Checking for overdue exeat requests...');
        
        // Find exeat requests that are overdue and haven't reached security_signin stage
        $overdueRequests = ExeatRequest::where('return_date', '<', $today)
            ->where('is_expired', false)
            ->whereNotIn('status', ['security_signin', 'hostel_signin', 'completed', 'rejected'])
            ->get();
        
        if ($overdueRequests->isEmpty()) {
            $this->info('No overdue exeat requests found.');
            return 0;
        }
        
        $this->info("Found {$overdueRequests->count()} overdue exeat request(s):");
        
        foreach ($overdueRequests as $request) {
            $student = $request->student;
            $daysOverdue = $today->diffInDays(Carbon::parse($request->return_date));
            
            $this->line("- ID: {$request->id}, Student: {$student->fname} {$student->lname} ({$request->matric_no})");
            $this->line("  Status: {$request->status}, Return Date: {$request->return_date}, Days Overdue: {$daysOverdue}");
            
            if (!$isDryRun) {
                // Update the request to expired status
                $request->update([
                    'is_expired' => true,
                    'expired_at' => now(),
                    'status' => 'completed' // Mark as completed with expired flag
                ]);
                
                // Log the expiration
                Log::info('Exeat request expired automatically', [
                    'exeat_request_id' => $request->id,
                    'student_id' => $request->student_id,
                    'matric_no' => $request->matric_no,
                    'original_status' => $request->getOriginal('status'),
                    'return_date' => $request->return_date,
                    'days_overdue' => $daysOverdue
                ]);
                
                $this->line("  ✓ Expired and marked as completed");
            } else {
                $this->line("  → Would be expired (dry run mode)");
            }
        }
        
        if ($isDryRun) {
            $this->warn('This was a dry run. No changes were made. Run without --dry-run to actually expire requests.');
        } else {
            $this->info("Successfully expired {$overdueRequests->count()} overdue exeat request(s).");
        }
        
        return 0;
    }
}
