<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Message $message,
    ) {
        //
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.conversation.{$this->message->conversation_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
            'delivered_at' => $this->message->delivered_at?->toIso8601String(),
            'read_at' => $this->message->read_at?->toIso8601String(),
            'reply_to' => $this->message->replyTo ? [
                'id' => $this->message->replyTo->id,
                'body' => Str::limit($this->message->replyTo->body, 80),
                'sender_name' => $this->message->replyTo->sender->name,
            ] : null,
            'attachment' => $this->message->attachment_path ? [
                'url' => $this->message->attachmentUrl(),
                'name' => $this->message->attachment_name,
                'mime' => $this->message->attachment_mime,
                'size' => $this->message->attachment_size,
            ] : null,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
                'avatar_url' => $this->message->sender->avatarUrl(),
                'initials' => $this->message->sender->initials(),
            ],
        ];
    }
}
