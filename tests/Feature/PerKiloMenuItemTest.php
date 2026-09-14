<?php

namespace Tests\Feature;

use App\Enums\PricingType;
use App\Enums\UserRole;
use App\Models\CookingStyle;
use App\Models\CookingStyleSet;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu Management for per-kilo ("weighed in person") items.
 *
 * The scale at the counter produces the amount; the price per kilo here is
 * a REFERENCE rate — shown to the customer, printed on the receipt, and the
 * figure the weigh station later checks the keyed amount against. So the
 * form is small, and what it does enforce it enforces hard.
 *
 * Cooking styles are NOT part of this form at all — a freshly created
 * per-kilo item has none and reads NEEDS SETUP until a Cooking Style Set
 * (or a per-item override) is assigned from the Weigh & Order "Weighted
 * Items" screen. See CookingStyleSetTest for that resolution rule.
 */
class PerKiloMenuItemTest extends TestCase
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

    private function cookingStyleSet(array $styleNames = ['Inihaw', 'Sinigang', 'Sweet and Sour', 'Buttered']): CookingStyleSet
    {
        $set = CookingStyleSet::create(['name' => 'Seafood', 'slug' => 'seafood', 'sort_order' => 0, 'is_active' => true]);

        foreach ($styleNames as $index => $name) {
            $style = CookingStyle::create(['name' => $name, 'surcharge' => 0, 'sort_order' => $index, 'is_active' => true]);
            $set->cookingStyles()->attach($style->id);
        }

        return $set;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'menu_category_id' => $this->category->id,
            'name' => 'Tilapia',
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '260.00',
            'min_weight_grams' => 250,
            'availability_status' => 'available',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Acceptance
    // ---------------------------------------------------------------

    public function test_a_per_kilo_item_can_be_created(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload())
            ->assertSessionHasNoErrors();

        $tilapia = MenuItem::where('name', 'Tilapia')->firstOrFail();

        $this->assertSame(PricingType::PerKilo, $tilapia->pricing_type);
        $this->assertSame('260.00', (string) $tilapia->price_per_kilo);
        $this->assertSame(250, $tilapia->min_weight_grams);
        $this->assertSame('₱260.00 / kg', $tilapia->priceRangeLabel());

        // No cooking style set assigned yet — that happens separately in
        // Weigh & Order, not on this form. Correctly still needs setup.
        $this->assertNull($tilapia->cooking_style_set_id);
        $this->assertTrue($tilapia->needsWeighedSetup());
    }

    public function test_assigning_a_cooking_style_set_clears_needs_setup(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload());
        $tilapia = MenuItem::where('name', 'Tilapia')->firstOrFail();
        $this->assertTrue($tilapia->needsWeighedSetup());

        $set = $this->cookingStyleSet();
        $tilapia->update(['cooking_style_set_id' => $set->id]);

        $tilapia->refresh();
        $this->assertCount(4, $tilapia->resolvedCookingStyles());
        $this->assertFalse($tilapia->needsWeighedSetup());
    }

    // ---------------------------------------------------------------
    // Required fields
    // ---------------------------------------------------------------

    public function test_price_per_kilo_is_required(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['price_per_kilo' => null]))
            ->assertSessionHasErrors('price_per_kilo');

        $this->assertSame(0, MenuItem::count());
    }

    // ---------------------------------------------------------------
    // Sanity range — read from Settings, never hard-coded
    // ---------------------------------------------------------------

    public function test_the_price_per_kilo_sanity_range_is_enforced(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['price_per_kilo' => '5']))
            ->assertSessionHasErrors('price_per_kilo');

        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['price_per_kilo' => '50000']))
            ->assertSessionHasErrors('price_per_kilo');

        $this->assertSame(0, MenuItem::count());

        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['price_per_kilo' => '295']))
            ->assertSessionHasNoErrors();

        $this->assertSame('295.00', (string) MenuItem::first()->price_per_kilo);
    }

    /** The range is an admin setting, so moving it moves what validates. */
    public function test_the_range_follows_the_settings_rather_than_a_constant(): void
    {
        Setting::current()->update([
            'weighed_price_per_kilo_min' => 300.00,
            'weighed_price_per_kilo_max' => 900.00,
        ]);

        // 260 was fine under the default range and is now too low.
        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['price_per_kilo' => '260']))
            ->assertSessionHasErrors('price_per_kilo');

        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['price_per_kilo' => '500']))
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------
    // Minimum weight
    // ---------------------------------------------------------------

    public function test_the_minimum_weight_floor_is_enforced(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['min_weight_grams' => 40]))
            ->assertSessionHasErrors('min_weight_grams');

        $this->assertSame(0, MenuItem::count());

        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['min_weight_grams' => 250]))
            ->assertSessionHasNoErrors();
    }

    public function test_the_minimum_weight_is_a_real_field_not_a_fixed_250(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload([
            'name' => 'Alimango',
            'min_weight_grams' => 500,
        ]));

        $this->assertSame(500, MenuItem::where('name', 'Alimango')->value('min_weight_grams'));
    }

    // ---------------------------------------------------------------
    // counter_only is implied and locked
    // ---------------------------------------------------------------

    public function test_counter_only_is_forced_on_for_a_per_kilo_item(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload());

        $this->assertTrue(MenuItem::where('name', 'Tilapia')->value('counter_only'));
    }

    /** Even a direct API post that says otherwise. */
    public function test_counter_only_cannot_be_turned_off_through_the_request(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload(['counter_only' => 0]));

        $this->assertTrue(MenuItem::where('name', 'Tilapia')->value('counter_only'));
    }

    /** ...and not through the model either, which seeders/imports use. */
    public function test_counter_only_is_forced_at_the_model_level(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Direct',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '300.00',
            'counter_only' => false,
            'availability_status' => 'available',
        ]);

        $this->assertTrue($item->fresh()->counter_only);
    }

    // ---------------------------------------------------------------
    // Toggling between the two pricing types
    // ---------------------------------------------------------------

    public function test_switching_back_to_fixed_clears_the_weighed_config(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload());
        $item = MenuItem::where('name', 'Tilapia')->firstOrFail();

        $set = $this->cookingStyleSet();
        $item->update(['cooking_style_set_id' => $set->id]);
        $item->cookingStyles()->sync([CookingStyle::first()->id]);
        $this->assertNotNull($item->fresh()->cooking_style_set_id);
        $this->assertCount(1, $item->fresh()->cookingStyles);

        $this->actingAs($this->admin)->put("/menu-items/{$item->id}", [
            'menu_category_id' => $this->category->id,
            'name' => 'Tilapia',
            'pricing_type' => 'fixed',
            'price' => '275.00',
            'availability_status' => 'available',
        ])->assertSessionHasNoErrors();

        $item->refresh()->load('cookingStyles');

        $this->assertSame(PricingType::Fixed, $item->pricing_type);
        $this->assertSame('275.00', (string) $item->price);
        $this->assertNull($item->price_per_kilo);
        $this->assertFalse($item->counter_only);
        $this->assertNull($item->cooking_style_set_id);
        $this->assertCount(0, $item->cookingStyles);
        $this->assertSame('₱275.00', $item->priceRangeLabel());
    }

    public function test_switching_a_fixed_item_to_per_kilo_drops_its_variants(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Bangus',
            'price' => '200.00',
            'availability_status' => 'available',
        ]);
        $item->variants()->create(['name' => 'Solo', 'price' => '200.00', 'sort_order' => 0, 'is_default' => true]);

        $this->actingAs($this->admin)->put("/menu-items/{$item->id}", $this->payload(['name' => 'Bangus']))
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertTrue($item->isPerKilo());
        $this->assertCount(0, $item->variants);
    }

    public function test_a_per_kilo_item_cannot_also_have_variants(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload([
            'variants' => [['name' => 'Solo', 'price' => '200.00']],
        ]))->assertSessionHasErrors('pricing_type');

        $this->assertSame(0, MenuItem::count());
    }

    // ---------------------------------------------------------------
    // Needs setup
    // ---------------------------------------------------------------

    public function test_a_per_kilo_item_without_cooking_styles_is_flagged_as_needing_setup(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Legacy Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'availability_status' => 'available',
        ]);

        $this->assertTrue($item->needsWeighedSetup());

        $this->actingAs($this->admin)
            ->get('/menu-items?pricing=per_kilo')
            ->assertInertia(fn ($page) => $page->where('items.0.needs_setup', true));
    }

    public function test_a_fixed_item_never_needs_weighed_setup(): void
    {
        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);

        $this->assertFalse($item->needsWeighedSetup());
    }

    // ---------------------------------------------------------------
    // Fixed items are untouched
    // ---------------------------------------------------------------

    public function test_a_fixed_price_item_is_unaffected(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', [
            'menu_category_id' => $this->category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ])->assertSessionHasNoErrors();

        $adobo = MenuItem::where('name', 'Adobo')->firstOrFail();

        $this->assertSame(PricingType::Fixed, $adobo->pricing_type);
        $this->assertNull($adobo->price_per_kilo);
        $this->assertFalse($adobo->counter_only);
        $this->assertSame('₱180.00', $adobo->priceRangeLabel());
    }

    public function test_the_form_receives_the_admin_price_range(): void
    {
        $this->actingAs($this->admin)
            ->get('/menu-items/create')
            ->assertInertia(fn ($page) => $page
                ->component('MenuItems/Create')
                ->where('weighed.price_per_kilo_min', 10)
                ->where('weighed.price_per_kilo_max', 10000));
    }
}
