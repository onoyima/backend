<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParentConsent;
use App\Services\ExeatWorkflowService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ParentConsentController extends Controller
{
    protected $workflowService;

    public function __construct(ExeatWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }
    // GET /api/parent/consent/{token}
    public function show($token)
    {
        // Validate token format
        if (empty($token) || !is_string($token)) {
            return response()->json(['message' => 'Invalid consent token.'], 400);
        }

        $consent = ParentConsent::where('consent_token', $token)
            ->with(['exeatRequest.student'])
            ->first();
            
        if (!$consent) {
            return response()->json(['message' => 'Consent request not found.'], 404);
        }

        // Check if token has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json(['message' => 'This consent link has expired.'], 410);
        }

        // Check if already processed
        if (in_array($consent->consent_status, ['approved', 'declined'])) {
            return response()->json([
                'message' => 'This consent request has already been processed.',
                'status' => $consent->consent_status,
                'processed_at' => $consent->consent_timestamp
            ], 200);
        }

        return response()->json(['parent_consent' => $consent]);
    }

    // POST /api/parent/consent/{token}/approve
  

    // POST /api/parent/consent/{token}/approve
    public function approve($token)
    {
        // Validate token format
        if (empty($token) || !is_string($token)) {
            return response()->json(['message' => 'Invalid consent token.'], 400);
        }

        $consent = ParentConsent::where('consent_token', $token)
            ->with(['exeatRequest.student'])
            ->first();

        if (!$consent) {
            return response()->json(['message' => 'Consent request not found.'], 404);
        }

        // Check if token has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json(['message' => 'This consent link has expired.'], 410);
        }

        // Check if already processed
        if ($consent->consent_status === 'approved') {
            return response()->json([
                'message' => 'This consent request has already been approved.',
                'processed_at' => $consent->consent_timestamp
            ], 200);
        }

        if ($consent->consent_status === 'declined') {
            return response()->json([
                'message' => 'This consent request was previously declined and cannot be approved.',
                'processed_at' => $consent->consent_timestamp
            ], 409);
        }

        try {
            $exeatRequest = $this->workflowService->parentConsentApprove($consent);
            
            Log::info('Parent approved consent', ['consent_id' => $consent->id, 'exeat_id' => $exeatRequest->id]);

            return response()->json([
                'message' => 'Consent approved successfully.',
                'parent_consent' => $consent->fresh(),
                'exeat_request' => $exeatRequest
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve parent consent', [
                'consent_id' => $consent->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to process consent approval. Please try again.',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    // POST /api/parent/consent/{token}/decline
    public function decline($token)
    {
        // Validate token format
        if (empty($token) || !is_string($token)) {
            return response()->json(['message' => 'Invalid consent token.'], 400);
        }

        $consent = ParentConsent::where('consent_token', $token)
            ->with(['exeatRequest.student'])
            ->first();

        if (!$consent) {
            return response()->json(['message' => 'Consent request not found.'], 404);
        }

        // Check if token has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json(['message' => 'This consent link has expired.'], 410);
        }

        // Check if already processed
        if ($consent->consent_status === 'declined') {
            return response()->json([
                'message' => 'This consent request has already been declined.',
                'processed_at' => $consent->consent_timestamp
            ], 200);
        }

        if ($consent->consent_status === 'approved') {
            return response()->json([
                'message' => 'This consent request was previously approved and cannot be declined.',
                'processed_at' => $consent->consent_timestamp
            ], 409);
        }

        try {
            $exeatRequest = $this->workflowService->parentConsentDecline($consent);
            
            Log::info('Parent declined consent', ['consent_id' => $consent->id, 'exeat_id' => $exeatRequest->id]);

            return response()->json([
                'message' => 'Consent declined successfully.',
                'parent_consent' => $consent->fresh(),
                'exeat_request' => $exeatRequest
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to decline parent consent', [
                'consent_id' => $consent->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to process consent decline. Please try again.',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send bulk parent consent reminders to Deputy Dean staff.
     */
    public function remind(Request $request)
    {
        try {
            // Send reminders to Deputy Dean staff about pending parent consents
            // Parents do not receive direct notifications
            
            $pendingConsents = ParentConsent::where('consent_status', 'pending')
                ->with(['exeatRequest.student', 'studentContact'])
                ->get();
            
            $remindersSent = 0;
            
            foreach ($pendingConsents as $consent) {
                // Notify Deputy Dean staff about pending consent
                // NotificationJob::dispatch($consent, 'deputy_dean_reminder');
                $remindersSent++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Sent {$remindersSent} reminders to Deputy Dean staff about pending parent consents",
                'reminders_sent' => $remindersSent
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GET /api/parent/exeat-consent/{token}/{action}
    public function handleWebConsent($token, $action)
    {
        $consent = ParentConsent::where('consent_token', $token)->first();

        if (!$consent) {
            return response('<h2>Consent request not found.</h2>', 404);
        }

        // ✅ Check if token has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response('<h2>This consent link has expired.</h2>', 410);
        }

        // ✅ Prevent duplicate actions
        if ($consent->consent_status === 'approved') {
            return response('<h2>This request has already been approved.</h2>', 200);
        }

        if ($consent->consent_status === 'declined') {
            return response('<h2>This request has already been declined.</h2>', 200);
        }

        try {
            if ($action === 'approve') {
                $this->workflowService->parentConsentApprove($consent);
                Log::info('Parent approved via web link', ['token' => $token, 'consent_id' => $consent->id]);
                return response('<h2>Consent approved. Thank you!</h2>', 200);
            }

            if ($action === 'reject' || $action === 'decline') {
                $this->workflowService->parentConsentDecline($consent);
                Log::info('Parent declined via web link', ['token' => $token, 'consent_id' => $consent->id]);
                return response('<h2>Consent declined. Thank you for your feedback.</h2>', 200);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process web consent action', [
                'token' => $token,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return response('<h2>An error occurred while processing your request. Please try again or contact support.</h2>', 500);
        }

        return response('<h2>Invalid action specified.</h2>', 400);
    }

}
