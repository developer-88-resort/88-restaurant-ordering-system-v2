<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-weight pricing foundation for menu items (fish/meat sold by the
 * kilo). A 'fixed' item keeps using `price`; a 'per_kilo' item prices from
 * `price_per_kilo` and the weight taken on the scale. Weights are integer
 * grams everywhere — never floating-point kilos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->enum('pricing_type', ['fixed', 'per_kilo'])->default('fixed')->after('price');
            $table->decimal('price_per_kilo', 10, 2)->nullable()->after('pricing_type');
            // Smallest sellable portion, and the increment the scale entry
            // snaps to — both in grams.
            $table->unsignedInteger('min_weight_grams')->default(250)->after('price_per_kilo');
            $table->unsignedInteger('weight_step_grams')->default(10)->after('min_weight_grams');
            // Whether the container/ice weight may be deducted before pricing.
            $table->boolean('allow_tare')->default(false)->after('weight_step_grams');
            // Counter-only items are weighed and added by staff at the
            // counter, never self-ordered from a customer QR menu.
            $table->boolean('counter_only')->default(false)->after('allow_tare');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn([
                'pricing_type',
                'price_per_kilo',
                'min_weight_grams',
                'weight_step_grams',
                'allow_tare',
                'counter_only',
            ]);
        });
    }
};
