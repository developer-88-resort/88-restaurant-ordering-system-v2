<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Name + PIN sign-in for Staff and Admin (Superadmin keeps email + password).
 *
 * - pin_hash:       bcrypt of the PIN — what sign-in actually checks.
 * - pin_lookup:     keyed HMAC of the PIN, unique, so "is this PIN already
 *                   taken?" is one indexed lookup instead of a bcrypt check
 *                   against every user (see App\Support\Pin::lookup()).
 * - pin_changed_at: when the owner last chose their own PIN. Null while the
 *                   PIN is one an admin set for them, which forces a change
 *                   at their next sign-in.
 *
 * Email becomes optional: a staff member may have no email at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('pin_hash')->nullable()->after('password');
            $table->string('pin_lookup', 64)->nullable()->unique()->after('pin_hash');
            $table->timestamp('pin_changed_at')->nullable()->after('pin_lookup');
        });
    }

    public function down(): void
    {
        // Email goes back to required, so give every email-less account a
        // placeholder address first rather than failing the rollback.
        DB::table('users')->whereNull('email')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['email' => "user-{$user->id}@no-email.invalid"]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pin_lookup']);
            $table->dropColumn(['pin_hash', 'pin_lookup', 'pin_changed_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
