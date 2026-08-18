<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\WeighedOrderSettings;
use App\Support\WeighVarianceChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one place that decides whether a keyed amount needs explaining.
 * Both the server's refusal and the tablet's live indicator read this
 * class, so its three outcomes — pass, needs a reason, needs a
 * supervisor — are covered at their exact boundaries.
 */
class WeighVarianceCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function checker(): WeighVarianceChecker
    {
        return WeighVarianceChecker::make(WeighedOrderSettings::current());
    }

    public function test_an_exact_match_passes_with_zero_variance(): void
    {
        $result = $this->checker()->check('177.00', '177.00');

        $this->assertTrue($result->passes());
        $this->assertSame('0.00', $result->varianceAmount);
        $this->assertFalse($result->requiresReason);
        $this->assertFalse($result->requiresOverride);
    }

    /** Default settings: max(₱1.00, 1%). At ₱177 expected, 1% (₱1.77) wins. */
    public function test_a_difference_within_the_percent_tolerance_passes(): void
    {
        $result = $this->checker()->check('177.00', '178.00');

        $this->assertTrue($result->passes());
        $this->assertSame('1.77', $result->tolerance);
    }

    public function test_a_difference_just_past_tolerance_requires_a_reason_only(): void
    {
        $result = $this->checker()->check('177.00', '179.00');

        $this->assertFalse($result->passes());
        $this->assertTrue($result->requiresReason);
        $this->assertFalse($result->requiresOverride);
    }

    /** The flat ₱1.00 floor wins for a small line where 1% would round to nothing. */
    public function test_the_flat_tolerance_wins_when_larger_than_the_percentage(): void
    {
        $result = $this->checker()->check('10.00', '10.50');

        // 1% of ₱10 is ₱0.10; the flat ₱1.00 floor is larger and wins.
        $this->assertSame('1.00', $result->tolerance);
        $this->assertTrue($result->passes());
    }

    public function test_a_difference_past_the_hard_ceiling_requires_override_too(): void
    {
        // ₱200 vs ₱100 expected is 100% — far past the default 10% ceiling.
        $result = $this->checker()->check('100.00', '200.00');

        $this->assertFalse($result->passes());
        $this->assertTrue($result->requiresReason);
        $this->assertTrue($result->requiresOverride);
    }

    public function test_a_charge_below_the_expected_amount_is_also_a_variance(): void
    {
        // Variance is unsigned distance — undercharging needs explaining too.
        $result = $this->checker()->check('177.00', '100.00');

        $this->assertFalse($result->passes());
        $this->assertSame('-77.00', $result->varianceAmount);
        $this->assertSame('77.00', $result->absoluteVariance());
    }

    public function test_moving_the_setting_moves_what_passes(): void
    {
        Setting::current()->update(['weighed_variance_tolerance_percent' => 10.00]);

        // Re-read fresh settings so the new percent is honoured.
        $result = WeighVarianceChecker::make(WeighedOrderSettings::current())->check('177.00', '185.00');

        $this->assertTrue($result->passes());
    }

    public function test_a_zero_expected_amount_is_reported_as_maximally_over_ceiling(): void
    {
        $result = $this->checker()->check('0.00', '50.00');

        $this->assertFalse($result->passes());
        $this->assertTrue($result->requiresOverride);
    }

    public function test_toarray_exposes_every_figure_the_tablet_indicator_needs(): void
    {
        $result = $this->checker()->check('177.00', '179.00');

        $this->assertSame([
            'passes',
            'computed_amount',
            'amount_charged',
            'tolerance',
            'variance_amount',
            'variance_percent',
            'hard_ceiling_percent',
            'requires_reason',
            'requires_override',
        ], array_keys($result->toArray()));
    }
}
