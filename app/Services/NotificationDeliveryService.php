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
    protected $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    /**
     * Queue notification for delivery.
     */
    public function queueNotificationDelivery(ExeatNotification $notification): void
    {
        $deliveryMethods = $notification->delivery_methods;
        
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
                event(new NotificationSent($notification, $method));
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
            'delivery_status' => NotificationDeliveryLog::STATUS_DELIVERED,
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
                'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
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
                'delivery_status' => NotificationDeliveryLog::STATUS_DELIVERED,
                'delivered_at' => now()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $log->update([
                'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
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
                'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
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
                    'delivery_status' => NotificationDeliveryLog::STATUS_DELIVERED,
                    'delivery_provider' => 'sms_service',
                    'provider_message_id' => $responseData['message_id'] ?? null,
                    'delivered_at' => now()
                ]);
                
                return true;
            } else {
                $log->update([
                    'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'SMS service returned error: ' . $response->body()
                ]);
                
                return false;
            }
            
        } catch (Exception $e) {
            $log->update([
                'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
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
                'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
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
                    'delivery_status' => NotificationDeliveryLog::STATUS_DELIVERED,
                    'delivery_provider' => 'whatsapp_business',
                    'provider_message_id' => $responseData['messages'][0]['id'] ?? null,
                    'delivered_at' => now()
                ]);
                
                return true;
            } else {
                $log->update([
                    'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
                    'error_message' => 'WhatsApp service returned error: ' . $response->body()
                ]);
                
                return false;
            }
            
        } catch (Exception $e) {
            $log->update([
                'delivery_status' => NotificationDeliveryLog::STATUS_FAILED,
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
                    'email' => $student->email,
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
        return NotificationDeliveryLog::create([
            'notification_id' => $notification->id,
            'delivery_method' => $method,
            'delivery_status' => NotificationDeliveryLog::STATUS_PENDING,
            'attempted_at' => now()
        ]);
    }

    /**
     * Update delivery status in notification.
     */
    protected function updateDeliveryStatus(
        ExeatNotification $notification,
        string $method,
        string $status,
        string $errorMessage = null
    ): void {
        $deliveryStatus = $notification->delivery_status;
        $deliveryStatus[$method] = [
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'error' => $errorMessage
        ];
        
        $notification->update(['delivery_status' => $deliveryStatus]);
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
        
        $preferences = $this->preferenceService->getUserPreferences(
            $notification->recipient_type,
            $notification->recipient_id
        );
        
        if (!$preferences || !$preferences->quiet_hours_start || !$preferences->quiet_hours_end) {
            return false;
        }
        
        $now = now();
        $quietStart = $now->copy()->setTimeFromTimeString($preferences->quiet_hours_start);
        $quietEnd = $now->copy()->setTimeFromTimeString($preferences->quiet_hours_end);
        
        // Handle overnight quiet hours (e.g., 22:00 to 06:00)
        if ($quietStart->gt($quietEnd)) {
            return $now->gte($quietStart) || $now->lte($quietEnd);
        }
        
        return $now->between($quietStart, $quietEnd);
    }

    /**
     * Schedule notification for later delivery.
     */
    protected function scheduleForLater(ExeatNotification $notification, string $method): void
    {
        $preferences = $this->preferenceService->getUserPreferences(
            $notification->recipient_type,
            $notification->recipient_id
        );
        
        if (!$preferences || !$preferences->quiet_hours_end) {
            // Default to 8 AM if no preferences
            $deliveryTime = now()->addDay()->setTime(8, 0);
        } else {
            $deliveryTime = now()->copy()->setTimeFromTimeString($preferences->quiet_hours_end);
            
            // If quiet hours end is earlier today, schedule for tomorrow
            if ($deliveryTime->lt(now())) {
                $deliveryTime->addDay();
            }
        }
        
        SendNotificationJob::dispatch($notification, $method)
            ->delay($deliveryTime)
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
        $failedLogs = NotificationDeliveryLog::where('delivery_status', NotificationDeliveryLog::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries)
            ->with('notification')
            ->get();
        
        $retriedCount = 0;
        
        foreach ($failedLogs as $log) {
            if ($this->deliverNotification($log->notification, $log->delivery_method)) {
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
            $query->where('delivery_method', $filters['method']);
        }
        
        $stats = $query->selectRaw('
            delivery_method,
            delivery_status,
            COUNT(*) as count
        ')
        ->groupBy('delivery_method', 'delivery_status')
        ->get()
        ->groupBy('delivery_method');
        
        return $stats->map(function ($methodStats) {
            $total = $methodStats->sum('count');
            $delivered = $methodStats->where('delivery_status', NotificationDeliveryLog::STATUS_DELIVERED)->sum('count');
            $failed = $methodStats->where('delivery_status', NotificationDeliveryLog::STATUS_FAILED)->sum('count');
            $pending = $methodStats->where('delivery_status', NotificationDeliveryLog::STATUS_PENDING)->sum('count');
            
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