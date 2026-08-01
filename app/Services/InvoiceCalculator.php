<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\TaxRegistrationType;

/**
 * Stateless, decimal-safe (bcmath) computation of the full BIR invoice
 * breakdown for one order: VATable/VAT-exempt sales, VAT amount, Senior/
 * PWD/promo discount, optional service charge, and the final amount due.
 * This is the single source of truth for these numbers — the checkout
 * flow calls it to produce the persisted OrderInvoiceSnapshot, and every
 * other place (receipt, PDF, reports) only ever reads that already-computed
 * snapshot rather than recomputing.
 *
 * bcmath is used because decimal:2-cast Eloquent money attributes are
 * already PHP strings, which is exactly what bcmath natively consumes and
 * produces — no cents-conversion boundary needed. Internal scale is 6;
 * every returned figure is rounded to 2 decimals exactly once, at the
 * point it's returned (bcmath truncates rather than rounds, so rounding
 * mid-calculation and reusing the rounded value would let small errors
 * compound — see round2()). Where two figures must sum to a known total
 * (e.g. vat_amount and vatable_sales against the inclusive amount), the
 * second is derived by subtraction from the first rather than rounded
 * independently, so a routine rounding_adjustment is never needed for
 * typical peso amounts.
 */
class InvoiceCalculator
{
    protected const SCALE = 6;

    /**
     * @param  array{
     *     gross_sales: string|float,
     *     tax_registration_type: TaxRegistrationType|string,
     *     tax_rate: string|float,
     *     prices_include_vat: bool,
     *     discount_type?: DiscountType|string|null,
     *     eligible_amount?: string|float|null,
     *     promo_percent?: string|float|null,
     *     service_charge_enabled?: bool,
     *     service_charge_percent?: string|float|null,
     *     service_charge_taxable?: bool,
     * }  $input
     * @return array<string, string>
     */
    public static function compute(array $input): array
    {
        $rate = self::normalize($input['tax_rate'] ?? '12.00');
        $inclusive = (bool) ($input['prices_include_vat'] ?? true);
        $isVat = self::isVat($input['tax_registration_type']);
        $grossSales = self::normalize($input['gross_sales'] ?? '0');

        $discountType = $input['discount_type'] ?? null;
        $eligibleAmount = ($input['eligible_amount'] ?? null) !== null
            ? self::normalize($input['eligible_amount'])
            : null;
        $promoPercent = $input['promo_percent'] ?? null;
        $hasDiscount = $discountType !== null && $eligibleAmount !== null;
        $isStatutory = $hasDiscount && self::isStatutory($discountType);

        $nonEligibleAmount = $hasDiscount
            ? bcsub($grossSales, $eligibleAmount, self::SCALE)
            : $grossSales;

        $vatableSales = '0';
        $vatAmount = '0';
        $vatExemptSales = '0';
        $vatExemption = '0';
        $discountAmount = '0';
        $nonEligibleDue = $nonEligibleAmount;
        $eligibleDue = '0';

        if ($isVat) {
            $split = self::splitTaxable($nonEligibleAmount, $rate, $inclusive);
            $vatableSales = $split['net'];
            $vatAmount = $split['vat'];
            $nonEligibleDue = $split['gross'];
        }

        if ($hasDiscount) {
            if ($isVat && $isStatutory) {
                // Senior/PWD: 20% off the amount NET of VAT (RA 9994 / RA
                // 10754 / BIR RR 8-2010) — strip VAT first, then discount.
                $eligibleSplit = self::splitTaxable($eligibleAmount, $rate, $inclusive);
                $vatExemptSales = $eligibleSplit['net'];
                $vatExemption = $eligibleSplit['vat'];
                $discountAmount = bcmul($vatExemptSales, '0.20', self::SCALE);
                $eligibleDue = bcsub($vatExemptSales, $discountAmount, self::SCALE);
            } elseif ($isVat) {
                // Promo: not statutory, stays fully VATable — simple
                // percentage off, no VAT-exemption treatment.
                $eligibleSplit = self::splitTaxable($eligibleAmount, $rate, $inclusive);
                $vatableSales = bcadd($vatableSales, $eligibleSplit['net'], self::SCALE);
                $vatAmount = bcadd($vatAmount, $eligibleSplit['vat'], self::SCALE);
                $percent = bcdiv(self::normalize($promoPercent ?? '0'), '100', self::SCALE);
                $discountAmount = bcmul($eligibleAmount, $percent, self::SCALE);
                $eligibleDue = bcsub($eligibleSplit['gross'], $discountAmount, self::SCALE);
            } else {
                // Non-VAT business: nothing labeled VAT anywhere.
                $percent = $isStatutory ? '0.20' : bcdiv(self::normalize($promoPercent ?? '0'), '100', self::SCALE);
                $discountAmount = bcmul($eligibleAmount, $percent, self::SCALE);
                $eligibleDue = bcsub($eligibleAmount, $discountAmount, self::SCALE);
            }
        }

        // Service charge: computed off gross_sales before discount, kept
        // as its own additive line so it never interacts with the
        // discount math above. Only contributes its own small VAT
        // component when explicitly marked taxable.
        $serviceChargeEnabled = (bool) ($input['service_charge_enabled'] ?? false);
        $serviceChargeAmount = '0';
        if ($serviceChargeEnabled && ! empty($input['service_charge_percent'])) {
            $scPercent = bcdiv(self::normalize($input['service_charge_percent']), '100', self::SCALE);
            $serviceChargeAmount = bcmul($grossSales, $scPercent, self::SCALE);

            if ($isVat && ($input['service_charge_taxable'] ?? false)) {
                $scSplit = self::splitTaxable($serviceChargeAmount, $rate, true);
                $vatAmount = bcadd($vatAmount, $scSplit['vat'], self::SCALE);
            }
        }

        $totalAmountDue = bcadd(bcadd($nonEligibleDue, $eligibleDue, self::SCALE), $serviceChargeAmount, self::SCALE);

        return [
            'gross_sales' => self::round2($grossSales),
            'vatable_sales' => self::round2($vatableSales),
            'vat_exempt_sales' => self::round2($vatExemptSales),
            'zero_rated_sales' => '0.00',
            'vat_amount' => self::round2($vatAmount),
            'vat_exemption_amount' => self::round2($vatExemption),
            'discount_amount' => self::round2($discountAmount),
            'service_charge_amount' => self::round2($serviceChargeAmount),
            'rounding_adjustment' => '0.00',
            'total_amount_due' => self::round2($totalAmountDue),
        ];
    }

