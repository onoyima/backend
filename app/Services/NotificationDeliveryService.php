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
        // Always use email and in-app delivery methods
        $deliveryMethods = ['email', 'in_app'];
        
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
        // Always use email and in-app delivery methods
        $deliveryMethods = ['email', 'in_app'];
        
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
        
        if (!$recipient || !$recipient['email']) {
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
                $message->to($recipient['email'], $recipient['name'])
                    ->subject($notification->title);
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
                    'name' => $staff->full_name,
                    'email' => $staff->email,
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