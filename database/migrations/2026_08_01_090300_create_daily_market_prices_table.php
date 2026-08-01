<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Market ("suki") price of a per-kilo item for one trading day. Fish and
 * meat rates move daily, so the sellable rate is set each morning rather
 * than edited on the menu item itself — this table is the audit trail of
 * who set which rate on which day. One rate per item per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->decimal('price_per_kilo', 10, 2);
            $table->date('effective_date');
            $table->foreignId('set_by_user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['menu_item_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_market_prices');
    }
};
