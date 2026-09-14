<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('menu_item_add_on_id')->nullable()->after('menu_item_variant_id')
                ->constrained('menu_item_add_ons')->nullOnDelete();
            $table->foreignId('parent_order_item_id')->nullable()->after('menu_item_add_on_id')
                ->constrained('order_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_order_item_id');
            $table->dropConstrainedForeignId('menu_item_add_on_id');
        });
    }
};
