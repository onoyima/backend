<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'type' => $this->message->type,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'type' => $this->message->sender_type,
                    'name' => method_exists($this->message->sender, 'fname') 
                        ? trim($this->message->sender->fname . ' ' . $this->message->sender->lname)
                        : $this->message->sender->name,
                    'email' => $this->message->sender->email,
                ],
                'metadata' => $this->message->metadata,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at->toDateTimeString(),
                'updated_at' => $this->message->updated_at->toDateTimeString(),
            ]
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}