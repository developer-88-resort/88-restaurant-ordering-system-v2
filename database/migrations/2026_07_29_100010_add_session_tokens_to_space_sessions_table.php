<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrades space_sessions to also serve as the QR "table dining
     * session": a shareable child-QR token, who opened/closed it, and an
     * optional hard expiry. Existing pooled-category sessions keep working
     * untouched — these columns are all nullable.
     */
    public function up(): void
    {
        Schema::table('space_sessions', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('status');
            $table->foreignId('opened_by')->nullable()->after('public_token')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('opened_by')->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('space_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opened_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['public_token', 'expires_at']);
        });
    }
};
