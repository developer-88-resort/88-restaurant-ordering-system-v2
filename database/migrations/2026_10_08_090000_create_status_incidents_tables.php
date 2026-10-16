<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status page (/status, and the maintenance page), like status.claude.com.
 * An incident opens when Maintenance Mode is switched on and resolves when it
 * is switched off; the Superadmin posts timeline updates to it, and the
 * 90-day uptime bars are coloured from these incidents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('impact', 20);
            $table->json('components');
            // dateTime, not timestamp: on MariaDB (XAMPP) the first NOT NULL
            // timestamp silently gets ON UPDATE CURRENT_TIMESTAMP, so resolving
            // an incident rewrote when it started.
            $table->dateTime('started_at');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['started_at', 'resolved_at']);
        });

        Schema::create('status_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_incident_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('message');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_incident_updates');
        Schema::dropIfExists('status_incidents');
    }
};
