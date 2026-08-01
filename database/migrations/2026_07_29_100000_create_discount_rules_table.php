<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable discount catalog — replaces the previously hardcoded
     * Senior/PWD/Promo trio as the source of what a cashier can pick at
     * checkout. `statutory_type` links a rule back to the existing
     * DiscountType enum when it must keep the BIR Senior/PWD VAT-exemption
     * treatment; every other rule is plain percent/fixed off.
     */
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('calculation_mode')->default('percent');
            // Preset value (e.g. 20.00 for the general 20% rule). Null +
            // is_custom_value=true means the cashier types the value.
            $table->decimal('value', 10, 2)->nullable();
            $table->boolean('is_custom_value')->default(false);
            $table->string('statutory_type')->nullable();
            $table->string('scope')->default('whole_bill');
            $table->boolean('is_stackable')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('min_bill_amount', 10, 2)->nullable();
            $table->boolean('requires_customer_id')->default(false);
            $table->boolean('requires_reason')->default(false);
            $table->boolean('requires_manager_approval')->default(false);
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
