<?php

namespace App\Jobs;

use App\Models\ExeatNotification;
use App\Services\NotificationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notification;
    protected $method;

    /**
     * Create a new job instance.
     */
    public function __construct(ExeatNotification $notification, string $method)
    {
        $this->notification = $notification;
        $this->method = $method;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationDeliveryService $deliveryService): void
    {
        try {
            Log::info('Processing notification job', [
                'notification_id' => $this->notification->id,
                'method' => $this->method,
                'recipient_type' => $this->notification->recipient_type,
                'recipient_id' => $this->notification->recipient_id
            ]);

            $success = $deliveryService->deliverNotification($this->notification, $this->method);
            
            if ($success) {
                Log::info('Notification sent successfully', [
                    'notification_id' => $this->notification->id,
                    'method' => $this->method
                ]);
            } else {
                Log::warning('Notification delivery failed', [
                    'notification_id' => $this->notification->id,
                    'method' => $this->method
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error processing notification job', [
                'notification_id' => $this->notification->id,
                'method' => $this->method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('SendNotificationJob failed', [
            'notification_id' => $this->notification->id,
            'method' => $this->method,
            'error' => $exception->getMessage()
        ]);
    }
}