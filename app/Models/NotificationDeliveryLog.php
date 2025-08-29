<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryLog extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_READ = 'read';

    // Delivery method constants
    const METHOD_EMAIL = 'email';
    const METHOD_SMS = 'sms';
    const METHOD_IN_APP = 'in_app';
    const METHOD_PUSH = 'push';

    protected $fillable = [
        'notification_id',
        'channel',
        'recipient',
        'status',
        'metadata',
        'sent_at',
        'delivered_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
        'retry_count' => 'integer',
        'max_retries' => 'integer'
    ];

    /**
     * Get the notification that this delivery log belongs to.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(ExeatNotification::class, 'notification_id');
    }

    /**
     * Check if the delivery was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if the delivery failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the delivery is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Mark the delivery as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    /**
     * Mark the delivery as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    /**
     * Mark the delivery as failed.
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason
        ]);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'read_at' => now()
        ]);
    }

    /**
     * Increment the retry count.
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }
}