<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several accounts may now hold the same STARTING PIN, so a Superadmin can
 * hand out the same easy one (1234) to every new hire. Signing in is a name
 * first and then its PIN, so a repeated PIN opens no one else's account.
 *
 * A PIN someone chose for themselves must still be theirs alone; that is now
 * checked in App\Rules\ValidPin (against other people's own PINs only), not
 * by this index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pin_lookup']);
            $table->index('pin_lookup');
        });
    }

    public function down(): void
    {
        // The unique index can't come back while duplicates exist. The oldest
        // account keeps the PIN; the rest lose theirs and need a new starting
        // PIN from a Superadmin.
        $duplicates = DB::table('users')
            ->whereNotNull('pin_lookup')
            ->select('pin_lookup')
            ->groupBy('pin_lookup')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('pin_lookup');

        foreach ($duplicates as $lookup) {
            $keep = DB::table('users')->where('pin_lookup', $lookup)->orderBy('id')->value('id');
            DB::table('users')->where('pin_lookup', $lookup)->where('id', '!=', $keep)
                ->update(['pin_hash' => null, 'pin_lookup' => null, 'pin_changed_at' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['pin_lookup']);
            $table->unique('pin_lookup');
        });
    }
};
