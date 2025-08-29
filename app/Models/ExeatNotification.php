<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExeatNotification extends Model
{
    use HasFactory;

    protected $table = 'exeat_notifications';

    protected $fillable = [
        'exeat_request_id',
        'recipient_type',
        'recipient_id',
        'notification_type',
        'title',
        'message',
        'delivery_methods',
        'data',
        'priority',
        'is_read',
        'read_at',
        'action_url',
        'icon',
    ];

    /**
     * Accessor for type attribute (maps to notification_type column)
     */
    public function getTypeAttribute()
    {
        return $this->notification_type;
    }

    /**
     * Mutator for type attribute (maps to notification_type column)
     */
    public function setTypeAttribute($value)
    {
        $this->attributes['notification_type'] = $value;
    }

    protected $casts = [
        'delivery_methods' => 'array',
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_read' => false,
        'priority' => 'medium',
    ];

    // Notification types
    const TYPE_STAGE_CHANGE = 'stage_change';
    const TYPE_APPROVAL_REQUIRED = 'approval_required';
    const TYPE_REMINDER = 'reminder';
    const TYPE_EMERGENCY = 'emergency';
    const TYPE_REQUEST_SUBMITTED = 'request_submitted';
    const TYPE_REJECTION = 'rejection';

    // Recipient types
    const RECIPIENT_STUDENT = 'student';
    const RECIPIENT_STAFF = 'staff';
    const RECIPIENT_ADMIN = 'admin';

    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Delivery methods
    const DELIVERY_IN_APP = 'in_app';
    const DELIVERY_EMAIL = 'email';
    const DELIVERY_SMS = 'sms';
    const DELIVERY_WHATSAPP = 'whatsapp';

    /**
     * Get the exeat request that owns the notification.
     */
    public function exeatRequest(): BelongsTo
    {
        return $this->belongsTo(ExeatRequest::class);
    }

    /**
     * Get the recipient (polymorphic relationship).
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo('recipient', 'recipient_type', 'recipient_id');
    }

    /**
     * Get the delivery logs for this notification.
     */
    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class, 'notification_id');
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for specific recipient type.
     */
    public function scopeForRecipientType($query, $type)
    {
        return $query->where('recipient_type', $type);
    }

    /**
     * Scope for specific recipient.
     */
    public function scopeForRecipient($query, $type, $id)
    {
        return $query->where('recipient_type', $type)->where('recipient_id', $id);
    }

    /**
     * Scope for specific notification type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope for specific priority.
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for urgent notifications.
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Check if notification has specific delivery method.
     */
    public function hasDeliveryMethod($method)
    {
        return in_array($method, $this->delivery_methods ?? []);
    }

    /**
     * Get delivery status for specific method.
     */
    public function getDeliveryStatus($method)
    {
        return $this->delivery_status[$method] ?? null;
    }

    /**
     * Update delivery status for specific method.
     */
    public function updateDeliveryStatus($method, $status)
    {
        $deliveryStatus = $this->delivery_status ?? [];
        $deliveryStatus[$method] = $status;

        $this->update(['delivery_status' => $deliveryStatus]);
    }

    /**
     * Check if notification is urgent.
     */
    public function isUrgent()
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    /**
     * Check if notification requires immediate attention.
     */
    public function requiresImmediateAttention()
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Get formatted delivery methods.
     */
    public function getFormattedDeliveryMethodsAttribute()
    {
        return implode(', ', array_map('ucfirst', $this->delivery_methods ?? []));
    }

    /**
     * Get human readable priority.
     */
    public function getHumanPriorityAttribute()
    {
        return ucfirst($this->priority);
    }

    /**
     * Get notification age in human readable format.
     */
    public function getAgeAttribute()
    {
        return $this->created_at->diffForHumans();
    }
}
