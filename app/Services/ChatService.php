<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ConversationParticipant;
use App\Models\ChatAuditLog;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ChatService
{
    public function createDirectConversation($user1, $user2): Conversation
{
    if (!$user1 || !$user2) {
        throw new \InvalidArgumentException('Both users must be valid');
    }

    return DB::transaction(function () use ($user1, $user2) {
        $existing = $this->getDirectConversation($user1, $user2);
        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create(['type' => 'direct']);

        $this->addParticipant($conversation, $user1);
        $this->addParticipant($conversation, $user2);

        return $conversation;
    });
}
    public function createGroupConversation($creator, $name, $description = null, $participants = []): Conversation
    {
        return DB::transaction(function () use ($creator, $name, $description, $participants) {
            $conversation = Conversation::create([
                'type' => 'group',
                'name' => $name,
                'description' => $description,
            ]);

            // Add creator as admin
            $this->addParticipant($conversation, $creator, 'creator', [
                'can_add_members' => true,
                'can_remove_members' => true,
                'can_edit_group' => true,
                'can_delete_messages' => true,
            ]);

            // Add other participants
            foreach ($participants as $participant) {
                $this->addParticipant($conversation, $participant, 'member');
            }

            ChatAuditLog::log('created', 'conversation', $creator, $conversation->id);

            return $conversation;
        });
    }

    public function getDirectConversation($user1, $user2): ?Conversation
    {
        if (!$user1 || !$user2) {
            \Log::error('Null participant passed to getDirectConversation', [
                'user1' => $user1,
                'user2' => $user2,
            ]);
            return null;
        }

        $user1Type = get_class($user1);
        $user2Type = get_class($user2);

        return Conversation::where('type', 'direct')
            ->whereHas('participants', function ($query) use ($user1, $user1Type) {
                $query->where('participant_id', $user1->id)
                    ->where('participant_type', $user1Type);
            })
            ->whereHas('participants', function ($query) use ($user2, $user2Type) {
                $query->where('participant_id', $user2->id)
                    ->where('participant_type', $user2Type);
            })
            ->first();
    }


    public function addParticipant(Conversation $conversation, $participant, string $role = 'member', array $permissions = []): ConversationParticipant
    {
        $participantType = get_class($participant);

        return ConversationParticipant::updateOrCreate([
            'conversation_id' => $conversation->id,
            'participant_id' => $participant->id,
            'participant_type' => $participantType,
        ], [
            'role' => $role,
            'can_add_members' => $permissions['can_add_members'] ?? false,
            'can_remove_members' => $permissions['can_remove_members'] ?? false,
            'can_edit_group' => $permissions['can_edit_group'] ?? false,
            'can_delete_messages' => $permissions['can_delete_messages'] ?? false,
            'joined_at' => now(),
            'left_at' => null,
            'is_active' => true,
        ]);
    }

    public function removeParticipant(Conversation $conversation, $participant): bool
    {
        $participantType = get_class($participant);

        $participantRecord = $conversation->participants()
            ->where('participant_id', $participant->id)
            ->where('participant_type', $participantType)
            ->first();

        if ($participantRecord) {
            $participantRecord->update([
                'left_at' => now(),
                'is_active' => false,
            ]);

            return true;
        }

        return false;
    }

    public function sendMessage(Conversation $conversation, $sender, string $content, string $type = 'text', array $metadata = []): Message
    {
        if ($conversation->is_suspended) {
            throw new \Exception('Cannot send messages in suspended conversation');
        }

        if (!$conversation->isUserParticipant($sender)) {
            throw new \Exception('User is not a participant in this conversation');
        }

        return DB::transaction(function () use ($conversation, $sender, $content, $type, $metadata) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'sender_type' => get_class($sender),
                'content' => $content,
                'type' => $type,
                'metadata' => $metadata,
                'sent_at' => now(),
            ]);

            ChatAuditLog::log('created', 'message', $sender, $conversation->id, $message->id);

            return $message;
        });
    }

    public function sendMediaMessage(Conversation $conversation, $sender, UploadedFile $file, string $type): Message
    {
        $allowedTypes = ['image', 'video', 'file'];
        if (!in_array($type, $allowedTypes)) {
            throw new \Exception('Invalid media type');
        }

        $maxSize = $type === 'video' ? 50 * 1024 * 1024 : 10 * 1024 * 1024; // 50MB for videos, 10MB for others
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds limit');
        }

        $allowedMimeTypes = match ($type) {
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'video' => ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm'],
            'file' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            default => [],
        };

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \Exception('Invalid file type');
        }

        return DB::transaction(function () use ($conversation, $sender, $file, $type) {
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('chat-media/' . date('Y/m/d'), $filename, 'public');

            $message = $this->sendMessage($conversation, $sender, null, $type, [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            $media = MessageMedia::create([
                'message_id' => $message->id,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_path' => $path,
                'disk' => 'public',
            ]);

            return $message;
        });
    }

    public function getUserConversations($user)
    {
        $participantType = get_class($user);

        return Conversation::whereHas('participants', function ($query) use ($user, $participantType) {
            $query->where('participant_id', $user->id)
                  ->where('participant_type', $participantType)
                  ->where('is_active', true);
        })
        ->with(['participants', 'latestMessage'])
        ->orderByDesc(
            Message::select('created_at')
                ->whereColumn('conversation_id', 'conversations.id')
                ->latest()
                ->take(1)
        )
        ->get();
    }

    public function searchMessages(Conversation $conversation, string $query, $user)
    {
        if (!$conversation->isUserParticipant($user)) {
            throw new \Exception('User is not a participant in this conversation');
        }

        return $conversation->messages()
            ->visible()
            ->where('type', 'text')
            ->where('content', 'like', '%' . $query . '%')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function suspendConversation(Conversation $conversation, Staff $admin, string $reason = null): void
    {
        $conversation->update([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspended_by' => $admin->id,
            'suspension_reason' => $reason,
        ]);

        ChatAuditLog::log('suspended', 'conversation', $admin, $conversation->id, null, null, null, $reason);
    }

    public function unsuspendConversation(Conversation $conversation, Staff $admin, string $reason = null): void
    {
        $oldValues = [
            'is_suspended' => true,
            'suspended_at' => $conversation->suspended_at,
            'suspended_by' => $conversation->suspended_by,
            'suspension_reason' => $conversation->suspension_reason,
        ];

        $conversation->update([
            'is_suspended' => false,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        ChatAuditLog::log('unsuspended', 'conversation', $admin, $conversation->id, null, $oldValues, $conversation->toArray(), $reason);
    }

    public function deleteMessage(Message $message, $user, string $reason = null): void
    {
        if (!$message->canDelete($user)) {
            throw new \Exception('User cannot delete this message');
        }

        $oldValues = $message->toArray();

        $message->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);

        ChatAuditLog::log('deleted', 'message', $user, $message->conversation_id, $message->id, $oldValues, $message->toArray(), $reason);
    }
}
