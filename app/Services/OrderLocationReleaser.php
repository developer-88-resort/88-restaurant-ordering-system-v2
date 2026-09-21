<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SpaceStatus;
use App\Models\Order;

/**
 * Frees the space (or pooled session) an order was using once the order
 * reaches a final state (Completed/Cancelled), so it's ready for the next
 * customer without staff having to release it by hand.
 *
 * A table can hold several slips at once, so finishing one of them only
 * frees the table when it was the last one still open — otherwise Slip #1
 * being completed would empty a table whose Slip #2 is still cooking.
 *
 * Shared by the status change on Order Management/Kitchen and by an item
 * cancellation that leaves nothing on the order.
 */
class OrderLocationReleaser
{
    public static function release(Order $order): void
    {
        $order->loadMissing(['space', 'spaceSession']);

        if (self::tableHasOtherOpenSlips($order)) {
            return;
        }

        if ($order->space && $order->space->status !== SpaceStatus::Available) {
            $order->space->setStatusWithSharedTables(SpaceStatus::Available);

            return;
        }

        if ($order->spaceSession && $order->spaceSession->status === 'active') {
            $order->spaceSession->update(['status' => 'completed', 'ended_at' => now()]);
        }
    }

    /**
     * Another slip still in play on the same table or the same tab. A
     * reservation for a later date doesn't count — it isn't at the table
     * yet (the same rule OrderAppender uses for what's open).
     */
    public static function tableHasOtherOpenSlips(Order $order): bool
    {
        if (! $order->space_id && ! $order->space_session_id) {
            return false;
        }

        return Order::query()
            ->whereKeyNot($order->getKey())
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->withoutFutureReservations()
            ->where(function ($query) use ($order) {
                $query->when($order->space_id, fn ($q) => $q->orWhere('space_id', $order->space_id))
                    ->when($order->space_session_id, fn ($q) => $q->orWhere('space_session_id', $order->space_session_id));
            })
            ->exists();
    }
}
