<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 addendum: where the keyed amount actually came from.
 *
 * Most counter scales show both the weight and the amount, and staff type
 * what they see — 'typed'. Some scales show only the weight, so the
 * wizard offers "Use expected (₱177.00)" as a shortcut that fills the
 * amount from the reference-rate computation instead — 'computed'. The
 * two look identical in amount_charged, but they are not the same claim:
 * a typed figure is an independent reading confirming the rate is right,
 * a computed one is the reference rate simply echoed back. An auditor
 * asking "did anyone actually confirm this fish's price?" needs to be
 * able to tell them apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_weighings', function (Blueprint $table) {
            $table->enum('amount_source', ['typed', 'computed'])->default('typed')->after('amount_charged');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_weighings', function (Blueprint $table) {
            $table->dropColumn('amount_source');
        });
    }
};
