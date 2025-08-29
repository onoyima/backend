<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get user notifications with pagination.
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 20);
        $type = $request->get('type');
        $unreadOnly = $request->get('unread_only');
        
        if ($unreadOnly !== null) {
            $unreadOnly = filter_var($unreadOnly, FILTER_VALIDATE_BOOLEAN);
        }

        // Determine recipient type based on user model
        $recipientType = $user instanceof \App\Models\Student ? 
            ExeatNotification::RECIPIENT_STUDENT : 
            ExeatNotification::RECIPIENT_STAFF;

        $query = ExeatNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $user->id)
            ->with(['exeatRequest.student'])
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('notification_type', $type);
        }

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate($perPage);

        Log::info('Notifications listed', [
            'user_id' => $user->id,
            'recipient_type' => $recipientType,
            'count' => $notifications->total(),
            'type' => $type,
            'unread_only' => $unreadOnly
        ]);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem()
            ]
        ]);
    }

    /**
     * Get unread notifications count.
     * GET /api/notifications/unread-count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Determine recipient type based on user model
        $recipientType = $user instanceof \App\Models\Student ? 
            ExeatNotification::RECIPIENT_STUDENT : 
            ExeatNotification::RECIPIENT_STAFF;
            
        $count = ExeatNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * Mark notifications as read.
     * POST /api/notifications/mark-read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        // Determine recipient type based on user model
        $recipientType = $user instanceof \App\Models\Student ? 
            ExeatNotification::RECIPIENT_STUDENT : 
            ExeatNotification::RECIPIENT_STAFF;

        $count = ExeatNotification::whereIn('id', $validated['ids'])
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        Log::info('Notifications marked as read', ['user_id' => $user->id, 'count' => $count]);
        
        return response()->json([
            'success' => true,
            'message' => "$count notifications marked as read.",
            'data' => [
                'marked_count' => $count
            ]
        ]);
    }

    /**
     * Mark all notifications as read.
     * POST /api/notifications/mark-all-read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Determine recipient type based on user model
        $recipientType = $user instanceof \App\Models\Student ? 
            ExeatNotification::RECIPIENT_STUDENT : 
            ExeatNotification::RECIPIENT_STAFF;
        
        $count = ExeatNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        Log::info('All notifications marked as read', ['user_id' => $user->id, 'count' => $count]);
        
        return response()->json([
            'success' => true,
            'message' => "All notifications marked as read.",
            'data' => [
                'marked_count' => $count
            ]
        ]);
    }

    /**
     * Get specific notification.
     * GET /api/notifications/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        // Determine recipient type based on user model
        $recipientType = $user instanceof \App\Models\Student ? 
            ExeatNotification::RECIPIENT_STUDENT : 
            ExeatNotification::RECIPIENT_STAFF;
        
        $notification = ExeatNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $user->id)
            ->where('id', $id)
            ->with(['exeatRequest.student'])
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        // Mark as read if not already read
        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }

    /**
     * Delete notification.
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        // Determine recipient type based on user model
        $recipientType = $user instanceof \App\Models\Student ? 
            ExeatNotification::RECIPIENT_STUDENT : 
            ExeatNotification::RECIPIENT_STAFF;
        
        $notification = ExeatNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        Log::info('Notification deleted', ['user_id' => $user->id, 'notification_id' => $id]);
        
        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }
}
