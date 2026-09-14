<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a per-kilo item sits within the customer-facing "By the Kilo"
 * section — separate from the item's normal `sort_order` (which only
 * matters within its own menu category and is meaningless once the item
 * is pulled out into this pinned, cross-category section).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('weighed_sort_order')->nullable()->after('cooking_style_set_id');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('weighed_sort_order');
        });
    }
};
