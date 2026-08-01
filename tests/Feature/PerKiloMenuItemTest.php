<?php

namespace Tests\Feature;

use App\Enums\PricingType;
use App\Enums\UserRole;
use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu Management for per-kilo (weighed) items: the pricing-type switch and
 * the cooking styles that take the place of variants.
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function perKiloPayload(array $overrides = []): array
    {
        return array_merge([
            'menu_category_id' => $this->category->id,
            'name' => 'Bangus',
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '450.00',
            'min_weight_grams' => 250,
            'weight_step_grams' => 10,
            'allow_tare' => 1,
            'counter_only' => 1,
            'availability_status' => 'available',
        ], $overrides);
    }

    private function styleIds(int $count = 3): array
    {
        return CookingStyle::query()->take($count)->pluck('id')->all();
    }

    private function seedStyles(): void
    {
        foreach (['Inihaw', 'Sinigang', 'Sweet and Sour', 'Buttered'] as $index => $name) {
            CookingStyle::create(['name' => $name, 'surcharge' => 0, 'sort_order' => $index, 'is_active' => true]);
        }
    }

    /** The M1 acceptance case, end to end. */
    public function test_a_per_kilo_item_can_be_created_with_cooking_styles(): void
    {
        $this->seedStyles();

        $response = $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload([
            'cooking_style_ids' => $this->styleIds(3),
        ]));

        $response->assertSessionHasNoErrors();

        $bangus = MenuItem::where('name', 'Bangus')->firstOrFail();

        $this->assertSame(PricingType::PerKilo, $bangus->pricing_type);
        $this->assertSame('450.00', (string) $bangus->price_per_kilo);
        $this->assertSame(250, $bangus->min_weight_grams);
        $this->assertSame(10, $bangus->weight_step_grams);
        $this->assertTrue($bangus->allow_tare);
        $this->assertTrue($bangus->counter_only);
        $this->assertCount(3, $bangus->cookingStyles);
    }

    public function test_a_per_kilo_item_is_listed_as_price_per_kg(): void
    {
        $this->seedStyles();

        $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload());

        $bangus = MenuItem::where('name', 'Bangus')->firstOrFail();

        $this->assertTrue($bangus->isPerKilo());
        $this->assertSame('₱450.00 / kg', $bangus->priceRangeLabel());
    }

    /**
     * A per-kilo item prices from the scale, so the fixed `price` column is
     * zeroed — leaving a stale price would show a second, wrong number
     * beside the ₱/kg rate.
     */
    public function test_the_fixed_price_is_zeroed_on_a_per_kilo_item(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload(['price' => '999.00']));

        $this->assertSame('0.00', (string) MenuItem::where('name', 'Bangus')->value('price'));
    }

    public function test_price_per_kilo_is_required_when_pricing_type_is_per_kilo(): void
    {
        $response = $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload([
            'price_per_kilo' => null,
        ]));

        $response->assertSessionHasErrors('price_per_kilo');
        $this->assertSame(0, MenuItem::count());
    }

    /**
     * The ₱10–₱10,000 band is a typo guard: a rate below it is nearly always
     * a per-100g price typed by mistake, above it a misplaced decimal.
     */
    public function test_price_per_kilo_is_rejected_outside_the_sanity_cap(): void
    {
        foreach (['9.99', '10000.01'] as $rate) {
            $response = $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload([
                'price_per_kilo' => $rate,
            ]));

            $response->assertSessionHasErrors('price_per_kilo');
        }

        $this->assertSame(0, MenuItem::count());
    }

    public function test_weight_bounds_are_enforced(): void
    {
        $tooLight = $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload(['min_weight_grams' => 49]));
        $tooLight->assertSessionHasErrors('min_weight_grams');

        $zeroStep = $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload(['weight_step_grams' => 0]));
        $zeroStep->assertSessionHasErrors('weight_step_grams');

        $this->assertSame(0, MenuItem::count());
    }

    public function test_a_per_kilo_item_cannot_also_have_variants(): void
    {
        $response = $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload([
            'variants' => [['name' => 'Solo', 'price' => '200.00']],
        ]));

        $response->assertSessionHasErrors('pricing_type');
        $this->assertSame(0, MenuItem::count());
    }

    /**
     * Switching an existing fixed item to per-kilo drops the variants that
     * no longer make sense — the size is whatever the scale says.
     */
    public function test_switching_a_fixed_item_to_per_kilo_drops_its_variants(): void
    {
        $this->seedStyles();

        $item = MenuItem::create([
            'menu_category_id' => $this->category->id,
            'name' => 'Tilapia',
            'price' => '200.00',
            'availability_status' => 'available',
        ]);
        $item->variants()->create(['name' => 'Solo', 'price' => '200.00', 'sort_order' => 0, 'is_default' => true]);

        $this->actingAs($this->admin)->put("/menu-items/{$item->id}", [
            'menu_category_id' => $this->category->id,
            'name' => 'Tilapia',
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '380.00',
            'cooking_style_ids' => $this->styleIds(2),
            'availability_status' => 'available',
        ]);

        $item->refresh();

        $this->assertTrue($item->isPerKilo());
        $this->assertCount(0, $item->variants);
        $this->assertCount(2, $item->cookingStyles);
    }

    /**
     * ...and switching back detaches the styles and resets the weight
     * fields, so stale config can't linger on a fixed-price item.
     */
    public function test_switching_a_per_kilo_item_back_to_fixed_clears_its_weight_config(): void
    {
        $this->seedStyles();

        $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload([
            'cooking_style_ids' => $this->styleIds(3),
            'min_weight_grams' => 500,
            'weight_step_grams' => 50,
        ]));

        $item = MenuItem::where('name', 'Bangus')->firstOrFail();
        $this->assertCount(3, $item->cookingStyles);

        $this->actingAs($this->admin)->put("/menu-items/{$item->id}", [
            'menu_category_id' => $this->category->id,
            'name' => 'Bangus',
            'pricing_type' => 'fixed',
            'price' => '275.00',
            'availability_status' => 'available',
        ]);

        $item->refresh()->load('cookingStyles');

        $this->assertSame(PricingType::Fixed, $item->pricing_type);
        $this->assertSame('275.00', (string) $item->price);
        $this->assertNull($item->price_per_kilo);
        $this->assertSame(250, $item->min_weight_grams);
        $this->assertSame(10, $item->weight_step_grams);
        $this->assertFalse($item->allow_tare);
        $this->assertFalse($item->counter_only);
        $this->assertCount(0, $item->cookingStyles);
        $this->assertSame('₱275.00', $item->priceRangeLabel());
    }

    /**
     * The form can only offer styles the page actually ships, so guard the
     * prop wiring on both create and edit.
     */
    public function test_the_item_form_receives_the_active_cooking_styles(): void
    {
        $this->seedStyles();
        CookingStyle::create(['name' => 'Retired Style', 'surcharge' => 0, 'sort_order' => 99, 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get('/menu-items/create')
            ->assertInertia(fn ($page) => $page
                ->component('MenuItems/Create')
                ->has('cookingStyles', 4)
                ->where('cookingStyles.0.name', 'Inihaw'));

        $this->actingAs($this->admin)->post('/menu-items', $this->perKiloPayload([
            'cooking_style_ids' => $this->styleIds(2),
        ]));
        $item = MenuItem::where('name', 'Bangus')->firstOrFail();

        $this->actingAs($this->admin)
            ->get("/menu-items/{$item->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->component('MenuItems/Edit')
                ->has('cookingStyles', 4)
                ->where('item.pricing_type', 'per_kilo')
                ->where('item.price_per_kilo', '450.00')
                ->has('item.cooking_style_ids', 2));
    }

    /** A fixed item keeps behaving exactly as before this feature existed. */
    public function test_a_fixed_price_item_is_unaffected(): void
    {
        $response = $this->actingAs($this->admin)->post('/menu-items', [
            'menu_category_id' => $this->category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);

        $response->assertSessionHasNoErrors();

        $adobo = MenuItem::where('name', 'Adobo')->firstOrFail();

        $this->assertSame(PricingType::Fixed, $adobo->pricing_type);
        $this->assertFalse($adobo->isPerKilo());
        $this->assertNull($adobo->price_per_kilo);
        $this->assertSame('₱180.00', $adobo->priceRangeLabel());
    }
}
