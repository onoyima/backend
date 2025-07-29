<?php

namespace App\Services;

use App\Models\ExeatRequest;
use App\Models\ExeatApproval;
use App\Models\ParentConsent;
use Illuminate\Support\Facades\Log;

class ExeatWorkflowService
{
    /**
     * Approve an exeat request for a given staff and approval step.
     */
    public function approve(ExeatRequest $exeatRequest, ExeatApproval $approval, $comment = null)
    {
        // Update approval status
        $approval->status = 'approved';
        $approval->comment = $comment;
        $approval->save();

        // Move the exeat request to the next stage based on current status and type
        $this->advanceStage($exeatRequest);

        Log::info('WorkflowService: Exeat approved', ['exeat_id' => $exeatRequest->id, 'approval_id' => $approval->id]);

        return $exeatRequest;
    }

    /**
     * Reject an exeat request for a given staff and approval step.
     */
    public function reject(ExeatRequest $exeatRequest, ExeatApproval $approval, $comment = null)
    {
        $approval->status = 'rejected';
        $approval->comment = $comment;
        $approval->save();

        $exeatRequest->status = 'rejected';
        $exeatRequest->save();

        Log::info('WorkflowService: Exeat rejected', ['exeat_id' => $exeatRequest->id, 'approval_id' => $approval->id]);

        return $exeatRequest;
    }

    /**
     * Advance the exeat request to the next workflow stage.
     */
    protected function advanceStage(ExeatRequest $exeatRequest)
    {
        switch ($exeatRequest->status) {
            case 'pending':
                if ($exeatRequest->is_medical) {
                    $exeatRequest->status = 'medical_review';
                } else {
                    // skip medical review for non-medical
                    $exeatRequest->status = 'recommendation1';
                }
                break;

            case 'medical_review':
                $exeatRequest->status = 'recommendation1';
                break;

            case 'recommendation1':
                $exeatRequest->status = 'parent_pending';
                break;

            case 'parent_pending':
                $exeatRequest->status = 'dean_final_pending';
                break;

            case 'dean_final_pending':
                $exeatRequest->status = 'hostel_signout';
                break;

            case 'hostel_signout':
                $exeatRequest->status = 'security_signout';
                break;

            case 'security_signout':
                $exeatRequest->status = 'security_signin';
                break;

            case 'security_signin':
                $exeatRequest->status = 'hostel_signin';
                break;

            case 'hostel_signin':
                $exeatRequest->status = 'completed';
                break;

            default:
                Log::warning("WorkflowService: Unknown or final status {$exeatRequest->status} for ExeatRequest ID {$exeatRequest->id}");
                break;
        }

        $exeatRequest->save();

        Log::info('WorkflowService: Exeat advanced to next stage', [
            'exeat_id' => $exeatRequest->id,
            'new_status' => $exeatRequest->status,
        ]);
    }

    /**
     * Send parent consent request.
     */
    public function sendParentConsent(ExeatRequest $exeatRequest, string $method, ?string $message = null)
    {
        // Create or update parent consent record
        $parentConsent = ParentConsent::updateOrCreate(
            ['exeat_request_id' => $exeatRequest->id],
            [
                'consent_status' => 'pending',
                'consent_method' => $method,
                'consent_token' => uniqid('consent_', true),
                'consent_message' => $message,
                'consent_timestamp' => null,
            ]
        );

        // Here you would integrate with your notification system (SMS, email, etc.)
        // e.g., Notification::sendParentConsent($parentConsent);

        Log::info('WorkflowService: Parent consent requested', [
            'exeat_id' => $exeatRequest->id,
            'method' => $method,
        ]);

        // Update exeat status
        $exeatRequest->status = 'parent_pending';
        $exeatRequest->save();

        return $parentConsent;
    }

    /**
     * Handle parent consent approval.
     */
    public function parentConsentApprove(ParentConsent $parentConsent)
    {
        $parentConsent->consent_status = 'approved';
        $parentConsent->consent_timestamp = now();
        $parentConsent->save();

        // Update exeat request status to next stage
        $exeatRequest = $parentConsent->exeatRequest;
        $exeatRequest->status = 'dean_final_pending';
        $exeatRequest->save();

        Log::info('WorkflowService: Parent consent approved', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
        ]);

        return $exeatRequest;
    }

    /**
     * Handle parent consent decline.
     */
    public function parentConsentDecline(ParentConsent $parentConsent)
    {
        $parentConsent->consent_status = 'declined';
        $parentConsent->consent_timestamp = now();
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $exeatRequest->status = 'rejected';
        $exeatRequest->save();

        Log::info('WorkflowService: Parent consent declined', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
        ]);

        return $exeatRequest;
    }
}
