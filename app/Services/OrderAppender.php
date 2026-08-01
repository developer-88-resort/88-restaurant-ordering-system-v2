<?php

namespace App\Services;

use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Models\CookingStyle;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SpaceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adding lines to an order that already exists.
 *
 * This is what makes "one receipt per table" possible: a party that orders
 * three times over an evening — and, later, has fish weighed at the counter
 * mid-meal — must end up on ONE bill, not three. Everything here therefore
 * works against the table's single open order rather than creating a new
 * one per request.
 *
 * Pricing is never taken from the client: fixed lines are re-priced from
 * the live MenuItem and weighed lines from WeighedLinePricer, both through
 * OrderCreator's line builders so a line is constructed identically whether
 * it opened the order or was appended to it.
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
        return $session->orders()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->oldest('id')
            ->first();
    }

    /**
     * The session's open order, creating an empty one if the table has none
     * yet. Used by the weigh station, where staff shouldn't have to know or
     * care whether this party has ordered anything before.
     *
     * @param  array<string, mixed>  $attributes  Extra order columns for the create case.
     */
    public static function findOrStartOrderForSession(SpaceSession $session, array $attributes = []): Order
    {
        return DB::transaction(function () use ($session, $attributes) {
            if ($existing = self::findOpenOrderForSession($session)) {
                return $existing;
            }

            $space = $session->space;

            $order = Order::create($attributes + [
                'order_type' => OrderType::DineIn,
                'area_id' => $space?->area_id,
                'space_category_id' => $space?->category_id ?? $session->category_id,
                'space_id' => $space?->id,
                'space_session_id' => $session->id,
                'batch_number' => $session->nextBatchNumber(),
                'order_number' => OrderNumberGenerator::generate(),
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'total_amount' => '0.00',
            ]);

            if ($space && $space->status === SpaceStatus::Available) {
                $space->setStatusWithSharedTables(SpaceStatus::Occupied);
            }

            return $order;
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
    public static function append(Order $order, array $line, ?User $actingUser = null): OrderItem
    {
        return DB::transaction(function () use ($order, $line, $actingUser) {
            // Re-read under a write lock: the guards below must run against
            // the order's committed state, not a copy that may have been
            // paid or cancelled since it was loaded for this request.
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            self::assertAppendable($locked);

            $menuItem = MenuItem::findOrFail($line['menu_item_id']);
            $isWeighed = ($line['line_type'] ?? LineType::Fixed->value) === LineType::Weighed->value;

            $attributes = $isWeighed
                ? OrderCreator::weighedLine($menuItem, $line, self::resolveCookingStyle($menuItem, $line))
                : OrderCreator::fixedLine($menuItem, $line);

            if ($isWeighed) {
                $attributes['weighed_by_user_id'] = $actingUser?->id;
                $attributes['weighed_at'] = now();
                $attributes['confirmation_status'] = $line['confirmation_status']
                    ?? OrderItemConfirmationStatus::Confirmed->value;

                // A rate that departs from the day's market price carries
                // its reason and its owner onto the line itself.
                if (! empty($line['price_override_reason'])) {
                    $attributes['price_override_reason'] = $line['price_override_reason'];
                    $attributes['price_overridden_by_user_id'] = $actingUser?->id;
                }
            }

            if (! empty($line['ordered_by_guest_id'])) {
                $attributes['ordered_by_guest_id'] = $line['ordered_by_guest_id'];
            }

            $item = $locked->items()->create($attributes);

            $locked->recalculateTotal();

            // A line added after the kitchen already finished the earlier
            // ones has to reopen the ticket, otherwise the new dish is on
            // the bill but never reaches the kitchen board.
            if (in_array($locked->status, [OrderStatus::Ready, OrderStatus::Served], true)) {
                $locked->update(['status' => OrderStatus::Preparing]);
            }

            // Keep the caller's instance in step with the row we just
            // changed under the lock, so it reads the new total.
            $order->refresh();

            return $item;
        });
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

    /**
     * A cooking style must be one the item actually offers — otherwise a
     * client could attach a surcharged style the kitchen never agreed to
     * cook for that dish.
     *
     * @param  array<string, mixed>  $line
     */
    protected static function resolveCookingStyle(MenuItem $menuItem, array $line): ?CookingStyle
    {
        if (empty($line['cooking_style_id'])) {
            return null;
        }

        $style = $menuItem->cookingStyles()->find($line['cooking_style_id']);

        if (! $style) {
            throw ValidationException::withMessages([
                'cooking_style_id' => __('That cooking style is not offered for :item.', ['item' => $menuItem->name]),
            ]);
        }

        return $style;
    }
}
