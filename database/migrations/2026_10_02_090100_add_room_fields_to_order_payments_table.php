<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which room a Room Charge went on, picked from `rooms` instead of typed.
     * All nullable and additive: every older room charge keeps its free-text
     * `charged_to` as it was and reads as "legacy".
     *
     * room_no and room_type_code are snapshots taken at checkout, so a receipt
     * or report keeps saying "511 PH" even if the room is later re-typed or
     * switched off. `charged_to` is still written ("RM 511 PH — <guest>") for
     * anything that reads only that.
     *
     * Plus the receipt setting for printing the room charge authorization a
     * second time as the front desk's copy.
     */
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('charged_to')->constrained('rooms')->nullOnDelete();
            $table->string('room_no', 20)->nullable()->after('room_id');
            $table->string('room_type_code', 10)->nullable()->after('room_no');
            $table->string('guest_name', 100)->nullable()->after('room_type_code');
            $table->string('guest_ref', 50)->nullable()->after('guest_name');

            $table->index(['room_no', 'received_at']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('room_charge_front_desk_copy')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropIndex(['room_no', 'received_at']);
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn(['room_no', 'room_type_code', 'guest_name', 'guest_ref']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('room_charge_front_desk_copy');
        });
    }
};
