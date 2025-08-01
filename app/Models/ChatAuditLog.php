<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatAuditLog extends Model
{
    use HasFactory;

    protected $table = 'chat_audit_logs';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'actor_id',
        'actor_type',
        'action',
        'action_type',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function actor()
    {
        return $this->morphTo('actor');
    }

    public static function log($action, $actionType, $actor, $conversationId = null, $messageId = null, $oldValues = null, $newValues = null, $reason = null)
    {
        return self::create([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'actor_id' => $actor->id,
            'actor_type' => get_class($actor),
            'action' => $action,
            'action_type' => $actionType,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}