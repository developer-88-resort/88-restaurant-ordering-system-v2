<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cooking styles are now curated per menu category instead of per item —
 * every per-kilo item in a category shares that category's styles
 * automatically (see MenuItemController::syncCookingStyles()). The old
 * `cooking_style_menu_item` pivot stays and is still what the weigh
 * station/receipts/validation read from; it's just cascade-synced from
 * this table now instead of hand-picked per item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooking_style_menu_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_style_id')->constrained('cooking_styles')->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->unique(['cooking_style_id', 'menu_category_id'], 'csmc_style_category_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooking_style_menu_category');
    }
};
