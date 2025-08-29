<?php

namespace App\Services;

use App\Models\ExeatNotification;
use App\Models\ExeatRequest;
use App\Models\Student;
use App\Models\Staff;
use App\Models\StudentContact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExeatNotificationService
{
    protected $deliveryService;

    public function __construct(
        NotificationDeliveryService $deliveryService
    ) {
        $this->deliveryService = $deliveryService;
    }

    /**
     * Create a notification for an exeat request.
     */
    public function createNotification(
        ExeatRequest $exeatRequest,
        array $recipients,
        string $type,
        string $title,
        string $message,
        string $priority = ExeatNotification::PRIORITY_MEDIUM
    ): Collection {
        $notifications = collect();

        foreach ($recipients as $recipient) {
            $notification = ExeatNotification::create([
                'exeat_request_id' => $exeatRequest->id,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['id'],
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
            ]);

            $notifications->push($notification);

            // Deliver notification synchronously
            $deliveryResults = $this->deliveryService->deliverNotificationSync($notification);

            // Log delivery results
            foreach ($deliveryResults as $method => $result) {
                if ($result['success']) {
                    Log::info("Notification delivered via {$method}", [
                        'notification_id' => $notification->id,
                        'recipient_type' => $notification->recipient_type,
                        'recipient_id' => $notification->recipient_id
                    ]);
                } else {
                    Log::warning("Failed to deliver notification via {$method}", [
                        'notification_id' => $notification->id,
                        'reason' => $result['reason']
                    ]);
                }
            }
        }

        return $notifications;
    }

    /**
     * Send stage change notification.
     */
    public function sendStageChangeNotification(
        ExeatRequest $exeatRequest,
        string $oldStatus,
        string $newStatus
    ): Collection {
        $recipients = $this->getStageChangeRecipients($exeatRequest, $newStatus);

        $title = "Exeat Request Status Updated";
        $message = $this->buildStageChangeMessage($exeatRequest, $oldStatus, $newStatus);

        return $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_STAGE_CHANGE,
            $title,
            $message,
            ExeatNotification::PRIORITY_MEDIUM
        );
    }

    /**
     * Send approval required notification.
     */
    public function sendApprovalRequiredNotification(
        ExeatRequest $exeatRequest,
        string $role
    ): Collection {
        $recipients = $this->getApprovalRecipients($exeatRequest, $role);

        $title = "Exeat Approval Required";
        $message = $this->buildApprovalRequiredMessage($exeatRequest, $role);

        return $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_APPROVAL_REQUIRED,
            $title,
            $message,
            ExeatNotification::PRIORITY_HIGH
        );
    }

    /**
     * Send reminder notification.
     */
    public function sendReminderNotification(
        ExeatRequest $exeatRequest,
        string $reminderType,
        array $customRecipients = null
    ): Collection {
        $recipients = $customRecipients ?? $this->getReminderRecipients($exeatRequest, $reminderType);

        $title = "Exeat Reminder";
        $message = $this->buildReminderMessage($exeatRequest, $reminderType);

        return $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_REMINDER,
            $title,
            $message,
            ExeatNotification::PRIORITY_MEDIUM
        );
    }

    /**
     * Send emergency notification.
     */
    public function sendEmergencyNotification(
        ExeatRequest $exeatRequest,
        string $message,
        array $customRecipients = null
    ): Collection {
        $recipients = $customRecipients ?? $this->getEmergencyRecipients($exeatRequest);

        $title = "URGENT: Exeat Emergency";

        return $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_EMERGENCY,
            $title,
            $message,
            ExeatNotification::PRIORITY_URGENT
        );
    }

    /**
     * Get recipients for stage change notifications.
     */
    protected function getStageChangeRecipients(ExeatRequest $exeatRequest, string $newStatus): array
    {
        $recipients = [];

        // Always notify the student
        $recipients[] = [
            'type' => ExeatNotification::RECIPIENT_STUDENT,
            'id' => $exeatRequest->student_id
        ];

        // Notify relevant staff based on new status
        switch ($newStatus) {
            case 'cmd_review':
                $recipients = array_merge($recipients, $this->getCMDStaff());
                break;

            case 'deputy-dean_review':
                $recipients = array_merge($recipients, $this->getDeputyDeanStaff());
                break;

            case 'parent_consent':
                // Parents do not receive notifications - removed parent recipients
                break;

            case 'dean_review':
                $recipients = array_merge($recipients, $this->getDeanStaff());
                break;

            case 'hostel_signout':
                $recipients = array_merge($recipients, $this->getHostelStaff($exeatRequest));
                break;

            case 'security_signout':
                $recipients = array_merge($recipients, $this->getSecurityStaff());
                break;
        }

        // Always notify admins
        $recipients = array_merge($recipients, $this->getAdminStaff());

        return $recipients;
    }

    /**
     * Get recipients for approval required notifications.
     */
    protected function getApprovalRecipients(ExeatRequest $exeatRequest, string $role): array
    {
        switch ($role) {
            case 'cmd':
                return $this->getCMDStaff();
            case 'deputy_dean':
                return $this->getDeputyDeanStaff();
            case 'dean':
                return $this->getDeanStaff();
            case 'hostel_admin':
                return $this->getHostelStaff($exeatRequest);
            case 'security':
                return $this->getSecurityStaff();
            default:
                return [];
        }
    }

    /**
     * Get recipients for reminder notifications.
     */
    protected function getReminderRecipients(ExeatRequest $exeatRequest, string $reminderType): array
    {
        switch ($reminderType) {
            case 'parent_consent_pending':
                return $this->getDeputyDeanStaff();
            case 'approval_overdue':
                return $this->getApprovalRecipients($exeatRequest, $exeatRequest->status);
            case 'return_reminder':
                return [[
                    'type' => ExeatNotification::RECIPIENT_STUDENT,
                    'id' => $exeatRequest->student_id
                ]];
            default:
                return [];
        }
    }

    /**
     * Get recipients for emergency notifications.
     */
    protected function getEmergencyRecipients(ExeatRequest $exeatRequest): array
    {
        return array_merge(
            [[
                'type' => ExeatNotification::RECIPIENT_STUDENT,
                'id' => $exeatRequest->student_id
            ]],
            $this->getAdminStaff(),
            $this->getDeanStaff(),
            $this->getDeputyDeanStaff()
        );
    }

    /**
     * Build stage change message.
     */
    protected function buildStageChangeMessage(ExeatRequest $exeatRequest, string $oldStatus, string $newStatus): string
    {
        $student = $exeatRequest->student;
        $statusMap = [
            'pending' => 'Pending Review',
            'cmd_review' => 'CMD Review',
            'deputy-dean_review' => 'Deputy Dean Review',
            'parent_consent' => 'Parent Consent',
            'dean_review' => 'Dean Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'hostel_signout' => 'Hostel Signout',
            'security_signout' => 'Security Signout',
            'out' => 'Student Out',
            'security_signin' => 'Security Sign-in',
            'hostel_signin' => 'Hostel Sign-in',
            'completed' => 'Completed'
        ];

        $studentInfo = '';
        if ($student) {
            $studentInfo = sprintf(
                "%s %s (Matric: %s)",
                $student->fname ?? '',
                $student->lname ?? '',
                $exeatRequest->matric_no ?? 'N/A'
            );
        } else {
            $studentInfo = 'Student (ID: ' . $exeatRequest->student_id . ')';
        }

        return sprintf(
            "Exeat request #%s for %s has been updated from '%s' to '%s'. Please check your dashboard for details.",
            $exeatRequest->id,
            $studentInfo,
            $statusMap[$oldStatus] ?? $oldStatus,
            $statusMap[$newStatus] ?? $newStatus
        );
    }

    /**
     * Build approval required message.
     */
    protected function buildApprovalRequiredMessage(ExeatRequest $exeatRequest, string $role): string
    {
        $student = $exeatRequest->student;
        $roleMap = [
            'cmd' => 'CMD',
            'deputy_dean' => 'Deputy Dean',
            'dean' => 'Dean',
            'hostel_admin' => 'Hostel Administrator',
            'security' => 'Security'
        ];

        $studentInfo = '';
        if ($student) {
            $studentInfo = sprintf(
                "%s %s (Matric: %s)",
                $student->fname ?? '',
                $student->lname ?? '',
                $exeatRequest->matric_no ?? 'N/A'
            );
        } else {
            $studentInfo = 'Student (ID: ' . $exeatRequest->student_id . ')';
        }

        return sprintf(
            "Exeat request #%s for %s requires your approval as %s. Please review and take action.",
            $exeatRequest->id,
            $studentInfo,
            $roleMap[$role] ?? $role
        );
    }

    /**
     * Build reminder message.
     */
    protected function buildReminderMessage(ExeatRequest $exeatRequest, string $reminderType): string
    {
        $student = $exeatRequest->student;

        switch ($reminderType) {
            case 'parent_consent_pending':
                return sprintf(
                    "Reminder: Parent consent is still pending for exeat request #%s for %s. Please respond as soon as possible.",
                    $exeatRequest->id,
                    $student->full_name ?? 'Student'
                );
            case 'approval_overdue':
                return sprintf(
                    "Reminder: Exeat request #%s for %s is overdue for approval. Please review and take action.",
                    $exeatRequest->id,
                    $student->full_name ?? 'Student'
                );
            case 'return_reminder':
                return sprintf(
                    "Reminder: Your exeat period is ending soon. Please ensure you return on time as specified in request #%s.",
                    $exeatRequest->id
                );
            default:
                return sprintf(
                    "Reminder regarding exeat request #%s for %s.",
                    $exeatRequest->id,
                    $student->full_name ?? 'Student'
                );
        }
    }

    // Helper methods to get staff by role
    protected function getCMDStaff(): array
    {
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'cmd');
        })->get()->map(function ($staff) {
            return [
                'type' => ExeatNotification::RECIPIENT_STAFF,
                'id' => $staff->id
            ];
        })->toArray();
    }

    protected function getDeputyDeanStaff(): array
    {
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'deputy_dean');
        })->get()->map(function ($staff) {
            return [
                'type' => ExeatNotification::RECIPIENT_STAFF,
                'id' => $staff->id
            ];
        })->toArray();
    }

    protected function getDeanStaff(): array
    {
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'dean');
        })->get()->map(function ($staff) {
            return [
                'type' => ExeatNotification::RECIPIENT_STAFF,
                'id' => $staff->id
            ];
        })->toArray();
    }

    protected function getHostelStaff(ExeatRequest $exeatRequest): array
    {
        // Get hostel admin for the student's hostel
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'hostel_admin');
        })->get()->map(function ($staff) {
            return [
                'type' => ExeatNotification::RECIPIENT_STAFF,
                'id' => $staff->id
            ];
        })->toArray();
    }

    protected function getSecurityStaff(): array
    {
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'security');
        })->get()->map(function ($staff) {
            return [
                'type' => ExeatNotification::RECIPIENT_STAFF,
                'id' => $staff->id
            ];
        })->toArray();
    }

    protected function getAdminStaff(): array
    {
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'admin');
        })->get()->map(function ($staff) {
            return [
                'type' => ExeatNotification::RECIPIENT_STAFF,
                'id' => $staff->id
            ];
        })->toArray();
    }



    /**
     * Mark notifications as read for a user.
     */
    public function markNotificationsAsRead(string $recipientType, int $recipientId, array $notificationIds = null): int
    {
        $query = ExeatNotification::forRecipient($recipientType, $recipientId)
            ->where('is_read', false);

        if ($notificationIds) {
            $query->whereIn('id', $notificationIds);
        }

        return $query->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(string $recipientType, int $recipientId): int
    {
        return ExeatNotification::forRecipient($recipientType, $recipientId)
            ->unread()
            ->count();
    }



    /**
     * Get notifications for a user with pagination.
     */
    public function getUserNotifications(
        string $recipientType,
        int $recipientId,
        int $perPage = 15,
        array $filters = []
    ) {
        $query = ExeatNotification::forRecipient($recipientType, $recipientId)
            ->with(['exeatRequest.student'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->withPriority($filters['priority']);
        }

        if (isset($filters['read_status'])) {
            if ($filters['read_status'] === 'unread') {
                $query->unread();
            } elseif ($filters['read_status'] === 'read') {
                $query->read();
            }
        }

        if (isset($filters['exeat_id'])) {
            $query->where('exeat_request_id', $filters['exeat_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Send submission confirmation to student.
     */
    public function sendSubmissionConfirmation(ExeatRequest $exeatRequest): void
    {
        $student = $exeatRequest->student;
        $recipients = [[
            'type' => ExeatNotification::RECIPIENT_STUDENT,
            'id' => $exeatRequest->student_id
        ]];

        $studentName = $student ? "{$student->fname} {$student->lname}" : 'Student';
        $title = 'Exeat Request Submitted Successfully';
        $message = sprintf(
            "Dear %s,\n\nYour exeat request has been submitted successfully and is now under review.\n\nRequest Details:\n- Reason: %s\n- Destination: %s\n- Departure Date: %s\n- Return Date: %s\n- Current Status: %s\n\nYou will receive notifications as your request progresses through the approval stages.\n\nThank you.",
            $studentName,
            $exeatRequest->reason,
            $exeatRequest->destination,
            \Carbon\Carbon::parse($exeatRequest->departure_date)->format('M d, Y'),
            \Carbon\Carbon::parse($exeatRequest->return_date)->format('M d, Y'),
            str_replace('_', ' ', ucwords($exeatRequest->status, '_'))
        );

        $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_REQUEST_SUBMITTED,
            $title,
            $message,
            ExeatNotification::PRIORITY_MEDIUM
        );
    }

    /**
     * Send rejection notification to student.
     */
    public function sendRejectionNotification(ExeatRequest $exeatRequest, ?string $comment = null): void
    {
        $student = $exeatRequest->student;
        $recipients = [[
            'type' => ExeatNotification::RECIPIENT_STUDENT,
            'id' => $exeatRequest->student_id
        ]];

        $studentName = $student ? "{$student->fname} {$student->lname}" : 'Student';
        $title = 'Exeat Request Rejected';

        $message = sprintf(
            "Dear %s,\n\nWe regret to inform you that your exeat request has been rejected.\n\nRequest Details:\n- Reason: %s\n- Destination: %s\n- Departure Date: %s\n- Return Date: %s",
            $studentName,
            $exeatRequest->reason,
            $exeatRequest->destination,
            \Carbon\Carbon::parse($exeatRequest->departure_date)->format('M d, Y'),
            \Carbon\Carbon::parse($exeatRequest->return_date)->format('M d, Y')
        );

        if ($comment) {
            $message .= "\n\nReason for rejection: {$comment}";
        }

        $message .= "\n\nIf you have any questions, please contact the appropriate office for clarification.\n\nThank you.";

        $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_REJECTION,
            $title,
            $message,
            ExeatNotification::PRIORITY_HIGH
        );
    }
}
