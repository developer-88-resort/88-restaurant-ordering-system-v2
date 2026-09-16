<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many guests the order is for, shown on the kitchen slip so the
     * line knows what it's plating for. Nullable and only collected for
     * dine-in: a take-out order has no seated party, and a waiter taking a
     * quick order shouldn't be blocked by a count they don't have yet.
     * Existing orders keep a null, which the slip simply omits.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('pax')->nullable()->after('order_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pax');
        });
    }
};
