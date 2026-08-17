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
            // Which round/submission this line arrived in, scoped to its
            // own order — batching moves here from orders.batch_number so
            // one order can keep accumulating rounds from any channel
            // (QR, Weigh, Quotation) instead of each round being a whole
            // new order row.
            $table->unsignedInteger('batch_number')->nullable()->after('ordered_by_guest_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('batch_number');
        });
    }
};
