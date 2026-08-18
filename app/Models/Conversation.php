<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id'];

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Find-or-create the single thread between two users. The pair is
     * always stored in canonical (smaller id, larger id) order so the
     * table's unique index guarantees exactly one thread regardless of
     * who initiates it.
     */
    public static function between(User $a, User $b): self
    {
        abort_if($a->id === $b->id, 422, 'Cannot start a conversation with yourself.');

        [$lowId, $highId] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return static::firstOrCreate([
            'user_one_id' => $lowId,
            'user_two_id' => $highId,
        ]);
    }

    public function otherParticipant(User $current): User
    {
        return $this->user_one_id === $current->id ? $this->userTwo : $this->userOne;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }
}
