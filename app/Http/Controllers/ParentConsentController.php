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
        $consent->consent_status = 'approved';
        $consent->consent_timestamp = Carbon::now();
        $consent->save();
        Log::info('Parent approved consent', ['consent_id' => $consent->id]);
        return response()->json(['message' => 'Consent approved.', 'parent_consent' => $consent]);
    }

    // POST /api/parent/consent/{token}/decline
    public function decline($token)
    {
        $consent = ParentConsent::where('consent_token', $token)->first();
        if (!$consent) {
            return response()->json(['message' => 'Consent request not found.'], 404);
        }
        $consent->consent_status = 'declined';
        $consent->consent_timestamp = Carbon::now();
        $consent->save();
        Log::info('Parent declined consent', ['consent_id' => $consent->id]);
        return response()->json(['message' => 'Consent declined.', 'parent_consent' => $consent]);
    }

    // POST /api/parent/consent/remind
    public function remind(Request $request)
    {
        // For demo, just log and return
        \Log::info('Bulk parent consent reminders sent by admin', ['admin_id' => $request->user()->id]);
        // In real app, find all pending consents and send reminders
        return response()->json(['message' => 'Bulk parent consent reminders sent (simulated).']);
    }
}
