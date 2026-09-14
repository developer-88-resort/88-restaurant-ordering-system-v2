<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Menu Item description field moved from a plain <textarea> to a
 * rich-text editor — staff used to type one line per included item (e.g.
 * "SET A": "2 Garlic Rice Platter\n5 pcs. Pork BBQ.\n...") and the customer
 * page detected that pattern and bulleted it on display. Now that
 * descriptions are stored as real HTML, this backfills every existing
 * plain-text row into the equivalent markup ONCE, so nothing regresses:
 * multi-line descriptions become a <ul><li> list (preserving the current
 * bulleted look), single-line ones become a <p>.
 */
return new class extends Migration
{
    public function up(): void
    {
        $converted = ['list' => 0, 'paragraph' => 0, 'skipped_already_html' => 0];

        DB::table('menu_items')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->select('id', 'description')
            ->cursor()
            ->each(function ($row) use (&$converted) {
                $raw = trim($row->description);

                // Idempotent: skip anything that already looks like markup
                // (a row already saved once through the new editor, or if
                // this migration ever runs twice).
                if (str_starts_with($raw, '<')) {
                    $converted['skipped_already_html']++;

                    return;
                }

                $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn ($line) => $line !== ''));

                if (count($lines) > 1) {
                    $html = '<ul>'.implode('', array_map(
                        fn ($line) => '<li>'.htmlspecialchars($line, ENT_QUOTES, 'UTF-8').'</li>',
                        $lines
                    )).'</ul>';
                    $converted['list']++;
                } else {
                    $html = '<p>'.htmlspecialchars($lines[0] ?? $raw, ENT_QUOTES, 'UTF-8').'</p>';
                    $converted['paragraph']++;
                }

                DB::table('menu_items')->where('id', $row->id)->update(['description' => $html]);
            });

        echo PHP_EOL;
        echo '  Menu item description backfill'.PHP_EOL;
        echo '  · converted to <ul> list: '.$converted['list'].PHP_EOL;
        echo '  · converted to <p> paragraph: '.$converted['paragraph'].PHP_EOL;
        echo '  · already HTML, skipped: '.$converted['skipped_already_html'].PHP_EOL.PHP_EOL;
    }

    /**
     * Best-effort and lossy by design: strips tags back to plain text and
     * turns list/paragraph boundaries back into newlines. Any formatting
     * (bold/color/links/headings) added after this migration ran cannot be
     * reconstructed — acceptable, since this only reverses formatting, not
     * data.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->whereNotNull('description')
            ->where('description', 'like', '<%')
            ->orderBy('id')
            ->select('id', 'description')
            ->cursor()
            ->each(function ($row) {
                $withBreaks = preg_replace('/<\/(li|p)>\s*<(li|p)[^>]*>/i', "\n", $row->description);
                $plain = trim(html_entity_decode(strip_tags($withBreaks), ENT_QUOTES, 'UTF-8'));

                DB::table('menu_items')->where('id', $row->id)->update(['description' => $plain]);
            });
    }
};
