<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which channel actually started this order — 'staff' (New Order, the
     * weigh station, or a converted quotation) or 'qr' (a customer's own
     * table/lobby QR self-ordering). `created_by` already records WHO for
     * the staff case, but nothing previously recorded WHICH CASE it was,
     * so a QR order and a staff order were indistinguishable once
     * `created_by` was null for both (or, for a bug fixed alongside this
     * migration, sometimes wrongly non-null for a QR order too — see
     * CustomerOrderController/CustomerWelcomeController). Nullable so
     * existing orders are left exactly as they are; Order::isStaffCreated()
     * infers a sensible source for those from `guest_session_id` instead.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_source')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_source');
        });
    }
};
