<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Stamped at creation time if the recipient was already online
            // (see ChatController::isUserOnline()); otherwise it's filled in
            // together with read_at the moment they open the thread — a
            // message can't be "seen" without having been "delivered" first.
            $table->timestamp('delivered_at')->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
