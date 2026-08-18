<?php

namespace App\Support;

/**
 * THE one place that decides whether a keyed amount needs explaining.
 *
 * The scale at the counter is the authority on what a fish costs, so the
 * system no longer computes the charge — it computes what the charge
 * SHOULD have been and compares. Three outcomes, and only three:
 *
 *   within tolerance  → through, silently
 *   over tolerance    → a written reason is required
 *   over the ceiling  → a reason AND weigh.override_price
 *
 * Both the server (which refuses lines) and the tablet's live "Expected
 * ₱177.00 ✓" indicator read this class, via POST /weigh/check-variance.
 * Re-implementing the comparison in JavaScript would let the screen
 * promise a line the server then rejects — the exact failure this exists
 * to prevent. All the numbers it compares against come from
 * {@see WeighedOrderSettings}; none are written here.
 */
class WeighVarianceChecker
{
    public function __construct(protected WeighedOrderSettings $settings) {}

    public static function make(?WeighedOrderSettings $settings = null): self
    {
        return new self($settings ?? WeighedOrderSettings::current());
    }

    /**
     * @param  string|float  $computedAmount  What the reference rate implies.
     * @param  string|float  $amountCharged  What the scale display said.
     */
    public function check(string|float $computedAmount, string|float $amountCharged): WeighVarianceResult
    {
        $computed = self::money($computedAmount);
        $charged = self::money($amountCharged);

        $variance = bcsub($charged, $computed, 2);
        $absolute = ltrim($variance, '-');

        $tolerance = $this->settings->varianceAllowanceFor($computed);
        $percent = $this->percentOf($variance, $computed);
        $ceiling = $this->settings->hardCeilingPercent();

        $overTolerance = bccomp($absolute, $tolerance, 2) > 0;
        $overCeiling = $overTolerance && bccomp(ltrim($percent, '-'), $ceiling, 2) > 0;

        return new WeighVarianceResult(
            computedAmount: $computed,
            amountCharged: $charged,
            tolerance: $tolerance,
            varianceAmount: $variance,
            variancePercent: $percent,
            hardCeilingPercent: $ceiling,
            // Past the ceiling still needs the reason — the override is an
            // extra requirement on top, not a replacement for explaining.
            requiresReason: $overTolerance,
            requiresOverride: $overCeiling,
        );
    }

    /**
     * Variance as a percentage of what was expected.
     *
     * A zero expectation has no percentage — dividing would be undefined —
     * so any charge against it is reported as unmistakably over any
     * ceiling. That case means the rate or the weight is missing, which is
     * precisely when a human should look.
     */
    protected function percentOf(string $variance, string $computed): string
    {
        if (bccomp($computed, '0.00', 2) === 0) {
            return bccomp($variance, '0.00', 2) === 0 ? '0.00' : '999.99';
        }

        return bcdiv(bcmul($variance, '100', 6), $computed, 2);
    }

    protected static function money(string|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
