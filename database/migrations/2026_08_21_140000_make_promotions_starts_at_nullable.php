<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Start date is now optional — a banner with no starts_at is simply live
 * as soon as it's published (see Promotion::status()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable(false)->change();
        });
    }
};
