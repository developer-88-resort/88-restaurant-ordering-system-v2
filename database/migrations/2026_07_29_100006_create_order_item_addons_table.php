<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weight-based add-on riding on a fixed-price order line (e.g. extra
     * fish by the gram on a set dish). Kept as its own row — never folded
     * into the base line's price — so the receipt can show
     * "Fish Add-on: 350g @ ₱600/kg" separately, per the business rule.
     */
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_addons');
    }
};
