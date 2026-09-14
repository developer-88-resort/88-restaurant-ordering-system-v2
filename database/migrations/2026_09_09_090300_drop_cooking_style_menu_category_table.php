<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes yesterday's one-day-old per-category cooking-style pivot, now
 * that its data has been carried forward into cooking_style_sets by the
 * companion backfill migration in this same batch. Category is back to a
 * pure customer-facing grouping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cooking_style_menu_category');
    }

    public function down(): void
    {
        // Recreates the empty table structure only — its data was already
        // carried forward into cooking_style_sets by the backfill migration
        // and is not restorable here.
        Schema::create('cooking_style_menu_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_style_id')->constrained('cooking_styles')->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->unique(['cooking_style_id', 'menu_category_id'], 'csmc_style_category_unique');
            $table->timestamps();
        });
    }
};
