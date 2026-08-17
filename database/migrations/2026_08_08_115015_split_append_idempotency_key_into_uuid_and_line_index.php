<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The old `key` column was sized for a bare UUID (CHAR(36)) but
 * OrderAppender::appendBatch() keyed each line as "{uuid}:{index}" — a
 * multi-line batch overflows it (SQLSTATE 22001, "Data too long"). Under a
 * laxer SQL mode the same overflow would silently TRUNCATE instead of
 * erroring, collapsing every line's key down to the same 36 characters and
 * making the idempotency guard treat lines 2+ as replays of line 1 —
 * silently dropping them. Splitting into two columns removes the overflow
 * risk structurally: the UUID half is always exactly 36 characters, and the
 * line position is a real integer, never a string suffix riding inside a
 * UUID column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('append_idempotency_keys', function (Blueprint $table) {
            $table->uuid('request_uuid')->nullable()->after('key');
            $table->unsignedInteger('line_index')->default(0)->after('request_uuid');
        });

        // Backfill from the old composite key: "{uuid}:{index}" splits on
        // the LAST colon (a UUID itself never contains one); a bare UUID
        // with no suffix — the single-line append() path — becomes line 0
        // of its own one-line "batch".
        DB::table('append_idempotency_keys')->orderBy('id')->cursor()->each(function ($row) {
            $lastColon = strrpos($row->key, ':');
            $suffix = $lastColon !== false ? substr($row->key, $lastColon + 1) : null;

            [$requestUuid, $lineIndex] = ($suffix !== null && ctype_digit($suffix))
                ? [substr($row->key, 0, $lastColon), (int) $suffix]
                : [$row->key, 0];

            DB::table('append_idempotency_keys')->where('id', $row->id)->update([
                'request_uuid' => $requestUuid,
                'line_index' => $lineIndex,
            ]);
        });

        Schema::table('append_idempotency_keys', function (Blueprint $table) {
            $table->uuid('request_uuid')->nullable(false)->change();
            $table->dropUnique(['key']);
            $table->dropColumn('key');
            $table->unique(['request_uuid', 'line_index']);
        });
    }

    public function down(): void
    {
        Schema::table('append_idempotency_keys', function (Blueprint $table) {
            $table->uuid('key')->nullable()->after('id');
        });

        DB::table('append_idempotency_keys')->orderBy('id')->cursor()->each(function ($row) {
            $key = $row->line_index > 0 ? "{$row->request_uuid}:{$row->line_index}" : $row->request_uuid;

            DB::table('append_idempotency_keys')->where('id', $row->id)->update(['key' => $key]);
        });

        Schema::table('append_idempotency_keys', function (Blueprint $table) {
            $table->uuid('key')->nullable(false)->change();
            $table->unique('key');
            $table->dropUnique(['request_uuid', 'line_index']);
            $table->dropColumn(['request_uuid', 'line_index']);
        });
    }
};
