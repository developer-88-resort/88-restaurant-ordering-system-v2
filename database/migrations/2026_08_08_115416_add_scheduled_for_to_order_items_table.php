<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receipt can now hold a mix of live and advance-order lines — an
 * already-open bill can have a same-day batch appended that's meant to be
 * cooked later. Order-level "when" (via Quotation::scheduled_for through
 * Order::sourceQuotation) can't represent that mix once more than one
 * quotation/batch lands on the same order, so each line carries its own
 * reserved time directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('scheduled_for');
        });
    }
};
