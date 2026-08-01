<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financial reversal of part or all of one order item ("void served
     * meal"). The original order_items row is never touched — receipts show
     * the original line plus a CANCELLED reversal line built from here.
     * `inventory_restored` is recorded for completeness even though no
     * inventory module exists yet; it defaults to false per the business
     * rule that served/contaminated food never goes back to stock.
     */
    public function up(): void
    {
        Schema::create('order_item_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('reversed_amount', 10, 2);
            $table->string('reason_code');
            $table->text('notes')->nullable();
            $table->boolean('inventory_restored')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_adjustments');
    }
};
