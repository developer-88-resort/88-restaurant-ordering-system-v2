<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One anonymous guest device inside one table dining session (several
     * guests can order independently at the same table). The public token
     * is the guest's only credential — random, unguessable, stored in the
     * guest's browser cookie so reopening the QR resumes the same session.
     */
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_session_id')->constrained('space_sessions')->cascadeOnDelete();
            $table->string('public_token', 64)->unique();
            $table->unsignedInteger('guest_number');
            $table->string('display_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};
