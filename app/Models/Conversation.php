<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'description',
        'avatar',
        'is_suspended',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
        'suspended_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // Relationships
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'conversation_participants', 'conversation_id', 'participant_id')
                   ->where('participant_type', Student::class)
                   ->withPivot('role', 'joined_at', 'left_at', 'is_active')
                   ->withTimestamps();
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'conversation_participants', 'conversation_id', 'participant_id')
                   ->where('participant_type', Staff::class)
                   ->withPivot('role', 'joined_at', 'left_at', 'is_active')
                   ->withTimestamps();
    }

    public function suspendedBy()
    {
        return $this->belongsTo(Staff::class, 'suspended_by');
    }

    public function getParticipantCount(): int
    {
        return $this->participants()->where('is_active', true)->count();
    }

    public function isUserParticipant($user): bool
    {
        $participantType = $user instanceof Student ? Student::class : Staff::class;
        
        return $this->participants()
                   ->where('participant_id', $user->id)
                   ->where('participant_type', $participantType)
                   ->where('is_active', true)
                   ->exists();
    }

    public function getParticipantRole($user): ?string
    {
        $participantType = $user instanceof Student ? Student::class : Staff::class;
        
        $participant = $this->participants()
                           ->where('participant_id', $user->id)
                           ->where('participant_type', $participantType)
                           ->where('is_active', true)
                           ->first();

        return $participant ? $participant->role : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_suspended', false);
    }

    public function scopeSuspended($query)
    {
        return $query->where('is_suspended', true);
    }

    public function scopeDirect($query)
    {
        return $query->where('type', 'direct');
    }

    public function scopeGroup($query)
    {
        return $query->where('type', 'group');
    }

    public function getLatestMessage()
    {
        return $this->messages()->latest()->first();
    }

    public function getUnreadCount($user): int
    {
        $participantType = $user instanceof Student ? Student::class : Staff::class;
        
        return $this->messages()
                   ->whereDoesntHave('readReceipts', function ($query) use ($user, $participantType) {
                       $query->where('reader_id', $user->id)
                             ->where('reader_type', $participantType);
                   })
                   ->where('sender_id', '!=', $user->id)
                   ->where('sender_type', '!=', $participantType)
                   ->count();
    }
}