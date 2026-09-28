<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\SpaceStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAdjustment;
use App\Models\Space;
use App\Models\SpaceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an open slip to another table when the party changes their mind at
 * the last minute.
 *
 * Before this, the only way to serve a party that moved was to open a
 * second slip on the new table, which left the first one recorded against
 * the table they never actually sat at — and both slips counted. Here the
 * SAME order row moves, so nothing is recorded twice; when staff choose to
 * fold it into a slip that is already open on the destination, the lines
 * move across and the emptied slip is marked `merged_into_order_id` so
 * Reports counts the sale once (see ReportController::countableOrders()).
 *
 * Deliberately permissive: a completed, cancelled or already-paid slip can
 * still be moved, because these are corrections of where something was
 * recorded and blocking them just sends staff back to the workaround. The
 * one restriction is merging a slip whose payment is already recorded —
 * its invoice froze those exact lines, and pulling them onto another order
 * would leave the invoice describing lines it no longer owns.
 */
class OrderSlipTransferrer
{
    /**
     * @return array{order: Order, mode: string, from: string, to: string}
     */
    public static function transfer(Order $order, Space $target, ?Order $mergeInto, User $actor): array
    {
        $fromLabel = $order->locationLabel();

        if ($mergeInto) {
            self::guardMerge($order, $mergeInto, $target);
        }

        $result = DB::transaction(function () use ($order, $target, $mergeInto, $actor, $fromLabel) {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Remembered before anything moves: releasing the old table has
            // to look at where the slip WAS, not where it now is.
            $oldSpaceId = $order->space_id;
            $oldSessionId = $order->space_session_id;

            if ($mergeInto) {
                $destination = Order::whereKey($mergeInto->getKey())->lockForUpdate()->firstOrFail();
                self::moveLinesInto($order, $destination);
                $mode = 'merged';
            } else {
                $destination = self::relocate($order, $target);
                $mode = 'moved';
            }

            self::releaseOldLocation($oldSpaceId, $oldSessionId, $order->getKey());

            $order->refresh();
            $destination->refresh();

            activity('audit')
                ->causedBy($actor)
                ->performedOn($destination)
                ->event('order_slip_transferred')
                ->withProperties([
                    'mode' => $mode,
                    'order_id' => $order->id,
                    'order_number' => $order->orderNumber(),
                    'from' => $fromLabel,
                    'to' => $destination->locationLabel(),
                    'merged_into_order_id' => $mode === 'merged' ? $destination->id : null,
                ])
                ->log(sprintf(
                    'ORDER SLIP %s: %s — %s → %s%s',
                    strtoupper($mode),
                    $order->orderNumber(),
                    $fromLabel,
                    $destination->locationLabel(),
                    $mode === 'merged' ? ' (into '.$destination->orderNumber().')' : '',
                ));

            return ['order' => $destination, 'mode' => $mode, 'from' => $fromLabel, 'to' => $destination->locationLabel()];
        });

        return $result;
    }

    /**
     * The slip keeps its identity and simply lands on the other table as
     * that table's next slip. A slip that is still in play joins (or opens)
     * the destination's live session; one that is already completed or
     * cancelled is only being re-filed, so it never opens a session or
     * re-occupies a table nobody is sitting at.
     */
    protected static function relocate(Order $order, Space $target): Order
    {
        $stillOpen = ! $order->status->isFinal();

        $session = $stillOpen
            ? TableSessionManager::findOrOpenFor($target)
            : TableSessionManager::activeSessionFor($target);

        $order->update([
            'area_id' => $target->area_id,
            'space_category_id' => $target->category_id,
            'space_id' => $target->id,
            'space_session_id' => $session?->id,
            'slip_number' => $session ? SpaceSession::nextSlipNumber($session->id) : null,
        ]);

        if ($stillOpen && $target->status === SpaceStatus::Available) {
            $target->setStatusWithSharedTables(SpaceStatus::Occupied);
        }

        return $order;
    }

