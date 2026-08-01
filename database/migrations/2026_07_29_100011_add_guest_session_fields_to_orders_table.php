<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('guest_session_id')->nullable()->after('space_session_id')->constrained('guest_sessions')->nullOnDelete();
            // Sequential per table-session ("Order Batch 2") — assigned at
            // submit time inside the same transaction as the order insert.
            $table->unsignedInteger('batch_number')->nullable()->after('guest_session_id');
            // Client-generated key that makes double-tap/duplicate submits
            // return the already-created order instead of a second one.
            $table->string('idempotency_key', 64)->nullable()->unique()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_session_id');
            $table->dropColumn(['batch_number', 'idempotency_key']);
        });
    }
};
