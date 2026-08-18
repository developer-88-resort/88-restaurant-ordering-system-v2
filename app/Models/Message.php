<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'reply_to_message_id', 'body', 'delivered_at', 'edited_at',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'delivered_at' => 'datetime', 'edited_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path
            ? Storage::disk('public')->url($this->attachment_path)
            : null;
    }

    /**
     * Groups this message's reactions by emoji — the one place that shape
     * gets built, reused by both formatMessage() and the broadcast event
     * so the two can never drift apart.
     *
     * @return array<int, array{emoji: string, count: int, user_ids: array<int, int>, reacted_by_me: bool}>
     */
    public function reactionSummary(int $viewerId): array
    {
        return $this->reactions
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'user_ids' => $group->pluck('user_id')->all(),
                'reacted_by_me' => $group->contains('user_id', $viewerId),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    public static function unreadCountForUser(int $userId): int
    {
        return static::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->whereHas('conversation', fn ($q) => $q
                ->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId))
            ->count();
    }

    public static function unreadCountForConversation(int $conversationId, int $userId): int
    {
        return static::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->count();
    }
}
