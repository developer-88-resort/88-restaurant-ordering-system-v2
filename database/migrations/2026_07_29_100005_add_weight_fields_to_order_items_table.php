<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weight-based ordering snapshots on the order line itself, mirroring
     * how item_name/unit_price already freeze the fixed-price case: the
     * per-kg rate is copied at order time so later menu price changes never
     * reprice an existing order. Weights are integer grams throughout.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('pricing_type')->default('fixed')->after('menu_item_variant_id');
            $table->decimal('price_per_kg_snapshot', 10, 2)->nullable()->after('unit_price');
            $table->unsignedInteger('requested_weight_grams')->nullable()->after('price_per_kg_snapshot');
            $table->unsignedInteger('confirmed_weight_grams')->nullable()->after('requested_weight_grams');
            $table->foreignId('weight_confirmed_by')->nullable()->after('confirmed_weight_grams')->constrained('users')->nullOnDelete();
            $table->timestamp('weight_confirmed_at')->nullable()->after('weight_confirmed_by');
            $table->string('weight_adjustment_reason')->nullable()->after('weight_confirmed_at');
            $table->string('cooking_method')->nullable()->after('weight_adjustment_reason');
            $table->decimal('cooking_fee', 10, 2)->default(0)->after('cooking_method');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weight_confirmed_by');
            $table->dropColumn([
                'pricing_type',
                'price_per_kg_snapshot',
                'requested_weight_grams',
                'confirmed_weight_grams',
                'weight_confirmed_at',
                'weight_adjustment_reason',
                'cooking_method',
                'cooking_fee',
            ]);
        });
    }
};
