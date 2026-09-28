<?php

namespace App\Services;

use App\Enums\OrderItemAdjustmentReason;
use App\Enums\OrderItemAdjustmentSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAdjustment;
use App\Models\User;
use App\Support\ManagerApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelling part or all of one order line — the one implementation behind
 * both the Kitchen Display and Order Management.
 *
 * The line itself is never deleted or edited: each cancellation is an
 * OrderItemAdjustment row (how many, why, who, who approved, when), and the
 * order total is re-derived from the lines afterwards. That is what lets
 * the slip keep showing the line struck through, and what keeps receipts
 * and reports netting the cancelled quantity out without being told to.
 */
class OrderItemCanceller
{
    /**
     * Food that is already Ready/Served, or a bill that is already paid,
     * needs a manager to take the charge off. Anything still only a ticket
     * (New / In Progress) can be cancelled by whoever is holding it.
     */
    public static function requiresApproval(Order $order): bool
    {
        return ! in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true)
            || $order->payment_status === PaymentStatus::Paid;
    }

    /**
     * @param  array<string, mixed>  $data  Validated CancelOrderItemRequest data: quantity, reason_code, notes?, inventory_restored?, manager_email?, manager_password?
     */
    public static function cancel(Order $order, OrderItem $item, array $data, User $actor, OrderItemAdjustmentSource $source): OrderItemAdjustment
    {
        // The manager's credentials are checked BEFORE the transaction: a
        // wrong PIN must leave its wrong-attempt count and audit entry
        // behind (PinAttempts), and anything written inside the transaction
        // is rolled back along with the error it throws.
        $current = Order::find($order->getKey()) ?? $order;
        $approver = self::requiresApproval($current) ? ManagerApproval::resolve($actor, $data) : null;

        [$adjustment, $order, $emptied] = DB::transaction(function () use ($order, $item, $data, $actor, $source, $approver) {
            // Locked so two tablets cancelling on the same slip at once each
            // see the other's cancellation before checking what's left.
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => __('Order :number is already cancelled.', ['number' => $order->orderNumber()]),
                ]);
            }

            // Re-checked inside the lock, not just in OrderItemPolicy: the
            // order can be settled between the page rendering its Cancel
            // button and the request landing, and the frozen invoice must
            // not end up describing lines that were taken off after it.
            if ($order->payment_status === PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => __('Order :number is already paid — void the payment first to change its items.', [
                        'number' => $order->orderNumber(),
                    ]),
                ]);
            }

            $item = OrderItem::whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $item->load('adjustments');

            $activeQuantity = $item->activeQuantity();

            if ($activeQuantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => __(':item is already fully cancelled.', ['item' => $item->item_name]),
                ]);
            }

            // A weighed line is one piece of food off the scale — it can
            // only ever be voided whole.
            $quantity = $item->isWeighed() ? $activeQuantity : (int) $data['quantity'];

            if ($quantity > $activeQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :count of this item can still be cancelled.', ['count' => $activeQuantity]),
                ]);
            }

            if (! self::requiresApproval($order)) {
                $approver = null;
            } elseif ($approver === null) {
                // The slip moved on (say, marked Ready) after this request
                // was checked: ask again, now with the approval fields.
                throw ValidationException::withMessages([
                    'manager_email' => __('Manager approval is required for this action.'),
                ]);
            }

            $adjustment =self::reverse($order, $item, $quantity, $data, $actor, $approver, $source);

            // An add-on belongs to its dish: once the dish is gone entirely,
            // "+ Extra rice" for it is gone too. A partial cancel leaves the
            // add-ons alone — which of the remaining plates they go with is
            // the kitchen's call, and each add-on line has its own buttons.
            $item->load('adjustments');
            if ($item->isFullyCancelled()) {
                $item->addOnLines()->with('adjustments')->get()
                    ->filter(fn (OrderItem $addOn) => $addOn->activeQuantity() > 0)
                    ->each(fn (OrderItem $addOn) => self::reverse($order, $addOn, $addOn->activeQuantity(), $data, $actor, $approver, $source));
            }

            $order->unsetRelation('items');
            $order->recalculateTotal();

            return [$adjustment, $order, self::cancelOrderIfEmpty($order)];
        });

        // Outside the transaction: these broadcast, and a screen reloading
        // on the event must read the committed rows, not the old ones.
        if ($emptied) {
            OrderLocationReleaser::release($order);
        }

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new OrderUpdated($order, $emptied ? 'order_cancelled' : 'item_cancelled'));

        return $adjustment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function reverse(Order $order, OrderItem $item, int $quantity, array $data, User $actor, ?User $approver, OrderItemAdjustmentSource $source): OrderItemAdjustment
    {
        $reason = OrderItemAdjustmentReason::from($data['reason_code']);
        $notes = filled($data['notes'] ?? null) ? trim($data['notes']) : null;

        // Reverse the per-unit charge, clamped so a line can never reverse
        // more than it actually charged.
        $reversed = bcmul((string) $item->unit_price, (string) $quantity, 2);
        $maxReversible = bcsub((string) $item->subtotal, $item->reversedAmount(), 2);
        if (bccomp($reversed, $maxReversible, 2) > 0) {
            $reversed = $maxReversible;
        }

        $adjustment = $order->itemAdjustments()->create([
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $item->unit_price,
            'reversed_amount' => $reversed,
            'reason_code' => $reason,
            'notes' => $notes,
            'source' => $source,
            // Served/contaminated food never restocks by default; an
            // explicit checkbox is the only way this becomes true.
            // (Recorded for the audit trail — no inventory module exists
            // yet to act on it.)
            'inventory_restored' => (bool) ($data['inventory_restored'] ?? false),
            'requested_by' => $actor->id,
            'approved_by' => $approver?->id,
        ]);

        // A weighed line's void also lands on its weighing record, so the
        // reading itself carries who voided it and why — the row is never
        // deleted, only marked.
        if ($item->isWeighed()) {
            WeighedLineRecorder::void($item, $notes ?? $reason->label(), $actor);
        }

        return $adjustment;
    }

    /**
     * Nothing left to cook or charge: the slip itself is cancelled, which is
     * what takes it off the kitchen's active columns. A Completed order is
     * final and keeps its status — only its lines are reversed.
     */
    protected static function cancelOrderIfEmpty(Order $order): bool
    {
        if ($order->status->isFinal()) {
            return false;
        }

        $order->loadMissing('items.adjustments');

        if ($order->items->isEmpty() || ! $order->items->every(fn (OrderItem $line) => $line->isFullyCancelled())) {
            return false;
        }

        $order->update(['status' => OrderStatus::Cancelled]);

        return true;
    }
}
