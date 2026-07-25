<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately does NOT touch status_changed_at, even though it was
     * added in the same original migration as these floor-plan fields —
     * status_changed_at is stamped on every real status change app-wide
     * (Space::setStatusWithSharedTables()) and has nothing to do with the
     * floor-plan canvas being removed here.
     */
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y', 'shape', 'width', 'height', 'rotation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->decimal('position_x', 8, 2)->nullable();
            $table->decimal('position_y', 8, 2)->nullable();
            $table->string('shape')->default('rectangle');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('rotation')->default(0);
        });
    }
};
