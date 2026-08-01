<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a weighed item is cooked (Inihaw, Sinigang, …). Most styles are free;
 * a non-zero `surcharge` is charged per piece on the order line. Styles are
 * offered per menu item through the pivot — a fish may allow Inihaw and
 * Sinigang while a crab allows Buttered and Chilli Garlic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooking_styles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Korean label — the resort serves a large Korean clientele and
            // the customer menu is already bilingual (see lang/ko.json).
            $table->string('name_ko')->nullable();
            $table->decimal('surcharge', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cooking_style_menu_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooking_style_id')->constrained('cooking_styles')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->unique(['cooking_style_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooking_style_menu_item');
        Schema::dropIfExists('cooking_styles');
    }
};
