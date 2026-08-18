<?php

namespace App\Support;

use App\Models\Setting;

/**
 * THE single read point for every weighed-order rule that an admin can
 * change.
 *
 * No validator, controller or Blade view may hard-code a tolerance, a
 * ceiling or a price range — they ask here. Keeping the reads in one class
 * is what makes "the admin changed the tolerance" a one-place change
 * instead of a hunt through the codebase, and it is why the numbers a
 * cashier sees can never disagree with the numbers the validator enforces.
 */
class WeighedOrderSettings
{
    public function __construct(protected Setting $setting) {}

    public static function current(): self
    {
        return new self(Setting::current());
    }

    /** Reference-rate typo guard on the menu item form. */
    public function pricePerKiloMin(): string
    {
        return (string) $this->setting->weighed_price_per_kilo_min;
    }

    public function pricePerKiloMax(): string
    {
        return (string) $this->setting->weighed_price_per_kilo_max;
    }

    /**
     * How far the amount keyed from the scale may sit from the amount the
     * reference rate implies, before a reason is required. The allowance is
     * whichever of the flat and percentage figures is LARGER, so a small
     * line isn't held to an impossibly tight peso figure and a large one
     * isn't waved through on a generous percentage.
     */
    public function varianceAllowanceFor(string|float $expectedAmount): string
    {
        $flat = (string) $this->setting->weighed_variance_tolerance_amount;
        $percent = bcmul(
            (string) $expectedAmount,
            bcdiv((string) $this->setting->weighed_variance_tolerance_percent, '100', 6),
            2,
        );

        return bccomp($flat, $percent, 2) >= 0 ? $flat : $percent;
    }

    /** Past this, a written reason is not enough — it needs weigh.override_price. */
    public function hardCeilingPercent(): string
    {
        return (string) $this->setting->weighed_variance_hard_ceiling_percent;
    }

    public function blocksBelowMinimum(): bool
    {
        return $this->setting->weighed_below_minimum_behavior !== 'bill_at_minimum';
    }

    public function billsAtMinimum(): bool
    {
        return ! $this->blocksBelowMinimum();
    }

    public function requiresCustomerConfirmation(): bool
    {
        return (bool) $this->setting->weigh_customer_confirmation_enabled;
    }

    public function printsWeighSlip(): bool
    {
        return (bool) $this->setting->weighed_print_slip;
    }

    /**
     * Shipped to the weigh station so the tablet applies the same rules the
     * server will enforce.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variance_tolerance_amount' => (float) $this->setting->weighed_variance_tolerance_amount,
            'variance_tolerance_percent' => (float) $this->setting->weighed_variance_tolerance_percent,
            'variance_hard_ceiling_percent' => (float) $this->setting->weighed_variance_hard_ceiling_percent,
            'blocks_below_minimum' => $this->blocksBelowMinimum(),
            'price_per_kilo_min' => (float) $this->pricePerKiloMin(),
            'price_per_kilo_max' => (float) $this->pricePerKiloMax(),
            'requires_customer_confirmation' => $this->requiresCustomerConfirmation(),
            'prints_weigh_slip' => $this->printsWeighSlip(),
        ];
    }
}
