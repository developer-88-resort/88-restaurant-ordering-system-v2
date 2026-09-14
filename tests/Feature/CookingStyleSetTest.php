<?php

namespace Tests\Feature;

use App\Models\CookingStyle;
use App\Models\CookingStyleSet;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cooking Style Sets — reusable, named bundles of cooking styles owned by
 * Weigh & Order, decoupled from menu categories. Replaces the one-day-old
 * per-category curation from MenuCategoryCookingStylesTest (deleted).
 *
 * The resolution rule (MenuItem::resolvedCookingStyles()) is the single
 * most important behaviour here: an explicit per-item override wins
 * outright as a full replacement, never a merge, over whatever the
 * assigned set offers.
 */
class CookingStyleSetTest extends TestCase
{
    use RefreshDatabase;

    private MenuCategory $category;

    private MenuItem $bangus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $this->bangus = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'min_weight_grams' => 250,
            'availability_status' => 'available',
        ]);
    }

    private function style(string $name, bool $active = true): CookingStyle
    {
        return CookingStyle::create(['name' => $name, 'surcharge' => 0, 'sort_order' => 0, 'is_active' => $active]);
    }

    private function set(string $name, array $styleIds): CookingStyleSet
    {
        $set = CookingStyleSet::create(['name' => $name, 'slug' => str($name)->slug(), 'sort_order' => 0, 'is_active' => true]);
        $set->cookingStyles()->sync($styleIds);

        return $set;
    }

    // ---------------------------------------------------------------
    // Set <-> style relation
    // ---------------------------------------------------------------

    public function test_a_set_can_have_multiple_styles_attached(): void
    {
        $inihaw = $this->style('Inihaw');
        $sinigang = $this->style('Sinigang');

        $set = $this->set('Seafood', [$inihaw->id, $sinigang->id]);

        $this->assertCount(2, $set->fresh()->cookingStyles);
    }

    public function test_two_items_in_different_categories_can_share_one_set(): void
    {
        $otherCategory = MenuCategory::create(['name' => 'Grill', 'sort_order' => 2, 'is_active' => true]);
        $pusit = MenuItem::create([
            'menu_category_id' => $otherCategory->id,
            'name' => 'Pusit', 'price' => 0, 'pricing_type' => 'per_kilo',
            'price_per_kilo' => '260.00', 'availability_status' => 'available',
        ]);

        $set = $this->set('Seafood', [$this->style('Inihaw')->id]);
        $this->bangus->update(['cooking_style_set_id' => $set->id]);
        $pusit->update(['cooking_style_set_id' => $set->id]);

        $this->assertSame($set->id, $this->bangus->fresh()->cooking_style_set_id);
        $this->assertSame($set->id, $pusit->fresh()->cooking_style_set_id);
    }

    public function test_two_items_in_the_same_category_can_use_different_sets(): void
    {
        $pusit = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Pusit', 'price' => 0, 'pricing_type' => 'per_kilo',
            'price_per_kilo' => '260.00', 'availability_status' => 'available',
        ]);

        $setA = $this->set('Fish', [$this->style('Inihaw')->id]);
        $setB = $this->set('Shellfish', [$this->style('Buttered')->id]);
        $this->bangus->update(['cooking_style_set_id' => $setA->id]);
        $pusit->update(['cooking_style_set_id' => $setB->id]);

        $this->assertSame(['Inihaw'], $this->bangus->fresh()->resolvedCookingStyles()->pluck('name')->all());
        $this->assertSame(['Buttered'], $pusit->fresh()->resolvedCookingStyles()->pluck('name')->all());
    }

    // ---------------------------------------------------------------
    // resolvedCookingStyles() — the resolution rule
    // ---------------------------------------------------------------

    public function test_no_override_and_no_set_resolves_to_empty(): void
    {
        $this->assertCount(0, $this->bangus->resolvedCookingStyles());
        $this->assertTrue($this->bangus->needsWeighedSetup());
    }

    public function test_no_override_falls_back_to_the_assigned_set(): void
    {
        $inihaw = $this->style('Inihaw');
        $sinigang = $this->style('Sinigang');
        $set = $this->set('Seafood', [$inihaw->id, $sinigang->id]);
        $this->bangus->update(['cooking_style_set_id' => $set->id]);

        $resolved = $this->bangus->fresh()->resolvedCookingStyles();

        $this->assertSame(['Inihaw', 'Sinigang'], $resolved->pluck('name')->all());
        $this->assertFalse($this->bangus->fresh()->needsWeighedSetup());
    }

    public function test_an_explicit_override_wins_outright_over_the_assigned_set(): void
    {
        $setStyle = $this->style('Sinigang');
        $overrideStyle = $this->style('Buttered');
        $set = $this->set('Seafood', [$setStyle->id]);
        $this->bangus->update(['cooking_style_set_id' => $set->id]);
        $this->bangus->cookingStyles()->sync([$overrideStyle->id]);

        $resolved = $this->bangus->fresh()->resolvedCookingStyles();

        // A full replacement, not a merge — the set's "Sinigang" must NOT appear.
        $this->assertSame(['Buttered'], $resolved->pluck('name')->all());
    }

    public function test_inactive_styles_are_excluded_whether_from_override_or_set(): void
    {
        $activeOverride = $this->style('Buttered');
        $inactiveOverride = $this->style('Retired Style', active: false);
        $this->bangus->cookingStyles()->sync([$activeOverride->id, $inactiveOverride->id]);

        $this->assertSame(['Buttered'], $this->bangus->fresh()->resolvedCookingStyles()->pluck('name')->all());

        $item2 = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Pusit', 'price' => 0, 'pricing_type' => 'per_kilo',
            'price_per_kilo' => '260.00', 'availability_status' => 'available',
        ]);
        $inactiveInSet = $this->style('Also Retired', active: false);
        $set = $this->set('Seafood', [$inactiveInSet->id]);
        $item2->update(['cooking_style_set_id' => $set->id]);

        $this->assertCount(0, $item2->fresh()->resolvedCookingStyles());
        $this->assertTrue($item2->fresh()->needsWeighedSetup());
    }

    public function test_a_deactivated_set_resolves_to_empty_even_though_it_still_has_active_styles(): void
    {
        $set = $this->set('Seafood', [$this->style('Inihaw')->id]);
        $set->update(['is_active' => false]);
        $this->bangus->update(['cooking_style_set_id' => $set->id]);

        $this->assertCount(0, $this->bangus->fresh()->resolvedCookingStyles());
        $this->assertTrue($this->bangus->fresh()->needsWeighedSetup());
    }
}
