<?php

use App\Enums\PricingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time data move: seeds each category's cooking styles from whatever
 * its per-kilo items already had configured (the union across all of
 * them), then levels every one of those items back up to its category's
 * full set — an item that previously had only a partial hand-picked
 * selection ends up with the complete list immediately, not just items
 * saved from now on. A category with no per-kilo items yet (or none of
 * them had any styles set) is left with an empty set, same as before.
 *
 * Not reversible — down() only clears what up() populated in the new
 * pivot; it does not restore the old per-item selections to their prior
 * (partial) state.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryStyleIds = DB::table('cooking_style_menu_item')
            ->join('menu_items', 'menu_items.id', '=', 'cooking_style_menu_item.menu_item_id')
            ->where('menu_items.pricing_type', PricingType::PerKilo->value)
            ->select('menu_items.menu_category_id', 'cooking_style_menu_item.cooking_style_id')
            ->distinct()
            ->get()
            ->groupBy('menu_category_id')
            ->map(fn ($rows) => $rows->pluck('cooking_style_id')->unique()->values());

        if ($categoryStyleIds->isEmpty()) {
            return;
        }

        $now = now();
        $pivotRows = $categoryStyleIds->flatMap(fn ($styleIds, $categoryId) => $styleIds->map(fn ($styleId) => [
            'menu_category_id' => $categoryId,
            'cooking_style_id' => $styleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]))->all();

        DB::table('cooking_style_menu_category')->insertOrIgnore($pivotRows);

        $perKiloItems = DB::table('menu_items')
            ->where('pricing_type', PricingType::PerKilo->value)
            ->select('id', 'menu_category_id')
            ->get();

        foreach ($perKiloItems as $item) {
            $styleIds = $categoryStyleIds->get($item->menu_category_id);

            if (! $styleIds || $styleIds->isEmpty()) {
                continue;
            }

            DB::table('cooking_style_menu_item')->where('menu_item_id', $item->id)->delete();
            DB::table('cooking_style_menu_item')->insert(
                $styleIds->map(fn ($styleId) => [
                    'menu_item_id' => $item->id,
                    'cooking_style_id' => $styleId,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        DB::table('cooking_style_menu_category')->truncate();
    }
};
