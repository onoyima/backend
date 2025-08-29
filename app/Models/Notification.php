<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        // Legacy fields for backward compatibility
        'user_id',
        'exeat_request_id',
        'channel',
        'status',
        'message',
        'sent_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the notifiable entity that the notification belongs to.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Legacy relationship for backward compatibility
     */
    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }

    /**
     * Legacy relationship for backward compatibility
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): void
    {
        if (!is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * Determine if a notification has been read.
     */
    public function read(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Determine if a notification has not been read.
     */
    public function unread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope a query to filter by notification type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the notification title from data.
     */
    public function getTitleAttribute()
    {
        return $this->data['title'] ?? $this->message ?? null;
    }

    /**
     * Get the notification message from data.
     */
    public function getMessageAttribute()
    {
        return $this->data['message'] ?? $this->attributes['message'] ?? null;
    }

    /**
     * Get the notification action URL from data.
     */
    public function getActionUrlAttribute()
    {
        return $this->data['action_url'] ?? null;
    }

    /**
     * Get the notification icon from data.
     */
    public function getIconAttribute()
    {
        return $this->data['icon'] ?? 'bell';
    }

    /**
     * Get the notification priority from data.
     */
    public function getPriorityAttribute()
    {
        return $this->data['priority'] ?? 'normal';
    }

    /**
     * Get the exeat request ID from data or direct field.
     */
    public function getExeatRequestIdAttribute()
    {
        return $this->data['exeat_request_id'] ?? $this->attributes['exeat_request_id'] ?? null;
    }
}
