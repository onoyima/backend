<?php

namespace App\Services;

use App\Models\ExeatRequest;
use App\Models\ExeatApproval;
use App\Models\ParentConsent;
use App\Models\AuditLog;
use App\Models\SecuritySignout;
use App\Models\HostelSignout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;
use Carbon\Carbon;
use App\Services\ExeatNotificationService;
use App\Services\NotificationDeliveryService;
use App\Services\UrlShortenerService;

class ExeatWorkflowService
{
    protected $notificationService;
    protected $deliveryService;
    protected $urlShortenerService;

    public function __construct(ExeatNotificationService $notificationService, NotificationDeliveryService $deliveryService, UrlShortenerService $urlShortenerService)
    {
        $this->notificationService = $notificationService;
        $this->deliveryService = $deliveryService;
        $this->urlShortenerService = $urlShortenerService;
    }
    public function approve(ExeatRequest $exeatRequest, ExeatApproval $approval, $comment = null)
    {
        $oldStatus = $exeatRequest->status;

        $approval->status = 'approved';
        $approval->comment = $comment;
        $approval->save();

        // Handle special actions for security and hostel stages
        $this->handleSpecialStageActions($exeatRequest, $approval, $oldStatus);

        $this->advanceStage($exeatRequest);

        // Check if dean has approved (status changed from dean_review to hostel_signout)
        // and send weekdays notification if needed
        if ($oldStatus === 'dean_review' && $exeatRequest->status === 'hostel_signout') {
            $exeatRequest->checkWeekdaysAndNotify();
        }

        $this->createAuditLog(
            $exeatRequest,
            $approval->staff_id,
            $exeatRequest->student_id,
            'approve',
            "Status changed from {$oldStatus} to {$exeatRequest->status}",
            $comment
        );

        Log::info('WorkflowService: Exeat approved', ['exeat_id' => $exeatRequest->id, 'approval_id' => $approval->id]);

        return $exeatRequest;
    }

