<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Idempotency key for the one-shot create screen — a retried
            // double-click carries the same value, so `store()` can return
            // the quotation it already made instead of making a second one.
            $table->string('request_id')->nullable()->unique()->after('quotation_number');
        });

        // The old Sent/Accepted/Confirmed states have no equivalent in the
        // new 3-state model (Draft/Added/Cancelled) — anything still
        // sitting in one of them never made it to a real order, so it
        // becomes Cancelled with a note explaining why, rather than being
        // silently reinterpreted as Draft or Added. Built in PHP rather than
        // DB::raw('CONCAT(...)') — SQLite (the test suite's driver) doesn't
        // support CONCAT the way MySQL does.
        DB::table('quotations')
            ->whereIn('status', ['sent', 'accepted', 'confirmed'])
            ->get(['id', 'notes'])
            ->each(function ($quotation) {
                DB::table('quotations')->where('id', $quotation->id)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'notes' => trim('[Migrated: legacy lifecycle removed — recreate under the new flow] '.($quotation->notes ?? '')),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('request_id');
        });
    }
};
