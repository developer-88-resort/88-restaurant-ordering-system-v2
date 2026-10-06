<?php

namespace App\Services;

use App\Enums\MassageOrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Models\MassageOrder;
use App\Models\MassagePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes payment for a massage order with the restaurant checkout's own
 * rules (PaymentFinalizer::validatePayments): split payments, cash change,
 * card type + Reference No. + Approval Code, a Room Charge's room and the
 * mode it's paid through. No receipt is issued — Massage doesn't print one.
 */
class MassageCheckout
{
    /**
     * @param  array<int, array<string, mixed>>  $payments
     */
    public static function pay(MassageOrder $order, array $payments, User $actingUser): void
    {
        DB::transaction(function () use ($order, $payments, $actingUser) {
            $order = MassageOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isOpen()) {
                throw ValidationException::withMessages([
                    'payments' => __('Order :number is already :status.', ['number' => $order->order_number, 'status' => $order->status->label()]),
                ]);
            }

            $entries = PaymentFinalizer::validatePayments(null, $payments, bcadd((string) $order->total_amount, '0', 2));

            foreach ($entries as $entry) {
                MassagePayment::create([
                    'massage_order_id' => $order->id,
                    'payment_method' => $entry['method'],
                    'settled_via' => $entry['settled_via'] ?? null,
                    'charged_to' => $entry['charged_to'] ?? null,
                    'status' => OrderPaymentStatus::Recorded,
                    'amount' => $entry['amount'],
                    'tendered_amount' => $entry['tendered_amount'] ?? null,
                    'change_amount' => $entry['change_amount'] ?? null,
                    'card_brand' => $entry['card_brand'] ?? null,
                    'reference' => $entry['reference'] ?? null,
                    'approval_code' => $entry['approval_code'] ?? null,
                    'notes' => $entry['notes'] ?? null,
                    'received_by' => $actingUser->id,
                    'received_at' => now(),
                ]);
            }

            $order->update(['status' => MassageOrderStatus::Paid, 'paid_at' => now()]);
        });
    }

    /**
     * A payment keyed wrong: its entries are kept but voided (they drop out
     * of Reports) and the order is open again to be paid properly.
     */
    public static function voidPayment(MassageOrder $order, User $actingUser): void
    {
        DB::transaction(function () use ($order, $actingUser) {
            $order->recordedPayments()->update([
                'status' => OrderPaymentStatus::Voided,
                'voided_by' => $actingUser->id,
                'voided_at' => now(),
            ]);

            $order->update(['status' => MassageOrderStatus::Pending, 'paid_at' => null]);
        });
    }
}
