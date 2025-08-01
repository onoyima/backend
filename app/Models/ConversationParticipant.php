<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'participant_id',
        'participant_type',
        'role',
        'can_add_members',
        'can_remove_members',
        'can_edit_group',
        'can_delete_messages',
        'joined_at',
        'left_at',
        'is_active',
    ];

    protected $casts = [
        'can_add_members' => 'boolean',
        'can_remove_members' => 'boolean',
        'can_edit_group' => 'boolean',
        'can_delete_messages' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function participant()
    {
        return $this->morphTo('participant');
    }

    public function isCreator(): bool
    {
        return $this->role === 'creator';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['creator', 'admin']);
    }

    public function canManageGroup(): bool
    {
        return $this->isAdmin() || $this->can_edit_group;
    }

    public function canAddMembers(): bool
    {
        return $this->isAdmin() || $this->can_add_members;
    }

    public function canRemoveMembers(): bool
    {
        return $this->isAdmin() || $this->can_remove_members;
    }

    public function canDeleteMessages(): bool
    {
        return $this->isAdmin() || $this->can_delete_messages;
    }
}