<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ExeatRequest;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class InAppNotificationService
{
    /**
     * Create an in-app notification for a user.
     */
    public function createNotification(
        Model $notifiable,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?ExeatRequest $exeatRequest = null
    ): Notification {
        $notificationData = [
            'title' => $title,
            'message' => $message,
            'action_url' => $data['action_url'] ?? null,
            'icon' => $data['icon'] ?? $this->getDefaultIcon($type),
            'priority' => $data['priority'] ?? 'medium',
            'metadata' => $data['metadata'] ?? []
        ];

        $notification = Notification::create([
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'type' => $type,
            'data' => $notificationData,
            // Legacy fields for backward compatibility
            'user_id' => $notifiable->id,
            'exeat_request_id' => $exeatRequest?->id,
            'channel' => 'in_app',
            'status' => 'sent',
            'message' => $message,
            'sent_at' => now()
        ]);

        Log::info('In-app notification created', [
            'notification_id' => $notification->id,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'type' => $type
        ]);

        return $notification;
    }

    /**
     * Send exeat stage change notification.
     */
    public function sendExeatStageChangeNotification(
        ExeatRequest $exeatRequest,
        string $oldStatus,
        string $newStatus
    ): Collection {
        $notifications = collect();
        
        // Notify student
        $student = $exeatRequest->student;
        if ($student) {
            $title = "Exeat Request Status Updated";
            $message = "Your exeat request status has changed from {$oldStatus} to {$newStatus}";
            $actionUrl = "/student/exeat-requests/{$exeatRequest->id}";
            
            $notification = $this->createNotification(
                $student,
                'exeat_status_change',
                $title,
                $message,
                [
                    'action_url' => $actionUrl,
                    'icon' => 'status-change',
                    'priority' => 'high',
                    'metadata' => [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'exeat_id' => $exeatRequest->id
                    ]
                ],
                $exeatRequest
            );
            
            $notifications->push($notification);
        }

        return $notifications;
    }

    /**
     * Send approval required notification to staff.
     */
    public function sendApprovalRequiredNotification(
        ExeatRequest $exeatRequest,
        string $role
    ): Collection {
        $notifications = collect();
        
        // Get staff members with the required role
        $staffMembers = Staff::whereHas('exeat_roles', function ($query) use ($role) {
            $query->where('name', $role);
        })->get();

        foreach ($staffMembers as $staff) {
            $title = "Exeat Approval Required";
            $message = "An exeat request requires your approval as {$role}";
            $actionUrl = "/staff/exeat-requests/{$exeatRequest->id}";
            
            $notification = $this->createNotification(
                $staff,
                'approval_required',
                $title,
                $message,
                [
                    'action_url' => $actionUrl,
                    'icon' => 'approval-required',
                    'priority' => 'high',
                    'metadata' => [
                        'role' => $role,
                        'exeat_id' => $exeatRequest->id,
                        'student_name' => $exeatRequest->student?->fname . ' ' . $exeatRequest->student?->lname
                    ]
                ],
                $exeatRequest
            );
            
            $notifications->push($notification);
        }

        return $notifications;
    }

    /**
     * Send reminder notification.
     */
    public function sendReminderNotification(
        Model $notifiable,
        string $reminderType,
        string $message,
        ?ExeatRequest $exeatRequest = null
    ): Notification {
        $title = $this->getReminderTitle($reminderType);
        $actionUrl = $this->getReminderActionUrl($reminderType, $exeatRequest);
        
        return $this->createNotification(
            $notifiable,
            'reminder',
            $title,
            $message,
            [
                'action_url' => $actionUrl,
                'icon' => 'reminder',
                'priority' => 'medium',
                'metadata' => [
                    'reminder_type' => $reminderType,
                    'exeat_id' => $exeatRequest?->id
                ]
            ],
            $exeatRequest
        );
    }

    /**
     * Send emergency notification.
     */
    public function sendEmergencyNotification(
        Collection $notifiables,
        string $title,
        string $message,
        ?ExeatRequest $exeatRequest = null
    ): Collection {
        $notifications = collect();
        
        foreach ($notifiables as $notifiable) {
            $notification = $this->createNotification(
                $notifiable,
                'emergency',
                $title,
                $message,
                [
                    'icon' => 'emergency',
                    'priority' => 'urgent',
                    'metadata' => [
                        'is_emergency' => true,
                        'exeat_id' => $exeatRequest?->id
                    ]
                ],
                $exeatRequest
            );
            
            $notifications->push($notification);
        }

        return $notifications;
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Notification $notification): bool
    {
        if ($notification->read_at) {
            return true; // Already read
        }

        $notification->markAsRead();
        
        Log::info('Notification marked as read', [
            'notification_id' => $notification->id,
            'notifiable_type' => $notification->notifiable_type,
            'notifiable_id' => $notification->notifiable_id
        ]);

        return true;
    }

    /**
     * Mark multiple notifications as read.
     */
    public function markMultipleAsRead(array $notificationIds, Model $notifiable): int
    {
        $count = Notification::where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->id)
            ->whereIn('id', $notificationIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Log::info('Multiple notifications marked as read', [
            'count' => $count,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id
        ]);

        return $count;
    }

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCount(Model $notifiable): int
    {
        return Notification::where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get notifications for a user with pagination.
     */
    public function getUserNotifications(
        Model $notifiable,
        int $perPage = 20,
        ?string $type = null,
        ?bool $unreadOnly = null
    ) {
        $query = Notification::where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->id)
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        if ($unreadOnly === true) {
            $query->whereNull('read_at');
        } elseif ($unreadOnly === false) {
            $query->whereNotNull('read_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get default icon for notification type.
     */
    private function getDefaultIcon(string $type): string
    {
        return match ($type) {
            'exeat_status_change' => 'status-change',
            'approval_required' => 'approval-required',
            'reminder' => 'reminder',
            'emergency' => 'emergency',
            'parent_consent' => 'parent-consent',
            'medical_review' => 'medical',
            default => 'notification'
        };
    }

    /**
     * Get reminder title based on type.
     */
    private function getReminderTitle(string $reminderType): string
    {
        return match ($reminderType) {
            'parent_consent_pending' => 'Parent Consent Pending',
            'approval_overdue' => 'Approval Overdue',
            'return_reminder' => 'Return Reminder',
            'medical_review_required' => 'Medical Review Required',
            default => 'Reminder'
        };
    }

    /**
     * Get action URL for reminder type.
     */
    private function getReminderActionUrl(string $reminderType, ?ExeatRequest $exeatRequest = null): ?string
    {
        if (!$exeatRequest) {
            return null;
        }

        return match ($reminderType) {
            'parent_consent_pending' => "/staff/exeat-requests/{$exeatRequest->id}",
            'approval_overdue' => "/staff/exeat-requests/{$exeatRequest->id}",
            'return_reminder' => "/student/exeat-requests/{$exeatRequest->id}",
            'medical_review_required' => "/cmd/exeat-requests/{$exeatRequest->id}",
            default => "/exeat-requests/{$exeatRequest->id}"
        };
    }
}