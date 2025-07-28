<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommunicationController extends Controller
{
    // POST /api/communication/test
    public function test(Request $request)
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,email,whatsapp',
            'to' => 'required|string',
            'message' => 'required|string',
        ]);
        // For demo, just log the request
        Log::info('Communication test', $validated);
        // In real app, send message via selected channel
        return response()->json(['message' => 'Test message sent (simulated).', 'channel' => $validated['channel']]);
    }

    // GET /api/communication/channels
    public function channels()
    {
        $channels = ['sms', 'email', 'whatsapp'];
        return response()->json(['channels' => $channels]);
    }
}
