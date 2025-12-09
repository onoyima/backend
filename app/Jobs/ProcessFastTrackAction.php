<?php

namespace App\Jobs;

use App\Models\ExeatRequest;
use App\Models\ExeatApproval;
use App\Services\ExeatWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFastTrackAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $exeatRequestId;
    public $staffId;
    public $action; // 'sign_out' or 'sign_in'

    /**
     * Create a new job instance.
     */
    public function __construct($exeatRequestId, $staffId, $action)
    {
        $this->exeatRequestId = $exeatRequestId;
        $this->staffId = $staffId;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(ExeatWorkflowService $workflowService)
    {
        try {
            $exeatRequest = ExeatRequest::find($this->exeatRequestId);

            if (!$exeatRequest) {
                Log::error('Fast-Track Job: Exeat request not found', ['id' => $this->exeatRequestId]);
                return;
            }

            DB::beginTransaction();

            if ($this->action === 'sign_out' && $exeatRequest->status === 'security_signout') {
                // Perform Sign Out
                $approval = ExeatApproval::create([
                    'exeat_request_id' => $exeatRequest->id,
                    'staff_id' => $this->staffId,
                    'role' => 'security',
                    'status' => 'approved',
                    'method' => 'security_signout',
                    'comment' => 'Fast-track sign out'
                ]);

                $workflowService->approve($exeatRequest, $approval, 'Fast-track sign out');

            } elseif ($this->action === 'sign_in' && $exeatRequest->status === 'security_signin') {
                // Perform Sign In
                $approval = ExeatApproval::create([
                    'exeat_request_id' => $exeatRequest->id,
                    'staff_id' => $this->staffId,
                    'role' => 'security',
                    'status' => 'approved',
                    'method' => 'security_signin',
                    'comment' => 'Fast-track sign in'
                ]);

                $workflowService->approve($exeatRequest, $approval, 'Fast-track sign in');
            }

            DB::commit();

            Log::info('Fast-Track Job: Successfully processed', [
                'exeat_request_id' => $this->exeatRequestId,
                'action' => $this->action
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fast-Track Job: Processing failed', [
                'exeat_request_id' => $this->exeatRequestId,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Fast-Track Job: Job failed permanently', [
            'exeat_request_id' => $this->exeatRequestId,
            'action' => $this->action,
            'error' => $exception->getMessage()
        ]);
    }
}
