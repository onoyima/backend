<?php

namespace App\Services;

use App\Models\ExeatNotification;
use App\Models\NotificationDeliveryLog;
use App\Models\Student;
use App\Models\Staff;
use App\Models\StudentContact;
use App\Jobs\SendNotificationJob;
use App\Events\NotificationSent;
use App\Utils\PhoneUtility;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Twilio\Rest\Client as TwilioClient;
use Exception;

class NotificationDeliveryService
{
    public function __construct()
    {
        // No dependencies needed
    }

    /**
     * Deliver notification directly instead of queuing.
     * Modified to avoid using queue since jobs table doesn't exist.
     */
    public function queueNotificationDelivery(ExeatNotification $notification): void
    {
        // Get delivery methods based on notification type and requirements
        $deliveryMethods = $this->getDeliveryMethodsForNotification($notification);
        
        foreach ($deliveryMethods as $method) {
            // Check if delivery is allowed based on quiet hours
            if ($this->isQuietHours($notification, $method)) {
                $this->scheduleForLater($notification, $method);
                continue;
            }
            
            // Deliver immediately instead of queuing
            $this->deliverNotification($notification, $method);
        }
    }


    /**
     * Deliver notification synchronously without using queues.
     */
    public function deliverNotificationSync(ExeatNotification $notification): array
    {
        $results = [];
        // Get delivery methods based on notification type and requirements
        $deliveryMethods = $this->getDeliveryMethodsForNotification($notification);
        
        foreach ($deliveryMethods as $method) {
            // Check if delivery is allowed based on quiet hours
            if ($this->isQuietHours($notification, $method)) {
                $results[$method] = ['success' => false, 'reason' => 'quiet_hours'];
                continue;
            }
            
            // Deliver immediately
            $success = $this->deliverNotification($notification, $method);
            $results[$method] = ['success' => $success, 'reason' => $success ? 'delivered' : 'failed'];
        }
        
        return $results;
    }

    /**
     * Determine which delivery methods to use based on notification type and requirements.
     */
    protected function getDeliveryMethodsForNotification(ExeatNotification $notification): array
    {
        $methods = ['in_app']; // Always include in-app notifications
        
        // Get the exeat request and student
        $exeatRequest = $notification->exeatRequest;
        $student = $exeatRequest ? $exeatRequest->student : null;
        
        switch ($notification->notification_type) {
            case ExeatNotification::TYPE_REQUEST_SUBMITTED:
                // Student emails only for exeat creation
                if ($notification->recipient_type === ExeatNotification::RECIPIENT_STUDENT) {
                    $methods[] = 'email';
                }
                break;
                
            case ExeatNotification::TYPE_STAGE_CHANGE:
                $notificationData = $notification->data ?? [];
                
                // Student emails for dean approval and security events
                if ($notification->recipient_type === ExeatNotification::RECIPIENT_STUDENT) {
                    if (isset($notificationData['is_dean_approval']) && $notificationData['is_dean_approval']) {
                        $methods[] = 'email';
                    }
                    // Fallback: check old_status and new_status
                    elseif (isset($notificationData['old_status']) && isset($notificationData['new_status'])) {
                        $oldStatus = $notificationData['old_status'];
                        $newStatus = $notificationData['new_status'];
                        
                        // Dean approval
                        if ($oldStatus === 'dean_review' && $newStatus === 'hostel_signout') {
                            $methods[] = 'email';
                        }
                        // Security sign-out
                        elseif ($oldStatus === 'hostel_signout' && $newStatus === 'security_signout') {
                            $methods[] = 'email';
                        }
                        // Security sign-in (return)
                        elseif ($oldStatus === 'security_signout' && $newStatus === 'security_signin') {
                            $methods[] = 'email';
                        }
                    }
                    // Last resort: check message content
                    else {
                        $message = $notification->message ?? '';
                        if (str_contains($message, 'dean_review') && str_contains($message, 'hostel_signout')) {
                            $methods[] = 'email';
                        }
                        elseif (str_contains($message, 'security_signout') || str_contains($message, 'security_signin')) {
                            $methods[] = 'email';
                        }
                    }
                }
                // Parent notifications for parent_consent stage after secretary_review approval
                elseif ($notification->recipient_type === ExeatNotification::RECIPIENT_PARENT) {
                    // Check if this is moving to parent_consent stage
                    if (isset($notificationData['old_status']) && isset($notificationData['new_status'])) {
                        if ($notificationData['old_status'] === 'secretary_review' && $notificationData['new_status'] === 'parent_consent') {
                            // Add delivery methods based on preferred contact mode
                            if ($exeatRequest && $exeatRequest->preferred_mode_of_contact) {
                                switch ($exeatRequest->preferred_mode_of_contact) {
                                    case 'email':
                                        $methods[] = 'email';
                                        break;
                                    case 'text':
                                    case 'sms':
                                        $methods[] = 'sms';
                                        break;
                                    case 'whatsapp':
                                        $methods[] = 'whatsapp';
                                        break;
                                    case 'phone':
                                    case 'phone_call':
                                    case 'phone call':
                                        // For phone calls, we'll send email as the primary method
                                        $methods[] = 'email';
                                        break;
                                    case 'any':
                                        // Send via all available methods
                                        $methods[] = 'email';
                                        $methods[] = 'sms';
                                        $methods[] = 'whatsapp';
                                        break;
                                }
                            } else {
                                // Default to email if no preference specified
                                $methods[] = 'email';
                            }
                        }
                    }
                }
                // Staff notifications for gate events (hostel admins on security sign-out/sign-in)
                elseif ($notification->recipient_type === ExeatNotification::RECIPIENT_STAFF) {
                    if (isset($notificationData['event']) && in_array($notificationData['event'], ['gate_signout', 'gate_signin'])) {
                        $methods[] = 'email';
                    }
                }
                break;
            case ExeatNotification::TYPE_REMINDER:
                $notificationData = $notification->data ?? [];
                // Staff notifications for gate events on reminder type
                if ($notification->recipient_type === ExeatNotification::RECIPIENT_STAFF) {
                    if (isset($notificationData['event']) && in_array($notificationData['event'], ['gate_signout', 'gate_signin'])) {
                        $methods[] = 'email';
                    }
                }
                break;
            
            case ExeatNotification::TYPE_STAFF_COMMENT:
                // Student emails for staff comments (always send email)
                if ($notification->recipient_type === ExeatNotification::RECIPIENT_STUDENT) {
                    $methods[] = 'email';
                    // Note: SMS for staff comments is handled separately in ExeatNotificationService
                    // via createStaffCommentSmsNotification() method
                }
                break;
        }
        
        return $methods;
    }

