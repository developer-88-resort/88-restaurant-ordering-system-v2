<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Massage department's own services, orders and payments — kept apart
 * from the restaurant's menu_items/orders so a massage service can never
 * show up on a restaurant menu, the Kitchen or a table's QR menu. Payments
 * carry the same columns as order_payments so Reports can tally them the
 * same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('massage_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('massage_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->string('room_number', 50)->nullable();
            $table->string('guest_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('massage_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('massage_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('massage_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });

        Schema::create('massage_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('massage_order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method');
            $table->string('settled_via')->nullable();
            $table->string('charged_to')->nullable();
            $table->string('status')->default('recorded');
            $table->decimal('amount', 10, 2);
            $table->decimal('tendered_amount', 10, 2)->nullable();
            $table->decimal('change_amount', 10, 2)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('reference')->nullable();
            $table->string('approval_code')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('massage_payments');
        Schema::dropIfExists('massage_order_items');
        Schema::dropIfExists('massage_orders');
        Schema::dropIfExists('massage_services');
    }
};
