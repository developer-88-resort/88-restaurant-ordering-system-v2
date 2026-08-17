<?php

namespace App\Services;

use App\Enums\LineType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Models\AppendIdempotencyKey;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Space;
use App\Models\SpaceSession;
use App\Models\User;
use App\Support\WeighAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adding lines to an order that already exists — and, via {@see resolveOrder},
 * the one place every channel decides WHICH order a line belongs to.
 *
 * This is what makes "one receipt per visit" possible: a party that scans
 * the table QR, has fish weighed at the counter mid-meal, and later has a
 * quotation converted for them — must end up on ONE bill, not three. Weigh-
 * and-Order, Quotation conversion, and QR self-ordering all call
 * {@see resolveOrder} to find (or start) that one order, then
 * {@see appendBatch}/{@see append} to add their lines to it. No other code
 * in the app creates an `Order` row for an occupied table.
 *
 * Pricing is never taken from the client: a fixed line is re-priced from
 * the live MenuItem through OrderCreator's builder, so it is constructed
 * identically whether it opened the order or was appended to it (Quotation
 * conversion is the one deliberate exception — its prices are frozen at
 * quote time, so it builds its own line attributes and passes them straight
 * through). A weighed line goes to {@see WeighedLineRecorder}, which is the
 * only place allowed to turn a scale reading into money.
 */
class OrderAppender
{
    /**
     * The one order a table session is currently accumulating onto, if any.
     * "Open" means still billable: not cancelled, not completed, not paid.
     * Oldest first, so a session that somehow has two open orders keeps
     * feeding the original bill instead of starting a fresh one.
     */
    public static function findOpenOrderForSession(SpaceSession $session): ?Order
    {
        $space = $session->space;

        if (! $space) {
            return self::openOrders()->where('space_session_id', $session->id)->first();
        }

        return self::findOpenOrdersForTable($space)->first();
    }

    /**
     * EVERY still-billable order attached to a table — not just the ones a
     * QR session opened.
     *
     * A table can end up with a staff-created walk-in bill and a QR guest
     * bill at the same time, and the old session-scoped lookup simply could
     * not see the first of those. The counter would then weigh a fish, find
     * "no open order", and start a second bill for a party that already had
     * one. Returning all of them and letting the caller choose is what
     * makes the weigh station's "which bill?" step honest.
     *
     * @return Collection<int, Order>
     */
    public static function findOpenOrdersForTable(Space $space): Collection
    {
        return self::openOrders()
            ->where(function ($query) use ($space) {
                $query->where('space_id', $space->id)
                    ->orWhereIn('space_session_id', $space->sessions()->select('id'));
            })
            ->with(['items', 'spaceSession'])
            ->get();
    }

    /**
     * The oldest still-billable order on this table, if any — the one a
     * new arrival (a converted quotation, say) should join rather than
     * starting a second bill next to it. Null when the table has no open
     * order yet.
     */
    public static function findOpenOrderForSpace(Space $space): ?Order
    {
        return self::findOpenOrdersForTable($space)->first();
    }

    /**
     * Still-billable: not cancelled, not completed, not paid, and not a
     * future-dated advance reservation. Oldest first, so the original bill
     * keeps accumulating.
     *
     * The reservation exclusion matters as much as the others: a converted
     * quotation scheduled for tomorrow sits in the database as a normal
     * Pending/Unpaid order well before its time. Without this, a walk-in
     * party seated at that same table TODAY would have their order silently
     * merged onto tomorrow's reservation bill the moment staff weighed
     * something or converted an unrelated quotation for them.
     */
    protected static function openOrders(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereDoesntHave('sourceQuotation', fn ($query) => $query->where('scheduled_for', '>', now()))
            ->oldest('id');
    }

