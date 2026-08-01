<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One anonymous guest device inside one table dining session. The public
 * token (stored in the guest's browser cookie) is their only credential —
 * a guest can only ever see or submit against their own token.
 */
class GuestSession extends Model
{
    protected $fillable = [
        'space_session_id',
        'public_token',
        'guest_number',
        'display_name',
        'status',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'guest_number' => 'integer',
            'last_active_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GuestSession $session) {
            $session->public_token ??= Str::random(40);
            $session->status ??= 'active';
            $session->last_active_at ??= now();
        });
    }

    public function spaceSession(): BelongsTo
    {
        return $this->belongsTo(SpaceSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function displayLabel(): string
    {
        return $this->display_name ?: __('Guest :number', ['number' => $this->guest_number]);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
