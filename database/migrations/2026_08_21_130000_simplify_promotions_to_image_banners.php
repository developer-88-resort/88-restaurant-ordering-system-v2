<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions became pure-image banners — a banner is now just an image,
 * an optional link, a schedule, and a publish/disable toggle. This drops
 * the applicable-items pivots (confirmed empty — every existing promotion
 * has applicable_scope='all') and relaxes title/type to nullable so new
 * banners can be created without them. Content-bearing columns that still
 * hold real data on existing rows (description, discount config, banner
 * text, etc.) are deliberately left in place, just unused going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->string('type', 30)->nullable()->change();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('applicable_scope');
        });

        Schema::dropIfExists('promotion_menu_items');
        Schema::dropIfExists('promotion_menu_categories');
    }

    public function down(): void
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

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('applicable_scope', 20)->default('all');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->index(['type']);
            $table->string('type', 30)->nullable(false)->change();
            $table->string('title')->nullable(false)->change();
        });
    }
};
