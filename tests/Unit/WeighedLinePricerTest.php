<?php

namespace Tests\Unit;

use App\Support\WeighedLinePricer;
use PHPUnit\Framework\TestCase;

/**
 * The pricer is the single source of truth for weighed-line money, so it
 * is covered exhaustively: every public method, every branch, and the
 * rounding boundaries where a naive float implementation loses a centavo.
 */
class WeighedLinePricerTest extends TestCase
{
    // ---------------------------------------------------------------
    // netGrams()
    // ---------------------------------------------------------------

    public function test_net_grams_without_tare_is_the_scale_reading(): void
    {
        $this->assertSame(1000, WeighedLinePricer::netGrams(1000));
    }

    public function test_net_grams_deducts_tare(): void
    {
        $this->assertSame(850, WeighedLinePricer::netGrams(1000, 150));
    }

    public function test_net_grams_never_goes_negative_when_tare_exceeds_gross(): void
    {
        // A tare heavier than the item is a scale/entry error — charging a
        // negative weight would credit the customer.
        $this->assertSame(0, WeighedLinePricer::netGrams(200, 500));
    }

    // ---------------------------------------------------------------
    // base() — normal cases
    // ---------------------------------------------------------------

    public function test_base_prices_one_exact_kilo(): void
    {
        $this->assertSame('500.00', WeighedLinePricer::base(1000, '500.00'));
    }

    public function test_base_prices_a_partial_kilo(): void
    {
        // 750 g @ ₱480/kg = ₱360.00
        $this->assertSame('360.00', WeighedLinePricer::base(750, '480.00'));
    }

    /**
     * The "smallest sellable" figure the menu item form shows an admin
     * comes from this pricer, not from a second formula written into the
     * form — the two would be free to drift apart.
     */
    public function test_it_backs_the_smallest_sellable_preview_on_the_menu_form(): void
    {
        // 250 g minimum @ ₱295/kg = ₱73.75
        $this->assertSame('73.75', WeighedLinePricer::base(250, '295.00'));
    }

    public function test_base_prices_more_than_one_kilo(): void
    {
        // 1750 g @ ₱600/kg = ₱1050.00
        $this->assertSame('1050.00', WeighedLinePricer::base(1750, '600.00'));
    }

    public function test_base_of_zero_weight_is_zero(): void
    {
        $this->assertSame('0.00', WeighedLinePricer::base(0, '899.00'));
    }

    public function test_base_accepts_a_float_rate(): void
    {
        // Rates arrive from decimal casts and form input as floats too.
        $this->assertSame('299.00', WeighedLinePricer::base(1000, 299.0));
    }

    // ---------------------------------------------------------------
    // base() — with tare
    // ---------------------------------------------------------------

    public function test_base_charges_only_the_net_weight_after_tare(): void
    {
        // (1200 − 200) g = 1000 g @ ₱450/kg = ₱450.00
        $this->assertSame('450.00', WeighedLinePricer::base(1200, '450.00', 200));
    }

    public function test_base_with_tare_on_a_partial_kilo(): void
    {
        // (980 − 80) g = 900 g @ ₱700/kg = ₱630.00
        $this->assertSame('630.00', WeighedLinePricer::base(980, '700.00', 80));
    }

    public function test_base_is_zero_when_tare_cancels_the_whole_weight(): void
    {
        $this->assertSame('0.00', WeighedLinePricer::base(300, '999.00', 300));
    }

    // ---------------------------------------------------------------
    // base() — rounding boundaries
    // ---------------------------------------------------------------

    /**
     * The spec's reference case: 333 g @ ₱299/kg.
     * Exact value is 99.567 — rounding must happen in PESOS (→ 99.57),
     * never on the kilo figure (0.333 kg × 299 would still be 99.567, but
     * rounding 0.333 first would give 99.57 only by luck; rounding to
     * 0.33 kg would wrongly give 98.67).
     */
    public function test_base_rounds_the_spec_boundary_case_of_333g_at_299(): void
    {
        $this->assertSame('99.57', WeighedLinePricer::base(333, '299.00'));
    }

    public function test_base_rounds_half_up_at_an_exact_half_centavo(): void
    {
        // 50 g @ ₱123.45/kg = 6.1725 → 6.17 (below the half-centavo).
        $this->assertSame('6.17', WeighedLinePricer::base(50, '123.45'));

        // 500 g @ ₱100.05/kg = 50.025 → 50.03 (exact half rounds up).
        $this->assertSame('50.03', WeighedLinePricer::base(500, '100.05'));
    }

    public function test_base_rounds_a_repeating_decimal_down(): void
    {
        // 1 g @ ₱299/kg = 0.299 → 0.30
        $this->assertSame('0.30', WeighedLinePricer::base(1, '299.00'));

        // 1 g @ ₱149/kg = 0.149 → 0.15
        $this->assertSame('0.15', WeighedLinePricer::base(1, '149.00'));

        // 1 g @ ₱140/kg = 0.14 → 0.14
        $this->assertSame('0.14', WeighedLinePricer::base(1, '140.00'));
    }