    /**
     * Deliver notification via specific method.
     */
    public function deliverNotification(ExeatNotification $notification, string $method): bool
    {
        try {
            $deliveryLog = $this->createDeliveryLog($notification, $method);
            
            $success = match ($method) {
                'in_app' => $this->deliverInApp($notification, $deliveryLog),
                'email' => $this->deliverEmail($notification, $deliveryLog),
                'sms' => $this->deliverSMS($notification, $deliveryLog),
                'whatsapp' => $this->deliverWhatsApp($notification, $deliveryLog),
                default => false
            };
            
            if ($success) {
                $this->updateDeliveryStatus($notification, $method, 'delivered');
                // TODO: Create NotificationSent event if needed
                // event(new NotificationSent($notification, $method));
            } else {
                $this->updateDeliveryStatus($notification, $method, 'failed');
            }
            
            return $success;
            
        } catch (Exception $e) {
            Log::error('Notification delivery failed', [
                'notification_id' => $notification->id,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            $this->updateDeliveryStatus($notification, $method, 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Deliver in-app notification.
     */
    protected function deliverInApp(ExeatNotification $notification, NotificationDeliveryLog $log): bool
    {
        // In-app notifications are already created in the database
        // Just mark as delivered
        $log->update([
            'status' => NotificationDeliveryLog::STATUS_DELIVERED,
            'delivered_at' => now()
        ]);
        
        return true;
    }

    /**
     * Deliver email notification.
     */
    protected function deliverEmail(ExeatNotification $notification, NotificationDeliveryLog $log): bool
    {
        $recipient = $this->getRecipientDetails($notification);
        
        if (!$recipient || empty($recipient['email'])) {
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_FAILED,
                'error_message' => 'No email address found for recipient'
            ]);
            return false;
        }
        
        try {
            Mail::send('emails.exeat-notification', [
                'notification' => $notification,
                'recipient' => $recipient
            ], function ($message) use ($recipient, $notification) {
                // Ensure email is a string and not null
                if (is_string($recipient['email']) && !empty(trim($recipient['email']))) {
                    $message->to($recipient['email'], $recipient['name'] ?? 'User')
                        ->subject($notification->title);
                } else {
                    throw new Exception('Invalid email address: must be a non-empty string');
                }
            });
            
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_DELIVERED,
                'delivered_at' => now()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_FAILED,
                'error_message' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Deliver SMS notification using Twilio.
     */
    protected function deliverSMS(ExeatNotification $notification, NotificationDeliveryLog $log): bool
    {
        $recipient = $this->getRecipientDetails($notification);
        
        if (!$recipient || !$recipient['phone']) {
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_FAILED,
                'error_message' => 'No phone number found for recipient'
            ]);
            return false;
        }
        
        try {
            // Format phone number for SMS using PhoneUtility
            $formattedPhone = PhoneUtility::formatForSMS($recipient['phone']);
            
            // Check if Twilio is configured
            if (!config('services.twilio.sid') || !config('services.twilio.token') || !config('services.twilio.sms_from')) {
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'Twilio SMS service is not properly configured'
                ]);
                return false;
            }
            
            // Using Twilio for SMS delivery
            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioFrom = config('services.twilio.sms_from');
            
            $client = new TwilioClient($twilioSid, $twilioToken);
            
            // Note on custom sender names for SMS:
            // Most SMS providers (including Twilio) don't support arbitrary alphanumeric sender names directly
            // due to carrier restrictions. There are two main approaches to customize the sender name:
            //
            // 1. Use a Messaging Service SID instead of a phone number
            //    - Create a Messaging Service in Twilio console
            //    - Configure the sender_id for the service
            //    - Use the Messaging Service SID instead of a phone number
            //
            // 2. Use Alphanumeric Sender ID where supported
            //    - This feature is available in some countries but not all
            //    - Register an Alphanumeric Sender ID in Twilio console
            //    - Use it as the 'from' parameter
            
            // Get a messaging service SID from config if available
            $messagingServiceSid = config('services.twilio.messaging_service_sid');
            $from = $messagingServiceSid ?: $twilioFrom;
            
            // For staff comments, we want to use a branded sender name if possible
            if ($notification->type === ExeatNotification::TYPE_STAFF_COMMENT) {
                // If we have a messaging service configured, we can use it to send with a branded name
                // Otherwise, we'll use the default Twilio phone number
                
                // Send SMS without prefix to save characters
                $message = $client->messages->create(
                    $formattedPhone,
                    [
                        'from' => $from,
                        'body' => $this->formatSMSMessage($notification)
                    ]
                );
            } else {
                // For other notification types, use standard format without prefix
                $message = $client->messages->create(
                    $formattedPhone,
                    [
                        'from' => $from,
                        'body' => $this->formatSMSMessage($notification)
                    ]
                );
            }
            
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_DELIVERED,
                'delivered_at' => now(),
                'metadata' => [
                    'delivery_provider' => 'twilio_sms',
                    'provider_message_id' => $message->sid,
                    'status' => $message->status,
                    'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                    'date_updated' => $message->dateUpdated->format('Y-m-d H:i:s')
                ]
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_FAILED,
                'error_message' => 'Twilio SMS error: ' . $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Deliver WhatsApp notification using Twilio.
     */
    protected function deliverWhatsApp(ExeatNotification $notification, NotificationDeliveryLog $log): bool
    {
        $recipient = $this->getRecipientDetails($notification);
        
        if (!$recipient || !$recipient['phone']) {
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_FAILED,
                'error_message' => 'No phone number found for recipient'
            ]);
            return false;
        }
        
        try {
            // Use the WhatsAppService for consistent messaging through Twilio
            $whatsAppService = app(\App\Services\WhatsAppService::class);
            
            if (!$whatsAppService->isConfigured()) {
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'WhatsApp service is not properly configured'
                ]);
                return false;
            }
            
            $message = $this->formatWhatsAppMessage($notification);
            // Format phone number for WhatsApp using PhoneUtility
            $formattedPhone = PhoneUtility::formatForWhatsApp($recipient['phone']);
            $result = $whatsAppService->sendMessage($formattedPhone, $message);
            
            if ($result['success']) {
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'metadata' => [
                        'delivery_provider' => 'twilio_whatsapp',
                        'provider_message_id' => $result['message_sid'] ?? null,
                        'status' => $result['status'] ?? null,
                        'date_created' => $result['date_created'] ?? null,
                        'date_updated' => $result['date_updated'] ?? null
                    ]
                ]);
                
