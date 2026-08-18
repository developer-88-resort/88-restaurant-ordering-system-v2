<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Delivered to the recipient's personal inbox channel so their sidebar
 * badge and a toast can update live even when they aren't currently
 * viewing this specific conversation thread.
 */
class ChatInboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $recipientId,
        public int $conversationId,
        public string $senderName,
        public string $preview,
    ) {
        //
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat-inbox.{$this->recipientId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatInboxUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'sender_name' => $this->senderName,
            'preview' => $this->preview,
            // Global total, for the sidebar badge.
            'unread_count' => Message::unreadCountForUser($this->recipientId),
            // Authoritative per-conversation count, so the conversation
            // list row can be SET to the true value instead of blindly
            // incrementing client-side — a naive "+1 per ping" drifts out
            // of sync the moment a message gets read through any path this
            // specific browser tab doesn't know about (another tab/device,
            // or simply not having reloaded in a while).
            'conversation_unread_count' => Message::unreadCountForConversation($this->conversationId, $this->recipientId),
        ];
    }
}