    /**
     * Multi-discount variant of compute() for the configurable
     * discount-rules checkout: several discount lines on one invoice, each
     * already validated/resolved by the caller (CheckoutDiscountResolver).
     * The legacy single-discount compute() above stays untouched — it
     * still serves the old request shape and the existing test suite.
     *
     * Processing order (mirrors the documented calculation rules):
     *  1. Statutory (Senior/PWD) lines get the BIR VAT-exemption treatment
     *     on their own eligible amounts, exactly like compute() does.
     *  2. Non-statutory percent lines apply sequentially, in the given
     *     order, against the running non-statutory balance (so stacked
     *     percentages compound rather than double-count).
     *  3. Fixed-amount lines subtract last, clamped to the remaining
     *     balance — the total can never go negative.
     * Each line respects its optional max_discount_amount cap. Service
     * charge stays computed off gross sales, same as compute().
     *
     * @param  array{
     *     gross_sales: string|float,
     *     tax_registration_type: TaxRegistrationType|string,
     *     tax_rate: string|float,
     *     prices_include_vat: bool,
     *     discount_lines?: array<int, array{
     *         calculation_mode: string,
     *         value: string|float|null,
     *         statutory_type?: string|null,
     *         eligible_amount?: string|float|null,
     *         max_discount_amount?: string|float|null,
     *     }>,
     *     service_charge_enabled?: bool,
     *     service_charge_percent?: string|float|null,
     *     service_charge_taxable?: bool,
     * }  $input
     * @return array<string, mixed>  compute()-shaped totals plus a
     *     'discount_lines' list carrying each line's calculated_amount and
     *     vat_exemption_amount in the same order they were given.
     */
    public static function computeWithDiscountLines(array $input): array
    {
        $rate = self::normalize($input['tax_rate'] ?? '12.00');
        $inclusive = (bool) ($input['prices_include_vat'] ?? true);
        $isVat = self::isVat($input['tax_registration_type']);
        $grossSales = self::normalize($input['gross_sales'] ?? '0');
        $lines = array_values($input['discount_lines'] ?? []);

        $statutoryEligibleTotal = '0';
        foreach ($lines as $line) {
            if (! empty($line['statutory_type'])) {
                $statutoryEligibleTotal = bcadd($statutoryEligibleTotal, self::normalize($line['eligible_amount'] ?? '0'), self::SCALE);
            }
        }

        // The statutory-eligible portion leaves the normal VATable pool
        // entirely; everything else is the running balance the sequential
        // percent/fixed lines chip away at.
        $nonStatutoryGross = bcsub($grossSales, $statutoryEligibleTotal, self::SCALE);

        $vatableSales = '0';
        $vatAmount = '0';
        $vatExemptSales = '0';
        $vatExemption = '0';
        $statutoryDue = '0';
        $runningDue = $nonStatutoryGross;

        if ($isVat) {
            $split = self::splitTaxable($nonStatutoryGross, $rate, $inclusive);
            $vatableSales = $split['net'];
            $vatAmount = $split['vat'];
            $runningDue = $split['gross'];
        }

        $lineResults = [];
        $totalDiscount = '0';

        // Pass 1 — statutory lines (order among themselves doesn't matter,
        // they each act on their own eligible base).
        foreach ($lines as $index => $line) {
            if (empty($line['statutory_type'])) {
                continue;
            }

            $eligible = self::normalize($line['eligible_amount'] ?? '0');
            $percent = bcdiv(self::normalize($line['value'] ?? '20'), '100', self::SCALE);
            $lineExemption = '0';

            if ($isVat) {
                $eligibleSplit = self::splitTaxable($eligible, $rate, $inclusive);
                $net = $eligibleSplit['net'];
                $lineExemption = $eligibleSplit['vat'];
                $vatExemptSales = bcadd($vatExemptSales, $net, self::SCALE);
                $vatExemption = bcadd($vatExemption, $lineExemption, self::SCALE);
            } else {
                $net = $eligible;
            }

            $discount = bcmul($net, $percent, self::SCALE);
            $discount = self::applyCap($discount, $line['max_discount_amount'] ?? null);
            $statutoryDue = bcadd($statutoryDue, bcsub($net, $discount, self::SCALE), self::SCALE);
            $totalDiscount = bcadd($totalDiscount, $discount, self::SCALE);

            $lineResults[$index] = [
                'calculated_amount' => self::round2($discount),
                'vat_exemption_amount' => self::round2($lineExemption),
            ];
        }

        // Pass 2 — non-statutory percent lines, sequential on the running
        // balance (a line with its own eligible base uses that base, but
        // never more than what's still owed).
        foreach ($lines as $index => $line) {
            if (! empty($line['statutory_type']) || ($line['calculation_mode'] ?? 'percent') !== 'percent') {
                continue;
            }

            $percent = bcdiv(self::normalize($line['value'] ?? '0'), '100', self::SCALE);
            $base = ($line['eligible_amount'] ?? null) !== null
                ? self::normalize($line['eligible_amount'])
                : $runningDue;
            if (bccomp($base, $runningDue, self::SCALE) > 0) {
                $base = $runningDue;
            }

            $discount = bcmul($base, $percent, self::SCALE);
            $discount = self::applyCap($discount, $line['max_discount_amount'] ?? null);
            if (bccomp($discount, $runningDue, self::SCALE) > 0) {
                $discount = $runningDue;
            }

            $runningDue = bcsub($runningDue, $discount, self::SCALE);
            $totalDiscount = bcadd($totalDiscount, $discount, self::SCALE);

            $lineResults[$index] = [
                'calculated_amount' => self::round2($discount),
                'vat_exemption_amount' => '0.00',
            ];
        }

        // Pass 3 — fixed-amount lines, clamped to what's still owed.
        foreach ($lines as $index => $line) {
            if (! empty($line['statutory_type']) || ($line['calculation_mode'] ?? 'percent') !== 'fixed') {
                continue;
            }

            $discount = self::normalize($line['value'] ?? '0');
            $discount = self::applyCap($discount, $line['max_discount_amount'] ?? null);
            if (bccomp($discount, $runningDue, self::SCALE) > 0) {
                $discount = $runningDue;
            }

            $runningDue = bcsub($runningDue, $discount, self::SCALE);
            $totalDiscount = bcadd($totalDiscount, $discount, self::SCALE);

            $lineResults[$index] = [
                'calculated_amount' => self::round2($discount),
                'vat_exemption_amount' => '0.00',
            ];
        }

        $serviceChargeEnabled = (bool) ($input['service_charge_enabled'] ?? false);
        $serviceChargeAmount = '0';
        if ($serviceChargeEnabled && ! empty($input['service_charge_percent'])) {
            $scPercent = bcdiv(self::normalize($input['service_charge_percent']), '100', self::SCALE);
            $serviceChargeAmount = bcmul($grossSales, $scPercent, self::SCALE);

            if ($isVat && ($input['service_charge_taxable'] ?? false)) {
                $scSplit = self::splitTaxable($serviceChargeAmount, $rate, true);
                $vatAmount = bcadd($vatAmount, $scSplit['vat'], self::SCALE);
            }
        }

        $totalAmountDue = bcadd(bcadd($runningDue, $statutoryDue, self::SCALE), $serviceChargeAmount, self::SCALE);
        if (bccomp($totalAmountDue, '0', self::SCALE) < 0) {
            $totalAmountDue = '0';
        }

        // Re-key results back to the caller's original line order.
        ksort($lineResults);

        return [
            'gross_sales' => self::round2($grossSales),
            'vatable_sales' => self::round2($vatableSales),
            'vat_exempt_sales' => self::round2($vatExemptSales),
            'zero_rated_sales' => '0.00',
            'vat_amount' => self::round2($vatAmount),
            'vat_exemption_amount' => self::round2($vatExemption),
            'discount_amount' => self::round2($totalDiscount),
            'service_charge_amount' => self::round2($serviceChargeAmount),
            'rounding_adjustment' => '0.00',
            'total_amount_due' => self::round2($totalAmountDue),
            'discount_lines' => array_values($lineResults),
        ];
    }

