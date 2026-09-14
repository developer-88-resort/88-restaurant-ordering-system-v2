<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivots for "Applicable Items" (All / Selected Categories / Selected Menu
 * Items) — plain belongsToMany tables, no dedicated models, mirroring the
 * cooking_style_menu_item pivot shape elsewhere in this app. Referencing
 * the real menu_categories/menu_items rows here (rather than duplicating
 * their name/price) is the whole point — a promotion's applicable items
 * always reflect current Menu Management data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->unique(['promotion_id', 'menu_category_id']);
        });

        Schema::create('promotion_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->unique(['promotion_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_menu_items');
        Schema::dropIfExists('promotion_menu_categories');
    }
};