    /**
     * The ONE order this checkout should land on right now: whatever's
     * already open for this space (see openOrders() for exactly what
     * counts), or a freshly created one if nothing qualifies.
     *
     * $forceNew skips the lookup entirely — used by Quotation conversion
     * for a future-dated reservation, which must always get its own
     * standalone order rather than merging into whatever the table happens
     * to have open today (see the class doc on openOrders()'s reservation
     * guard for why that matters).
     *
     * @param  array<string, mixed>  $baseAttributes  Order columns for the create case (area_id, space_category_id, created_by, customer_name, notes, etc.) — order_number/status/payment_status/total_amount are filled in here.
     */
    public static function resolveOrder(Space $space, array $baseAttributes, ?SpaceSession $session = null, bool $forceNew = false): Order
    {
        return DB::transaction(function () use ($space, $baseAttributes, $session, $forceNew) {
            // Lock the stable parent (the session, or the space itself when
            // there's no session yet) so two concurrent "first submission"
            // requests for the same table can't both find nothing open and
            // each create their own order.
            if ($session) {
                SpaceSession::whereKey($session->id)->lockForUpdate()->first();
            } else {
                Space::whereKey($space->id)->lockForUpdate()->first();
            }

            if (! $forceNew && $existing = self::findOpenOrderForSpace($space)) {
                return Order::whereKey($existing->id)->lockForUpdate()->firstOrFail();
            }

            $order = Order::create($baseAttributes + [
                'order_number' => OrderNumberGenerator::generate(),
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'total_amount' => '0.00',
            ]);

            if ($space->status === SpaceStatus::Available) {
                $space->setStatusWithSharedTables(SpaceStatus::Occupied);
            }

            return $order;
        });
    }

