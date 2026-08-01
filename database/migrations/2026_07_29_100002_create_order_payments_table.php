<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment entry (split payments = several rows per order).
     * Card rows only ever hold masked terminal-slip details (brand + last
     * four + reference numbers) — the full PAN/CVV is never accepted by
     * any request in the first place. Voiding one entry stamps it rather
     * than deleting, so the other entries and the audit trail survive.
     */
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_invoice_snapshot_id')->nullable()->constrained('order_invoice_snapshots')->nullOnDelete();
            $table->string('payment_method');
            $table->string('status')->default('recorded');
            $table->decimal('amount', 10, 2);
            $table->decimal('tendered_amount', 10, 2)->nullable();
            $table->decimal('change_amount', 10, 2)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('terminal_reference')->nullable()->index();
            $table->string('approval_code')->nullable();
            $table->string('terminal_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
