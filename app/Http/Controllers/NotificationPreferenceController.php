<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationPreferenceController extends Controller
{
    // GET /api/user/notification-preferences
    public function show(Request $request)
    {
        $user = $request->user();
        // For demo, return a static preferences array
        $prefs = $user->notification_preferences ?? [
            'email' => true,
            'sms' => false,
            'whatsapp' => false,
        ];
        return response()->json(['preferences' => $prefs]);
    }

    // PUT /api/user/notification-preferences
    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => 'boolean',
            'sms' => 'boolean',
            'whatsapp' => 'boolean',
        ]);
        // For demo, just log and return
        Log::info('User updated notification preferences', ['user_id' => $user->id, 'prefs' => $validated]);
        // In real app, save to DB (e.g., $user->notification_preferences = $validated; $user->save();)
        return response()->json(['message' => 'Preferences updated.', 'preferences' => $validated]);
    }
}
