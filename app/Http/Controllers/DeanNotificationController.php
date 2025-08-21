<?php

namespace App\Http\Controllers;

use App\Models\ExeatNotification;
use App\Models\ExeatRequest;
use App\Services\ExeatNotificationService;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class DeanNotificationController extends Controller
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
        $this->middleware('role:dean');
    }

    /**
     * Get dean's notifications with filtering.
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
            'read_status' => Rule::in(['read', 'unread']),
            'exeat_id' => 'integer|exists:exeat_requests,id',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $dean = Auth::user();
        $perPage = $request->get('per_page', 20);
        
        $query = ExeatNotification::with(['exeatRequest.student'])
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->has('type')) {
            $query->where('notification_type', $request->type);
        }
        
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        
        if ($request->has('read_status')) {
            $isRead = $request->read_status === 'read';
            $query->where('is_read', $isRead);
        }
        
        if ($request->has('exeat_id')) {
            $query->where('exeat_request_id', $request->exeat_id);
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
     * Get unread notification count.
     */
    public function getUnreadCount(): JsonResponse
    {
        $dean = Auth::user();
        
        $count = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->where('is_read', false)
            ->count();
        
        $urgentCount = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->where('is_read', false)
            ->where('priority', ExeatNotification::PRIORITY_URGENT)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_unread' => $count,
                'urgent_unread' => $urgentCount
            ]
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, $notificationId): JsonResponse
    {
        $dean = Auth::user();
        
        $notification = ExeatNotification::where('id', $notificationId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->first();
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        
        $this->notificationService->markAsRead($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $dean = Auth::user();
        
        $count = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'data' => [
                'marked_count' => $count
            ]
        ]);
    }

    /**
     * Get dean's notification preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $dean = Auth::user();
        
        $preferences = $this->preferenceService->getUserPreferences('staff', $dean->id);
        
        if (!$preferences) {
            $preferences = $this->preferenceService->createDefaultPreferences('staff', $dean->id);
        }

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Update dean's notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'notification_type' => Rule::in(['all', 'urgent', 'none']),
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'quiet_hours_start' => 'date_format:H:i',
            'quiet_hours_end' => 'date_format:H:i'
        ]);

        $dean = Auth::user();
        
        $updates = $request->only([
            'notification_type',
            'in_app_enabled',
            'email_enabled',
            'sms_enabled',
            'whatsapp_enabled',
            'quiet_hours_start',
            'quiet_hours_end'
        ]);
        
        $preference = $this->preferenceService->updateUserPreferences('staff', $dean->id, $updates);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'data' => $preference
        ]);
    }

    /**
     * Get pending approvals requiring dean's attention.
     */
    public function getPendingApprovals(): JsonResponse
    {
        $dean = Auth::user();
        
        $pendingApprovals = ExeatNotification::with(['exeatRequest.student'])
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->where('notification_type', ExeatNotification::TYPE_APPROVAL_REQUIRED)
            ->where('is_read', false)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // Group by priority
        $groupedApprovals = $pendingApprovals->groupBy('priority');

        return response()->json([
            'success' => true,
            'data' => [
                'total_pending' => $pendingApprovals->count(),
                'by_priority' => $groupedApprovals,
                'urgent_count' => $pendingApprovals->where('priority', ExeatNotification::PRIORITY_URGENT)->count(),
                'high_count' => $pendingApprovals->where('priority', ExeatNotification::PRIORITY_HIGH)->count()
            ]
        ]);
    }

    /**
     * Send notification to students under dean's supervision.
     */
    public function sendNotificationToStudents(Request $request): JsonResponse
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'priority' => Rule::in([
                ExeatNotification::PRIORITY_LOW,
                ExeatNotification::PRIORITY_MEDIUM,
                ExeatNotification::PRIORITY_HIGH,
                ExeatNotification::PRIORITY_URGENT
            ]),
            'exeat_id' => 'nullable|integer|exists:exeat_requests,id'
        ]);

        $dean = Auth::user();
        
        // Verify dean has authority over these students (implement based on your business logic)
        // For now, we'll allow all deans to send notifications to any student
        
        $recipients = collect($request->student_ids)->map(function ($studentId) {
            return [
                'type' => ExeatNotification::RECIPIENT_STUDENT,
                'id' => $studentId
            ];
        })->toArray();
        
        $exeatRequest = $request->exeat_id 
            ? ExeatRequest::find($request->exeat_id) 
            : new ExeatRequest(['id' => 0]);
        
        $notifications = $this->notificationService->createNotification(
            $exeatRequest,
            $recipients,
            'dean_announcement',
            $request->title,
            $request->message,
            $request->get('priority', ExeatNotification::PRIORITY_MEDIUM)
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification sent to students successfully',
            'data' => [
                'notification_count' => $notifications->count(),
                'recipients' => count($recipients)
            ]
        ]);
    }

    /**
     * Send reminder notifications for pending exeat requests.
     */
    public function sendReminders(Request $request): JsonResponse
    {
        $request->validate([
            'exeat_ids' => 'required|array|min:1',
            'exeat_ids.*' => 'integer|exists:exeat_requests,id',
            'reminder_message' => 'nullable|string|max:500'
        ]);

        $dean = Auth::user();
        $sentCount = 0;
        
        foreach ($request->exeat_ids as $exeatId) {
            $exeatRequest = ExeatRequest::with('student')->find($exeatId);
            
            if ($exeatRequest) {
                $message = $request->get('reminder_message', 
                    "This is a reminder regarding your exeat request #{$exeatRequest->id}. Please take necessary action."
                );
                
                $this->notificationService->sendReminderNotification(
                    $exeatRequest,
                    $message,
                    ExeatNotification::PRIORITY_MEDIUM
                );
                
                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sent {$sentCount} reminder notifications",
            'data' => [
                'sent_count' => $sentCount
            ]
        ]);
    }

    /**
     * Send emergency notifications.
     */
    public function sendEmergencyNotification(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_type' => ['required', Rule::in(['students', 'staff', 'all'])],
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'exeat_id' => 'nullable|integer|exists:exeat_requests,id'
        ]);

        $dean = Auth::user();
        
        // Determine recipients based on type
        $recipients = [];
        
        if ($request->recipient_type === 'all') {
            // Send to all students and staff (implement based on your business logic)
            $recipients = collect()
                ->merge(\App\Models\Student::pluck('id')->map(fn($id) => ['type' => ExeatNotification::RECIPIENT_STUDENT, 'id' => $id]))
                ->merge(\App\Models\Staff::pluck('id')->map(fn($id) => ['type' => ExeatNotification::RECIPIENT_STAFF, 'id' => $id]))
                ->toArray();
        } elseif ($request->recipient_type === 'students') {
            if ($request->has('recipient_ids')) {
                $recipients = collect($request->recipient_ids)->map(fn($id) => ['type' => ExeatNotification::RECIPIENT_STUDENT, 'id' => $id])->toArray();
            } else {
                $recipients = \App\Models\Student::pluck('id')->map(fn($id) => ['type' => ExeatNotification::RECIPIENT_STUDENT, 'id' => $id])->toArray();
            }
        } elseif ($request->recipient_type === 'staff') {
            if ($request->has('recipient_ids')) {
                $recipients = collect($request->recipient_ids)->map(fn($id) => ['type' => ExeatNotification::RECIPIENT_STAFF, 'id' => $id])->toArray();
            } else {
                $recipients = \App\Models\Staff::pluck('id')->map(fn($id) => ['type' => ExeatNotification::RECIPIENT_STAFF, 'id' => $id])->toArray();
            }
        }
        
        $exeatRequest = $request->exeat_id 
            ? ExeatRequest::find($request->exeat_id) 
            : new ExeatRequest(['id' => 0]);
        
        $notifications = $this->notificationService->sendEmergencyNotification(
            $exeatRequest,
            $recipients,
            $request->title,
            $request->message
        );

        return response()->json([
            'success' => true,
            'message' => 'Emergency notification sent successfully',
            'data' => [
                'notification_count' => $notifications->count(),
                'recipients' => count($recipients)
            ]
        ]);
    }

    /**
     * Get dean's notification statistics.
     */
    public function getStats(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $dean = Auth::user();
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        
        $stats = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                COUNT(*) as total_notifications,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_notifications,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_notifications,
                SUM(CASE WHEN priority = "urgent" THEN 1 ELSE 0 END) as urgent_notifications,
                SUM(CASE WHEN notification_type = "approval_required" THEN 1 ELSE 0 END) as approval_notifications
            ')
            ->first();
        
        $typeBreakdown = ExeatNotification::where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('notification_type, COUNT(*) as count')
            ->groupBy('notification_type')
            ->pluck('count', 'notification_type');

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => $stats,
                'by_type' => $typeBreakdown,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]
        ]);
    }

    /**
     * Get notification details.
     */
    public function show($notificationId): JsonResponse
    {
        $dean = Auth::user();
        
        $notification = ExeatNotification::with(['exeatRequest.student'])
            ->where('id', $notificationId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->first();
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }

    /**
     * Get notifications for a specific exeat request.
     */
    public function getExeatNotifications($exeatId): JsonResponse
    {
        $dean = Auth::user();
        
        $notifications = ExeatNotification::with(['exeatRequest.student'])
            ->where('exeat_request_id', $exeatId)
            ->where('recipient_type', ExeatNotification::RECIPIENT_STAFF)
            ->where('recipient_id', $dean->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }
}