<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per hosted-checkout attempt (Maya Checkout). The bill is only
     * finalized — invoice snapshot + order_payments row — once the provider
     * confirms the payment; until then the attempt waits here with the
     * discounts and buyer details the cashier picked, so they can be applied
     * exactly as chosen when the money lands. Never holds card data or a
     * manager's password: an approval is checked up front and kept as the
     * approver's id.
     */
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->uuid('request_reference_number')->unique();
            $table->string('checkout_id')->nullable()->unique();
            $table->text('redirect_url')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->string('provider_status')->nullable();
            $table->json('checkout_data');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('initiated_by')->constrained('users');
            $table->text('error_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
