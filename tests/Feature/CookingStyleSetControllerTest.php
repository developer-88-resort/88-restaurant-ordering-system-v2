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

class CookingStyleSetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    }

    public function test_a_set_can_be_created_with_styles(): void
    {
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($this->admin)->post('/weigh/cooking-styles', [
            'name' => 'Seafood',
            'sort_order' => 0,
            'is_active' => 1,
            'cooking_style_ids' => [$style->id],
        ])->assertSessionHasNoErrors();

        $set = CookingStyleSet::where('name', 'Seafood')->firstOrFail();
        $this->assertCount(1, $set->cookingStyles);
        $this->assertNotEmpty($set->slug);
    }

    public function test_updating_a_sets_styles_cascades_to_every_item_using_it(): void
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood', 'sort_order' => 0, 'is_active' => true]);
        $inihaw = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 0, 'is_active' => true]);
        $set->cookingStyles()->attach($inihaw->id);

        $item = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus', 'price' => 0, 'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00', 'availability_status' => 'available',
            'cooking_style_set_id' => $set->id,
        ]);

        $sinigang = CookingStyle::create(['name' => 'Sinigang', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($this->admin)->put("/weigh/cooking-styles/{$set->id}", [
            'name' => 'Seafood',
            'sort_order' => 0,
            'is_active' => 1,
            'cooking_style_ids' => [$sinigang->id],
        ])->assertSessionHasNoErrors();

        // The item never had an override, so it resolves through the set —
        // updating the set's styles is immediately reflected for it.
        $this->assertSame(['Sinigang'], $item->fresh()->resolvedCookingStyles()->pluck('name')->all());
    }

    public function test_a_set_still_assigned_to_an_item_cannot_be_deleted(): void
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood', 'sort_order' => 0, 'is_active' => true]);
        MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus', 'price' => 0, 'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00', 'availability_status' => 'available',
            'cooking_style_set_id' => $set->id,
        ]);

        $this->actingAs($this->admin)->delete("/weigh/cooking-styles/{$set->id}");

        $this->assertNotNull($set->fresh());
    }

    public function test_an_unused_set_can_be_deleted(): void
    {
        $set = CookingStyleSet::create(['name' => 'Unused', 'slug' => 'unused', 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($this->admin)->delete("/weigh/cooking-styles/{$set->id}");

        $this->assertNull($set->fresh());
    }

    public function test_a_master_style_can_be_added_inline(): void
    {
        $this->actingAs($this->admin)->post('/weigh/cooking-styles/master-styles', [
            'name' => 'Chilli Garlic',
            'surcharge' => '15.00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cooking_styles', ['name' => 'Chilli Garlic', 'surcharge' => '15.00']);
    }

    public function test_a_staff_member_cannot_manage_cooking_style_sets(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($staff)->get('/weigh/cooking-styles')->assertForbidden();
    }
}
