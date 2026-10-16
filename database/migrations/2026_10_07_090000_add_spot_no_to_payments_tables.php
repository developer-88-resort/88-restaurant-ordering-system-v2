<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Locker Charge and Kubo Charge go on a numbered locker (1–50) or kubo
     * (1–20) the way a Room Charge goes on a room: which one is kept here,
     * the method says which kind. Nullable and additive — every older
     * payment simply has none.
     */
    public function up(): void
    {
        foreach (['order_payments', 'massage_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedSmallInteger('spot_no')->nullable()->after('room_type_code');
            });
        }
    }

    public function down(): void
    {
        foreach (['order_payments', 'massage_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('spot_no');
            });
        }
    }
};
