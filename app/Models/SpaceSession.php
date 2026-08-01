<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpaceSession extends Model
{
    protected $fillable = [
        'space_id',
        'category_id',
        'status',
        'public_token',
        'opened_by',
        'closed_by',
        'started_at',
        'ended_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SpaceSession $session) {
            $session->status ??= 'active';
            $session->started_at ??= now();
        });
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SpaceCategory::class, 'category_id');
    }

    public function guestSessions(): HasMany
    {
        return $this->hasMany(GuestSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * A QR dining session accepts orders only while status is active AND
     * it hasn't passed its optional hard expiry.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Close this dining session (settled bill, staff action, or expiry) —
     * every guest token under it stops working at the same moment.
     */
    public function close(?int $closedByUserId = null): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'closed_by' => $closedByUserId,
        ]);

        $this->guestSessions()->where('status', 'active')->update(['status' => 'closed']);
    }

    /**
     * Next sequential order-batch number within this dining session,
     * resolved with a lock so two guests submitting at the same instant
     * can't both get the same batch number.
     */
    public function nextBatchNumber(): int
    {
        $max = $this->orders()->lockForUpdate()->max('batch_number');

        return ((int) $max) + 1;
    }
}
