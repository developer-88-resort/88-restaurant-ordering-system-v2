<?php

namespace App\Services\Payments;

use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\DashboardStatsChanged;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\User;
use App\Services\PaymentFinalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pays a bill through Maya Checkout instead of at the counter.
 *
 * start(): prices the bill exactly as checkout would (PaymentFinalizer::quote),
 * records the attempt, and asks Maya for a hosted checkout page for that
 * amount. Nothing about the bill changes yet.
 *
 * sync(): re-reads the payment's status from Maya and, once Maya says it was
 * paid, finalizes the bill with the discounts the cashier picked and a single
 * Maya payment entry. Safe to call any number of times, from the guest's
 * return, a webhook or a "check status" tap — the attempt row is locked and a
 * bill is only ever finalized once.
 */
class MayaCheckoutService
{
    public function __construct(protected MayaCheckoutClient $client) {}

    /**
     * @param  array<string, mixed>  $data  Validated StartMayaCheckoutRequest data
     */
    public function start(Order $order, array $data, User $actingUser): OnlinePayment
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['payments' => __('Order :number is cancelled and cannot be paid.', ['number' => $order->orderNumber()])]);
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            throw ValidationException::withMessages(['payments' => __('Order :number is already paid.', ['number' => $order->orderNumber()])]);
        }

        if ($order->onlinePayments()->where('status', OnlinePaymentStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['payments' => __('A Maya checkout is already waiting for payment on this order. Check its status or cancel it first.')]);
        }

        $quote = PaymentFinalizer::quote($order, $data, $actingUser);

        if (bccomp($quote['total_due'], '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['payments' => __('Nothing is due on this bill, so there is nothing to pay online.')]);
        }

        $onlinePayment = OnlinePayment::create([
            'order_id' => $order->id,
            'provider' => 'maya',
            'request_reference_number' => (string) Str::uuid(),
            'amount' => $quote['total_due'],
            'status' => OnlinePaymentStatus::Pending,
            // What finalizing needs later — never the manager's password;
            // an approval was checked just now and is kept as approved_by.
            'checkout_data' => [
                'discounts' => array_values($data['discounts'] ?? []),
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_tin' => $data['buyer_tin'] ?? null,
                'buyer_address' => $data['buyer_address'] ?? null,
            ],
            'approved_by' => $quote['approved_by'],
            'initiated_by' => $actingUser->id,
        ]);

        try {
            $checkout = $this->client->createCheckout($this->checkoutPayload($order, $onlinePayment));
        } catch (RuntimeException $e) {
            Log::warning('Maya checkout creation failed', ['online_payment_id' => $onlinePayment->id, 'error' => $e->getMessage()]);
            $onlinePayment->update(['status' => OnlinePaymentStatus::Failed, 'error_message' => $e->getMessage()]);

            throw ValidationException::withMessages(['payments' => __('Maya could not start the checkout. Try again, or take the payment another way.')]);
        }

        $onlinePayment->update([
            'checkout_id' => $checkout['checkoutId'],
            'redirect_url' => $checkout['redirectUrl'],
        ]);

        return $onlinePayment;
    }

    /**
     * Bring the attempt up to date with Maya, finalizing the bill once paid.
     */
    public function sync(OnlinePayment $onlinePayment): OnlinePayment
    {
        if (in_array($onlinePayment->status, [OnlinePaymentStatus::Paid, OnlinePaymentStatus::NeedsReview], true)
            || ! $onlinePayment->checkout_id) {
            return $onlinePayment;
        }

        // Read Maya before taking the lock — a slow API call must never hold
        // the row (or the order) locked.
        $providerStatus = $this->client->paymentStatus($onlinePayment->checkout_id);
        $mapped = OnlinePaymentStatus::fromMaya($providerStatus);

        $finalized = DB::transaction(function () use ($onlinePayment, $providerStatus, $mapped) {
            $locked = OnlinePayment::whereKey($onlinePayment->id)->lockForUpdate()->first();

            if (in_array($locked->status, [OnlinePaymentStatus::Paid, OnlinePaymentStatus::NeedsReview], true)) {
                return false;
            }

            if ($mapped !== OnlinePaymentStatus::Paid) {
                // A checkout cancelled on our side stays cancelled while Maya
                // still calls it pending — it only changes if money arrives.
                $status = $locked->status === OnlinePaymentStatus::Cancelled && $mapped === OnlinePaymentStatus::Pending
                    ? OnlinePaymentStatus::Cancelled
                    : $mapped;
                $locked->update(['status' => $status, 'provider_status' => $providerStatus]);

                return false;
            }

            return $this->finalize($locked, $providerStatus);
        });

        if ($finalized) {
            broadcast(new CustomerOrderStatusUpdated($onlinePayment->order));
            broadcast(new DashboardStatsChanged());
        }

        return $onlinePayment->refresh();
    }

    /**
     * Stop waiting on a checkout. Re-checks Maya first: one that turns out to
     * be paid is finalized instead of cancelled.
     */
    public function cancel(OnlinePayment $onlinePayment): OnlinePayment
    {
        $onlinePayment = $this->sync($onlinePayment);

        if ($onlinePayment->status === OnlinePaymentStatus::Pending) {
            $onlinePayment->update(['status' => OnlinePaymentStatus::Cancelled]);
        }

        return $onlinePayment;
    }

    /**
     * Maya says the money is in. Finalize the bill for exactly what was
     * charged — or, if the bill moved since checkout started (lines added or
     * cancelled, or it was settled another way), keep the money on record
     * and flag it for a person instead of guessing.
     */
    protected function finalize(OnlinePayment $onlinePayment, string $providerStatus): bool
    {
        $order = Order::whereKey($onlinePayment->order_id)->lockForUpdate()->first();
        $initiator = User::findOrFail($onlinePayment->initiated_by);
        $approver = $onlinePayment->approved_by ? User::find($onlinePayment->approved_by) : null;
        $data = $onlinePayment->checkout_data;

        $review = function (string $reason) use ($onlinePayment, $providerStatus) {
            Log::warning('Maya payment received but needs review', ['online_payment_id' => $onlinePayment->id, 'reason' => $reason]);
            $onlinePayment->update([
                'status' => OnlinePaymentStatus::NeedsReview,
                'provider_status' => $providerStatus,
                'paid_at' => now(),
                'error_message' => $reason,
            ]);

            return false;
        };

        if ($order->payment_status === PaymentStatus::Paid) {
            return $review(__('The bill was already settled another way when this Maya payment came in.'));
        }

        try {
            $quote = PaymentFinalizer::quote($order, $data, $initiator, $approver);
        } catch (ValidationException $e) {
            return $review(collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }

        if (bccomp($quote['total_due'], (string) $onlinePayment->amount, 2) !== 0) {
            return $review(__('The bill changed after the Maya checkout started: ₱:paid was paid online, the bill is now ₱:due.', [
                'paid' => number_format((float) $onlinePayment->amount, 2),
                'due' => number_format((float) $quote['total_due'], 2),
            ]));
        }

        PaymentFinalizer::finalize($order, $data + [
            'payments' => [[
                'method' => PaymentMethod::Maya->value,
                'amount' => (string) $onlinePayment->amount,
                'reference' => $onlinePayment->checkout_id,
                'notes' => __('Maya Checkout (online)'),
            ]],
        ], $initiator, preApprovedBy: $approver);

        $onlinePayment->update([
            'status' => OnlinePaymentStatus::Paid,
            'provider_status' => $providerStatus,
            'paid_at' => now(),
            'error_message' => null,
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkoutPayload(Order $order, OnlinePayment $onlinePayment): array
    {
        $amount = (float) $onlinePayment->amount;
        $back = fn (string $result) => route('online-payments.maya.return', ['onlinePayment' => $onlinePayment, 'result' => $result]);

        return [
            'totalAmount' => ['value' => $amount, 'currency' => 'PHP'],
            'requestReferenceNumber' => $onlinePayment->request_reference_number,
            // One line for the whole bill: discounts, VAT and service charge
            // are already in the amount, and Maya only needs it to show the
            // guest what they're paying for.
            'items' => [[
                'name' => __('Order :number', ['number' => $order->orderNumber()]),
                'quantity' => 1,
                'code' => (string) $order->id,
                'amount' => ['value' => $amount],
                'totalAmount' => ['value' => $amount],
            ]],
            'redirectUrl' => [
                'success' => $back('success'),
                'failure' => $back('failure'),
                'cancel' => $back('cancel'),
            ],
        ];
    }
}
