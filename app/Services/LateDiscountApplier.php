<?php

namespace App\Services;

use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A discount that was forgotten at checkout, put on a bill that is already
 * paid — e.g. the night's hand tally came out lower than the system because
 * one order's Senior discount was never entered.
 *
 * Void Payment + checking out again gets the same numbers, but it files the
 * corrected payment under today, so the night it happened still doesn't
 * match. This does it in one step and keeps the sale where it was:
 *
 *   - the current invoice and its payments are voided (kept on record),
 *   - a new invoice is issued with the full discount set the cashier chose
 *     (the bill's existing discounts come pre-ticked on the form),
 *   - the same payments are re-recorded, dated when they were first taken,
 *     so the Reports for that day show the discounted amount.
 *
 * The discount comes off the cash first: non-cash entries (card, GCash, room
 * charge...) keep their amounts wherever the new total allows and cash takes
 * the difference, since cash is what a cashier can hand back.
 *
 * Changing a settled bill needs a manager, the same as voiding a payment
 * entry: staff enter a manager's credentials, admins approve their own.
 */
class LateDiscountApplier
{
    /** Its own name, so the Audit Logs page can filter to it. */
    public const AUDIT_EVENT = 'discount_added_after_payment';

    /**
     * @param  array<string, mixed>  $data  Validated ApplyLateDiscountRequest data
     */
    public static function apply(Order $order, array $data, User $actingUser): OrderInvoiceSnapshot
    {
        CheckoutDiscountResolver::resolveApprover(
            $actingUser,
            $data['manager_email'] ?? null,
            $data['manager_password'] ?? null,
        );

        return DB::transaction(function () use ($order, $data, $actingUser) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'discounts' => __('Order :number is cancelled.', ['number' => $order->orderNumber()]),
                ]);
            }

            $snapshot = $order->currentInvoiceSnapshot;

            if ($order->payment_status !== PaymentStatus::Paid || ! $snapshot) {
                throw ValidationException::withMessages([
                    'discounts' => __('Only a paid order can have a discount added afterwards.'),
                ]);
            }

            $payments = $order->payments()
                ->where('order_invoice_snapshot_id', $snapshot->id)
                ->where('status', OrderPaymentStatus::Recorded)
                ->get();

            // When the money was first taken: the corrected bill stays there.
            $settledAt = $payments->min('received_at') ?? $order->paid_at ?? $snapshot->computed_at;

            $entries = $payments->isNotEmpty()
                ? self::entriesFromPayments($payments)
                : self::entriesFromOrder($order, $snapshot);

            $replacedNumber = $snapshot->invoice_number;
            $note = trim((string) ($data['note'] ?? ''));
            $voidReason = __('Replaced: discount added after payment').($note !== '' ? ' — '.$note : '');

            $snapshot->update([
                'status' => InvoiceSnapshotStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $actingUser->id,
            ]);

            $payments->each(fn (OrderPayment $payment) => $payment->update([
                'status' => OrderPaymentStatus::Voided,
                'voided_by' => $actingUser->id,
                'voided_at' => now(),
                'void_reason' => $voidReason,
            ]));

            $newSnapshot = PaymentFinalizer::finalize($order, [
                'discounts' => array_values($data['discounts']),
                'payments' => $entries,
                'manager_email' => $data['manager_email'] ?? null,
                'manager_password' => $data['manager_password'] ?? null,
                'buyer_name' => $snapshot->buyer_name,
                'buyer_tin' => $snapshot->buyer_tin,
                'buyer_address' => $snapshot->buyer_address,
            ], $actingUser, $settledAt);

            if (bccomp((string) $newSnapshot->total_amount_due, (string) $snapshot->total_amount_due, 2) > 0) {
                throw ValidationException::withMessages([
                    'discounts' => __('This would make the bill higher than what was paid (₱:paid). Only add discounts here.', [
                        'paid' => number_format((float) $snapshot->total_amount_due, 2),
                    ]),
                ]);
            }

            activity()
                ->performedOn($order)
                ->causedBy($actingUser)
                ->event(self::AUDIT_EVENT)
                ->withProperties([
                    'replaced_invoice' => $replacedNumber,
                    'new_invoice' => $newSnapshot->invoice_number,
                    'old_total' => (string) $snapshot->total_amount_due,
                    'new_total' => (string) $newSnapshot->total_amount_due,
                    'note' => $note !== '' ? $note : null,
                ])
                ->log(__('Discount added after payment on order :number (₱:old → ₱:new).', [
                    'number' => $order->orderNumber(),
                    'old' => number_format((float) $snapshot->total_amount_due, 2),
                    'new' => number_format((float) $newSnapshot->total_amount_due, 2),
                ]));

            return $newSnapshot;
        });
    }

    /**
     * The payments as they were recorded, non-cash first so cash is the
     * one PaymentFinalizer trims when the new total is lower.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function entriesFromPayments($payments): array
    {
        return $payments
            ->sortBy(fn (OrderPayment $payment) => $payment->payment_method === PaymentMethod::Cash ? 1 : 0)
            ->map(fn (OrderPayment $payment) => [
                'method' => $payment->payment_method->value,
                'settled_via' => $payment->settled_via?->value,
                'charged_to' => $payment->charged_to,
                // Taken as it was recorded, even from before a field was required.
                'carried_over' => true,
                'amount' => (string) $payment->amount,
                'tendered_amount' => $payment->tendered_amount !== null ? (string) $payment->tendered_amount : null,
                'card_brand' => $payment->card_brand,
                'card_last_four' => $payment->card_last_four,
                'terminal_reference' => $payment->terminal_reference,
                'approval_code' => $payment->approval_code,
                'terminal_id' => $payment->terminal_id,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * A bill settled through the older single-payment checkout has no
     * payment rows, only the method and amounts on the order itself.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function entriesFromOrder(Order $order, OrderInvoiceSnapshot $snapshot): array
    {
        $method = $order->payment_method ?? PaymentMethod::Cash;

        return [[
            'method' => $method->value,
            'amount' => (string) $snapshot->total_amount_due,
            'tendered_amount' => $method === PaymentMethod::Cash && $order->amount_received !== null ? (string) $order->amount_received : null,
            'terminal_reference' => $method === PaymentMethod::Card ? $order->payment_reference : null,
            'reference' => $method !== PaymentMethod::Card ? $order->payment_reference : null,
        ]];
    }
}
