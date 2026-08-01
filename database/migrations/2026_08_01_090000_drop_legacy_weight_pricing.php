<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the first weight-pricing attempt (per-kg columns, JSON cooking
 * methods, weight add-ons) so the per-kilo foundation that follows starts
 * from one unambiguous shape instead of two competing ones. Safe to run:
 * the dropped columns/tables never held a single row.
 *
 * media_evidence is deliberately NOT dropped — it is polymorphic and still
 * carries payment proof for order_payments.
 */
return new class extends Migration
{
    public function up(): void
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

        Schema::dropIfExists('order_item_addons');

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('default_min_weight_grams');
        });
    }

    /**
     * Restores the legacy shape exactly as the 2026_07_29 migrations left
     * it, so a rollback past this point lands on a consistent schema.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('pricing_type')->default('fixed')->after('price');
            $table->decimal('price_per_kg', 10, 2)->nullable()->after('pricing_type');
            $table->unsignedInteger('min_weight_grams')->nullable()->after('price_per_kg');
            $table->json('cooking_methods')->nullable()->after('min_weight_grams');
            $table->decimal('cooking_fee', 10, 2)->default(0)->after('cooking_methods');
            $table->string('weight_evidence_policy')->default('optional')->after('cooking_fee');
            $table->boolean('allows_weight_addons')->default(false)->after('weight_evidence_policy');
        });

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

        Schema::create('order_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('name');
            $table->decimal('price_per_kg_snapshot', 10, 2);
            $table->unsignedInteger('requested_weight_grams')->nullable();
            $table->unsignedInteger('confirmed_weight_grams')->nullable();
            $table->foreignId('weight_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('weight_confirmed_at')->nullable();
            $table->string('cooking_method')->nullable();
            $table->decimal('calculated_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('default_min_weight_grams')->default(250);
        });
    }
};
