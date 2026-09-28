<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which room or guest a Room Charge went on. It had been kept in
 * `reference`, but a room charge paid through GCash or a card needs that
 * field for the GCash / card receipt's own Reference No.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('charged_to', 100)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('charged_to');
        });
    }
};
