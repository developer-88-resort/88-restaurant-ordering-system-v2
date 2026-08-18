<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Remembers which append requests have already been served, keyed on a
 * (request_uuid, line_index) pair rather than one packed string — a
 * request_uuid is always the client's bare UUID (append() uses index 0 for
 * its one line; appendBatch() gives each line in the round its own index),
 * so the UUID half never has to share column space with a suffix.
 */
class AppendIdempotencyKey extends Model
{
    protected $fillable = ['request_uuid', 'line_index', 'order_id', 'order_item_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * The line a previous request with this (request_uuid, line_index)
     * pair already produced, or null if it's new (or old enough that a
     * retry is no longer plausible and a repeat is more likely a genuine
     * second fish).
     */
    public static function resolve(?string $requestUuid, int $lineIndex, Order $order): ?OrderItem
    {
        if (! $requestUuid) {
            return null;
        }

        $existing = static::where('request_uuid', $requestUuid)
            ->where('line_index', $lineIndex)
            ->where('order_id', $order->id)
            ->where('expires_at', '>', now())
            ->first();

        return $existing?->orderItem;
    }

    public static function remember(?string $requestUuid, int $lineIndex, Order $order, OrderItem $item): void
    {
        if (! $requestUuid) {
            return;
        }

        // The column is a native CHAR(36) UUID. Anything longer would
        // either error under strict SQL mode or silently truncate and
        // collide with an unrelated request under a laxer one — exactly
        // the bug this model used to have when the line index rode along
        // inside this same string. Fail loudly here instead of at the
        // database, and before any row is written.
        if (strlen($requestUuid) > 36) {
            throw new InvalidArgumentException("Idempotency request_uuid exceeds 36 characters: {$requestUuid}");
        }

        static::updateOrCreate(
            ['request_uuid' => $requestUuid, 'line_index' => $lineIndex],
            ['order_id' => $order->id, 'order_item_id' => $item->id, 'expires_at' => now()->addDay()],
        );
    }
}