    public function reject(ExeatRequest $exeatRequest, ExeatApproval $approval, $comment = null)
    {
        $oldStatus = $exeatRequest->status;

        $approval->status = 'rejected';
        $approval->comment = $comment;
        $approval->save();

        $exeatRequest->status = 'rejected';
        $exeatRequest->save();

        // Send rejection notification to student
        try {
            $this->notificationService->sendRejectionNotification($exeatRequest, $comment);
        } catch (\Exception $e) {
            Log::error('Failed to send rejection notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        $this->createAuditLog(
            $exeatRequest,
            $approval->staff_id,
            $exeatRequest->student_id,
            'reject',
            "Status changed from {$oldStatus} to rejected",
            $comment
        );

        Log::info('WorkflowService: Exeat rejected', ['exeat_id' => $exeatRequest->id, 'approval_id' => $approval->id]);

        return $exeatRequest;
    }

    protected function advanceStage(ExeatRequest $exeatRequest)
    {
        $oldStatus = $exeatRequest->status;
        $hostelEnabled = (bool) config('app.hostel_stages_enabled');

        switch ($exeatRequest->status) {
            case 'pending':
                $exeatRequest->status = $exeatRequest->is_medical ? 'cmd_review' : 'secretary_review';
                break;
            case 'cmd_review':
                $exeatRequest->status = 'secretary_review';
                break;
            case 'secretary_review':
                $exeatRequest->status = 'parent_consent';
                break;
            case 'parent_consent':
                // Check category type for special workflows
                $categoryName = $exeatRequest->category ? strtolower($exeatRequest->category->name) : '';
                $isDailyCategory = $categoryName === 'daily' || $categoryName === 'daily_medical';
                $isHolidayCategory = $categoryName === 'holiday';
                $isMedical = $exeatRequest->is_medical;

                if ($isDailyCategory) {
                    // ALL daily exeats (medical and non-medical): skip dean_review and go directly to hostel_signout
                    $exeatRequest->status = 'hostel_signout';
                    Log::info('WorkflowService: Daily exeat skipping dean_review', [
                        'exeat_id' => $exeatRequest->id,
                        'category' => $exeatRequest->category->name,
                        'is_medical' => $isMedical,
                        'type' => $isMedical ? 'daily_medical' : 'daily_non_medical',
                        'skipped_to' => 'hostel_signout'
                    ]);
                } else {
                    // All non-daily categories: go through dean_review
                    $exeatRequest->status = 'dean_review';
                    Log::info('WorkflowService: Non-daily exeat proceeding through dean_review', [
                        'exeat_id' => $exeatRequest->id,
                        'category' => $exeatRequest->category ? $exeatRequest->category->name : 'unknown',
                        'is_medical' => $isMedical
                    ]);
                }
                break;
            case 'dean_review':
                // Check if it's a holiday category - skip hostel steps
                $categoryName = $exeatRequest->category ? strtolower($exeatRequest->category->name) : '';
                $isHolidayCategory = $categoryName === 'holiday';

                if ($isHolidayCategory) {
                    // Holiday exeats: skip hostel steps and go directly to security_signout
                    $exeatRequest->status = 'security_signout';
                    Log::info('WorkflowService: Holiday exeat skipping hostel steps', [
                        'exeat_id' => $exeatRequest->id,
                        'category' => $exeatRequest->category->name,
                        'skipped_to' => 'security_signout'
                    ]);
                } else {
                    $exeatRequest->status = $hostelEnabled ? 'hostel_signout' : 'security_signout';
                }
                break;
            case 'hostel_signout':
                $exeatRequest->status = 'security_signout';
                break;
            case 'security_signout':
                $exeatRequest->status = 'security_signin';
                break;
            case 'security_signin':
                $exeatRequest->status = $hostelEnabled ? 'hostel_signin' : 'completed';
                break;
            case 'hostel_signin':
                $exeatRequest->status = 'completed';
                break;
            default:
                Log::warning("WorkflowService: Unknown or final status {$exeatRequest->status} for ExeatRequest ID {$exeatRequest->id}");
                return;
        }
        $exeatRequest->save();

        if ($oldStatus !== $exeatRequest->status) {
            // Gate event notifications are handled in handleSpecialStageActions during actual security actions
        }

        // Send stage change notification to student
        try {
            $this->notificationService->sendStageChangeNotification($exeatRequest, $oldStatus, $exeatRequest->status);
        } catch (\Exception $e) {
            Log::error('Failed to send stage change notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        // Send approval required notification to next role
        try {
            $this->sendApprovalNotificationForStage($exeatRequest);
        } catch (\Exception $e) {
            Log::error('Failed to send approval required notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        // ✅ Automatically trigger parent consent mail
        if ($exeatRequest->status === 'parent_consent') {
            $staffId = $exeatRequest->approvals()->latest()->first()->staff_id ?? null;

            $this->sendParentConsent($exeatRequest, $exeatRequest->preferred_mode_of_contact ?? 'email', null, $staffId);
        }

        Log::info('WorkflowService: Exeat advanced to next stage', [
            'exeat_id' => $exeatRequest->id,
            'old_status' => $oldStatus,
            'new_status' => $exeatRequest->status,
        ]);
    }

    public function sendParentConsent(ExeatRequest $exeatRequest, string $method, ?string $message = null, ?int $staffId = null)
    {
        $exeatRequest->loadMissing('student');
        $oldStatus = $exeatRequest->status;

        // Set expiration for 24 hours from now
        $expiresAt = Carbon::now()->addHours(24);

        $parentConsent = ParentConsent::updateOrCreate(
            ['exeat_request_id' => $exeatRequest->id],
            [
                'consent_status'    => 'pending',
                'consent_method'    => $method,
                'consent_token'     => uniqid('consent_', true),
                'consent_timestamp' => null,
                'expires_at'        => $expiresAt,
            ]
        );

        $student      = $exeatRequest->student;
        $parentEmail  = $exeatRequest->parent_email;
        $parentPhone  = $exeatRequest->parent_phone_no;
        $studentName  = $student ? "{$student->fname} {$student->lname}" : '';
        $reason       = $exeatRequest->reason;

        $linkApprove  = $this->urlShortenerService->shortenUrl(url('/api/parent/consent/' . $parentConsent->consent_token . '/approve'));
        $linkReject   = $this->urlShortenerService->shortenUrl(url('/api/parent/consent/' . $parentConsent->consent_token . '/decline'));

        $expiryText = $expiresAt->format('F j, Y g:i A');

        $matricNumber = $exeatRequest->matric_no ?? 'N/A';

        $notificationEmail = <<<EOD
    Dear Parent/Guardian,

    Your ward $studentName with matric number $matricNumber has requested to leave campus.

    Please provide your consent by clicking one of the buttons below.

    — VERITAS University Exeat Management Team
    EOD;

        $notificationSMS = "EXEAT: $studentName needs approval. Reason: $reason\nApprove: $linkApprove\nReject: $linkReject\nExpires: $expiryText";

        $emailSent = false;
        $additionalNotificationSent = false;

        // Only send parent email if preferred mode is email AND parent email exists
        if ($method === 'email' && !empty($parentEmail)) {
            try {
                $this->sendParentConsentEmail($parentEmail, 'Parent', 'Exeat Consent Request', $notificationEmail, $exeatRequest, $linkApprove, $linkReject);
                $emailSent = true;
                Log::info('Parent consent email sent successfully', ['exeat_id' => $exeatRequest->id, 'email' => $parentEmail]);
            } catch (\Exception $e) {
                Log::error('Failed to send parent consent email', [
                    'exeat_id' => $exeatRequest->id,
                    'email' => $parentEmail,
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($method === 'email' && empty($parentEmail)) {
            Log::warning('Cannot send parent consent email - no parent email address provided', [
                'exeat_id' => $exeatRequest->id,
                'student_id' => $exeatRequest->student_id,
                'preferred_mode' => $method
            ]);
        }

        // Send copy to admin if admin email is configured
        $adminEmail = env('ADMIN_EMAIL');
        if (!empty($adminEmail)) {
            try {
                $this->sendParentConsentEmail($adminEmail, 'Administrator', 'Exeat Consent Request', $notificationEmail, $exeatRequest, $linkApprove, $linkReject);
                Log::info('Administrator consent email sent successfully', ['exeat_id' => $exeatRequest->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send administrator consent email', [
                    'exeat_id' => $exeatRequest->id,
                    'admin_email' => $adminEmail,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            Log::info('Admin email not configured - skipping admin notification', ['exeat_id' => $exeatRequest->id]);
        }

        // Send additional notifications based on preferred_mode_of_contact
        switch ($method) {
            case 'any':
                try {
                    $this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'whatsapp');
                    $additionalNotificationSent = true;
                    Log::info('WhatsApp notification sent for preferred mode: any', ['exeat_id' => $exeatRequest->id]);
                } catch (\Exception $e) {
                    Log::error('WhatsApp notification failed, attempting SMS fallback', [
                        'exeat_id' => $exeatRequest->id,
                        'error' => $e->getMessage()
                    ]);
                    try {
                        $this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'sms');
                        $additionalNotificationSent = true;
                        Log::info('SMS fallback sent successfully for preferred mode: any', ['exeat_id' => $exeatRequest->id]);
                    } catch (\Exception $smsError) {
                        Log::error('SMS fallback also failed for preferred mode: any', [
                            'exeat_id' => $exeatRequest->id,
                            'error' => $smsError->getMessage()
                        ]);
                    }
                }
                break;

            case 'text':
            case 'sms':
                try {
                    $this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'sms');
                    $additionalNotificationSent = true;
                    Log::info('SMS notification sent for preferred mode: ' . $method, ['exeat_id' => $exeatRequest->id]);
                } catch (\Exception $e) {
                    Log::error('SMS notification failed for preferred mode: ' . $method, [
                        'exeat_id' => $exeatRequest->id,
                        'error' => $e->getMessage()
                    ]);
                }
                break;

            case 'whatsapp':
                try {
                    $this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'whatsapp');
                    $additionalNotificationSent = true;
                    Log::info('WhatsApp notification sent for preferred mode: whatsapp', ['exeat_id' => $exeatRequest->id]);
                } catch (\Exception $e) {
                    Log::error('WhatsApp notification failed for preferred mode: whatsapp', [
                        'exeat_id' => $exeatRequest->id,
                        'error' => $e->getMessage()
                    ]);
                }
                break;

            case 'phone call':
            case 'phone_call':
            case 'phone':
                Log::info('Only email sent for preferred mode: ' . $method, ['exeat_id' => $exeatRequest->id]);
                $additionalNotificationSent = true; // Consider email as sufficient
                break;

            case 'email':
                Log::info('Only email sent for preferred mode: email', ['exeat_id' => $exeatRequest->id]);
                $additionalNotificationSent = true; // Email is the preferred method
                break;

            default:
                Log::info('Only email sent for unknown preferred mode: ' . $method, ['exeat_id' => $exeatRequest->id]);
                $additionalNotificationSent = $emailSent; // Consider email as sufficient
                break;
        }

        // Log overall notification status
        if (!$emailSent && !$additionalNotificationSent) {
            Log::error('All notification methods failed for parent consent', [
                'exeat_id' => $exeatRequest->id,
                'method' => $method,
                'parent_email' => $parentEmail,
                'parent_phone' => $parentPhone
            ]);
        } elseif (!$emailSent) {
            Log::warning('Email failed but alternative notification sent', [
                'exeat_id' => $exeatRequest->id,
                'method' => $method
            ]);
        } elseif (!$additionalNotificationSent && in_array($method, ['any', 'text', 'whatsapp'])) {
            Log::warning('Email sent but preferred notification method failed', [
                'exeat_id' => $exeatRequest->id,
                'method' => $method
            ]);
        }

        // Determine overall notification status
        $notificationStatus = 'failed';
        $statusMessage = 'No notifications could be sent';

        if ($method === 'email') {
            if (empty($parentEmail)) {
                $notificationStatus = 'no_email';
                $statusMessage = 'No parent email address provided - cannot send email notification';
            } elseif ($emailSent) {
                $notificationStatus = 'success';
                $statusMessage = 'Parent consent email sent successfully';
            } else {
                $notificationStatus = 'failed';
                $statusMessage = 'Failed to send parent consent email';
            }
        } else {
            if ($additionalNotificationSent) {
                $notificationStatus = 'success';
                $statusMessage = "Parent consent sent via {$method}";
            } else {
                $notificationStatus = 'failed';
                $statusMessage = "Failed to send parent consent via {$method}";
            }
        }

        Log::info('Parent consent requested', [
            'exeat_id' => $exeatRequest->id,
            'method' => $method,
            'parent_email' => $parentEmail,
            'parent_phone' => $parentPhone,
            'expires_at' => $expiryText,
            'notification_status' => $notificationStatus,
            'status_message' => $statusMessage
        ]);

        $exeatRequest->status = 'parent_consent';
        $exeatRequest->save();

        if ($staffId) {
            $this->createAuditLog(
                $exeatRequest,
                $staffId,
                $exeatRequest->student_id,
                'parent_consent_request',
                "Changed from {$oldStatus} to parent_consent - {$statusMessage}",
                "Method: {$method}"
            );
        }

        // Add notification status to parent consent record
        $parentConsent->notification_status = $notificationStatus;
        $parentConsent->status_message = $statusMessage;

        return $parentConsent;
    }
    public function parentConsentApprove(ParentConsent $parentConsent)
    {
        $parentConsent->consent_status = 'approved';
        $parentConsent->consent_timestamp = now();
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $oldStatus = $exeatRequest->status;
        $exeatRequest->status = 'dean_review';
        $exeatRequest->save();

        // Send stage change notification to student
        try {
            $this->notificationService->sendStageChangeNotification($exeatRequest, 'parent_consent', 'dean_review');
        } catch (\Exception $e) {
            Log::error('Failed to send stage change notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        // Send approval required notification to dean
        try {
            $this->notificationService->sendApprovalRequiredNotification($exeatRequest, 'dean');
        } catch (\Exception $e) {
            Log::error('Failed to send approval required notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        $this->createAuditLog(
            $exeatRequest,
            null,
            $exeatRequest->student_id,
            'parent_consent_approve',
            "Status changed from {$oldStatus} to dean_review",
            "Parent approved consent request"
        );

        Log::info('WorkflowService: Parent consent approved', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
        ]);

        return $exeatRequest;
    }

    public function parentConsentDecline(ParentConsent $parentConsent)
    {
        $parentConsent->consent_status = 'declined';
        $parentConsent->consent_timestamp = now();
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $oldStatus = $exeatRequest->status;
        $exeatRequest->status = 'rejected';
        $exeatRequest->save();

        // Send rejection notification to student
        try {
            $this->notificationService->sendRejectionNotification($exeatRequest, 'Parent declined consent for this exeat request');
        } catch (\Exception $e) {
            Log::error('Failed to send rejection notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        $this->createAuditLog(
            $exeatRequest,
            null,
            $exeatRequest->student_id,
            'parent_consent_decline',
            "Status changed from {$oldStatus} to rejected",
            "Parent declined consent request"
        );

        Log::info('WorkflowService: Parent consent declined', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
        ]);

        return $exeatRequest;
    }

    /**
     * Secretary approves parent consent on behalf of parent.
     */
    public function secretaryParentConsentApprove(ParentConsent $parentConsent, int $secretaryId, string $reason)
    {
        $parentConsent->consent_status = 'approved';
        $parentConsent->consent_timestamp = now();
        $parentConsent->acted_by_staff_id = $secretaryId;
        $parentConsent->action_type = 'secretary_approval';
        $parentConsent->secretary_reason = $reason;
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $oldStatus = $exeatRequest->status;
        $exeatRequest->status = 'dean_review';
        $exeatRequest->save();

        // Send stage change notification to student
        try {
            $this->notificationService->sendStageChangeNotification($exeatRequest, 'parent_consent', 'dean_review');
        } catch (\Exception $e) {
            Log::error('Failed to send stage change notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        // Send approval required notification to dean
        try {
            $this->notificationService->sendApprovalRequiredNotification($exeatRequest, 'dean');
        } catch (\Exception $e) {
            Log::error('Failed to send approval required notification', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
        }

        $this->createAuditLog(
            $exeatRequest,
            $secretaryId,
            $exeatRequest->student_id,
            'secretary_parent_consent_approve',
            "Status changed from {$oldStatus} to dean_review",
            "Secretary approved on behalf of parent. Reason: {$reason}"
        );

        Log::info('WorkflowService: Secretary approved parent consent', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
            'secretary_id' => $secretaryId,
            'reason' => $reason
        ]);

        return $exeatRequest;
    }

    /**
     * Secretary rejects parent consent on behalf of parent.
     */
    public function secretaryParentConsentReject(ParentConsent $parentConsent, int $secretaryId, string $reason)
    {
        $parentConsent->consent_status = 'declined';
        $parentConsent->consent_timestamp = now();
        $parentConsent->acted_by_staff_id = $secretaryId;
        $parentConsent->action_type = 'secretary_rejection';
        $parentConsent->secretary_reason = $reason;
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $oldStatus = $exeatRequest->status;
        $exeatRequest->status = 'rejected';
        $exeatRequest->save();
        $this->notifyStudentStatusChange($exeatRequest);

        $this->createAuditLog(
            $exeatRequest,
            $secretaryId,
            $exeatRequest->student_id,
            'secretary_parent_consent_reject',
            "Status changed from {$oldStatus} to rejected",
            "Secretary rejected on behalf of parent. Reason: {$reason}"
        );

        Log::info('WorkflowService: Secretary rejected parent consent', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
            'secretary_id' => $secretaryId,
            'reason' => $reason
        ]);

        return $exeatRequest;
    }

    public function createAuditLog(ExeatRequest $exeatRequest, ?int $staffId, ?int $studentId, string $action, string $details, ?string $comment = null)
    {
        $logDetails = $details;
        if ($comment) {
            $logDetails .= " | Comment: {$comment}";
        }

        $auditLog = AuditLog::create([
            'staff_id' => $staffId,
            'student_id' => $studentId,
            'action' => $action,
            'target_type' => 'exeat_request',
            'target_id' => $exeatRequest->id,
            'details' => $logDetails,
            'timestamp' => now(),
        ]);

        Log::info('WorkflowService: Created audit log', [
            'exeat_id' => $exeatRequest->id,
            'audit_log_id' => $auditLog->id,
            'action' => $action
        ]);

        return $auditLog;
    }

    protected function sendSmsOrWhatsapp(string $to, string $message, string $channel)
    {
        if ($channel === 'whatsapp') {
            $this->sendWhatsAppMessage($to, $message);
        } else {
            $this->sendSmsMessage($to, $message);
        }
    }

    protected function formatNigerianPhone($phone)
    {
        // Use the PhoneUtility class for consistent formatting
        return \App\Utils\PhoneUtility::formatToInternational($phone, '234');
    }

    protected function sendSmsMessage(string $to, string $message)
    {
        try {
            // Validate configuration
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.sms_from');

            if (!$sid || !$token || !$from) {
                throw new \Exception('Twilio configuration is incomplete');
            }

            // Format phone number using PhoneUtility
            $formattedTo = \App\Utils\PhoneUtility::formatForSMS($to);

            $client = new TwilioClient($sid, $token);
            $result = $client->messages->create($formattedTo, [
                'from' => $from,
                'body' => $message,
            ]);

            Log::info("SMS sent successfully", [
                'original_to' => $to,
                'formatted_to' => $formattedTo,
                'message_sid' => $result->sid,
                'status' => $result->status
            ]);
        } catch (TwilioException $e) {
            Log::error("Twilio SMS API error", [
                'original_to' => $to,
                'formatted_to' => isset($formattedTo) ? $formattedTo : $to,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'twilio_error_code' => method_exists($e, 'getErrorCode') ? $e->getErrorCode() : null
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send SMS message", [
                'original_to' => $to,
                'formatted_to' => isset($formattedTo) ? $formattedTo : $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function sendWhatsAppMessage(string $to, string $message)
    {
        try {
            // Use the WhatsAppService for consistent messaging through Twilio
            $whatsAppService = app(\App\Services\WhatsAppService::class);

            if (!$whatsAppService->isConfigured()) {
                throw new \Exception('WhatsApp service is not properly configured');
            }

            $result = $whatsAppService->sendMessage($to, $message);

            if ($result['success']) {
                Log::info("WhatsApp message sent successfully", [
                    'to' => $to,
                    'message_sid' => $result['message_sid'] ?? null,
                    'status' => $result['status'] ?? null
                ]);
            } else {
                Log::error("WhatsApp message failed", [
                    'to' => $to,
                    'error_code' => $result['error_code'] ?? null,
                    'error_message' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message", [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function formatPhoneForWhatsApp(string $phone)
    {
        // Use the PhoneUtility class for consistent formatting
        // Strip the 'whatsapp:' prefix since we're using Meta WhatsApp API which doesn't need it
        $formatted = \App\Utils\PhoneUtility::formatForWhatsApp($phone);
        return str_replace('whatsapp:', '', $formatted);
    }

    /**
     * Send templated email using the exeat-notification template
     */
    private function sendTemplatedEmail($email, $recipientName, $subject, $message, $exeatRequest, $priority = 'medium')
    {
        // Validate email before attempting to send
        if (!is_string($email) || trim($email) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            \Log::warning('Skipped sending templated email due to invalid or missing email', [
                'email' => $email,
                'subject' => $subject
            ]);
            return;
        }
        try {
            // Create a notification-like object for the template
            $notification = (object) [
                'title' => $subject,
                'message' => $message,
                'priority' => $priority,
                'notification_type' => 'stage_change',
                'exeatRequest' => $exeatRequest,
                'approveUrl' => null,
                'rejectUrl' => null
            ];

            $recipient = [
                'email' => $email,
                'name' => $recipientName
            ];

            Mail::send('emails.exeat-notification', [
                'notification' => $notification,
                'recipient' => $recipient,
                'approveUrl' => $notification->approveUrl ?? null,
                'rejectUrl' => $notification->rejectUrl ?? null
            ], function ($msg) use ($email, $recipientName, $subject) {
                $msg->to($email, $recipientName)->subject($subject);
            });

            Log::info('Templated email sent successfully', [
                'email' => $email,
                'subject' => $subject
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send templated email', [
                'error' => $e->getMessage(),
                'email' => $email,
                'subject' => $subject
            ]);
            throw $e;
        }
    }

    /**
     * Send parent consent email with approve/reject URLs
     */
    private function sendParentConsentEmail($email, $recipientName, $subject, $message, $exeatRequest, $approveUrl, $rejectUrl, $priority = 'high')
    {
        try {
            // Create a notification-like object for the template
            $notification = (object) [
                'title' => $subject,
                'message' => $message,
                'priority' => $priority,
                'notification_type' => 'parent_consent',
                'exeatRequest' => $exeatRequest,
                'approveUrl' => $approveUrl,
                'rejectUrl' => $rejectUrl
            ];

            $recipient = [
                'email' => $email,
                'name' => $recipientName
            ];

            Mail::send('emails.exeat-notification', [
                'notification' => $notification,
                'recipient' => $recipient,
                'approveUrl' => $approveUrl,
                'rejectUrl' => $rejectUrl
            ], function ($msg) use ($email, $recipientName, $subject) {
                $msg->to($email, $recipientName)->subject($subject);
            });

            Log::info('Parent consent email sent successfully', [
                'email' => $email,
                'subject' => $subject,
                'approve_url' => $approveUrl,
                'reject_url' => $rejectUrl
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send parent consent email', [
                'error' => $e->getMessage(),
                'email' => $email,
                'subject' => $subject
            ]);
            throw $e;
        }
    }

    protected function triggerVoiceCall(string $to, string $message)
    {
        // Optional: Replace this with Twilio Voice API
        \Log::info("Simulated call to $to with message: $message");
    }


    protected function sendApprovalNotificationForStage(ExeatRequest $exeatRequest)
    {
        $roleMap = [
            'cmd_review' => 'cmd',
            'secretary_review' => 'secretary',
            'dean_review' => 'dean',
            'hostel_signout' => 'hostel_admin',
            'security_signout' => 'security',
            'security_signin' => 'security',
            'hostel_signin' => 'hostel_admin'
        ];

        if (isset($roleMap[$exeatRequest->status])) {
            $role = $roleMap[$exeatRequest->status];
            $this->notificationService->sendApprovalRequiredNotification($exeatRequest, $role);
        }
    }

    protected function notifyStudentStatusChange(ExeatRequest $exeatRequest)
    {
        $student = $exeatRequest->student;

        if (!$student || !$student->username) {
            \Log::warning("No email available for student ID {$exeatRequest->student_id}");
            return;
        }

        $message = <<<EOT
Dear {$student->fname} {$student->lname},

Your exeat request status has changed.

Current status: {$exeatRequest->status}
Reason: {$exeatRequest->reason}

Thank you.

— VERITAS University Exeat Management System
EOT;

        try {
            $this->sendTemplatedEmail(
                $student->username, // Email is stored in 'username' field
                $student->fname . ' ' . $student->lname,
                'Exeat Request Status Updated',
                $message,
                $exeatRequest
            );
        } catch (\Exception $e) {
            Log::error('Failed to send status update email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if student is returning late and create overdue debt
     */
    private function checkAndCreateOverdueDebt(ExeatRequest $exeatRequest)
    {
        // Enhanced debug logging
        Log::info('DEBUG: checkAndCreateOverdueDebt called', [
            'exeat_id' => $exeatRequest->id,
            'student_id' => $exeatRequest->student_id,
            'return_date' => $exeatRequest->return_date,
            'current_time' => now()->toDateTimeString()
        ]);

        $returnDate = \Carbon\Carbon::parse($exeatRequest->return_date);
        $actualReturnTime = now();

        // Calculate debt using exact 24-hour periods at 11:59 PM
        $daysOverdue = $this->calculateDaysOverdue($returnDate, $actualReturnTime);
        $debtAmount = $daysOverdue * 10000;

        Log::info('DEBUG: Time comparison and overdue calculation', [
            'return_date_parsed' => $returnDate->toDateTimeString(),
            'actual_return_time' => $actualReturnTime->toDateTimeString(),
            'days_overdue' => $daysOverdue,
            'is_late' => $daysOverdue > 0
        ]);

        // Only create debt when at least one full overdue day has passed
        if ($daysOverdue > 0) {

            // Check if debt already exists for this exeat
            $existingDebt = \App\Models\StudentExeatDebt::where('exeat_request_id', $exeatRequest->id)
                ->where('payment_status', '!=', 'cleared')
                ->first();

            Log::info('DEBUG: Debt creation check', [
                'days_overdue' => $daysOverdue,
                'debt_amount' => $debtAmount,
                'existing_debt' => $existingDebt ? $existingDebt->toArray() : null
            ]);

            if (!$existingDebt) {
                // Create new debt record
                $debt = \App\Models\StudentExeatDebt::create([
                    'student_id' => $exeatRequest->student_id,
                    'exeat_request_id' => $exeatRequest->id,
                    'amount' => $debtAmount,
                    'payment_status' => 'unpaid',
                ]);

                // Create audit log for debt creation
                AuditLog::create([
                    'staff_id' => null, // System-generated debt
                    'student_id' => $exeatRequest->student_id,
                    'action' => 'debt_created_for_late_return',
                    'target_type' => 'student_exeat_debt',
                    'target_id' => $debt->id,
                    'details' => json_encode([
                        'exeat_request_id' => $exeatRequest->id,
                        'days_overdue' => $daysOverdue,
                        'amount' => $debtAmount,
                        'return_date' => $exeatRequest->return_date,
                        'actual_return_time' => $actualReturnTime->toDateTimeString(),
                        'created_by' => 'system'
                    ]),
                    'timestamp' => now(),
                ]);

                // Send debt notification to student (email only, no SMS)
                try {
                    $student = \App\Models\Student::find($exeatRequest->student_id);
                    if ($student) {
                        $this->notificationService->sendDebtNotification($student, $exeatRequest, $debtAmount);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send debt notification', [
                        'student_id' => $exeatRequest->student_id,
                        'exeat_id' => $exeatRequest->id,
                        'error' => $e->getMessage()
                    ]);
                }

                Log::info('Created overdue debt for late return', [
                    'exeat_id' => $exeatRequest->id,
                    'student_id' => $exeatRequest->student_id,
                    'days_overdue' => $daysOverdue,
                    'debt_amount' => $debtAmount
                ]);
            }
        } else {
            Log::info('DEBUG: No debt created - student returned on time for debt calculation window', [
                'exeat_id' => $exeatRequest->id,
                'student_id' => $exeatRequest->student_id,
                'days_overdue' => $daysOverdue,
            ]);
        }
    }

    /**
     * Handle special stage actions for security and hostel stages
     */
    private function handleSpecialStageActions(ExeatRequest $exeatRequest, ExeatApproval $approval, $oldStatus)
    {
        // Handle security signout
        if ($oldStatus === 'security_signout') {
            $allowedRoles = ['security', 'admin', 'dean', 'deputy-dean'];
            if (!in_array($approval->role, $allowedRoles)) {
                Log::warning('Unauthorized role attempted security signout approval', [
                    'exeat_id' => $exeatRequest->id,
                    'attempted_role' => $approval->role,
                    'staff_id' => $approval->staff_id
                ]);
            } else {
                SecuritySignout::create([
                    'exeat_request_id' => $exeatRequest->id,
                    'signout_time' => now(),
                    'signin_time' => null,
                    'security_id' => $approval->staff_id,
                ]);

                // Send parent notification for sign-out
                $this->sendParentNotification($exeatRequest, 'OUT');

                Log::info('Security signed out student at gate', [
                    'exeat_id' => $exeatRequest->id,
                    'security_id' => $approval->staff_id,
                    'approving_role' => $approval->role
                ]);

                // Notify assigned hostel admins about gate sign-out
                try {
                    $this->notificationService->sendHostelGateEventNotificationToAssignedAdmins($exeatRequest, 'signout');
                } catch (\Exception $e) {
                    Log::error('Failed to notify hostel admins for gate signout (on action)', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
                }
            }
        }

        // Handle security signin - Allow any role with permission to approve security signin
        if ($oldStatus === 'security_signin') {
            // Check if the approving role has permission for security signin
            $allowedRoles = ['security', 'admin', 'dean', 'deputy-dean'];

            if (in_array($approval->role, $allowedRoles)) {
                Log::info('DEBUG: Security signin process started', [
                    'exeat_id' => $exeatRequest->id,
                    'old_status' => $oldStatus,
                    'approval_role' => $approval->role,
                    'staff_id' => $approval->staff_id
                ]);

                $signout = SecuritySignout::where('exeat_request_id', $exeatRequest->id)
                    ->whereNull('signin_time')
                    ->first();

                Log::info('DEBUG: Security signout record found', [
                    'signout_record' => $signout ? $signout->toArray() : null
                ]);

                if ($signout) {
                    $signout->signin_time = now();
                    $signout->save();

                    Log::info('DEBUG: About to call checkAndCreateOverdueDebt', [
                        'exeat_id' => $exeatRequest->id,
                        'approving_role' => $approval->role
                    ]);

                    // Check if student is returning late and create debt
                    $this->checkAndCreateOverdueDebt($exeatRequest);

                    // Send parent notification for sign-in
                    $this->sendParentNotification($exeatRequest, 'IN');

                    Log::info('Student signed in at gate', [
                        'exeat_id' => $exeatRequest->id,
                        'approving_staff_id' => $approval->staff_id,
                        'approving_role' => $approval->role
                    ]);

                    // Notify assigned hostel admins about gate sign-in
                    try {
                        $this->notificationService->sendHostelGateEventNotificationToAssignedAdmins($exeatRequest, 'signin');
                    } catch (\Exception $e) {
                        Log::error('Failed to notify hostel admins for gate signin (on action)', ['error' => $e->getMessage(), 'exeat_id' => $exeatRequest->id]);
                    }
                }
            } else {
                Log::warning('Unauthorized role attempted security signin approval', [
                    'exeat_id' => $exeatRequest->id,
                    'attempted_role' => $approval->role,
                    'staff_id' => $approval->staff_id
                ]);
            }
        }

        // Handle hostel signout
        if ($oldStatus === 'hostel_signout' && $approval->role === 'hostel_admin') {
            HostelSignout::create([
                'exeat_request_id' => $exeatRequest->id,
                'signout_time' => now(),
                'signin_time' => null,
                'hostel_admin_id' => $approval->staff_id,
            ]);

            Log::info('Hostel admin signed out student', [
                'exeat_id' => $exeatRequest->id,
                'admin_id' => $approval->staff_id
            ]);
        }

        // Handle hostel signin
        if ($oldStatus === 'hostel_signin' && $approval->role === 'hostel_admin') {
            $signout = HostelSignout::where('exeat_request_id', $exeatRequest->id)
                ->whereNull('signin_time')
                ->first();

            if ($signout) {
                $signout->signin_time = now();
                $signout->save();

                Log::info('Hostel admin signed in student', [
                    'exeat_id' => $exeatRequest->id,
                    'admin_id' => $approval->staff_id
                ]);
            }
        }
    }

    /**
     * Send parent notification for security sign-in/out events
     */
    private function sendParentNotification(ExeatRequest $exeat, string $action)
    {
        // Check if parent email exists
        if (!$exeat->parent_email) {
            Log::warning('No parent email available for exeat request', ['exeat_id' => $exeat->id]);
            return;
        }

        // Only send email if preferred contact mode is email
        // if ($exeat->preferred_mode_of_contact !== 'email') {
        //     Log::info('Parent notification skipped - preferred contact mode is not email', [
        //         'exeat_id' => $exeat->id,
        //         'preferred_mode' => $exeat->preferred_mode_of_contact,
        //         'action' => $action
        //     ]);
        //     return;
        // }

        $student = $exeat->student;
        $studentName = $student ? "{$student->fname} {$student->lname}" : 'Student';
        $matricNo = $exeat->matric_no ?? 'N/A';
        $timestamp = now()->format('M d, Y g:i A');

        $subject = "Student Security {$action} - {$studentName}";
        $message = sprintf(
            // "Dear Parent/Guardian,\n\nThis is to inform you that your ward %s (Matric No: %s) has been signed %s by Security on %s.\n\nExeat Details:\n- Reason: %s\n- Destination: %s\n- Expected Return: %s\n\nIf you have any concerns, please contact the university immediately.\n\nThank you.\n\n— VERITAS University Security Department",
            "This is to inform you that your ward %s (Matric No: %s) has been signed %s by Security on %s.\n\n
             ",

            $studentName,
            $matricNo,
            $action,
            $timestamp,
            $exeat->reason,
            $exeat->destination,
            \Carbon\Carbon::parse($exeat->return_date)->format('M d, Y')
        );

        try {
            $this->sendTemplatedEmail(
                $exeat->parent_email,
                'Parent/Guardian',
                $subject,
                $message,
                $exeat
            );

            Log::info('Parent notification sent for security action', [
                'exeat_id' => $exeat->id,
                'action' => $action,
                'parent_email' => $exeat->parent_email,
                'preferred_mode' => $exeat->preferred_mode_of_contact
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send parent notification', [
                'error' => $e->getMessage(),
                'exeat_id' => $exeat->id,
                'action' => $action,
                'preferred_mode' => $exeat->preferred_mode_of_contact
            ]);
        }
    }

    /**
     * Bulk approve multiple exeat requests
     */
    public function bulkApprove(array $exeatRequestIds, int $staffId, string $role, ?string $comment = null): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($exeatRequestIds as $exeatId) {
            try {
                $exeatRequest = ExeatRequest::findOrFail($exeatId);

                // Create approval record
                $approval = ExeatApproval::create([
                    'exeat_request_id' => $exeatRequest->id,
                    'staff_id' => $staffId,
                    'role' => $role,
                    'status' => 'approved',
                    'comment' => $comment,
                ]);

                // Use existing approve method
                $this->approve($exeatRequest, $approval, $comment);

                $results[$exeatId] = [
                    'success' => true,
                    'message' => 'Successfully approved',
                    'new_status' => $exeatRequest->status
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[$exeatId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;

                Log::error('Bulk approve failed for exeat request', [
                    'exeat_id' => $exeatId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bulk approve operation completed', [
            'staff_id' => $staffId,
            'role' => $role,
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($exeatRequestIds),
                'success' => $successCount,
                'failed' => $failureCount
            ]
        ];
    }

    /**
     * Bulk reject multiple exeat requests
     */
    public function bulkReject(array $exeatRequestIds, int $staffId, string $role, ?string $comment = null): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($exeatRequestIds as $exeatId) {
            try {
                $exeatRequest = ExeatRequest::findOrFail($exeatId);

                // Create approval record
                $approval = ExeatApproval::create([
                    'exeat_request_id' => $exeatRequest->id,
                    'staff_id' => $staffId,
                    'role' => $role,
                    'status' => 'rejected',
                    'comment' => $comment,
                ]);

                // Use existing reject method
                $this->reject($exeatRequest, $approval, $comment);

                $results[$exeatId] = [
                    'success' => true,
                    'message' => 'Successfully rejected',
                    'new_status' => $exeatRequest->status
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[$exeatId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;

                Log::error('Bulk reject failed for exeat request', [
                    'exeat_id' => $exeatId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bulk reject operation completed', [
            'staff_id' => $staffId,
            'role' => $role,
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($exeatRequestIds),
                'success' => $successCount,
                'failed' => $failureCount
            ]
        ];
    }

    /**
     * Special Dean Override - Bypass entire workflow to security/hostel signout
     */
    public function specialDeanOverride(array $exeatRequestIds, int $staffId, string $overrideReason, array $options = []): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        $bypassSecurity = $options['bypass_security_check'] ?? false;
        $bypassHostel = $options['bypass_hostel_signout'] ?? false;
        $emergencyContact = $options['emergency_contact'] ?? null;
        $specialInstructions = $options['special_instructions'] ?? null;

        foreach ($exeatRequestIds as $exeatId) {
            try {
                $exeatRequest = ExeatRequest::findOrFail($exeatId);
                $oldStatus = $exeatRequest->status;

                // Determine final status based on bypass options
                if ($bypassSecurity && $bypassHostel) {
                    $finalStatus = 'completed';
                } elseif ($bypassSecurity) {
                    $finalStatus = 'hostel_signin';
                } elseif ($bypassHostel) {
                    $finalStatus = 'security_signin';
                } else {
                    $finalStatus = 'security_signout';
                }

                // Update exeat request status directly
                $exeatRequest->status = $finalStatus;
                $exeatRequest->save();

                // Create special approval record
                $approval = ExeatApproval::create([
                    'exeat_request_id' => $exeatRequest->id,
                    'staff_id' => $staffId,
                    'role' => 'dean',
                    'status' => 'approved',
                    'comment' => "SPECIAL DEAN OVERRIDE: {$overrideReason}",
                ]);

                // Create security signout record if not bypassed
                if (!$bypassSecurity) {
                    SecuritySignout::create([
                        'exeat_request_id' => $exeatRequest->id,
                        'signout_time' => now(),
                        'signin_time' => $finalStatus === 'completed' ? now() : null,
                        'security_id' => $staffId,
                    ]);
                }

                // Create hostel signout record if not bypassed
                if (!$bypassHostel) {
                    HostelSignout::create([
                        'exeat_request_id' => $exeatRequest->id,
                        'signout_time' => now(),
                        'signin_time' => $finalStatus === 'completed' ? now() : null,
                        'hostel_admin_id' => $staffId,
                    ]);
                }

                // Create audit log
                $this->createAuditLog(
                    $exeatRequest,
                    $staffId,
                    $exeatRequest->student_id,
                    'special_dean_override',
                    "Special Dean Override: Status changed from {$oldStatus} to {$finalStatus}. Reason: {$overrideReason}",
                    $overrideReason
                );

                // Send notifications
                try {
                    $this->notificationService->sendSpecialOverrideNotification(
                        $exeatRequest,
                        $overrideReason,
                        $emergencyContact,
                        $specialInstructions
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to send special override notification', [
                        'error' => $e->getMessage(),
                        'exeat_id' => $exeatRequest->id
                    ]);
                }

                $results[$exeatId] = [
                    'success' => true,
                    'message' => 'Special dean override applied successfully',
                    'old_status' => $oldStatus,
                    'new_status' => $finalStatus,
                    'bypassed' => [
                        'security' => $bypassSecurity,
                        'hostel' => $bypassHostel
                    ]
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[$exeatId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;

                Log::error('Special dean override failed for exeat request', [
                    'exeat_id' => $exeatId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Special dean override operation completed', [
            'staff_id' => $staffId,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'override_reason' => $overrideReason
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($exeatRequestIds),
                'success' => $successCount,
                'failed' => $failureCount
            ]
        ];
    }

    /**
     * Calculate days overdue using exact 24-hour periods at 11:59 PM
     *
     * @param \Carbon\Carbon $returnDate
     * @param \Carbon\Carbon $actualReturnTime
     * @return int
     */
    private function calculateDaysOverdue(\Carbon\Carbon $returnDate, \Carbon\Carbon $actualReturnTime): int
    {
        // Set return date to 11:59 PM of the expected return date
        $returnDateEnd = $returnDate->copy()->setTime(23, 59, 59);

        // If actual return is before or at 11:59 PM of return date, no debt
        if ($actualReturnTime->lte($returnDateEnd)) {
            return 0;
        }

        // Calculate full 24-hour periods after 11:59 PM of return date
        $daysPassed = 0;
        $currentCheckDate = $returnDateEnd->copy();

        while ($currentCheckDate->lt($actualReturnTime)) {
            $currentCheckDate->addDay()->setTime(23, 59, 59);
            $daysPassed++;
        }

        return $daysPassed;
    }
}
