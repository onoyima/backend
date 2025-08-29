<?php

namespace App\Services;

use App\Models\ExeatNotification;
use App\Models\NotificationDeliveryLog;
use App\Models\Student;
use App\Models\Staff;
use App\Models\StudentContact;
use App\Jobs\SendNotificationJob;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Exception;

class NotificationDeliveryService
{
    public function __construct()
    {
        // No dependencies needed
    }

    /**
     * Queue notification for delivery.
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
            
            // Queue immediate delivery
            SendNotificationJob::dispatch($notification, $method)
                ->onQueue('notifications');
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
     * Deliver SMS notification.
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
            // Using a generic SMS service - replace with your actual SMS provider
            $response = Http::post(config('services.sms.endpoint'), [
                'to' => $recipient['phone'],
                'message' => $this->formatSMSMessage($notification),
                'api_key' => config('services.sms.api_key')
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'metadata' => [
                        'delivery_provider' => 'sms_service',
                        'provider_message_id' => $responseData['message_id'] ?? null
                    ]
                ]);
                
                return true;
            } else {
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'SMS service returned error: ' . $response->body()
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
     * Deliver WhatsApp notification.
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
            // Using WhatsApp Business API - replace with your actual WhatsApp provider
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.whatsapp.token'),
                'Content-Type' => 'application/json'
            ])->post(config('services.whatsapp.endpoint'), [
                'messaging_product' => 'whatsapp',
                'to' => $recipient['phone'],
                'type' => 'text',
                'text' => [
                    'body' => $this->formatWhatsAppMessage($notification)
                ]
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'metadata' => [
                        'delivery_provider' => 'whatsapp_business',
                        'provider_message_id' => $responseData['messages'][0]['id'] ?? null
                    ]
                ]);
                
                return true;
            } else {
                $log->update([
                    'status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'WhatsApp service returned error: ' . $response->body()
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
     */
    protected function getRecipientDetails(ExeatNotification $notification): ?array
    {
        switch ($notification->recipient_type) {
            case ExeatNotification::RECIPIENT_STUDENT:
                $student = Student::find($notification->recipient_id);
                return $student ? [
                    'name' => $student->full_name,
                    'email' => $student->username,
                    'phone' => $student->phone
                ] : null;
                
            case ExeatNotification::RECIPIENT_STAFF:
                $staff = Staff::find($notification->recipient_id);
                return $staff ? [
                    'name' => $staff->full_name,
                    'email' => $staff->email,
                    'phone' => $staff->phone
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
     */
    protected function scheduleForLater(ExeatNotification $notification, string $method): void
    {
        // No scheduling needed - send immediately
        SendNotificationJob::dispatch($notification, $method)
            ->onQueue('notifications');
    }

    /**
     * Format SMS message.
     */
    protected function formatSMSMessage(ExeatNotification $notification): string
    {
        $message = $notification->title . "\n\n" . $notification->message;
        
        // Truncate if too long for SMS
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