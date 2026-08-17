<?php

namespace App\Services;

use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The new-shape checkout core: multi-discount (configurable rules) +
 * split-payment finalization in one atomic transaction. The legacy
 * single-discount/single-payment path in OrderController::markAsPaid()
 * stays as-is for backward compatibility; this service handles requests
 * that use the new `discounts[]` / `payments[]` arrays.
 *
 * Everything money-related is computed server-side with bcmath from the
 * live order lines — the client never submits a trusted total.
 */
class PaymentFinalizer
{
    /**
     * @param  array<string, mixed>  $data  Validated FinalizeOrderPaymentRequest data
     */
    public static function finalize(Order $order, array $data, User $actingUser): OrderInvoiceSnapshot
    {
        return DB::transaction(function () use ($order, $data, $actingUser) {
            $order->load(['items.adjustments']);

            // Re-derive the authoritative order total from the live lines
            // (net of cancellations) before any math.
            $order->recalculateTotal();
            $order->refresh()->load(['items.adjustments']);

            // A repay after a void must never inherit stale eligibility
            // flags from an earlier payment attempt.
            OrderItem::where('order_id', $order->id)->update(['is_discount_eligible' => false]);

            $resolution = CheckoutDiscountResolver::resolve(
                $order,
                array_values($data['discounts'] ?? []),
                $actingUser,
                $data['manager_email'] ?? null,
                $data['manager_password'] ?? null,
            );

            if ($resolution['eligible_item_ids'] !== []) {
                OrderItem::where('order_id', $order->id)
                    ->whereIn('id', $resolution['eligible_item_ids'])
                    ->update(['is_discount_eligible' => true]);
            }

            $setting = Setting::current();

            $breakdown = InvoiceCalculator::computeWithDiscountLines([
                'gross_sales' => (string) $order->total_amount,
                'tax_registration_type' => $setting->tax_registration_type,
                'tax_rate' => (string) $setting->tax_rate,
                'prices_include_vat' => $setting->prices_include_vat,
                'discount_lines' => $resolution['lines'],
                'service_charge_enabled' => $setting->service_charge_enabled,
                'service_charge_percent' => $setting->service_charge_percent,
                'service_charge_taxable' => $setting->service_charge_taxable,
            ]);

            $paymentEntries = self::validatePayments($order, array_values($data['payments'] ?? []), $breakdown['total_amount_due']);

            // Legacy single-discount columns stay populated when the new
            // shape maps cleanly onto them (exactly one statutory or one
            // percent line), so pre-existing views/reports keep working.
            $legacy = self::legacyDiscountColumns($resolution, $breakdown);

            $invoiceNumber = InvoiceNumberGenerator::generate($setting->invoice_number_prefix);

            $totalReceived = '0.00';
            $totalChange = '0.00';
            foreach ($paymentEntries as $entry) {
                $totalReceived = bcadd($totalReceived, $entry['tendered_amount'] ?? $entry['amount'], 2);
                $totalChange = bcadd($totalChange, $entry['change_amount'] ?? '0.00', 2);
            }

            $primaryEntry = collect($paymentEntries)->sortByDesc(fn ($entry) => (float) $entry['amount'])->first();

            $snapshot = OrderInvoiceSnapshot::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceSnapshotStatus::Active,
                'business_name' => $setting->invoiceBusinessName(),
                'trade_name' => $setting->resort_name,
                'business_address' => $setting->address,
                'contact_number' => $setting->contact_number,
                'email' => $setting->email,
                'website' => $setting->website,
                'tin' => $setting->tin,
                'branch_code' => $setting->branch_code,
                'tax_registration_type' => $setting->tax_registration_type,
                'tax_rate' => $setting->tax_rate,
                'prices_include_vat' => $setting->prices_include_vat,
                'invoice_title' => $setting->resolvedInvoiceTitle(),
                'bir_permit_number' => $setting->bir_permit_number,
                'atp_ocn_number' => $setting->atp_ocn_number,
                'atp_ocn_date_issued' => $setting->atp_ocn_date_issued,
                'invoice_serial_from' => $setting->invoice_serial_from,
                'invoice_serial_to' => $setting->invoice_serial_to,
                'footer_message' => $setting->resolvedFooterMessage(),
                'gross_sales' => $breakdown['gross_sales'],
                'vatable_sales' => $breakdown['vatable_sales'],
                'vat_exempt_sales' => $breakdown['vat_exempt_sales'],
                'zero_rated_sales' => $breakdown['zero_rated_sales'],
                'vat_amount' => $breakdown['vat_amount'],
                'vat_exemption_amount' => $breakdown['vat_exemption_amount'],
                'service_charge_enabled' => $setting->service_charge_enabled,
                'service_charge_percent' => $setting->service_charge_percent,
                'service_charge_amount' => $breakdown['service_charge_amount'],
                'service_charge_taxable' => $setting->service_charge_taxable,
                'discount_amount' => $breakdown['discount_amount'],
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_tin' => $data['buyer_tin'] ?? null,
                'buyer_address' => $data['buyer_address'] ?? null,
                'rounding_adjustment' => $breakdown['rounding_adjustment'],
                'total_amount_due' => $breakdown['total_amount_due'],
                'payment_method' => $primaryEntry['method'],
                'payment_reference' => $primaryEntry['terminal_reference'] ?? $primaryEntry['reference'] ?? null,
                'amount_received' => $totalReceived,
                'change_amount' => $totalChange,
                'computed_by' => $actingUser->id,
                'computed_at' => now(),
            ] + $legacy);

            foreach ($resolution['records'] as $index => $record) {
                $snapshot->discounts()->create($record + [
                    'calculated_amount' => $breakdown['discount_lines'][$index]['calculated_amount'],
                    'vat_exemption_amount' => $breakdown['discount_lines'][$index]['vat_exemption_amount'],
                ]);
            }

            foreach ($paymentEntries as $entry) {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'order_invoice_snapshot_id' => $snapshot->id,
                    'payment_method' => $entry['method'],
                    'status' => OrderPaymentStatus::Recorded,
                    'amount' => $entry['amount'],
                    'tendered_amount' => $entry['tendered_amount'] ?? null,
                    'change_amount' => $entry['change_amount'] ?? null,
                    'card_brand' => $entry['card_brand'] ?? null,
                    'card_last_four' => $entry['card_last_four'] ?? null,
                    'terminal_reference' => $entry['terminal_reference'] ?? null,
                    'approval_code' => $entry['approval_code'] ?? null,
                    'terminal_id' => $entry['terminal_id'] ?? null,
                    'reference' => $entry['reference'] ?? null,
                    'notes' => $entry['notes'] ?? null,
                    'received_by' => $actingUser->id,
                    'received_at' => now(),
                ]);
            }

            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => $primaryEntry['method'],
                'payment_reference' => $primaryEntry['terminal_reference'] ?? $primaryEntry['reference'] ?? null,
                'amount_received' => $totalReceived,
                'change_amount' => $totalChange,
                'receipt_number' => $invoiceNumber,
                'current_invoice_snapshot_id' => $snapshot->id,
                'paid_at' => now(),
            ]);

            return $snapshot;
        });
    }

    /**
     * Validate the split-payment entries against the server-computed total.
     * The client's `amount` is never trusted as-is: every entry's applied
     * amount is derived and capped here against what's still due at that
     * point in the list, so the running total can never walk past the
     * amount due no matter what was submitted. Cash tendered is never
     * capped — it's the cashier's real cash in hand — and any excess over
     * what an entry can still apply becomes that entry's change, not a
     * rejected "overpayment". Card rows need their terminal slip
     * reference, and a terminal reference that was already recorded (here
     * or on any other order) is rejected as a duplicate.
     *
     * @param  array<int, array<string, mixed>>  $rawEntries
     * @return array<int, array<string, mixed>>
     */
    protected static function validatePayments(Order $order, array $rawEntries, string $totalDue): array
    {
        if ($rawEntries === []) {
            throw ValidationException::withMessages([
                'payments' => __('At least one payment entry is required.'),
            ]);
        }

        $entries = [];
        $totalApplied = '0.00';
        $seenTerminalReferences = [];

        foreach ($rawEntries as $raw) {
            $method = PaymentMethod::from($raw['method']);
            $rawAmount = bcadd((string) $raw['amount'], '0', 2);
            if (bccomp($rawAmount, '0.00', 2) < 0) {
                $rawAmount = '0.00';
            }

            // What's still open before this entry — every entry's applied
            // amount is capped against this, never against its own raw
            // client-submitted amount.
            $remaining = bccomp($totalDue, $totalApplied, 2) > 0 ? bcsub($totalDue, $totalApplied, 2) : '0.00';

            $entry = [
                'method' => $method,
                'card_brand' => $raw['card_brand'] ?? null,
                'card_last_four' => $raw['card_last_four'] ?? null,
                'terminal_reference' => isset($raw['terminal_reference']) ? trim((string) $raw['terminal_reference']) ?: null : null,
                'approval_code' => $raw['approval_code'] ?? null,
                'terminal_id' => $raw['terminal_id'] ?? null,
                'reference' => $raw['reference'] ?? null,
                'notes' => $raw['notes'] ?? null,
            ];

            if ($method === PaymentMethod::Cash) {
                $tendered = isset($raw['tendered_amount']) && $raw['tendered_amount'] !== null && $raw['tendered_amount'] !== ''
                    ? bcadd((string) $raw['tendered_amount'], '0', 2)
                    : $rawAmount;
                if (bccomp($tendered, '0.00', 2) < 0) {
                    $tendered = '0.00';
                }

                $amount = bccomp($tendered, $remaining, 2) > 0 ? $remaining : $tendered;

                $entry['amount'] = $amount;
                $entry['tendered_amount'] = $tendered;
                $entry['change_amount'] = bcsub($tendered, $amount, 2);
            } else {
                // No change concept outside cash — cap what's applied at
                // what's still due and leave it there.
                $entry['amount'] = bccomp($rawAmount, $remaining, 2) > 0 ? $remaining : $rawAmount;

                if ($method === PaymentMethod::Card) {
                    if ($entry['terminal_reference'] === null) {
                        throw ValidationException::withMessages([
                            'payments' => __('A card payment needs the terminal transaction/reference number from the card machine slip.'),
                        ]);
                    }

                    if (isset($seenTerminalReferences[$entry['terminal_reference']])) {
                        throw ValidationException::withMessages([
                            'payments' => __('The same terminal reference number was entered twice.'),
                        ]);
                    }
                    $seenTerminalReferences[$entry['terminal_reference']] = true;

                    $alreadyRecorded = OrderPayment::where('terminal_reference', $entry['terminal_reference'])
                        ->where('status', OrderPaymentStatus::Recorded)
                        ->exists();

                    if ($alreadyRecorded) {
                        throw ValidationException::withMessages([
                            'payments' => __('Terminal reference :ref has already been recorded on another payment.', [
                                'ref' => $entry['terminal_reference'],
                            ]),
                        ]);
                    }
                } elseif ($method->requiresReference() && empty($entry['reference'])) {
                    throw ValidationException::withMessages([
                        'payments' => __('A reference number is required for :method payments.', ['method' => $method->label()]),
                    ]);
                }
            }

            $totalApplied = bcadd($totalApplied, $entry['amount'], 2);
            $entries[] = $entry;
        }

        // Overpayment is never an error — it's change (cash) or simply
        // capped (non-cash), handled above. Only a genuine shortfall
        // blocks finalizing.
        if (bccomp($totalApplied, $totalDue, 2) < 0) {
            throw ValidationException::withMessages([
                'payments' => __('Insufficient payment — ₱:short still due.', [
                    'short' => number_format((float) bcsub($totalDue, $totalApplied, 2), 2),
                ]),
            ]);
        }

        return $entries;
    }

    /**
     * Best-effort mapping of the new multi-discount shape back onto the
     * snapshot's legacy single-discount columns (only when exactly one
     * discount line applied and it looks like the old Senior/PWD/Promo
     * model). Multiple/fixed discounts leave the legacy columns null — the
     * per-line order_invoice_discounts rows are the source of truth.
     *
     * @param  array{lines: array, records: array}  $resolution
     * @param  array<string, mixed>  $breakdown
     * @return array<string, mixed>
     */
    protected static function legacyDiscountColumns(array $resolution, array $breakdown): array
    {
        if (count($resolution['records']) !== 1) {
            return [];
        }

        $record = $resolution['records'][0];
        $line = $resolution['lines'][0];

        if (! empty($record['statutory_type'])) {
            return [
                'discount_type' => $record['statutory_type'],
                'discount_qualified_name' => $record['qualified_name'],
                'discount_id_number' => $record['id_number'],
                'discount_eligible_amount' => $record['eligible_amount'],
                'discount_notes' => $record['reason'],
            ];
        }

        if ($line['calculation_mode'] === 'percent') {
            return [
                'discount_type' => 'promo',
                'discount_promo_percent' => $line['value'],
                'discount_eligible_amount' => $record['eligible_amount'],
                'discount_notes' => $record['reason'],
            ];
        }

        return [];
    }
}
