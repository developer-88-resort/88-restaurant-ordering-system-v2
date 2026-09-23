<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The discounts chosen on the Kitchen Display for an order's printed slip
 * (Senior, PWD, a custom percentage). They only change what the slip shows —
 * checkout picks its own discounts for the receipt and never reads these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('slip_discounts')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('slip_discounts');
        });
    }
};
