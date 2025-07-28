<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
        Log::info('Notifications listed', ['user_id' => $user->id, 'count' => $notifications->count()]);
        return response()->json(['notifications' => $notifications]);
    }

    // POST /api/notifications/mark-read
    public function markRead(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);
        $count = Notification::where('user_id', $user->id)
            ->whereIn('id', $validated['ids'])
            ->update(['read_at' => now()]);
        Log::info('Notifications marked as read', ['user_id' => $user->id, 'count' => $count]);
        return response()->json(['message' => "$count notifications marked as read."]);
    }
}
