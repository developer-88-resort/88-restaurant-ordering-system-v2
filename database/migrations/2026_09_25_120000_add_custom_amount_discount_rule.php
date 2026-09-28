<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Brings back a straight peso discount at checkout: "less ₱500", rather
     * than only "less 20%". The engine already had the fixed mode
     * (InvoiceCalculator's third pass) and the checkout form already
     * switches its input to an amount field — the only thing missing was a
     * rule row to switch it on, which the 2026-07-31 catalog trim removed.
     *
     * No cap of its own: the cashier types any amount and the calculator
     * clamps it to what is still owed, so a discount can zero a bill but
     * never make it negative. The reason is optional — the field is offered
     * on every custom-value rule but only enforced where the rule says so.
     * Manager approval is kept ON, matching its percentage sibling: an
     * arbitrary amount off a bill is the one control worth signing for.
     */
    public function up(): void
    {
        if (DB::table('discount_rules')->where('code', 'custom_amount')->exists()) {
            return;
        }

        DB::table('discount_rules')->insert([
            'code' => 'custom_amount',
            'name' => 'Custom Amount Discount',
            'calculation_mode' => 'fixed',
            'value' => null,
            'is_custom_value' => true,
            'statutory_type' => null,
            'scope' => 'whole_bill',
            'is_stackable' => false,
            'priority' => 21,
            'max_discount_amount' => null,
            'min_bill_amount' => null,
            'requires_customer_id' => false,
            'requires_reason' => false,
            'requires_manager_approval' => true,
            'active_from' => null,
            'active_until' => null,
            'is_active' => true,
            'sort_order' => 21,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Invoices that already used this rule keep rendering from their own
     * frozen order_invoice_discounts row (rule name, code, mode and amount
     * are copied onto it, and its discount_rule_id is nullOnDelete), so
     * removing the rule only takes it off the cashier's list.
     */
    public function down(): void
    {
        DB::table('discount_rules')->where('code', 'custom_amount')->delete();
    }
};
