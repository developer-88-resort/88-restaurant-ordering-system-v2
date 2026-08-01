<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a weighed line has to be accepted by the customer before the
 * kitchen proceeds. Off by default: at the counter the customer is
 * standing right there watching the scale, so the extra confirmation step
 * is opt-in rather than something every resort has to switch off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('weigh_customer_confirmation_enabled')->default(false)->after('service_charge_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('weigh_customer_confirmation_enabled');
        });
    }
};
