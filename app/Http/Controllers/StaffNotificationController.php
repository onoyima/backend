<?php

namespace App\Http\Controllers;

use App\Models\ExeatNotification;
use App\Models\ExeatRequest;
use App\Services\ExeatNotificationService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StaffNotificationController extends Controller
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
     * Get staff's notifications with pagination.
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
            'exeat_id' => 'integer|exists:exeat_requests,id',
            'student_id' => 'integer|exists:students,id'
        ]);

        $staff = Auth::user();
        $perPage = $request->get('per_page', 15);
        
        $filters = $request->only(['type', 'priority', 'read_status', 'exeat_id', 'student_id']);
        
        $notifications = $this->notificationService->getUserNotifications(
            ExeatNotification::RECIPIENT_STAFF,
            $staff->id,
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
        $staff = Auth::user();
        
        $count = $this->notificationService->getUnreadCount(
            ExeatNotification::RECIPIENT_STAFF,
            $staff->id
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

        $staff = Auth::user();
        $notificationIds = $request->get('mark_all', false) ? null : $request->get('notification_ids');
        
        $markedCount = $this->notificationService->markNotificationsAsRead(
            ExeatNotification::RECIPIENT_STAFF,
            $staff->id,
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
        $staff = Auth::user();
        
        $summary = $this->preferenceService->getNotificationSummary(
            'staff',
            $staff->id
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

        $staff = Auth::user();
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
            'staff',
            $staff->id,
            $updates
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'data' => $this->preferenceService->getNotificationSummary('staff', $staff->id)
        ]);
    }

    /**
     * Get pending approvals for staff.
     */
    public function getPendingApprovals(): JsonResponse
    {
        $staff = Auth::user();
        
        // Get staff roles to determine what approvals they can handle
        $staffRoles = $staff->exeatRoles->pluck('role')->toArray();
        
        $pendingApprovals = [];
        
        foreach ($staffRoles as $role) {
            $status = match ($role) {
                'cmd' => 'cmd_review',
                'secretary' => 'secretary_review',
                'dean' => 'dean_review',
                'hostel_admin' => 'hostel_signout',
                'security' => 'security_signout',
                default => null
            };
            
            if ($status) {
                $requests = ExeatRequest::where('status', $status)
                    ->with(['student', 'notifications' => function ($query) use ($staff) {
                        $query->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
                            ->where('recipient_id', $staff->id)
                            ->where('notification_type', ExeatNotification::TYPE_APPROVAL_REQUIRED)
                            ->where('is_read', false);
                    }])
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                $pendingApprovals[$role] = $requests;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $pendingApprovals
        ]);
    }

    /**
     * Send custom notification to students.
     */
    public function sendCustomNotification(Request $request): JsonResponse
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'integer|exists:students,id',
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

        $staff = Auth::user();
        
        // Check if staff has permission to send notifications
        if (!$staff->hasPermission('send_notifications')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to send notifications'
            ], 403);
        }
        
        $recipients = collect($request->student_ids)->map(function ($studentId) {
            return [
                'type' => ExeatNotification::RECIPIENT_STUDENT,
                'id' => $studentId
            ];
        })->toArray();
        
        // Create a dummy exeat request for custom notifications
        $dummyExeat = new ExeatRequest(['id' => 0]);
        
        $notifications = $this->notificationService->createNotification(
            $dummyExeat,
            $recipients,
            'custom',
            $request->title,
            $request->message,
            $request->get('priority', ExeatNotification::PRIORITY_MEDIUM)
        );

        return response()->json([
            'success' => true,
            'message' => 'Custom notification sent successfully',
            'data' => [
                'notification_count' => $notifications->count(),
                'recipients' => count($recipients)
            ]
        ]);
    }

    /**
     * Get notification statistics for staff dashboard.
     */
    public function getStats(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $staff = Auth::user();
        
        $query = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $staff->id);
        
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $stats = $query->selectRaw('
            notification_type,
            priority,
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
        ')
        ->groupBy('notification_type', 'priority')
        ->get()
        ->groupBy('notification_type');
        
        // Get pending approvals count
        $staffRoles = $staff->exeatRoles->pluck('role')->toArray();
        $pendingApprovalsCount = 0;
        
        foreach ($staffRoles as $role) {
            $status = match ($role) {
                'cmd' => 'cmd_review',
                'secretary' => 'secretary_review',
                'dean' => 'dean_review',
                'hostel_admin' => 'hostel_signout',
                'security' => 'security_signout',
                default => null
            };
            
            if ($status) {
                $pendingApprovalsCount += ExeatRequest::where('status', $status)->count();
            }
        }
        
        $summary = [
            'total_notifications' => $query->count(),
            'unread_notifications' => ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
                ->where('recipient_id', $staff->id)
                ->where('is_read', false)
                ->count(),
            'pending_approvals' => $pendingApprovalsCount,
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

    /**
     * Get notification details.
     */
    public function show(int $notificationId): JsonResponse
    {
        $staff = Auth::user();
        
        $notification = ExeatNotification::where('id', $notificationId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $staff->id)
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
        $staff = Auth::user();
        
        $notifications = ExeatNotification::where('exeat_request_id', $exeatId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $staff->id)
            ->with(['exeatRequest.student'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Send reminder notification.
     */
    public function sendReminder(Request $request): JsonResponse
    {
        $request->validate([
            'exeat_id' => 'required|integer|exists:exeat_requests,id',
            'reminder_type' => 'required|string|in:approval_overdue,parent_consent_pending,return_reminder',
            'custom_message' => 'string|max:500'
        ]);

        $staff = Auth::user();
        
        // Check if staff has permission to send reminders
        if (!$staff->hasPermission('send_reminders')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to send reminders'
            ], 403);
        }
        
        $exeatRequest = ExeatRequest::find($request->exeat_id);
        
        $notifications = $this->notificationService->sendReminderNotification(
            $exeatRequest,
            $request->reminder_type
        );

        return response()->json([
            'success' => true,
            'message' => 'Reminder notification sent successfully',
            'data' => [
                'notification_count' => $notifications->count()
            ]
        ]);
    }

    /**
     * Send emergency notification.
     */
    public function sendEmergencyNotification(Request $request): JsonResponse
    {
        $request->validate([
            'exeat_id' => 'required|integer|exists:exeat_requests,id',
            'message' => 'required|string|max:1000'
        ]);

        $staff = Auth::user();
        
        // Check if staff has permission to send emergency notifications
        if (!$staff->hasPermission('send_emergency_notifications')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to send emergency notifications'
            ], 403);
        }
        
        $exeatRequest = ExeatRequest::find($request->exeat_id);
        
        $notifications = $this->notificationService->sendEmergencyNotification(
            $exeatRequest,
            $request->message
        );

        return response()->json([
            'success' => true,
            'message' => 'Emergency notification sent successfully',
            'data' => [
                'notification_count' => $notifications->count()
            ]
        ]);
    }
}