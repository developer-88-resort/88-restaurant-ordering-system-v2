<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which screen a cancellation came from. The Kitchen Display can now
     * cancel lines too, and "the kitchen took it off" versus "the cashier
     * took it off" is the first thing anyone asks when a bill moves. Every
     * row that already exists came from Order Management — the only place
     * a cancel could be made before this column.
     */
    public function up(): void
    {
        Schema::table('order_item_adjustments', function (Blueprint $table) {
            $table->string('source', 32)->default('order_management')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_adjustments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
