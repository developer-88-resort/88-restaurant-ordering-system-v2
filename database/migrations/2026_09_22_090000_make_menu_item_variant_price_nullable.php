<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A variant may now have no price: the printed menu lists some options as
 * "----" (e.g. a liquor sold per bottle but not per shot). Such a variant is
 * kept on the item for Menu Management, but MenuItem::variants() leaves it
 * out, so no order screen can sell it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_variants', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not zero: a price-less variant restored as ₱0 would become orderable
        // for free. Archive it instead so it drops off every order screen.
        DB::table('menu_item_variants')->whereNull('price')->update([
            'price' => 0,
            'is_default' => false,
            'deleted_at' => now(),
        ]);

        Schema::table('menu_item_variants', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable(false)->change();
        });
    }
};
