<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('pricing_type')->default('fixed')->after('price');
            $table->decimal('price_per_kg', 10, 2)->nullable()->after('pricing_type');
            // Null = fall back to the system-wide default (250 g).
            $table->unsignedInteger('min_weight_grams')->nullable()->after('price_per_kg');
            $table->json('cooking_methods')->nullable()->after('min_weight_grams');
            // Cooking is free by default; a non-zero fee here is the only
            // way a cooking method ever adds a charge.
            $table->decimal('cooking_fee', 10, 2)->default(0)->after('cooking_methods');
            $table->string('weight_evidence_policy')->default('optional')->after('cooking_fee');
            $table->boolean('allows_weight_addons')->default(false)->after('weight_evidence_policy');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn([
                'pricing_type',
                'price_per_kg',
                'min_weight_grams',
                'cooking_methods',
                'cooking_fee',
                'weight_evidence_policy',
                'allows_weight_addons',
            ]);
        });
    }
};