    /**
     * Clamp a computed discount to its rule's optional maximum.
     */
    protected static function applyCap(string $discount, string|float|null $cap): string
    {
        if ($cap === null || $cap === '') {
            return $discount;
        }

        $capNormalized = self::normalize($cap);

        return bccomp($discount, $capNormalized, self::SCALE) > 0 ? $capNormalized : $discount;
    }

    /**
     * Splits an amount into its net-of-tax and VAT components. When prices
     * are VAT-inclusive (the normal case), $amount already IS the gross
     * charge and is divided down; when they're VAT-exclusive, $amount is
     * the net base and VAT is added on top.
     *
     * @return array{net: string, vat: string, gross: string}
     */
    protected static function splitTaxable(string $amount, string $rate, bool $inclusive): array
    {
        if ($inclusive) {
            $divisor = bcadd('1', bcdiv($rate, '100', self::SCALE), self::SCALE);
            $net = bcdiv($amount, $divisor, self::SCALE);
            $vat = bcsub($amount, $net, self::SCALE);

            return ['net' => $net, 'vat' => $vat, 'gross' => $amount];
        }

        $vat = bcmul($amount, bcdiv($rate, '100', self::SCALE), self::SCALE);
        $gross = bcadd($amount, $vat, self::SCALE);

        return ['net' => $amount, 'vat' => $vat, 'gross' => $gross];
    }

    /**
     * bcmath truncates rather than rounds — this adds half a centavo
     * before truncating to 2 decimals, i.e. standard round-half-up.
     * Every monetary value in this system is non-negative, so this simple
     * form (no sign handling) is sufficient.
     */
    protected static function round2(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    protected static function normalize(string|float|int $value): string
    {
        return rtrim(rtrim(sprintf('%.'.self::SCALE.'F', (float) $value), '0'), '.') ?: '0';
    }

    protected static function isVat(TaxRegistrationType|string $type): bool
    {
        return $type instanceof TaxRegistrationType
            ? $type === TaxRegistrationType::Vat
            : $type === TaxRegistrationType::Vat->value;
    }

    protected static function isStatutory(DiscountType|string $type): bool
    {
        return $type instanceof DiscountType
            ? $type->isStatutory()
            : in_array($type, [DiscountType::SeniorCitizen->value, DiscountType::Pwd->value], true);
    }
}
