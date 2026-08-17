<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings for weighed orders.
 *
 * These replace the step/tare logic that used to live on the menu item.
 * The scale at the counter now produces both the weight and the amount;
 * staff key in what the display says. What the system does instead is
 * CHECK that keyed amount against the reference rate — and how far it may
 * drift before someone has to explain is a business decision, so it lives
 * here rather than as a constant in a validator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Whichever of the two is LARGER is the allowance for a line.
            $table->decimal('weighed_variance_tolerance_amount', 10, 2)->default(1.00)->after('weigh_customer_confirmation_enabled');
            $table->decimal('weighed_variance_tolerance_percent', 5, 2)->default(1.00)->after('weighed_variance_tolerance_amount');
            // Past this, a written reason is not enough — it needs the
            // weigh.override_price permission.
            $table->decimal('weighed_variance_hard_ceiling_percent', 5, 2)->default(10.00)->after('weighed_variance_tolerance_percent');
            $table->string('weighed_below_minimum_behavior')->default('block')->after('weighed_variance_hard_ceiling_percent');
            // Typo guard for the reference rate on the menu item form.
            $table->decimal('weighed_price_per_kilo_min', 10, 2)->default(10.00)->after('weighed_below_minimum_behavior');
            $table->decimal('weighed_price_per_kilo_max', 10, 2)->default(10000.00)->after('weighed_price_per_kilo_min');
            $table->boolean('weighed_print_slip')->default(false)->after('weighed_price_per_kilo_max');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'weighed_variance_tolerance_amount',
                'weighed_variance_tolerance_percent',
                'weighed_variance_hard_ceiling_percent',
                'weighed_below_minimum_behavior',
                'weighed_price_per_kilo_min',
                'weighed_price_per_kilo_max',
                'weighed_print_slip',
            ]);
        });
    }
};
