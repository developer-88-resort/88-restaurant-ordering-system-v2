<?php

namespace Database\Seeders;

use App\Models\DiscountRule;
use Illuminate\Database\Seeder;

/**
 * Default discount catalog. Idempotent — updateOrCreate keyed on `code`,
 * so re-running never duplicates rows and never overwrites an admin's
 * later tweaks to anything except the seeded baseline fields it sets.
 * Safe to run against the live database.
 *
 * The resort offers three discounts: a manager-approved custom percentage
 * and the two statutory ones (Senior Citizen, PWD). The 20% Total-Bill,
 * Custom Fixed-Amount, Promotional and Complimentary/Management rules were
 * retired on 2026-07-31 and are deliberately NOT seeded — re-adding them
 * here would switch them back on for cashiers at the next db:seed.
 * Invoices that already used a retired rule keep rendering from their own
 * frozen order_invoice_discounts row, which needs no DiscountRule at all.
 */
class DiscountRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'code' => 'custom_percent',
                'name' => 'Custom Percentage Discount',
                'calculation_mode' => 'percent',
                'value' => null,
                'is_custom_value' => true,
                'statutory_type' => null,
                'scope' => 'whole_bill',
                'is_stackable' => false,
                'priority' => 20,
                'requires_customer_id' => false,
                'requires_reason' => true,
                'requires_manager_approval' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'senior_citizen',
                'name' => 'Senior Citizen Discount',
                'calculation_mode' => 'percent',
                'value' => 20.00,
                'is_custom_value' => false,
                'statutory_type' => 'senior_citizen',
                'scope' => 'eligible_items',
                'is_stackable' => true,
                'priority' => 40,
                'requires_customer_id' => true,
                'requires_reason' => false,
                'requires_manager_approval' => false,
                'sort_order' => 40,
            ],
            [
                'code' => 'pwd',
                'name' => 'PWD Discount',
                'calculation_mode' => 'percent',
                'value' => 20.00,
                'is_custom_value' => false,
                'statutory_type' => 'pwd',
                'scope' => 'eligible_items',
                'is_stackable' => true,
                'priority' => 50,
                'requires_customer_id' => true,
                'requires_reason' => false,
                'requires_manager_approval' => false,
                'sort_order' => 50,
            ],
        ];

        foreach ($rules as $rule) {
            DiscountRule::updateOrCreate(['code' => $rule['code']], $rule + ['is_active' => true]);
        }
    }
}
