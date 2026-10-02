<?php

use Database\Seeders\RoomSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rooms a Room Charge can go on. Room Charge used to take a free-text
     * "Room No. / Guest name", which came out as "RM 511", "rm512", "RM.512",
     * "ROOM 203-204" or a name alone — impossible to tally per room against
     * the front desk's postings. A charge now picks one of these instead.
     *
     * room_no stays a string ("101") — it's a label, not a number. Rooms are
     * deactivated, never deleted: past payments point at them.
     *
     * Seeded with the 57 rooms copied from WinCloud (RoomSeeder) so a deploy
     * has them straight away.
     */
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_no', 20)->unique();
            $table->foreignId('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });

        (new RoomSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
    }
};
