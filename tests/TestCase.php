<?php

namespace Tests;

use App\Models\DiscountRule;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a discount rule for a test to exercise.
     *
     * The engine still supports percent/fixed/stacked/100% discounts even
     * though the resort's seeded catalog only offers a custom percentage
     * plus the two statutory rules — so tests that cover those engine
     * behaviors build the rule they need instead of depending on the
     * seeder, which reflects what cashiers may actually offer today.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeDiscountRule(array $attributes = []): DiscountRule
    {
        static $sequence = 0;
        $sequence++;

        return DiscountRule::create($attributes + [
            'code' => 'test_rule_'.$sequence,
            'name' => 'Test Discount',
            'calculation_mode' => 'percent',
            'value' => 20.00,
            'is_custom_value' => false,
            'statutory_type' => null,
            'scope' => 'whole_bill',
            'is_stackable' => false,
            'priority' => 10,
            'requires_customer_id' => false,
            'requires_reason' => false,
            'requires_manager_approval' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }
}
