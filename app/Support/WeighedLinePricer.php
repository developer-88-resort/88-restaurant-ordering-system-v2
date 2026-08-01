<?php

namespace App\Support;

/**
 * THE single source of truth for what a weighed order line costs.
 *
 *     netGrams = weight_grams − tare_grams
 *     base     = round((netGrams / 1000) × price_per_kilo, 2)
 *     total    = base + (cooking surcharge × max(pieces, 1))
 *
 * Rounding happens in PESOS and only at the two points above — never on
 * the kilo figure, which stays exact until it is multiplied by the rate.
 * Money is carried as decimal strings via bcmath (matching
 * InvoiceCalculator) so no binary-float drift can reach an invoice.
 *
 * Every other module — order creation, kitchen weighing, receipts,
 * quotations, reports — MUST price weighed lines through this class.
 * Re-implementing the formula anywhere else is a bug by definition: the
 * two copies will disagree the first time a rule changes.
 */
class WeighedLinePricer
{
    /** Intermediate precision; wide enough that the gram→kilo division never loses a centavo. */
    protected const SCALE = 6;

    /**
     * Net weight actually being charged, in grams. Never negative — a tare
     * larger than the gross reading is a scale/entry error, and charging a
     * negative weight would credit the customer.
     */
    public static function netGrams(int $weightGrams, int $tareGrams = 0): int
    {
        return max(0, $weightGrams - $tareGrams);
    }

    /**
     * Weight charge alone, before any cooking surcharge, as "0.00".
     */
    public static function base(int $weightGrams, string|float $pricePerKilo, int $tareGrams = 0): string
    {
        $net = self::netGrams($weightGrams, $tareGrams);

        $exact = bcdiv(
            bcmul(self::normalize($pricePerKilo), (string) $net, self::SCALE),
            '1000',
            self::SCALE
        );

        return self::round2($exact);
    }

    /**
     * Cooking surcharge for this line: charged once per physical piece, so
     * a 3-piece line pays it three times. A line with no piece count is
     * still one piece.
     */
    public static function surcharge(string|float $surchargePerPiece = 0, ?int $pieces = null): string
    {
        $units = max(1, $pieces ?? 1);

        return self::round2(bcmul(self::normalize($surchargePerPiece), (string) $units, self::SCALE));
    }

    /**
     * Full charge for the line: weight base + cooking surcharge.
     *
     * @param  int  $weightGrams  Gross scale reading in grams.
     * @param  string|float  $pricePerKilo  Snapshot rate — the line's frozen rate, not today's.
     * @param  int  $tareGrams  Container/ice weight to deduct.
     * @param  string|float  $surchargePerPiece  Cooking style surcharge, 0 when free.
     * @param  int|null  $pieces  Physical pieces on this line; null counts as 1.
     */
    public static function total(
        int $weightGrams,
        string|float $pricePerKilo,
        int $tareGrams = 0,
        string|float $surchargePerPiece = 0,
        ?int $pieces = null,
    ): string {
        return bcadd(
            self::base($weightGrams, $pricePerKilo, $tareGrams),
            self::surcharge($surchargePerPiece, $pieces),
            2
        );
    }

    /**
     * Half-up rounding to 2 decimals. bcadd truncates, so nudging by 0.005
     * first turns truncation into the round-half-up the peso amounts need
     * (e.g. 99.567 → 99.57, and the 333 g @ ₱299/kg case 99.567 → 99.57).
     */
    protected static function round2(string $value): string
    {
        if (bccomp($value, '0', self::SCALE) < 0) {
            return bcsub($value, '0.005', 2);
        }

        return bcadd($value, '0.005', 2);
    }

    /**
     * Floats arrive from casts and form input; normalize to a fixed-scale
     * decimal string before any bcmath call so PHP's float→string
     * conversion can't inject scientific notation or stray precision.
     */
    protected static function normalize(string|float $value): string
    {
        $formatted = sprintf('%.'.self::SCALE.'F', (float) $value);

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
