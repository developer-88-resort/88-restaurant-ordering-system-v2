<?php

namespace App\Services;

use App\Enums\LineType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Space;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The trusted "build an order" core shared by staff order creation
 * (OrderController::store()) and customer self-service ordering
 * (CustomerOrderController::store()). Both callers resolve *which*
 * space/area/category/type an order belongs to differently — this class
 * only handles the part that must never differ between them: re-pricing
 * items from the live MenuItem record (never trusting a submitted price),
 * assigning the order number, and flipping the space to Occupied.
 */
class OrderCreator
{
    /**
     * @param  array<int, array<string, mixed>>  $items  Each: menu_item_id, quantity, menu_item_variant_id?, notes?
     * @param  array<string, mixed>  $orderAttributes  Everything except order_number/status/payment_status/total_amount, which this method fills in.
     */
    public static function create(array $items, array $orderAttributes, ?Space $space): Order
    {
        return DB::transaction(function () use ($items, $orderAttributes, $space) {
            $lines = collect($items)->map(function (array $line) {
                $menuItem = MenuItem::findOrFail($line['menu_item_id']);

                return self::fixedLine($menuItem, $line);
            });

            $total = '0.00';
            foreach ($lines as $line) {
                $total = bcadd($total, (string) $line['subtotal'], 2);
            }

            // A per-kilo item's own price is null by design (it's only ever
            // priced through Weigh & Order, never a flat line here) — if
            // every line somehow resolves to 0, that's not a real order.
            if (bccomp($total, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'items' => __('This order totals ₱0.00 — add at least one priced item before placing it.'),
                ]);
            }

            $order = Order::create($orderAttributes + [
                'order_number' => OrderNumberGenerator::generate(),
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'total_amount' => $total,
            ]);

            $order->items()->createMany($lines->all());

            if ($space && $space->status === SpaceStatus::Available) {
                $space->setStatusWithSharedTables(SpaceStatus::Occupied);
            }

            return $order;
        });
    }

    /**
     * Build one fixed-price line. Public because OrderAppender builds lines
     * for an EXISTING order through this same method — the rule that a
     * line's price is re-derived from the live MenuItem, never from the
     * client, has to be identical whether the line opens a new order or is
     * appended to one already on the table.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function fixedLine(MenuItem $menuItem, array $line): array
    {
        // Mirrors the guard AppendOrderItemRequest already enforces for the
        // Weigh & Order path — a per-kilo item has no flat unit price, so
        // letting it through here would silently bill it at ₱0.00.
        if ($menuItem->isPerKilo()) {
            throw ValidationException::withMessages([
                'items' => __(':item is priced per kilogram — use Weigh & Order to add it with an actual weight.', ['item' => $menuItem->name]),
            ]);
        }

        $quantity = (int) $line['quantity'];

        // Once an item has variants, its own price is meaningless —
        // resolve the chosen variant (falling back to the item's
        // default/first one if the caller somehow didn't pass one,
        // so this never silently falls through to the stale base
        // price). Freezing the variant name straight into item_name
        // means every existing display surface (receipt, kitchen
        // board, reports) needs zero changes to show it correctly.
        $variant = null;
        if ($menuItem->hasVariants()) {
            $variant = ! empty($line['menu_item_variant_id'])
                ? $menuItem->variants->firstWhere('id', (int) $line['menu_item_variant_id'])
                : null;
            $variant ??= $menuItem->variants->firstWhere('is_default', true) ?? $menuItem->variants->first();
        }

        $unitPrice = (float) ($variant->price ?? $menuItem->price);
        $itemName = $variant ? "{$menuItem->name} — {$variant->name}" : $menuItem->name;

        return [
            'menu_item_id' => $menuItem->id,
            'menu_item_variant_id' => $variant?->id,
            'item_name' => $itemName,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $unitPrice * $quantity,
            'notes' => $line['notes'] ?? null,
            'line_type' => LineType::Fixed,
        ];
    }

}
