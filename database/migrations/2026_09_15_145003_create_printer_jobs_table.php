<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable queue for the thermal kitchen printer bridge (see
     * App\Services\Printing\ThermalPrinterService / printer:bridge
     * command). Production runs on a Hostinger VPS with no route to the
     * printer's LAN IP, so a job sits here — rather than firing as a
     * live Reverb broadcast — until a local bridge process on the
     * resort's own network polls for it, prints it, and acks it. A job
     * created while the bridge is offline just waits; nothing is lost.
     */
    public function up(): void
    {
        Schema::create('printer_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_jobs');
    }
};
