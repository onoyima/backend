<?php

namespace App\Http\Controllers;

use App\Models\ExeatNotification;
use App\Services\ExeatNotificationService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StudentNotificationController extends Controller
{
    protected $notificationService;
    protected $preferenceService;

    public function __construct(
        ExeatNotificationService $notificationService,
        NotificationPreferenceService $preferenceService
    ) {
        $this->notificationService = $notificationService;
        $this->preferenceService = $preferenceService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Get student's notifications with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:50',
            'type' => Rule::in([
                ExeatNotification::TYPE_STAGE_CHANGE,
                ExeatNotification::TYPE_APPROVAL_REQUIRED,
                ExeatNotification::TYPE_REMINDER,
                ExeatNotification::TYPE_EMERGENCY
            ]),
            'priority' => Rule::in([
                ExeatNotification::PRIORITY_LOW,
                ExeatNotification::PRIORITY_MEDIUM,
                ExeatNotification::PRIORITY_HIGH,
                ExeatNotification::PRIORITY_URGENT
            ]),
            'read_status' => Rule::in(['read', 'unread']),
            'exeat_id' => 'integer|exists:exeat_requests,id'
        ]);

        $student = Auth::user();
        $perPage = $request->get('per_page', 15);
        
        $filters = $request->only(['type', 'priority', 'read_status', 'exeat_id']);
        
        $notifications = $this->notificationService->getUserNotifications(
            ExeatNotification::RECIPIENT_STUDENT,
            $student->id,
            $perPage,
            $filters
        );

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
     * Get unread notification count.
     */
    public function unreadCount(): JsonResponse
    {
        $student = Auth::user();
        
        $count = $this->notificationService->getUnreadCount(
            ExeatNotification::RECIPIENT_STUDENT,
            $student->id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * Mark notifications as read.
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'array',
            'notification_ids.*' => 'integer|exists:exeat_notifications,id',
            'mark_all' => 'boolean'
        ]);

        $student = Auth::user();
        $notificationIds = $request->get('mark_all', false) ? null : $request->get('notification_ids');
        
        $markedCount = $this->notificationService->markNotificationsAsRead(
            ExeatNotification::RECIPIENT_STUDENT,
            $student->id,
            $notificationIds
        );

        return response()->json([
            'success' => true,
            'message' => "Marked {$markedCount} notifications as read",
            'data' => [
                'marked_count' => $markedCount
            ]
        ]);
    }

    /**
     * Get notification preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $student = Auth::user();
        
        $summary = $this->preferenceService->getNotificationSummary(
            'student',
            $student->id
        );

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'notification_type' => Rule::in(['all', 'urgent', 'none']),
            'delivery_methods' => 'array',
            'delivery_methods.*' => Rule::in(['in_app', 'email', 'sms', 'whatsapp']),
            'quiet_hours_start' => 'date_format:H:i',
            'quiet_hours_end' => 'date_format:H:i'
        ]);

        $student = Auth::user();
        $updates = [];
        
        if ($request->has('notification_type')) {
            $updates['notification_type'] = $request->notification_type;
        }
        
        if ($request->has('delivery_methods')) {
            $methods = $request->delivery_methods;
            $updates['in_app_enabled'] = in_array('in_app', $methods);
            $updates['email_enabled'] = in_array('email', $methods);
            $updates['sms_enabled'] = in_array('sms', $methods);
            $updates['whatsapp_enabled'] = in_array('whatsapp', $methods);
        }
        
        if ($request->has('quiet_hours_start')) {
            $updates['quiet_hours_start'] = $request->quiet_hours_start;
        }
        
        if ($request->has('quiet_hours_end')) {
            $updates['quiet_hours_end'] = $request->quiet_hours_end;
        }
        
        $preference = $this->preferenceService->updateUserPreferences(
            'student',
            $student->id,
            $updates
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'data' => $this->preferenceService->getNotificationSummary('student', $student->id)
        ]);
    }

    /**
     * Reset preferences to default.
     */
    public function resetPreferences(): JsonResponse
    {
        $student = Auth::user();
        
        $preference = $this->preferenceService->resetToDefaults('student', $student->id);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences reset to default',
            'data' => $this->preferenceService->getNotificationSummary('student', $student->id)
        ]);
    }

    /**
     * Get notification details.
     */
    public function show(int $notificationId): JsonResponse
    {
        $student = Auth::user();
        
        $notification = ExeatNotification::where('id', $notificationId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STUDENT)
            ->where('recipient_id', $student->id)
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
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }

    /**
     * Get notifications for a specific exeat request.
     */
    public function getExeatNotifications(int $exeatId): JsonResponse
    {
        $student = Auth::user();
        
        // Verify the exeat request belongs to the student
        $exeatRequest = $student->exeatRequests()->find($exeatId);
        
        if (!$exeatRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Exeat request not found'
            ], 404);
        }
        
        $notifications = ExeatNotification::where('exeat_request_id', $exeatId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STUDENT)
            ->where('recipient_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Get notification statistics for the student.
     */
    public function getStats(): JsonResponse
    {
        $student = Auth::user();
        
        $stats = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STUDENT)
            ->where('recipient_id', $student->id)
            ->selectRaw('
                notification_type,
                priority,
                COUNT(*) as total,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
            ')
            ->groupBy('notification_type', 'priority')
            ->get()
            ->groupBy('notification_type');
        
        $summary = [
            'total_notifications' => ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STUDENT)
                ->where('recipient_id', $student->id)
                ->count(),
            'unread_notifications' => ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STUDENT)
                ->where('recipient_id', $student->id)
                ->where('is_read', false)
                ->count(),
            'by_type' => $stats->map(function ($typeStats) {
                return $typeStats->map(function ($stat) {
                    return [
                        'priority' => $stat->priority,
                        'total' => $stat->total,
                        'read' => $stat->read_count,
                        'unread' => $stat->unread_count
                    ];
                })->keyBy('priority');
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}