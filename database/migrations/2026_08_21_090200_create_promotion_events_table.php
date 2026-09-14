<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * View/click tracking, wired for future use. Nothing in this app yet
 * records into this table — see App\Support\PromotionEventRecorder — since
 * there is no customer-facing banner display surface today. Views/Clicks
 * on the Promotions page legitimately read 0 until that surface exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20);
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_events');
    }
};
