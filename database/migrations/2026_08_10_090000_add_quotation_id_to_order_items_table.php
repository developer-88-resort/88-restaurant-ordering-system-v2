<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * scheduled_for already lives per line (see the migration that added it),
 * but there was no way to trace a specific line back to which quotation
 * asked for it — needed so a receipt can label each advance-ordered item
 * individually (its own quotation number) instead of one order-level
 * block that's wrong the moment an order mixes advance and walk-in lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('scheduled_for')->constrained()->nullOnDelete();
        });

        // Backfill lines created before this column existed. Each
        // quotation's converted_order_id points at the order its batch
        // landed on — good enough for existing data, where every order
        // has at most one quotation attached so far.
        DB::table('quotations')->whereNotNull('converted_order_id')->orderBy('id')->cursor()->each(function ($quotation) {
            DB::table('order_items')
                ->where('order_id', $quotation->converted_order_id)
                ->whereNotNull('scheduled_for')
                ->whereNull('quotation_id')
                ->update(['quotation_id' => $quotation->id]);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
        });
    }
};
