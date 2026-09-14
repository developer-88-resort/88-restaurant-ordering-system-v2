<?php

namespace Tests\Feature;

use App\Models\CookingStyle;
use App\Models\CookingStyleSet;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\WeighedItemReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each "NEEDS SETUP" reason must carry a URL that actually fixes it —
 * this replaces the old hardcoded "Finish setup" link into the item edit
 * page, which couldn't fix a missing cooking-style assignment.
 */
class WeighedItemReadinessTest extends TestCase
{
    use RefreshDatabase;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
    }

    private function item(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'menu_category_id' => $this->category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'min_weight_grams' => 250,
            'availability_status' => 'available',
        ], $overrides));
    }

    public function test_a_fixed_item_has_no_reasons(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);

        $this->assertSame([], WeighedItemReadiness::reasons($item));
    }

    public function test_a_fully_configured_item_has_no_reasons(): void
    {
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood', 'sort_order' => 0, 'is_active' => true]);
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $set->cookingStyles()->attach($style->id);

        $item = $this->item(['cooking_style_set_id' => $set->id]);

        $this->assertSame([], WeighedItemReadiness::reasons($item));
    }

    public function test_missing_rate_points_at_the_item_edit_page(): void
    {
        $item = $this->item(['price_per_kilo' => null]);

        $reasons = WeighedItemReadiness::reasons($item);
        $codes = array_column($reasons, 'code');

        $this->assertContains('no_rate', $codes);
        $reason = collect($reasons)->firstWhere('code', 'no_rate');
        $this->assertSame(route('menu-items.edit', $item), $reason['url']);
    }

    public function test_missing_minimum_weight_points_at_the_item_edit_page(): void
    {
        // The column is NOT NULL, so the realistic "missing" case is 0, not
        // literally null.
        $item = $this->item(['min_weight_grams' => 0]);

        $reasons = WeighedItemReadiness::reasons($item);
        $reason = collect($reasons)->firstWhere('code', 'no_minimum');

        $this->assertNotNull($reason);
        $this->assertSame(route('menu-items.edit', $item), $reason['url']);
    }

    public function test_no_set_assigned_points_at_the_weighted_items_screen(): void
    {
        $item = $this->item();

        $reasons = WeighedItemReadiness::reasons($item);
        $reason = collect($reasons)->firstWhere('code', 'no_set');

        $this->assertNotNull($reason);
        $this->assertSame(route('weigh.items.index'), $reason['url']);
    }

    public function test_a_set_with_no_active_styles_points_at_that_specific_sets_edit_page(): void
    {
        $set = CookingStyleSet::create(['name' => 'Empty Set', 'slug' => 'empty-set', 'sort_order' => 0, 'is_active' => true]);
        $item = $this->item(['cooking_style_set_id' => $set->id]);

        $reasons = WeighedItemReadiness::reasons($item);
        $reason = collect($reasons)->firstWhere('code', 'set_empty');

        $this->assertNotNull($reason);
        $this->assertSame(route('weigh.cooking-styles.edit', $set->id), $reason['url']);
    }

    public function test_an_out_of_stock_item_is_not_flagged_for_that_reason_alone(): void
    {
        // Availability is a merchandising choice, not a setup gap — an
        // otherwise fully configured item that's just 86'd today must not
        // read NEEDS SETUP.
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood-2', 'sort_order' => 0, 'is_active' => true]);
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $set->cookingStyles()->attach($style->id);

        $item = $this->item(['cooking_style_set_id' => $set->id, 'availability_status' => 'out_of_stock']);

        $this->assertSame([], WeighedItemReadiness::reasons($item));
    }
}
