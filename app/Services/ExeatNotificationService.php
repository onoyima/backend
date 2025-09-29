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
     * Send notification about exeat request modification.
     */
    public function sendExeatModifiedNotification(ExeatRequest $exeatRequest, string $message): void
    {
        try {
            // Notify the student in-app only (email removed to reduce excessive notifications)
            $this->createNotification(
                $exeatRequest,
                [['type' => 'App\\Models\\Student', 'id' => $exeatRequest->student_id]],
                'exeat_modified',
                'Exeat Request Modified',
                $message,
                ExeatNotification::PRIORITY_HIGH
            );
        } catch (\Exception $e) {
            Log::error('Failed to send exeat modification notification', [
                'exeat_id' => $exeatRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Create SMS notification for exeat modification.
     */
    protected function createExeatModifiedSmsNotification(ExeatRequest $exeatRequest, Student $student, string $message): ExeatNotification
    {
        $smsContent = "EXEAT ALERT: {$message} Please check your exeat dashboard for details.";

        return new ExeatNotification([
            'exeat_request_id' => $exeatRequest->id,
            'recipient_type' => get_class($student),
            'recipient_id' => $student->id,
            'notification_type' => 'exeat_modified_sms',
            'title' => 'Exeat Modified',
            'message' => $smsContent,
            'priority' => ExeatNotification::PRIORITY_HIGH,
        ]);
    }
    
    /**
     * Send notification about debt recalculation.
     *
     * @param Student $student
     * @param ExeatRequest $exeatRequest
     * @param float $additionalAmount
     * @param float $totalAmount
     * @return void
     */
    public function sendDebtRecalculationNotification(Student $student, ExeatRequest $exeatRequest, float $additionalAmount, float $totalAmount): void
    {
        try {
            $message = "Your exeat debt has been recalculated due to a change in your return date. Additional amount: ₦{$additionalAmount}. Total debt: ₦{$totalAmount}.";
            
            // Notify the student in-app only (email and SMS removed for cost optimization)
            $this->createNotification(
                $exeatRequest,
                [['type' => 'App\\Models\\Student', 'id' => $student->id]],
                'debt_recalculated',
                'Exeat Debt Recalculated',
                $message,
                ExeatNotification::PRIORITY_HIGH
            );
        } catch (\Exception $e) {
            Log::error('Failed to send debt recalculation notification', [
                'exeat_id' => $exeatRequest->id,
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Create SMS notification for debt recalculation.
     *
     * @param ExeatRequest $exeatRequest
     * @param Student $student
     * @param float $additionalAmount
     * @param float $totalAmount
     * @return ExeatNotification
     */
    protected function createDebtRecalculationSmsNotification(ExeatRequest $exeatRequest, Student $student, float $additionalAmount, float $totalAmount): ExeatNotification
    {
        $smsContent = "EXEAT DEBT ALERT: Your debt has been recalculated. Additional: ₦{$additionalAmount}. Total: ₦{$totalAmount}. Check dashboard for details.";

        return new ExeatNotification([
            'exeat_request_id' => $exeatRequest->id,
            'recipient_type' => get_class($student),
            'recipient_id' => $student->id,
            'notification_type' => 'debt_recalculated_sms',
            'title' => 'Debt Recalculated',
            'message' => $smsContent,
            'priority' => ExeatNotification::PRIORITY_HIGH,
        ]);
    }

    /**
     * Send notification about student debt.
     */
    public function sendDebtNotification(Student $student, ExeatRequest $exeatRequest, float $amount): void
    {
        try {
            $message = "You have incurred a debt of ₦{$amount} due to late return from your exeat.";

            // Notify the student in-app only (email and SMS removed for cost optimization)
            $this->createNotification(
                $exeatRequest,
                [['type' => 'student', 'id' => $student->id]],
                'student_debt_created',
                'Exeat Debt Notification',
                $message,
                ExeatNotification::PRIORITY_HIGH
            );
        } catch (\Exception $e) {
            Log::error('Failed to send debt notification', [
                'student_id' => $student->id,
                'exeat_id' => $exeatRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Create SMS notification for student debt.
     */
    protected function createDebtSmsNotification(ExeatRequest $exeatRequest, Student $student, float $amount): ExeatNotification
    {
        $smsContent = "EXEAT DEBT ALERT: You have incurred a debt of ₦{$amount} due to late return from exeat #{$exeatRequest->id}. Please make payment and submit proof through the exeat system.";

        return new ExeatNotification([
            'exeat_request_id' => $exeatRequest->id,
            'recipient_type' => get_class($student),
            'recipient_id' => $student->id,
            'notification_type' => 'student_debt_sms',
            'title' => 'Exeat Debt Alert',
            'message' => $smsContent,
            'priority' => ExeatNotification::PRIORITY_HIGH,
        ]);
    }

    /**
     * Send notification about student debt clearance.
     * Email notifications removed - only in-app notifications sent.
     */
    public function sendDebtClearanceNotification(Student $student, ExeatRequest $exeatRequest): void
    {
        try {
            $message = "Your exeat debt has been cleared successfully.";

            // Notify the student in-app only (email removed to reduce notification volume)
            $this->createNotification(
                $exeatRequest,
                [['type' => 'App\\Models\\Student', 'id' => $student->id]],
                'student_debt_cleared',
                'Exeat Debt Cleared',
                $message,
                ExeatNotification::PRIORITY_MEDIUM
            );

            Log::info('Debt clearance notification sent (in-app only)', [
                'student_id' => $student->id,
                'exeat_id' => $exeatRequest->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send debt clearance notification', [
                'student_id' => $student->id,
                'exeat_id' => $exeatRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Create SMS notification for student debt clearance.
     */
    protected function createDebtClearanceSmsNotification(ExeatRequest $exeatRequest, Student $student): ExeatNotification
    {
        $smsContent = "EXEAT NOTIFICATION: Your debt for exeat #{$exeatRequest->id} has been cleared successfully. Thank you.";

        return new ExeatNotification([
            'exeat_request_id' => $exeatRequest->id,
            'recipient_type' => get_class($student),
            'recipient_id' => $student->id,
            'notification_type' => 'student_debt_cleared_sms',
            'title' => 'Exeat Debt Cleared',
            'message' => $smsContent,
            'priority' => ExeatNotification::PRIORITY_MEDIUM,
        ]);
    }

    /**
     * Create SMS notification for staff comment (raw comment only).
     */
    protected function createStaffCommentSmsNotification(ExeatRequest $exeatRequest, Student $student, string $comment): ExeatNotification
    {
        return new ExeatNotification([
            'exeat_request_id' => $exeatRequest->id,
            'recipient_type' => get_class($student),
            'recipient_id' => $student->id,
            'notification_type' => 'staff_comment_sms',
            'title' => 'Staff Comment',
            'message' => $comment, // Raw comment only, no template
            'priority' => ExeatNotification::PRIORITY_HIGH,
            'data' => [
                'status' => $exeatRequest->status,
                'delivery_type' => 'sms_only'
            ]
        ]);
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
        string $priority = ExeatNotification::PRIORITY_MEDIUM,
        array $data = []
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
                'data' => $data,
            ]);

            $notifications->push($notification);

            // Deliver notification synchronously for in-app only
            // For staff comments, we'll handle email and SMS separately
            $deliveryResults = [];
            if ($type !== ExeatNotification::TYPE_STAFF_COMMENT) {
                $deliveryResults = $this->deliveryService->deliverNotificationSync($notification);
            }

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
     * Send staff comment notification to a specific student via email and SMS.
     * The notification is only sent to the student associated with the exeat request.
     * Email uses full template, SMS uses only the raw comment for character efficiency.
     */
    public function sendStaffCommentNotification(
        ExeatRequest $exeatRequest,
        Staff $staff,
        string $comment
    ): Collection {
        // Only send to the student recipient
        $recipients = [
            [
                'type' => 'student', // Use simple string instead of class constant
                'id' => $exeatRequest->student_id
            ]
        ];

        // Get student details
        $student = \App\Models\Student::find($exeatRequest->student_id);
        $studentName = "{$student->fname} {$student->lname}";
        $staffName = "{$staff->fname} {$staff->lname}";

        // Get staff role based on exeat workflow
        $staffRoles = $staff->exeatRoles()->with('role')->get();
        $roleDisplayNames = $staffRoles->pluck('role.display_name')->toArray();

        // Map status names to more user-friendly titles
        $statusTitles = [
            'cmd' => 'Chief Medical Director',
            'secretary' => 'Secretary of Students Affairs',
            'dean' => 'Dean of Students Affairs',
            'dean2' => 'Dean of Students Affairs',
            'hostel_admin' => 'Hostel Administrator',
            'security' => 'Security Officer',
            'admin' => 'Administrator'
        ];

        // Get the appropriate office title based on staff roles
        $staffOffice = 'Staff';
        foreach ($staffRoles as $roleAssignment) {
            $roleName = $roleAssignment->role->name;
            if (isset($statusTitles[$roleName])) {
                $staffOffice = $statusTitles[$roleName];
                break;
            }
        }

        // Create full template for email (with proper formatting)
        $emailMessage = $comment;
        if (!str_contains($comment, "signed: {$staffName}")) {
            $emailMessage .= "\n\nThank you,\nsigned: {$staffName}, {$staffOffice}";
        }

        // SMS message is just the raw comment (no template, no student name)
        $smsMessage = $comment;

        $title = "Comment from {$staffName}";

        // Create the in-app notification with email message (full template)
        $notifications = $this->createNotification(
            $exeatRequest,
            $recipients,
            ExeatNotification::TYPE_STAFF_COMMENT,
            $title,
            $emailMessage,
            ExeatNotification::PRIORITY_HIGH,
            [
                'status' => $exeatRequest->status,
                'staff_id' => $staff->id,
                'staff_name' => $staffName,
                'staff_office' => $staffOffice,
                'raw_comment' => $comment
            ]
        );

        // Handle email and SMS delivery separately with different messages
        foreach ($notifications as $notification) {
            // Deliver email with full template
            $this->deliveryService->deliverNotification($notification, 'email');

            // Create a separate SMS notification with just the raw comment
            $smsNotification = $this->createStaffCommentSmsNotification(
                $exeatRequest, 
                $student, 
                $smsMessage
            );
            
            // Deliver SMS with raw comment only
            $this->deliveryService->deliverNotification($smsNotification, 'sms');

            // Add email to notification's delivery methods for tracking
            if (!in_array('email', $notification->delivery_methods ?? [])) {
                $deliveryMethods = $notification->delivery_methods ?? [];
                $deliveryMethods[] = 'email';
                $notification->update(['delivery_methods' => $deliveryMethods]);
            }
        }

        return $notifications;
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

            case 'secretary_review':
                $recipients = array_merge($recipients, $this->getSecretaryStaff());
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
            case 'secretary':
                return $this->getSecretaryStaff();
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
                return $this->getSecretaryStaff();
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
            $this->getSecretaryStaff()
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
            'secretary_review' => 'Secretary Review',
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
            "Your exeat request status has changed from '%s' to '%s'. Please check your dashboard for more details.",
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
            'secretary' => 'Secretary',
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

    protected function getSecretaryStaff(): array
    {
        return Staff::whereHas('exeat_roles', function ($query) {
            $query->where('name', 'secretary');
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
        // Get specific hostel admin for the student's accommodation
        $studentAccommodation = $exeatRequest->student_accommodation;
        
        if (!empty($studentAccommodation)) {
            // Find the hostel by name
            $hostel = \App\Models\VunaAccomodation::where('name', $studentAccommodation)->first();
            
            if ($hostel) {
                // Get staff assigned to this specific hostel
                $assignedStaff = \App\Models\HostelAdminAssignment::where('vuna_accomodation_id', $hostel->id)
                    ->where('status', 'active')
                    ->with('staff')
                    ->get();
                
                if ($assignedStaff->isNotEmpty()) {
                    return $assignedStaff->map(function ($assignment) {
                        return [
                            'type' => 'staff',
                            'id' => $assignment->staff_id
                        ];
                    })->toArray();
                }
            }
        }
        
        // Fallback: Get all hostel admins if no specific assignment found
        return Staff::whereHas('exeatRoles', function ($query) {
            $query->whereHas('role', function ($roleQuery) {
                $roleQuery->where('name', 'hostel_admin');
            });
        })->get()->map(function ($staff) {
            return [
                'type' => 'staff',
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
            "Your exeat request has been submitted successfully. Current status: %s. Please check your dashboard for more details.",
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

        $message = "Your exeat request has been rejected.";

        if ($comment) {
            $message .= " Reason: {$comment}.";
        }

        $message .= " Please check your dashboard for more details.";

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
