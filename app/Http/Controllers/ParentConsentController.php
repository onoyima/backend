<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParentConsent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ParentConsentController extends Controller
{
    // GET /api/parent/consent/{token}
    public function show($token)
    {
        $consent = ParentConsent::where('consent_token', $token)->first();
        if (!$consent) {
            return response()->json(['message' => 'Consent request not found.'], 404);
        }
        return response()->json(['parent_consent' => $consent]);
    }

    // POST /api/parent/consent/{token}/approve
  

public function approve($token)
{
    $consent = ParentConsent::where('consent_token', $token)->first();

    if (!$consent) {
        return response()->json(['message' => 'Consent request not found.'], 404);
    }

    if ($consent->expires_at && now()->gt($consent->expires_at)) {
        return response()->json(['message' => 'This consent link has expired.'], 410);
    }

    $workflow = new \App\Services\ExeatWorkflowService();
    $exeatRequest = $workflow->parentConsentApprove($consent);

    Log::info('Parent approved consent', ['consent_id' => $consent->id]);

    return response()->json([
        'message' => 'Consent approved.',
        'parent_consent' => $consent,
        'exeat_request' => $exeatRequest
    ]);
}

public function decline($token)
{
    $consent = ParentConsent::where('consent_token', $token)->first();

    if (!$consent) {
        return response()->json(['message' => 'Consent request not found.'], 404);
    }

    if ($consent->expires_at && now()->gt($consent->expires_at)) {
        return response()->json(['message' => 'This consent link has expired.'], 410);
    }

    $workflow = new \App\Services\ExeatWorkflowService();
    $exeatRequest = $workflow->parentConsentDecline($consent);

    Log::info('Parent declined consent', ['consent_id' => $consent->id]);

    return response()->json([
        'message' => 'Consent declined.',
        'parent_consent' => $consent,
        'exeat_request' => $exeatRequest
    ]);
}

    // POST /api/parent/consent/remind
    public function remind(Request $request)
    {
        // For demo, just log and return
        \Log::info('Bulk parent consent reminders sent by admin', ['admin_id' => $request->user()->id]);
        // In real app, find all pending consents and send reminders
        return response()->json(['message' => 'Bulk parent consent reminders sent (simulated).']);
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

        $workflow = new \App\Services\ExeatWorkflowService();

        if ($action === 'approve') {
            $workflow->parentConsentApprove($consent);
            \Log::info('Parent approved via web link', ['token' => $token]);
            return response('<h2>Consent approved. Thank you!</h2>', 200);
        }

        if ($action === 'reject') {
            $workflow->parentConsentDecline($consent);
            \Log::info('Parent declined via web link', ['token' => $token]);
            return response('<h2>Consent declined. Thank you for your feedback.</h2>', 200);
        }

        return response('<h2>Invalid action specified.</h2>', 400);
    }



}
