<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two small changes that belong together, both about legacy weighed lines.
 *
 * `flagged_for_review` is how weigh:audit-legacy-lines marks a line a
 * manager still has to look at — a per-kilo line with no weighing record,
 * or one billed at ₱0.00. Flagging is deliberately not deleting: those
 * rows are on real bills.
 *
 * `tare_grams` is deprecated for the same reason weight_step_grams was.
 * The scale's own TARE button produces the net figure, so the app has no
 * business subtracting a second time. The column stays because existing
 * order lines carry real tare values and rewriting them would falsify
 * history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('flagged_for_review')->default(false)->after('confirmation_status');
            $table->index('flagged_for_review');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE order_items MODIFY COLUMN tare_grams INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'DEPRECATED 2026-08-03: the scale hardware TARE button produces the net weight; new lines always write 0. Historical values are real — do not rewrite.'");
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['flagged_for_review']);
            $table->dropColumn('flagged_for_review');
        });
    }
};
