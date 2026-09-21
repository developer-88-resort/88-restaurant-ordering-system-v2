<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order is now one kitchen SLIP, and a table's dining session
     * (space_sessions) is the tab that groups them: every new submission for
     * an occupied table opens "Cottage 3 — Slip #2" instead of adding lines
     * to Slip #1. The number counts within the tab, so it only means
     * something next to space_session_id — hence the pair is unique.
     *
     * Orders already attached to a session are numbered in the order they
     * were created, so existing tables read "Slip #1, #2, ..." straight
     * away. Orders with no session (take-out, older staff orders) keep a
     * null number and display exactly as before.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('slip_number')->nullable()->after('space_session_id');
        });

        $counters = [];

        DB::table('orders')
            ->whereNotNull('space_session_id')
            ->orderBy('space_session_id')
            ->orderBy('id')
            ->select(['id', 'space_session_id'])
            ->lazy()
            ->each(function ($order) use (&$counters) {
                $counters[$order->space_session_id] = ($counters[$order->space_session_id] ?? 0) + 1;

                DB::table('orders')->where('id', $order->id)->update(['slip_number' => $counters[$order->space_session_id]]);
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['space_session_id', 'slip_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['space_session_id', 'slip_number']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('slip_number');
        });
    }
};
