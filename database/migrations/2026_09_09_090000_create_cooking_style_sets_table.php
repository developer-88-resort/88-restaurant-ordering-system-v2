<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cooking styles are curated as reusable, named sets (e.g. "Seafood",
 * "Meat") owned entirely by Weigh & Order — not by menu categories (that
 * lasted one day; see 2026_09_08_090000_create_cooking_style_menu_category_table.php,
 * dropped in a companion migration in this same batch) and not per item
 * either, except as an explicit override (the existing
 * cooking_style_menu_item pivot, reused for that purpose — see
 * MenuItem::resolvedCookingStyles()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooking_style_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cooking_style_set_style', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_style_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cooking_style_id')->constrained('cooking_styles')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['cooking_style_set_id', 'cooking_style_id'], 'css_set_style_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooking_style_set_style');
        Schema::dropIfExists('cooking_style_sets');
    }
};
