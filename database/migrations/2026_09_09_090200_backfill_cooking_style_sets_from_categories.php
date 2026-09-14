<?php

use App\Enums\PricingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time data move: turns yesterday's per-category cooking-style
 * curation into named Cooking Style Sets, one per category that actually
 * had styles selected, then assigns that set to every per-kilo item in
 * that category (replacing the category-cascade — items keep exactly the
 * styles they already had). A category with zero styles produces no set;
 * its items get cooking_style_set_id = null and correctly read
 * NEEDS SETUP afterward via the new readiness reason.
 *
 * Reversible for this migration's own effect: down() removes only the
 * sets it created (identified by the 'migrated-from-category-' slug
 * prefix) and nulls cooking_style_set_id on whatever items it assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryStyleIds = DB::table('cooking_style_menu_category')
            ->select('menu_category_id', 'cooking_style_id')
            ->get()
            ->groupBy('menu_category_id')
            ->map(fn ($rows) => $rows->pluck('cooking_style_id')->unique()->values());

        if ($categoryStyleIds->isEmpty()) {
            return;
        }

        $categories = DB::table('menu_categories')
            ->whereIn('id', $categoryStyleIds->keys())
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $now = now();

        foreach ($categoryStyleIds as $categoryId => $styleIds) {
            $category = $categories->get($categoryId);

            if (! $category) {
                continue;
            }

            $setId = DB::table('cooking_style_sets')->insertGetId([
                'name' => "{$category->name} (migrated)",
                'slug' => 'migrated-from-category-'.Str::slug($category->name).'-'.$categoryId,
                'description' => null,
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('cooking_style_set_style')->insert(
                $styleIds->map(fn ($styleId, $index) => [
                    'cooking_style_set_id' => $setId,
                    'cooking_style_id' => $styleId,
                    'sort_order' => $index,
                ])->values()->all()
            );

            DB::table('menu_items')
                ->where('menu_category_id', $categoryId)
                ->where('pricing_type', PricingType::PerKilo->value)
                ->update(['cooking_style_set_id' => $setId]);
        }
    }

    public function down(): void
    {
        $migratedSetIds = DB::table('cooking_style_sets')
            ->where('slug', 'like', 'migrated-from-category-%')
            ->pluck('id');

        if ($migratedSetIds->isEmpty()) {
            return;
        }

        DB::table('menu_items')->whereIn('cooking_style_set_id', $migratedSetIds)->update(['cooking_style_set_id' => null]);
        DB::table('cooking_style_set_style')->whereIn('cooking_style_set_id', $migratedSetIds)->delete();
        DB::table('cooking_style_sets')->whereIn('id', $migratedSetIds)->delete();
    }
};
