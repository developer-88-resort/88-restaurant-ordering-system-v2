<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Room Charge is paid off at the front desk through some other method —
 * cash, GCash, QR... This records which, so the Room Charges report can
 * say how each one was (or will be) settled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('settled_via', 30)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('settled_via');
        });
    }
};
