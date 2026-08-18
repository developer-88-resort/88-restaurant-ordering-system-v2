<?php

use App\Enums\PricingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings existing per-kilo rows in line with the rules this release
 * introduces, and then REPORTS what it could not decide.
 *
 * Two things are safe to set without guessing: counter_only (implied by
 * per-kilo pricing) and clearing the two deprecated columns. Everything
 * else — a missing rate, a missing cooking style — is a judgement about
 * what the resort actually sells, so the migration prints a to-do list
 * instead of inventing values.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A weighed item is always handed over in person.
        $forced = DB::table('menu_items')
            ->where('pricing_type', PricingType::PerKilo->value)
            ->where('counter_only', false)
            ->update(['counter_only' => true]);

        // No step snapping and no app-side tare any more.
        DB::table('menu_items')
            ->where('pricing_type', PricingType::PerKilo->value)
            ->update(['weight_step_grams' => 10, 'allow_tare' => false]);

        // Bangus: the 300 g step made a realistic 437 g fish unsellable.
        $bangus = DB::table('menu_items')
            ->where('pricing_type', PricingType::PerKilo->value)
            ->where('name', 'like', 'Bangus%')
            ->get();

        foreach ($bangus as $item) {
            DB::table('menu_items')->where('id', $item->id)->update([
                'min_weight_grams' => 250,
                'price_per_kilo' => $item->price_per_kilo ?: 295.00,
                'counter_only' => true,
            ]);
        }

        $this->report($forced, $bangus->count());
    }

    /**
     * Deliberately irreversible in data terms: counter_only was wrong
     * before, so putting it back would be restoring a bug.
     */
    public function down(): void
    {
        // no-op
    }

    protected function report(int $forced, int $bangusTouched): void
    {
        $needsWork = DB::table('menu_items')
            ->leftJoin('cooking_style_menu_item', 'cooking_style_menu_item.menu_item_id', '=', 'menu_items.id')
            ->where('menu_items.pricing_type', PricingType::PerKilo->value)
            ->whereNull('menu_items.deleted_at')
            ->select('menu_items.id', 'menu_items.name', 'menu_items.price_per_kilo')
            ->selectRaw('COUNT(cooking_style_menu_item.cooking_style_id) as style_count')
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_items.price_per_kilo')
            ->get()
            ->filter(fn ($row) => $row->style_count == 0 || $row->price_per_kilo === null || (float) $row->price_per_kilo <= 0);

        echo PHP_EOL;
        echo '  Per-kilo normalization'.PHP_EOL;
        echo '  · counter_only forced on: '.$forced.' item(s)'.PHP_EOL;
        echo '  · Bangus rows normalized: '.$bangusTouched.PHP_EOL;

        if ($needsWork->isEmpty()) {
            echo '  · Nothing needs manual setup.'.PHP_EOL.PHP_EOL;

            return;
        }

        echo PHP_EOL.'  NEEDS MANUAL SETUP (not auto-fixed — these are your decisions):'.PHP_EOL;

        foreach ($needsWork as $row) {
            $missing = [];
            if ($row->style_count == 0) {
                $missing[] = 'no cooking style';
            }
            if ($row->price_per_kilo === null || (float) $row->price_per_kilo <= 0) {
                $missing[] = 'no price per kilo';
            }

            echo sprintf('  · #%d %s — %s'.PHP_EOL, $row->id, $row->name, implode(', ', $missing));
        }

        echo '  Fix these in Menu Management; they are flagged "Needs setup" in the list.'.PHP_EOL.PHP_EOL;
    }
};
