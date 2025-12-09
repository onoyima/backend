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
    protected $description = 'Automatically expire exeat requests that have passed their departure date + 6 hours and student has not left school (not reached security signin stage)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $now = Carbon::now();

        $this->info('Checking for expired exeat requests...');

        // Find exeat requests that are expired based on departure date + 6 hours
        // and student hasn't left school yet (not reached security_signin stage)
        $expiredRequests = ExeatRequest::with(['category'])
            ->where('is_expired', false)
            ->whereNotIn('status', ['completed', 'security_signin', 'hostel_signin', 'rejected', 'security_signout'])
            ->whereHas('category', function ($query) {
                // Exempt 'Daily' and 'Holiday' categories from auto-expiration
                $query->whereNotIn('name', ['Daily', 'Holiday']);
            })
            ->where(function ($query) use ($now) {
                $query->whereRaw('DATE_ADD(departure_date, INTERVAL 6 HOUR) < ?', [$now->format('Y-m-d H:i:s')]);
            })
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('No expired exeat requests found.');
            return 0;
        }

        $this->info("Found {$expiredRequests->count()} expired exeat request(s):");

        foreach ($expiredRequests as $request) {
            $student = $request->student;
            $departureDeadline = Carbon::parse($request->departure_date)->addHours(6);
            $hoursOverdue = $departureDeadline->diffInHours($now);

            $this->line("- ID: {$request->id}, Student: {$student->fname} {$student->lname} ({$request->matric_no})");
            $this->line("  Status: {$request->status}, Category: {$request->category->name}");
            $this->line("  Departure Date: {$request->departure_date}, Deadline: {$departureDeadline->format('Y-m-d H:i:s')}");
            $this->line("  Hours past deadline: {$hoursOverdue}");

            if (!$isDryRun) {
                // Update the request to expired status
                $request->update([
                    'is_expired' => true,
                    'expired_at' => now(),
                    'status' => 'completed' // Mark as completed with expired flag
                ]);

                // Log the expiration
                Log::info('Exeat request expired automatically (departure-based)', [
                    'exeat_request_id' => $request->id,
                    'student_id' => $request->student_id,
                    'matric_no' => $request->matric_no,
                    'original_status' => $request->getOriginal('status'),
                    'category' => $request->category->name,
                    'departure_date' => $request->departure_date,
                    'deadline_passed' => $departureDeadline->format('Y-m-d H:i:s'),
                    'hours_overdue' => $hoursOverdue
                ]);

                $this->line("  ✓ Expired - student hasn't left school by deadline");
            } else {
                $this->line("  → Would be expired (dry run mode)");
            }
        }

        if ($isDryRun) {
            $this->warn('This was a dry run. No changes were made. Run without --dry-run to actually expire requests.');
        } else {
            $this->info("Successfully expired {$expiredRequests->count()} exeat request(s).");
        }

        return 0;
    }
}
