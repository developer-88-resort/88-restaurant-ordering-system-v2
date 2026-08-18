<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which append requests have already been served.
 *
 * The counter tablet is touched with wet hands and lives on resort WiFi,
 * so "Add to Order" gets double-tapped and requests get retried after a
 * timeout that actually succeeded. Either one silently bills the customer
 * for two fish. The client sends a UUID per intended append; the first
 * request stores it with the line it produced, and any repeat returns that
 * same line instead of creating another.
 *
 * Rows expire after 24 hours — long enough to cover any realistic retry,
 * short enough that the table stays small.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('append_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('append_idempotency_keys');
    }
};