    /**
     * Folds this slip's lines into one that is already open on the
     * destination. The emptied slip is kept, not deleted — its number was
     * already printed on a kitchen slip and adjustments point at it — but
     * it is closed and stamped so no report ever counts it beside the slip
     * that absorbed it.
     */
    protected static function moveLinesInto(Order $order, Order $destination): void
    {
        // order_item_adjustments carries a denormalized order_id of its own
        // (see Order::itemAdjustments) — left behind, a cancelled line's
        // reversal would keep discounting the slip it no longer belongs to.
        OrderItemAdjustment::where('order_id', $order->id)->update(['order_id' => $destination->id]);
        OrderItem::where('order_id', $order->id)->update(['order_id' => $destination->id]);

        $order->update([
            'merged_into_order_id' => $destination->id,
            'status' => OrderStatus::Completed,
            'notes' => trim((string) $order->notes.' '.__('Merged into :number', ['number' => $destination->orderNumber()])),
        ]);

        $order->load('items');
        $destination->load('items.adjustments');

        $order->recalculateTotal();
        $destination->recalculateTotal();
    }

    /**
     * Frees the table the slip just left, but only once nothing else is
     * still open on it — OrderLocationReleaser answers the same question
     * for an order at its current table; here the slip has already moved,
     * so the old location has to be asked about explicitly.
     */
    protected static function releaseOldLocation(?int $spaceId, ?int $sessionId, int $movedOrderId): void
    {
        if (! $spaceId && ! $sessionId) {
            return;
        }

        $stillBusy = Order::query()
            ->whereKeyNot($movedOrderId)
            ->whereNull('merged_into_order_id')
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->withoutFutureReservations()
            ->where(function ($query) use ($spaceId, $sessionId) {
                $query->when($spaceId, fn ($q) => $q->orWhere('space_id', $spaceId))
                    ->when($sessionId, fn ($q) => $q->orWhere('space_session_id', $sessionId));
            })
            ->exists();

        if ($stillBusy) {
            return;
        }

        if ($spaceId && $space = Space::find($spaceId)) {
            if ($space->status !== SpaceStatus::Available) {
                $space->setStatusWithSharedTables(SpaceStatus::Available);
            }
        }

        if ($sessionId && $session = SpaceSession::find($sessionId)) {
            if ($session->status === 'active') {
                $session->update(['status' => 'completed', 'ended_at' => now()]);
            }
        }
    }

    protected static function guardMerge(Order $order, Order $mergeInto, Space $target): void
    {
        if ($mergeInto->is($order)) {
            throw ValidationException::withMessages([
                'merge_into_order_id' => __('A slip cannot be merged into itself.'),
            ]);
        }

        if ($mergeInto->space_id !== $target->id) {
            throw ValidationException::withMessages([
                'merge_into_order_id' => __('That slip is not on the table you picked — refresh and choose again.'),
            ]);
        }

        if ($mergeInto->status->isFinal() || $mergeInto->merged_into_order_id !== null) {
            throw ValidationException::withMessages([
                'merge_into_order_id' => __('That slip is already closed — pick another one, or move this slip on its own.'),
            ]);
        }

        // An invoice freezes the exact lines it was computed from. Moving
        // paid lines onto another order would leave that invoice describing
        // food it no longer owns, and the sale would drop out of Reports.
        foreach ([$order, $mergeInto] as $candidate) {
            if ($candidate->payments()->where('status', OrderPaymentStatus::Recorded)->exists()) {
                throw ValidationException::withMessages([
                    'merge_into_order_id' => __('A slip with a recorded payment cannot be merged — void the payment first, or move the slip on its own instead.'),
                ]);
            }
        }
    }

    /**
     * Slips already open on a table that this order could be folded into.
     */
    public static function mergeCandidatesFor(Order $order, Space $target): \Illuminate\Support\Collection
    {
        return OrderAppender::findOpenOrdersForTable($target)
            ->reject(fn (Order $candidate) => $candidate->is($order) || $candidate->merged_into_order_id !== null)
            ->values();
    }
}