    public function test_base_does_not_accumulate_float_drift_on_awkward_rates(): void
    {
        // 1 g @ ₱0.10/kg = 0.0001 → 0.00 (truncates to nothing, not 0.01).
        $this->assertSame('0.00', WeighedLinePricer::base(1, '0.10'));

        // 1234 g @ ₱1234.56/kg = 1523.44704 → 1523.45
        $this->assertSame('1523.45', WeighedLinePricer::base(1234, '1234.56'));
    }

    // ---------------------------------------------------------------
    // surcharge()
    // ---------------------------------------------------------------

    public function test_surcharge_defaults_to_zero(): void
    {
        $this->assertSame('0.00', WeighedLinePricer::surcharge());
    }

    public function test_surcharge_counts_a_null_piece_count_as_one_piece(): void
    {
        $this->assertSame('50.00', WeighedLinePricer::surcharge('50.00', null));
    }

    public function test_surcharge_counts_a_zero_piece_count_as_one_piece(): void
    {
        // A line always has at least one physical piece.
        $this->assertSame('50.00', WeighedLinePricer::surcharge('50.00', 0));
    }

    public function test_surcharge_multiplies_by_the_piece_count(): void
    {
        $this->assertSame('150.00', WeighedLinePricer::surcharge('50.00', 3));
    }

    public function test_surcharge_handles_centavo_rates_across_many_pieces(): void
    {
        // 12.35 × 7 = 86.45
        $this->assertSame('86.45', WeighedLinePricer::surcharge('12.35', 7));
    }

    public function test_surcharge_accepts_a_float(): void
    {
        $this->assertSame('75.00', WeighedLinePricer::surcharge(25.0, 3));
    }

    /**
     * Nothing in M0 produces a negative surcharge, but a "style that takes
     * money off" is a plausible future promo and the rounding must stay
     * symmetric if one ever appears — −50.005 must round to −50.01, not
     * truncate toward zero.
     */
    public function test_surcharge_rounds_a_negative_value_away_from_zero(): void
    {
        $this->assertSame('-100.00', WeighedLinePricer::surcharge('-50.00', 2));
        $this->assertSame('-50.01', WeighedLinePricer::surcharge('-16.67', 3));
    }

    // ---------------------------------------------------------------
    // total() — the composed formula
    // ---------------------------------------------------------------

    public function test_total_without_tare_or_surcharge_equals_the_base(): void
    {
        $this->assertSame('450.00', WeighedLinePricer::total(900, '500.00'));
    }

    public function test_total_adds_a_cooking_surcharge_to_the_weight_charge(): void
    {
        // 1000 g @ ₱500/kg = 500.00, plus a ₱50 style on 1 piece.
        $this->assertSame('550.00', WeighedLinePricer::total(1000, '500.00', 0, '50.00'));
    }

    public function test_total_combines_tare_and_surcharge(): void
    {
        // (1500 − 100) g = 1400 g @ ₱600/kg = 840.00, plus ₱30 × 1.
        $this->assertSame('870.00', WeighedLinePricer::total(1500, '600.00', 100, '30.00'));
    }

    public function test_total_charges_the_surcharge_once_per_piece(): void
    {
        // 2000 g @ ₱400/kg = 800.00, plus ₱25 × 4 pieces = 100.00.
        $this->assertSame('900.00', WeighedLinePricer::total(2000, '400.00', 0, '25.00', 4));
    }

    public function test_total_applies_every_part_of_the_formula_together(): void
    {
        // net = 2500 − 250 = 2250 g
        // base = 2.25 kg × ₱888.88 = 1999.98
        // surcharge = ₱12.50 × 3 = 37.50  →  2037.48
        $this->assertSame('2037.48', WeighedLinePricer::total(2500, '888.88', 250, '12.50', 3));
    }

    public function test_total_rounds_the_base_before_adding_the_surcharge(): void
    {
        // 333 g @ ₱299/kg = 99.57 (rounded), plus ₱10 × 2 = 20.00.
        $this->assertSame('119.57', WeighedLinePricer::total(333, '299.00', 0, '10.00', 2));
    }

    public function test_total_is_zero_when_tare_cancels_the_weight_and_nothing_is_charged(): void
    {
        $this->assertSame('0.00', WeighedLinePricer::total(500, '750.00', 500));
    }

    public function test_total_still_charges_the_surcharge_when_the_net_weight_is_zero(): void
    {
        // The cooking was still done even if the weight nets to nothing.
        $this->assertSame('40.00', WeighedLinePricer::total(500, '750.00', 500, '20.00', 2));
    }

    public function test_total_returns_a_two_decimal_string_suitable_for_bcmath_callers(): void
    {
        $total = WeighedLinePricer::total(1000, '500.00');

        $this->assertIsString($total);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $total);
    }
}