    /**
     * Append a whole round of already-priced line arrays (each shaped like
     * an OrderItem::create() payload) to an order, all stamped with the
     * same new batch number — one QR cart submission, one quotation's
     * items, one staff "New Order" cart, all land as one round each.
     *
     * Pricing is the CALLER's job: this never re-derives a price. QR and
     * staff New Order run each line through OrderCreator::fixedLine() first
     * to re-price from the live MenuItem; Quotation conversion passes its
     * already-frozen quoted attributes straight through.
     *
     * @param  array<int, array<string, mixed>>  $lineAttributes
     * @param  string|null  $idempotencyKeyPrefix  A key unique to this submission — each line is checked/remembered under "{prefix}:{index}" via AppendIdempotencyKey, so a retried whole-cart submission can't double-insert.
     * @return Collection<int, OrderItem>
     */
    public static function appendBatch(Order $order, array $lineAttributes, ?User $actingUser = null, ?string $idempotencyKeyPrefix = null): Collection
    {
        return DB::transaction(function () use ($order, $lineAttributes, $actingUser, $idempotencyKeyPrefix) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            self::assertAppendable($locked);

            $batchNumber = null;
            $items = collect();

            foreach ($lineAttributes as $i => $attributes) {
                if ($idempotencyKeyPrefix && $replayed = AppendIdempotencyKey::resolve($idempotencyKeyPrefix, $i, $locked)) {
                    $items->push($replayed);
                    $batchNumber ??= $replayed->batch_number;

                    continue;
                }

                $batchNumber ??= ((int) $locked->items()->max('batch_number')) + 1;

                $item = $locked->items()->create($attributes + ['batch_number' => $batchNumber]);
                WeighAudit::lineAppended($item, $locked, $actingUser);

                if ($idempotencyKeyPrefix) {
                    AppendIdempotencyKey::remember($idempotencyKeyPrefix, $i, $locked, $item);
                }

                $items->push($item);
            }

            $locked->recalculateTotal();

            if (in_array($locked->status, [OrderStatus::Ready, OrderStatus::Served], true)) {
                $locked->update(['status' => OrderStatus::Preparing]);
            }

            $order->refresh();

            return $items;
        });
    }

    /**
     * Append one line to an existing order and recompute its total.
     *
     * The order row is locked for the whole transaction so two staff
     * appending at the same moment can't both read the same pre-append
     * total and write back a figure that loses one of the two lines.
     *
     * @param  array<string, mixed>  $line
     */
    public static function append(Order $order, array $line, ?User $actingUser = null, ?string $idempotencyKey = null): OrderItem
    {
        return DB::transaction(function () use ($order, $line, $actingUser, $idempotencyKey) {
            // Re-read under a write lock: the guards below must run against
            // the order's committed state, not a copy that may have been
            // paid or cancelled since it was loaded for this request. The
            // lock is also what makes the idempotency check below safe — a
            // double-tap that arrives twice in the same millisecond has to
            // queue here rather than both find "no key yet".
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Already served this exact request. Return the line it made
            // instead of billing the customer for a second fish. A single
            // append() call has no batch position of its own, so it's
            // always line 0 of its own one-line "batch".
            if ($replayed = AppendIdempotencyKey::resolve($idempotencyKey, 0, $locked)) {
                return $replayed;
            }

            self::assertAppendable($locked);

            $menuItem = MenuItem::findOrFail($line['menu_item_id']);

            // Every line carries which round it arrived in, whichever path
            // creates it — the kitchen board and receipt group by this.
            $line['batch_number'] = ((int) $locked->items()->max('batch_number')) + 1;

            $item = ($line['line_type'] ?? LineType::Fixed->value) === LineType::Weighed->value
                ? WeighedLineRecorder::record($locked, $menuItem, $line, $actingUser)
                : self::appendFixedLine($locked, $menuItem, $line, $actingUser);

            $locked->recalculateTotal();

            // A line added after the kitchen already finished the earlier
            // ones has to reopen the ticket, otherwise the new dish is on
            // the bill but never reaches the kitchen board.
            if (in_array($locked->status, [OrderStatus::Ready, OrderStatus::Served], true)) {
                $locked->update(['status' => OrderStatus::Preparing]);
            }

            AppendIdempotencyKey::remember($idempotencyKey, 0, $locked, $item);

            // Keep the caller's instance in step with the row we just
            // changed under the lock, so it reads the new total.
            $order->refresh();

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected static function appendFixedLine(Order $order, MenuItem $menuItem, array $line, ?User $actingUser): OrderItem
    {
        $attributes = OrderCreator::fixedLine($menuItem, $line);
        $attributes['batch_number'] = $line['batch_number'] ?? null;

        if (! empty($line['ordered_by_guest_id'])) {
            $attributes['ordered_by_guest_id'] = $line['ordered_by_guest_id'];
        }

        $item = $order->items()->create($attributes);

        // Its own audit event: a line that appeared on a bill after the
        // order was placed must be findable as that, not buried under a
        // generic "Order Updated".
        WeighAudit::lineAppended($item, $order, $actingUser);

        return $item;
    }

    /**
     * Why this order can't take another line, or null when it can.
     */
    public static function appendBlockedReason(Order $order): ?string
    {
        if ($order->status === OrderStatus::Cancelled) {
            return __('Order :number is cancelled — reopen or create a new order to add items.', [
                'number' => $order->orderNumber(),
            ]);
        }

        if ($order->status === OrderStatus::Completed) {
            return __('Order :number is already completed — start a new order for additional items.', [
                'number' => $order->orderNumber(),
            ]);
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return __('Order :number is already paid. Void the payment first, or start a new order.', [
                'number' => $order->orderNumber(),
            ]);
        }

        $session = $order->spaceSession;

        if ($session && ! $session->isActive()) {
            return __('The dining session for this table has been closed. Start a new session to order again.');
        }

        // An issued invoice is a BIR document: its lines and its total are
        // fixed the moment it is generated. Appending to the order behind
        // one would make the paper and the database disagree.
        if ($order->current_invoice_snapshot_id) {
            return __('Order :number already has an issued invoice. Void it first, or start a new order.', [
                'number' => $order->orderNumber(),
            ]);
        }

        return null;
    }

    public static function canAppend(Order $order): bool
    {
        return self::appendBlockedReason($order) === null;
    }

    public static function assertAppendable(Order $order): void
    {
        if ($reason = self::appendBlockedReason($order)) {
            throw ValidationException::withMessages(['order' => $reason]);
        }
    }

}
