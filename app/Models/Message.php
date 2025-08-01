<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'content',
        'type',
        'metadata',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'reply_to_id',
        'is_edited',
        'edited_at',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // Relationships
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo('sender');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(MessageMedia::class, 'message_id');
    }

    public function readReceipts(): HasMany
    {
        return $this->hasMany(MessageReadReceipt::class, 'message_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(Staff::class, 'deleted_by');
    }

    // Scopes
    public function scopeVisible($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeUnread($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeText($query)
    {
        return $query->where('type', 'text');
    }

    public function scopeMedia($query)
    {
        return $query->whereIn('type', ['image', 'video', 'file']);
    }

    // Helper methods
    public function isReadBy($user): bool
    {
        $readerType = $user instanceof Student ? Student::class : Staff::class;
        
        return $this->readReceipts()
                   ->where('reader_id', $user->id)
                   ->where('reader_type', $readerType)
                   ->exists();
    }

    public function markAsRead($user): void
    {
        $readerType = $user instanceof Student ? Student::class : Staff::class;
        
        $this->readReceipts()->firstOrCreate([
            'reader_id' => $user->id,
            'reader_type' => $readerType,
        ], [
            'read_at' => now(),
        ]);

        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        if (!$this->delivered_at) {
            $this->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
        }
    }

    public function getReadCount(): int
    {
        return $this->readReceipts()->count();
    }

    public function canEdit($user): bool
    {
        $senderType = $user instanceof Student ? Student::class : Staff::class;
        
        return $this->sender_id === $user->id && 
               $this->sender_type === $senderType &&
               !$this->is_deleted &&
               $this->created_at->diffInMinutes(now()) < 15; // 15-minute edit window
    }

    public function canDelete($user): bool
    {
        $senderType = $user instanceof Student ? Student::class : Staff::class;
        
        // Sender can delete their own messages
        $isSender = $this->sender_id === $user->id && $this->sender_type === $senderType;
        
        // Admins can delete any message
        $isAdmin = false;
        if ($user instanceof Staff) {
            $isAdmin = $user->exeatRoles()->whereHas('role', function ($query) {
                $query->where('name', 'admin');
            })->exists();
        }

        return $isSender || $isAdmin;
    }
}