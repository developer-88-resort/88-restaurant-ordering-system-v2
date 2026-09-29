<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting a space used to be a hard delete, and orders.space_id is
     * nullOnDelete — so every past order of that table lost which table it
     * was. Spaces are archived instead: gone from the floor and every picker,
     * still named on the orders that were rung up on them.
     */
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
