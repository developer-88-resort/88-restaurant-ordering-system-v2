<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\OrderInvoiceDiscount;
use App\Models\OrderItem;

/**
 * The single read-model every surface asks "what does this order actually
 * owe right now?" — customer tracking page, staff order view, receipt,
 * PDF, and reports all render from this one object so they can never
 * disagree with each other.
 *
 * Nothing here is stored on the order. Every figure is derived on read
 * from two immutable sources: the live order lines (with their
 * cancellation adjustments) and, once issued, the frozen invoice
 * snapshot. That is deliberate — a persisted `refund_due` column would
 * need re-syncing on every quantity, price, status, discount, tax and
 * payment change, which is exactly the staleness this class exists to
 * eliminate.
 *
 * Money is bcmath strings end to end, rounded to 2 decimals only where a
 * value is returned, matching InvoiceCalculator.
 */
final class OrderTotals
{
    private function __construct(
        /** Sum of every line as originally charged, before cancellations. */
        public readonly string $originalSubtotal,
        /** Total reversed by item cancellations/voids. */
        public readonly string $cancelledAmount,
        /** What is actually still being sold: original − cancelled. */
        public readonly string $activeSubtotal,
        /** Total due on the invoice as it was ISSUED (null until paid). */
        public readonly ?string $issuedTotalDue,
        /**
         * The invoice's own frozen tax/discount rules re-applied to the
         * CURRENT active subtotal. Equals issuedTotalDue when nothing has
         * been cancelled since payment. Null until paid.
         */
        public readonly ?string $correctedTotalDue,
        /** Sum of non-voided payment entries. */
        public readonly string $amountPaid,
        /** Owed back to the customer (paid − corrected), never negative. */
        public readonly string $refundDue,
        /** Still owed by the customer (corrected − paid), never negative. */
        public readonly string $balanceDue,
    ) {}

    public static function for(Order $order): self
    {
        $order->loadMissing(['items.adjustments', 'payments', 'currentInvoiceSnapshot.discounts']);

        $originalSubtotal = '0.00';
        $cancelledAmount = '0.00';

        foreach ($order->items as $item) {
            $originalSubtotal = bcadd($originalSubtotal, self::lineGross($item), 2);
            $cancelledAmount = bcadd($cancelledAmount, $item->reversedAmount(), 2);
        }

        // Per-line clamping (a line can never reverse more than it charged)
        // already happens in OrderItem::lineTotalNet(); mirror that here so
        // the two can't drift apart.
        $activeSubtotal = '0.00';
        foreach ($order->items as $item) {
            $activeSubtotal = bcadd($activeSubtotal, $item->lineTotalNet(), 2);
        }

        $amountPaid = '0.00';
        foreach ($order->payments as $payment) {
            if ($payment->status === OrderPaymentStatus::Recorded) {
                $amountPaid = bcadd($amountPaid, (string) $payment->amount, 2);
            }
        }

        $snapshot = $order->currentInvoiceSnapshot;
        $issuedTotalDue = $snapshot ? (string) $snapshot->total_amount_due : null;
        $correctedTotalDue = $snapshot ? self::recomputeAgainst($order, $activeSubtotal) : null;

        $refundDue = '0.00';
        $balanceDue = '0.00';

        if ($correctedTotalDue !== null) {
            $delta = bcsub($amountPaid, $correctedTotalDue, 2);
            if (bccomp($delta, '0.00', 2) > 0) {
                $refundDue = $delta;
            } elseif (bccomp($delta, '0.00', 2) < 0) {
                $balanceDue = bcsub('0.00', $delta, 2);
            }
        }

        return new self(
            originalSubtotal: $originalSubtotal,
            cancelledAmount: $cancelledAmount,
            activeSubtotal: $activeSubtotal,
            issuedTotalDue: $issuedTotalDue,
            correctedTotalDue: $correctedTotalDue,
            amountPaid: $amountPaid,
            refundDue: $refundDue,
            balanceDue: $balanceDue,
        );
    }

    /**
     * True when an item was cancelled after the invoice was issued, so the
     * customer paid more than the order is now worth.
     */
    public function hasRefundDue(): bool
    {
        return bccomp($this->refundDue, '0.00', 2) > 0;
    }

    public function hasCancellations(): bool
    {
        return bccomp($this->cancelledAmount, '0.00', 2) > 0;
    }

    /**
     * The number to show as "the total" wherever only one figure fits:
     * the corrected amount due once an invoice exists, otherwise the live
     * active subtotal.
     */
    public function payableTotal(): string
    {
        return $this->correctedTotalDue ?? $this->activeSubtotal;
    }

    /**
     * What this line originally charged, before any cancellation reversal.
     */
    private static function lineGross(OrderItem $item): string
    {
        return (string) $item->subtotal;
    }

    /**
     * Re-run the invoice's OWN frozen tax rules and discount lines against
     * a different subtotal. Used to answer "what would this invoice have
     * totalled if the cancelled items had never been on it?" without ever
     * touching the issued invoice itself.
     */
    private static function recomputeAgainst(Order $order, string $subtotal): string
    {
        $snapshot = $order->currentInvoiceSnapshot;

        $lines = $snapshot->discounts->map(fn (OrderInvoiceDiscount $discount) => [
            // InvoiceCalculator branches on these as plain strings, and the
            // model casts them to enums — unwrap or every line silently
            // fails to match and no discount gets re-applied.
            'calculation_mode' => self::scalar($discount->calculation_mode),
            'value' => $discount->entered_value,
            'statutory_type' => self::scalar($discount->statutory_type),
            // An eligible base can never exceed what is still being sold —
            // otherwise a discount scoped to items that were cancelled
            // would keep discounting money no longer being charged.
            'eligible_amount' => self::clamp((string) $discount->eligible_amount, $subtotal),
        ])->all();

        $breakdown = InvoiceCalculator::computeWithDiscountLines([
            'gross_sales' => $subtotal,
            // The tax rules frozen ON THE INVOICE, not today's Settings —
            // a later VAT-rate change must not move an issued invoice.
            'tax_registration_type' => $snapshot->tax_registration_type,
            'tax_rate' => (string) $snapshot->tax_rate,
            'prices_include_vat' => (bool) $snapshot->prices_include_vat,
            'discount_lines' => $lines,
            'service_charge_enabled' => (bool) $snapshot->service_charge_enabled,
            'service_charge_percent' => $snapshot->service_charge_percent,
            'service_charge_taxable' => (bool) $snapshot->service_charge_taxable,
        ]);

        return $breakdown['total_amount_due'];
    }

    private static function clamp(string $value, string $max): string
    {
        return bccomp($value, $max, 2) > 0 ? $max : $value;
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
