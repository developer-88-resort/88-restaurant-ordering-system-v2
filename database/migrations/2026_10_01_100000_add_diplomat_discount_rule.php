<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diplomat discount: a diplomat pays their share of the bill without VAT
     * and with nothing else off. A group of 5 sharing ₱5,000 with one
     * diplomat → that diplomat's share is ₱1,000, which becomes
     * ₱1,000 / 1.12 = ₱892.86; the rest of the group pays as normal.
     *
     * It rides the statutory pass Senior/PWD already use (eligible base →
     * VAT stripped → percentage off the net), with the percentage at 0. The
     * new 'per_person' scope works the eligible base out from headcounts —
     * bill ÷ persons in the group × diplomats among them — and the counts
     * are kept on the invoice line for the audit trail.
     */
    public function up(): void
    {
        Schema::table('order_invoice_discounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_persons')->nullable()->after('eligible_amount');
            $table->unsignedSmallInteger('qualified_persons')->nullable()->after('total_persons');
        });

        if (DB::table('discount_rules')->where('code', 'diplomat')->exists()) {
            return;
        }

        DB::table('discount_rules')->insert([
            'code' => 'diplomat',
            'name' => 'Diplomat Discount',
            'calculation_mode' => 'percent',
            'value' => 0,
            'is_custom_value' => false,
            'statutory_type' => 'diplomat',
            'scope' => 'per_person',
            'is_stackable' => true,
            'priority' => 60,
            'max_discount_amount' => null,
            'min_bill_amount' => null,
            'requires_customer_id' => true,
            'requires_reason' => false,
            'requires_manager_approval' => false,
            'active_from' => null,
            'active_until' => null,
            'is_active' => true,
            'sort_order' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('discount_rules')->where('code', 'diplomat')->delete();

        Schema::table('order_invoice_discounts', function (Blueprint $table) {
            $table->dropColumn(['total_persons', 'qualified_persons']);
        });
    }
};
