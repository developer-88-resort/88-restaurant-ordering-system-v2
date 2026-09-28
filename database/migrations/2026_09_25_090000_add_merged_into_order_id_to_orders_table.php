<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a slip went when staff moved it onto another table's existing
     * slip (see OrderSlipTransferrer). The emptied slip is kept rather than
     * deleted — its order number was already printed on a kitchen slip and
     * the audit trail has to keep pointing somewhere real — so this column
     * is what tells Reports the row is a shell that must not be counted a
     * second time alongside the slip that absorbed it.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('merged_into_order_id')
                ->nullable()
                ->after('space_session_id')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_order_id');
        });
    }
};
