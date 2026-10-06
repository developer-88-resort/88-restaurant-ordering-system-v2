<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Massage services get what a menu item has: variants (e.g. 30 mins / 1 hr /
 * 1 hr 30 mins, each with its own price and length) and add-ons (e.g. Hot
 * Stone). An order line keeps copies of the variant and add-ons it was sold
 * with, so editing the service later never changes a past order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('massage_service_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('massage_service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('massage_service_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('massage_service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('massage_order_items', function (Blueprint $table) {
            $table->foreignId('massage_service_variant_id')->nullable()->after('massage_service_id')->constrained()->nullOnDelete();
            $table->string('variant_name', 100)->nullable()->after('name');
        });

        Schema::create('massage_order_item_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('massage_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('massage_service_add_on_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('massage_order_item_add_ons');

        Schema::table('massage_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('massage_service_variant_id');
            $table->dropColumn('variant_name');
        });

        Schema::dropIfExists('massage_service_add_ons');
        Schema::dropIfExists('massage_service_variants');
    }
};
