<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per discount line applied on one invoice snapshot — extends
     * the snapshot's existing single-discount columns (kept for backward
     * compatibility) to support several stacked discounts, each shown as
     * its own line on the receipt. Everything needed to re-render the line
     * is frozen here (rule name/mode/value), same immutable-snapshot
     * philosophy as the parent OrderInvoiceSnapshot.
     */
    public function up(): void
    {
        Schema::create('order_invoice_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_invoice_snapshot_id')->constrained('order_invoice_snapshots')->cascadeOnDelete();
            $table->foreignId('discount_rule_id')->nullable()->constrained('discount_rules')->nullOnDelete();
            $table->string('rule_name');
            $table->string('rule_code')->nullable();
            $table->string('calculation_mode');
            $table->decimal('entered_value', 10, 2)->nullable();
            $table->string('statutory_type')->nullable();
            $table->decimal('eligible_amount', 10, 2)->default(0);
            $table->decimal('calculated_amount', 10, 2)->default(0);
            $table->decimal('vat_exemption_amount', 10, 2)->default(0);
            $table->string('qualified_name')->nullable();
            $table->string('id_number')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_invoice_discounts');
    }
};
