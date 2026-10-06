<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Massage services get what a menu item has on New Menu Item: photos (one
 * of them primary) and a sort order for the list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('massage_services', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_available');
        });

        Schema::create('massage_service_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('massage_service_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('massage_service_images');

        Schema::table('massage_services', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
