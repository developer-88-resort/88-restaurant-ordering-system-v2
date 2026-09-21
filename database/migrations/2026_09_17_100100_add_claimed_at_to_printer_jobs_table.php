<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every new slip now queues its own kitchen print job, so the queue has
     * to hand each job to exactly one bridge. Handing a job out moves it to
     * 'printing' and stamps claimed_at; a claim nobody acknowledged within
     * the timeout is offered again (see Api\PrinterJobController).
     */
    public function up(): void
    {
        Schema::table('printer_jobs', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        // Without a claim time a 'printing' job could never be offered
        // again, so put any in flight back in the queue first.
        DB::table('printer_jobs')->where('status', 'printing')->update(['status' => 'pending']);

        Schema::table('printer_jobs', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });
    }
};
