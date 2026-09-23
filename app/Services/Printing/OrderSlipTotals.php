<?php

namespace App\Services\Printing;

use App\Models\Order;

/**
 * The money on an order slip: the subtotal of its lines, the discounts picked
 * for the slip on the Kitchen Display, and what is left to pay.
 *
 * This is deliberately NOT the receipt. The receipt's figures come from
 * InvoiceCalculator at checkout, where Senior/PWD first strips VAT; the slip
 * shows no VAT at all and takes every discount as a plain share of the price
 * (Senior 20% of ₱1,120 = ₱224). Checkout never reads these discounts, and
 * nothing here writes to the order's own totals.
 *
 * A slip discount entry (stored on orders.slip_discounts, built by
 * UpdateSlipDiscountsRequest::entries()):
 *   rule_id, name, mode (percent|fixed), value, scope (whole_bill|eligible_items),
 *   item_ids, eligible_amount, qualified_name, max_amount
 */
class OrderSlipTotals
{
    /**
     * @return array{
     *     subtotal: string,
     *     discounts: array<int, array{name: string, rate: ?string, basis: string, qualified_name: ?string, item_names: array<int, string>, amount: string}>,
     *     discount_total: string,
     *     total: string,
     * }
     */
    public static function for(Order $order, ?array $entries = null): array
    {
        $order->loadMissing('items.adjustments');

        $subtotal = self::subtotal($order);
        $discounts = [];
        $discountTotal = '0.00';

        foreach ($entries ?? (array) $order->slip_discounts as $entry) {
            $basis = self::basis($order, $entry, $subtotal);
            $amount = self::amount($entry, $basis);

            // Never more off than is still left on the slip.
            $remaining = bcsub($subtotal, $discountTotal, 2);
            if (bccomp($amount, $remaining, 2) > 0) {
                $amount = $remaining;
            }
            $discountTotal = bcadd($discountTotal, $amount, 2);

            $discounts[] = [
                'name' => (string) $entry['name'],
                'rate' => ($entry['mode'] ?? 'percent') === 'percent' ? self::trimZeros((string) $entry['value']).'%' : null,
                'basis' => $basis,
                'qualified_name' => $entry['qualified_name'] ?? null,
                'item_names' => self::eligibleItems($order, $entry)->pluck('item_name')->values()->all(),
                'amount' => $amount,
            ];
        }

        return [
            'subtotal' => $subtotal,
            'discounts' => $discounts,
            'discount_total' => $discountTotal,
            'total' => bcsub($subtotal, $discountTotal, 2),
        ];
    }

    /** Sum of every line net of cancellations — the same figure as orders.total_amount. */
    public static function subtotal(Order $order): string
    {
        $order->loadMissing('items.adjustments');

        $subtotal = '0.00';
        foreach ($order->items as $item) {
            $subtotal = bcadd($subtotal, $item->lineTotalNet(), 2);
        }

        return $subtotal;
    }

    /**
     * What a discount is taken from: the whole slip, the chosen lines (priced
     * as they stand now, so a later cancellation lowers it), or a typed amount.
     */
    public static function basis(Order $order, array $entry, string $subtotal): string
    {
        if (($entry['scope'] ?? 'whole_bill') !== 'eligible_items') {
            return $subtotal;
        }

        if (! empty($entry['item_ids'])) {
            $basis = '0.00';
            foreach (self::eligibleItems($order, $entry) as $item) {
                $basis = bcadd($basis, $item->lineTotalNet(), 2);
            }

            return $basis;
        }

        $amount = bcadd((string) ($entry['eligible_amount'] ?? '0'), '0', 2);

        return bccomp($amount, $subtotal, 2) > 0 ? $subtotal : $amount;
    }

    public static function amount(array $entry, string $basis): string
    {
        $value = bcadd((string) ($entry['value'] ?? '0'), '0', 2);

        $amount = ($entry['mode'] ?? 'percent') === 'fixed'
            ? (bccomp($value, $basis, 2) > 0 ? $basis : $value)
            : self::round2(bcdiv(bcmul($basis, $value, 6), '100', 6));

        if (! empty($entry['max_amount'])) {
            $max = bcadd((string) $entry['max_amount'], '0', 2);
            if (bccomp($amount, $max, 2) > 0) {
                $amount = $max;
            }
        }

        return $amount;
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\OrderItem> */
    protected static function eligibleItems(Order $order, array $entry)
    {
        if (($entry['scope'] ?? 'whole_bill') !== 'eligible_items' || empty($entry['item_ids'])) {
            return collect();
        }

        $ids = array_map('intval', $entry['item_ids']);

        return $order->items->filter(fn ($item) => in_array($item->id, $ids, true) && ! $item->isFullyCancelled());
    }

    /** Half-up to centavos; bcmath on its own only truncates. */
    protected static function round2(string $value): string
    {
        return bcadd(bcadd($value, '0.005', 6), '0', 2);
    }

    protected static function trimZeros(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
