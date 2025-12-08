<?php

namespace App\Http\Controllers;

use App\Models\ExeatNotification;
use App\Models\ExeatRequest;
use App\Models\Student;
use App\Models\Staff;
use App\Services\ExeatNotificationService;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminNotificationController extends Controller
{
    protected $notificationService;
    protected $preferenceService;
    protected $deliveryService;

    public function __construct(
        ExeatNotificationService $notificationService,
        NotificationPreferenceService $preferenceService,
        NotificationDeliveryService $deliveryService
    ) {
        $this->notificationService = $notificationService;
        $this->preferenceService = $preferenceService;
        $this->deliveryService = $deliveryService;
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    public function unreadCount(): JsonResponse
    {
        $admin = Auth::user();
        $staffCount = $this->notificationService->getUnreadCount(ExeatNotification::RECIPIENT_STAFF, $admin->id);
        $adminCount = $this->notificationService->getUnreadCount(ExeatNotification::RECIPIENT_ADMIN, $admin->id);
        $count = $staffCount + $adminCount;

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * Get all notifications with advanced filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:100',
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
            'recipient_type' => Rule::in([
                ExeatNotification::RECIPIENT_STUDENT,
                ExeatNotification::RECIPIENT_STAFF,
                ExeatNotification::RECIPIENT_ADMIN
            ]),
            'read_status' => Rule::in(['read', 'unread']),
            'exeat_id' => 'integer|exists:exeat_requests,id',
            'student_id' => 'integer|exists:students,id',
            'staff_id' => 'integer|exists:staff,id',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $perPage = $request->get('per_page', 20);
        
        $query = ExeatNotification::with(['exeatRequest.student'])
            ->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->has('type')) {
            $query->where('notification_type', $request->type);
        }
        
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        
        if ($request->has('recipient_type')) {
            $query->where('recipient_type', $request->recipient_type);
        }
        
        if ($request->has('read_status')) {
            $isRead = $request->read_status === 'read';
            $query->where('is_read', $isRead);
        }
        
        if ($request->has('exeat_id')) {
            $query->where('exeat_request_id', $request->exeat_id);
        }
        
        if ($request->has('student_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('recipient_type', ExeatNotification::RECIPIENT_STUDENT)
                  ->where('recipient_id', $request->student_id);
            });
        }
        
        if ($request->has('staff_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
                  ->where('recipient_id', $request->staff_id);
            });
        }
        
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $notifications = $query->paginate($perPage);

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
     * Get comprehensive notification statistics.
     */
    public function getStats(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'group_by' => Rule::in(['day', 'week', 'month'])
        ]);

        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $groupBy = $request->get('group_by', 'day');
        
        // Overall statistics
        $overallStats = ExeatNotification::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                COUNT(*) as total_notifications,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_notifications,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_notifications,
                COUNT(DISTINCT recipient_id, recipient_type) as unique_recipients
            ')
            ->first();
        
        // Statistics by type
        $typeStats = ExeatNotification::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                notification_type,
                priority,
                COUNT(*) as count,
                AVG(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) * 100 as read_percentage
            ')
            ->groupBy('notification_type', 'priority')
            ->get()
            ->groupBy('notification_type');
        
        // Statistics by recipient type
        $recipientStats = ExeatNotification::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                recipient_type,
                COUNT(*) as count,
                COUNT(DISTINCT recipient_id) as unique_recipients,
                AVG(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) * 100 as read_percentage
            ')
            ->groupBy('recipient_type')
            ->get()
            ->keyBy('recipient_type');
        
        // Time-based statistics
        $dateFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        $timeStats = ExeatNotification::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as count,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        
        // Delivery statistics
        $deliveryStats = $this->deliveryService->getDeliveryStats([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => $overallStats,
                'by_type' => $typeStats,
                'by_recipient_type' => $recipientStats,
                'time_series' => $timeStats,
                'delivery_stats' => $deliveryStats,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'group_by' => $groupBy
                ]
            ]
        ]);
    }

    /**
     * Send bulk notifications.
     */
    public function sendBulkNotification(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_type' => ['required', Rule::in([
                ExeatNotification::RECIPIENT_STUDENT,
                ExeatNotification::RECIPIENT_STAFF,
                ExeatNotification::RECIPIENT_ADMIN
            ])],
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'integer',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'priority' => Rule::in([
                ExeatNotification::PRIORITY_LOW,
                ExeatNotification::PRIORITY_MEDIUM,
                ExeatNotification::PRIORITY_HIGH,
                ExeatNotification::PRIORITY_URGENT
            ]),
            'delivery_methods' => 'array',
            'delivery_methods.*' => Rule::in(['in_app', 'email', 'sms', 'whatsapp'])
        ]);
        
        $recipients = collect($request->recipient_ids)->map(function ($recipientId) use ($request) {
            return [
                'type' => $request->recipient_type,
                'id' => $recipientId
            ];
        })->toArray();
        
        // Create a dummy exeat request for bulk notifications
        $dummyExeat = new ExeatRequest(['id' => 0]);
        
        $notifications = $this->notificationService->createNotification(
            $dummyExeat,
            $recipients,
            'bulk_admin',
            $request->title,
            $request->message,
            $request->get('priority', ExeatNotification::PRIORITY_MEDIUM)
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk notification sent successfully',
            'data' => [
                'notification_count' => $notifications->count(),
                'recipients' => count($recipients)
            ]
        ]);
    }

    /**
     * Get notification delivery logs.
     */
    public function getDeliveryLogs(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'notification_id' => 'integer|exists:exeat_notifications,id',
            'delivery_method' => Rule::in(['in_app', 'email', 'sms', 'whatsapp']),
            'delivery_status' => Rule::in(['pending', 'delivered', 'failed']),
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $perPage = $request->get('per_page', 20);
        
        $query = \App\Models\NotificationDeliveryLog::with(['notification.exeatRequest.student'])
            ->orderBy('attempted_at', 'desc');
        
        if ($request->has('notification_id')) {
            $query->where('notification_id', $request->notification_id);
        }
        
        if ($request->has('delivery_method')) {
            $query->where('delivery_method', $request->delivery_method);
        }
        
        if ($request->has('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }
        
        if ($request->has('date_from')) {
            $query->where('attempted_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('attempted_at', '<=', $request->date_to);
        }
        
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem()
            ]
        ]);
    }

    /**
     * Retry failed notification deliveries.
     */
    public function retryFailedDeliveries(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'array',
            'notification_ids.*' => 'integer|exists:exeat_notifications,id',
            'delivery_method' => Rule::in(['in_app', 'email', 'sms', 'whatsapp']),
            'max_retries' => 'integer|min:1|max:5'
        ]);

        $maxRetries = $request->get('max_retries', 3);
        
        if ($request->has('notification_ids')) {
            // Retry specific notifications
            $retriedCount = 0;
            
            foreach ($request->notification_ids as $notificationId) {
                $notification = ExeatNotification::find($notificationId);
                
                if ($notification) {
                    $methods = $request->has('delivery_method') 
                        ? [$request->delivery_method] 
                        : $notification->delivery_methods;
                    
                    foreach ($methods as $method) {
                        if ($this->deliveryService->deliverNotification($notification, $method)) {
                            $retriedCount++;
                        }
                    }
                }
            }
        } else {
            // Retry all failed deliveries
            $retriedCount = $this->deliveryService->retryFailedDeliveries($maxRetries);
        }

        return response()->json([
            'success' => true,
            'message' => "Retried {$retriedCount} failed deliveries",
            'data' => [
                'retried_count' => $retriedCount
            ]
        ]);
    }

    /**
     * Get user notification preferences.
     */
    public function getUserPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'user_type' => ['required', Rule::in(['student', 'staff'])],
            'user_id' => 'required|integer'
        ]);

        $preferences = $this->preferenceService->getUserPreferences(
            $request->user_type,
            $request->user_id
        );
        
        if (!$preferences) {
            $preferences = $this->preferenceService->createDefaultPreferences(
                $request->user_type,
                $request->user_id
            );
        }

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Update user notification preferences.
     */
    public function updateUserPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'user_type' => ['required', Rule::in(['student', 'staff'])],
            'user_id' => 'required|integer',
            'notification_type' => Rule::in(['all', 'urgent', 'none']),
            'delivery_methods' => 'array',
            'delivery_methods.*' => Rule::in(['in_app', 'email', 'sms', 'whatsapp']),
            'quiet_hours_start' => 'date_format:H:i',
            'quiet_hours_end' => 'date_format:H:i'
        ]);

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
            $request->user_type,
            $request->user_id,
            $updates
        );

        return response()->json([
            'success' => true,
            'message' => 'User notification preferences updated successfully',
            'data' => $preference
        ]);
    }

    /**
     * Get system-wide notification preferences statistics.
     */
    public function getPreferencesStats(): JsonResponse
    {
        $stats = $this->preferenceService->getPreferencesStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Clear notification preferences cache.
     */
    public function clearPreferencesCache(): JsonResponse
    {
        $this->preferenceService->clearAllPreferencesCache();

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences cache cleared successfully'
        ]);
    }

    /**
     * Get notification templates (for future implementation).
     */
    public function getNotificationTemplates(): JsonResponse
    {
        $templates = [
            'stage_change' => [
                'title' => 'Exeat Request Status Updated',
                'message' => 'Your exeat request #{exeat_id} status has been updated to {new_status}.'
            ],
            'approval_required' => [
                'title' => 'Exeat Approval Required',
                'message' => 'Exeat request #{exeat_id} requires your approval as {role}.'
            ],
            'reminder' => [
                'title' => 'Exeat Reminder',
                'message' => 'This is a reminder regarding exeat request #{exeat_id}.'
            ],
            'emergency' => [
                'title' => 'URGENT: Exeat Emergency',
                'message' => 'Emergency notification regarding exeat request #{exeat_id}.'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }
}