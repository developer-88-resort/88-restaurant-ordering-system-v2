<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A foreign card (e.g. Korean) pays a surcharge on top of what it covers
     * of the bill — 12% for now (settings.foreign_card_surcharge_percent). The
     * bill itself doesn't change: the payment's `amount` is still what it
     * covers; the surcharge is kept beside it, so the card terminal total
     * (amount + surcharge) tallies while sales stay as they were.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('foreign_card_surcharge_percent', 5, 2)->default(12.00);
        });

        foreach (['order_payments', 'massage_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('foreign_card')->default(false)->after('card_brand');
                $table->decimal('surcharge_percent', 5, 2)->nullable()->after('foreign_card');
                $table->decimal('surcharge_amount', 10, 2)->default(0)->after('surcharge_percent');
            });
        }
    }

    public function down(): void
    {
        foreach (['order_payments', 'massage_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['foreign_card', 'surcharge_percent', 'surcharge_amount']);
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('foreign_card_surcharge_percent');
        });
    }
};
