<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weighed order lines. Everything needed to reprice or audit the line is
 * frozen here at weigh time — most importantly `price_per_kilo_snapshot`,
 * so tomorrow's market price can never silently reprice today's order.
 *
 * Weights are integer grams. Money is decimal(10,2). The line's charge is
 * always computed by App\Support\WeighedLinePricer, never inline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('line_type', ['fixed', 'weighed'])->default('fixed')->after('menu_item_variant_id');

            // Gross reading from the scale, and the container/ice weight
            // deducted from it. net = weight_grams − tare_grams.
            $table->unsignedInteger('weight_grams')->nullable()->after('unit_price');
            $table->unsignedInteger('tare_grams')->default(0)->after('weight_grams');
            // How many physical pieces make up this weighed line — the
            // cooking surcharge is charged per piece.
            $table->unsignedInteger('pieces')->nullable()->after('tare_grams');
            $table->decimal('price_per_kilo_snapshot', 10, 2)->nullable()->after('pieces');

            $table->foreignId('cooking_style_id')->nullable()->after('price_per_kilo_snapshot')
                ->constrained('cooking_styles')->nullOnDelete();
            $table->text('cooking_note')->nullable()->after('cooking_style_id');

            $table->foreignId('weighed_by_user_id')->nullable()->after('cooking_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('weighed_at')->nullable()->after('weighed_by_user_id');

            // A manual price correction always carries a reason and an owner.
            $table->string('price_override_reason')->nullable()->after('weighed_at');
            $table->foreignId('price_overridden_by_user_id')->nullable()->after('price_override_reason')
                ->constrained('users')->nullOnDelete();

            // Which guest at the table asked for this line (multi-guest QR
            // sessions already model guests as rows, so this is a real FK).
            $table->foreignId('ordered_by_guest_id')->nullable()->after('price_overridden_by_user_id')
                ->constrained('guest_sessions')->nullOnDelete();

            // Weighed lines the customer must accept before the kitchen
            // proceeds; fixed lines are 'confirmed' from the start.
            $table->enum('confirmation_status', ['pending_customer', 'confirmed', 'rejected'])
                ->default('confirmed')->after('ordered_by_guest_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cooking_style_id');
            $table->dropConstrainedForeignId('weighed_by_user_id');
            $table->dropConstrainedForeignId('price_overridden_by_user_id');
            $table->dropConstrainedForeignId('ordered_by_guest_id');
            $table->dropColumn([
                'line_type',
                'weight_grams',
                'tare_grams',
                'pieces',
                'price_per_kilo_snapshot',
                'cooking_note',
                'weighed_at',
                'price_override_reason',
                'confirmation_status',
            ]);
        });
    }
};
