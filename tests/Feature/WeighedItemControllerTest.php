<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CookingStyle;
use App\Models\CookingStyleSet;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeighedItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
    }

    private function item(string $name): MenuItem
    {
        return MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => $name,
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'min_weight_grams' => 250,
            'availability_status' => 'available',
        ]);
    }

    public function test_the_index_lists_every_per_kilo_item_with_its_readiness(): void
    {
        $ready = $this->item('Bangus');
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood', 'sort_order' => 0, 'is_active' => true]);
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $set->cookingStyles()->attach($style->id);
        $ready->update(['cooking_style_set_id' => $set->id]);

        $this->item('Pusit'); // deliberately left without a set — must show as not ready

        MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);

        $this->actingAs($this->admin)
            ->get('/weigh/items')
            ->assertInertia(fn ($page) => $page
                ->component('Weigh/Items/Index')
                ->has('items', 2) // only the two per-kilo items, not the fixed one
                ->where('items.0.ready', true)
                ->where('items.1.ready', false));
    }

    public function test_assigning_a_set_to_one_item_does_not_touch_a_sibling_item_in_the_same_category(): void
    {
        $bangus = $this->item('Bangus');
        $pusit = $this->item('Pusit');

        $seafood = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood-set', 'sort_order' => 0, 'is_active' => true]);
        $inihaw = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $seafood->cookingStyles()->attach($inihaw->id);

        $shellfish = CookingStyleSet::create(['name' => 'Shellfish', 'slug' => 'shellfish-set', 'sort_order' => 0, 'is_active' => true]);
        $buttered = CookingStyle::create(['name' => 'Buttered', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $shellfish->cookingStyles()->attach($buttered->id);

        $this->actingAs($this->admin)->patch("/weigh/items/{$bangus->id}", [
            'cooking_style_set_id' => $seafood->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($seafood->id, $bangus->fresh()->cooking_style_set_id);
        $this->assertNull($pusit->fresh()->cooking_style_set_id, 'A sibling item in the same category must be untouched.');

        $this->actingAs($this->admin)->patch("/weigh/items/{$pusit->id}", [
            'cooking_style_set_id' => $shellfish->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Inihaw'], $bangus->fresh()->resolvedCookingStyles()->pluck('name')->all());
        $this->assertSame(['Buttered'], $pusit->fresh()->resolvedCookingStyles()->pluck('name')->all());
    }

    public function test_setting_a_per_item_override_wins_over_the_assigned_set(): void
    {
        $item = $this->item('Bangus');
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood-override', 'sort_order' => 0, 'is_active' => true]);
        $setStyle = CookingStyle::create(['name' => 'Sinigang', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $set->cookingStyles()->attach($setStyle->id);
        $overrideStyle = CookingStyle::create(['name' => 'Buttered', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($this->admin)->patch("/weigh/items/{$item->id}", [
            'cooking_style_set_id' => $set->id,
            'cooking_style_ids' => [$overrideStyle->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Buttered'], $item->fresh()->resolvedCookingStyles()->pluck('name')->all());
    }

    public function test_a_staff_member_cannot_reach_the_weighted_items_screen(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($staff)->get('/weigh/items')->assertForbidden();
    }
}
