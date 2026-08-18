<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marks weight_step_grams and allow_tare as DEPRECATED without dropping
 * them.
 *
 * Nothing reads these two any more: there is no step snapping (the scale's
 * actual reading is accepted, e.g. 437 g) and the hardware TARE button
 * already produces a net figure, so the app never deducts a tare itself.
 *
 * They are deliberately left in place for one release. Real rows still
 * carry values (Bangus had a 300 g step) and order_items.tare_grams holds
 * history on lines that were already billed — dropping the pair in the same
 * change as the code removal would leave nothing to fall back to if any of
 * this has to be reverted. A follow-up migration drops them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->comment(
            'weight_step_grams',
            'DEPRECATED 2026-08-03: no step snapping; the scale reading is accepted as-is. Drop after one release.',
        );

        $this->comment(
            'allow_tare',
            'DEPRECATED 2026-08-03: the scale hardware TARE button produces the net weight. Drop after one release.',
        );
    }

    public function down(): void
    {
        $this->comment('weight_step_grams', '');
        $this->comment('allow_tare', '');
    }

    /**
     * Column comments are MySQL-specific and have to be restated with the
     * full column definition. SQLite (the test database) has no equivalent,
     * so this is a no-op there rather than a failure.
     */
    protected function comment(string $column, string $comment): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $definition = match ($column) {
            'weight_step_grams' => 'INT UNSIGNED NOT NULL DEFAULT 10',
            'allow_tare' => 'TINYINT(1) NOT NULL DEFAULT 0',
        };

        DB::statement(sprintf(
            'ALTER TABLE menu_items MODIFY COLUMN %s %s COMMENT %s',
            $column,
            $definition,
            DB::getPdo()->quote($comment),
        ));
    }
};
