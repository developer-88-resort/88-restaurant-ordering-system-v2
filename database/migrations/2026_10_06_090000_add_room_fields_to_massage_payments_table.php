<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Massage takes payment through the same payment rows and validation as
     * the restaurant, so its Room Charge now picks a room from `rooms` too.
     * Same snapshot columns as order_payments: all nullable and additive —
     * older massage room charges keep their free-text `charged_to`.
     */
    public function up(): void
    {
        Schema::table('massage_payments', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('charged_to')->constrained('rooms')->nullOnDelete();
            $table->string('room_no', 20)->nullable()->after('room_id');
            $table->string('room_type_code', 10)->nullable()->after('room_no');
            $table->string('guest_name', 100)->nullable()->after('room_type_code');
            $table->string('guest_ref', 50)->nullable()->after('guest_name');

            $table->index(['room_no', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('massage_payments', function (Blueprint $table) {
            $table->dropIndex(['room_no', 'received_at']);
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn(['room_no', 'room_type_code', 'guest_name', 'guest_ref']);
        });
    }
};
