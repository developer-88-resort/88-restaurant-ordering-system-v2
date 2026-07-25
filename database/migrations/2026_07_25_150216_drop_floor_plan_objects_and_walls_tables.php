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
        Schema::dropIfExists('floor_plan_objects');
        Schema::dropIfExists('floor_plan_walls');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('floor_plan_walls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->decimal('x1', 8, 2);
            $table->decimal('y1', 8, 2);
            $table->decimal('x2', 8, 2);
            $table->decimal('y2', 8, 2);
            $table->unsignedSmallInteger('thickness')->default(14);
            $table->timestamps();
        });

        Schema::create('floor_plan_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('object_type');
            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }
};