                return true;
            } else {
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'WhatsApp service returned error: ' . ($result['error'] ?? 'Unknown error'),
                    'metadata' => [
                        'error_code' => $result['error_code'] ?? null
                    ]
                ]);
                
                return false;
            }
            
        } catch (Exception $e) {
            $log->update([
                'status' => NotificationDeliveryLog::STATUS_FAILED,
                'error_message' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get recipient details based on notification.
     * Uses PhoneUtility for consistent phone number formatting.
     */
    protected function getRecipientDetails(ExeatNotification $notification): ?array
    {
        switch ($notification->recipient_type) {
            case ExeatNotification::RECIPIENT_STUDENT:
                $student = Student::find($notification->recipient_id);
                return $student ? [
                    'name' => $student->full_name,
                    'email' => $student->username,
                    'phone' => $student->phone ? PhoneUtility::formatToInternational($student->phone) : null
                ] : null;
                
            case ExeatNotification::RECIPIENT_STAFF:
                $staff = Staff::find($notification->recipient_id);
                return $staff ? [
                    'name' => trim((string) (($staff->fname ?? '') . ' ' . ($staff->lname ?? ''))),
                    'email' => ($staff->email ?? null) ?: ($staff->p_email ?? null),
                    'phone' => $staff->phone ? PhoneUtility::formatToInternational($staff->phone) : null
                ] : null;
                
            default:
                return null;
        }
    }

    /**
     * Create delivery log entry.
     */
    protected function createDeliveryLog(ExeatNotification $notification, string $method): NotificationDeliveryLog
    {
        $recipient = $this->getRecipientDetails($notification);
        $recipientIdentifier = $recipient ? ($recipient['email'] ?: $recipient['phone'] ?: $recipient['name']) : 'unknown';
        
        return NotificationDeliveryLog::create([
            'notification_id' => $notification->id,
            'channel' => $method,
            'recipient' => $recipientIdentifier,
            'status' => NotificationDeliveryLog::STATUS_PENDING
        ]);
    }

    /**
     * Update delivery status in notification.
     * Note: This method is deprecated as we now use NotificationDeliveryLog table
     */
    protected function updateDeliveryStatus(
        ExeatNotification $notification,
        string $method,
        string $status,
        string $errorMessage = null
    ): void {
        // This method is no longer used as we track delivery status in NotificationDeliveryLog table
        // Keeping for backward compatibility but not implementing
    }

    /**
     * Check if it's quiet hours for the recipient.
     */
    protected function isQuietHours(ExeatNotification $notification, string $method): bool
    {
        // Skip quiet hours for urgent notifications
        if ($notification->priority === ExeatNotification::PRIORITY_URGENT) {
            return false;
        }
        
        // Skip quiet hours for in-app notifications
        if ($method === 'in_app') {
            return false;
        }
        
        // No quiet hours logic - always send immediately
        return false;
    }

    /**
     * Schedule notification for later delivery.
     * Modified to deliver directly instead of using queue.
     */
    protected function scheduleForLater(ExeatNotification $notification, string $method): void
    {
        // No scheduling needed - deliver immediately
        $this->deliverNotification($notification, $method);
    }

    /**
     * Format SMS message with character optimization for cost efficiency.
     * For staff comments, uses raw message only.
     */
    protected function formatSMSMessage(ExeatNotification $notification): string
    {
        // For staff comments, use raw message only (no title, no formatting)
        if ($notification->type === ExeatNotification::TYPE_STAFF_COMMENT) {
            $message = $notification->message;
        } else {
            // For other types, use message only (no title to save characters)
            $message = $notification->message;
        }
        
        // Optimize for SMS character limits (160 chars)
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }

        return $message;
    }

    /**
     * Format WhatsApp message.
     */
    protected function formatWhatsAppMessage(ExeatNotification $notification): string
    {
        return "*" . $notification->title . "*\n\n" . $notification->message;
    }

    /**
     * Retry failed deliveries.
     */
    public function retryFailedDeliveries(int $maxRetries = 3): int
    {
        $failedLogs = NotificationDeliveryLog::where('status', NotificationDeliveryLog::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries)
            ->with('notification')
            ->get();
        
        $retriedCount = 0;
        
        foreach ($failedLogs as $log) {
            if ($this->deliverNotification($log->notification, $log->channel)) {
                $retriedCount++;
            }
            
            $log->increment('retry_count');
        }
        
        return $retriedCount;
    }

    /**
     * Get delivery statistics.
     */
    public function getDeliveryStats(array $filters = []): array
    {
        $query = NotificationDeliveryLog::query();
        
        if (isset($filters['date_from'])) {
            $query->where('attempted_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('attempted_at', '<=', $filters['date_to']);
        }
        
        if (isset($filters['method'])) {
            $query->where('channel', $filters['method']);
        }
        
        $stats = $query->selectRaw('
            channel,
            status,
            COUNT(*) as count
        ')
        ->groupBy('channel', 'status')
        ->get()
        ->groupBy('channel');
        
        return $stats->map(function ($methodStats) {
            $total = $methodStats->sum('count');
            $delivered = $methodStats->where('status', NotificationDeliveryLog::STATUS_DELIVERED)->sum('count');
            $failed = $methodStats->where('status', NotificationDeliveryLog::STATUS_FAILED)->sum('count');
            $pending = $methodStats->where('status', NotificationDeliveryLog::STATUS_PENDING)->sum('count');
            
            return [
                'total' => $total,
                'delivered' => $delivered,
                'failed' => $failed,
                'pending' => $pending,
                'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0
            ];
        })->toArray();
    }
}