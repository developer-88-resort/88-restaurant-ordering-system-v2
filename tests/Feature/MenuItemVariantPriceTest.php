<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A variant with a blank price is the printed menu's "----" — e.g. Alfonso,
 * sold per bottle but not per shot. It is saved and shown in Menu
 * Management, but no order screen may offer or accept it.
 */
class MenuItemVariantPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $this->category = MenuCategory::create(['name' => 'LIQUORS', 'sort_order' => 1, 'is_active' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function payload(array $variants, int $defaultIndex = 0): array
    {
        return [
            'menu_category_id' => $this->category->id,
            'name' => 'Alfonso - 1 L',
            'price' => '',
            'pricing_type' => 'fixed',
            'availability_status' => 'available',
            'variants' => $variants,
            'default_variant_index' => $defaultIndex,
        ];
    }

    /**
     * One variant row the way ItemForm sends it — every key, blanks included.
     *
     * @return array<string, mixed>
     */
    private function row(string $name, string $price, ?int $id = null): array
    {
        return ['id' => $id, 'name' => $name, 'description' => '', 'sku' => '', 'price' => $price];
    }

    private function alfonso(): MenuItem
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload([
            $this->row('PER SHOT', ''),
            $this->row('PER BOTTLE', '780'),
        ]))->assertRedirect(route('menu-items.index'))->assertSessionHasNoErrors();

        return MenuItem::where('name', 'Alfonso - 1 L')->firstOrFail();
    }

    public function test_a_variant_can_be_saved_with_a_blank_price(): void
    {
        $item = $this->alfonso();

        $this->assertCount(2, $item->allVariants);
        $this->assertNull($item->allVariants->firstWhere('name', 'PER SHOT')->price);
        $this->assertSame('780.00', $item->allVariants->firstWhere('name', 'PER BOTTLE')->price);
    }

    public function test_the_default_moves_off_a_price_less_variant(): void
    {
        $item = $this->alfonso();

        $this->assertFalse($item->allVariants->firstWhere('name', 'PER SHOT')->is_default);
        $this->assertTrue($item->allVariants->firstWhere('name', 'PER BOTTLE')->is_default);
    }

    public function test_price_less_variants_are_left_out_of_everything_orderable(): void
    {
        $item = $this->alfonso();

        $this->assertSame(['PER BOTTLE'], $item->variants->pluck('name')->all());
        $this->assertSame('₱780.00', $item->priceRangeLabel());
    }

    public function test_an_item_needs_at_least_one_priced_variant(): void
    {
        $this->actingAs($this->admin)->post('/menu-items', $this->payload([
            $this->row('PER SHOT', ''),
            $this->row('PER BOTTLE', ''),
        ]))->assertSessionHasErrors('variants');

        $this->assertSame(0, MenuItem::count());
    }

    public function test_menu_management_still_shows_and_keeps_the_price_less_variant(): void
    {
        $item = $this->alfonso();
        [$shot, $bottle] = [$item->allVariants[0], $item->allVariants[1]];

        $this->actingAs($this->admin)->get("/menu-items/{$item->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('item.variants.0.name', 'PER SHOT')
                ->where('item.variants.0.price', null)
                ->where('item.variants.1.name', 'PER BOTTLE'));

        $this->actingAs($this->admin)->get('/menu-items')
            ->assertInertia(fn ($page) => $page->where('items.0.variants_count', 2));

        // Re-saving must update the price-less row, not create it again.
        $this->actingAs($this->admin)->put("/menu-items/{$item->id}", $this->payload([
            $this->row('PER SHOT', '', $shot->id),
            $this->row('PER BOTTLE', '780', $bottle->id),
        ], 1))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([$shot->id, $bottle->id], $item->fresh()->allVariants->pluck('id')->all());
    }

    public function test_removing_a_price_less_variant_deletes_it(): void
    {
        $item = $this->alfonso();
        $bottle = $item->allVariants->firstWhere('name', 'PER BOTTLE');

        $this->actingAs($this->admin)->put("/menu-items/{$item->id}", $this->payload([
            $this->row('PER BOTTLE', '780', $bottle->id),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['PER BOTTLE'], $item->fresh()->allVariants->pluck('name')->all());
    }

    public function test_a_price_less_variant_cannot_be_ordered(): void
    {
        $item = $this->alfonso();
        $shot = $item->allVariants->firstWhere('name', 'PER SHOT');

        $this->actingAs($this->admin)->post('/orders', [
            'order_type' => 'takeout',
            'items' => [['menu_item_id' => $item->id, 'menu_item_variant_id' => $shot->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.menu_item_variant_id');

        $this->assertSame(0, Order::count());
    }

    public function test_the_priced_variant_orders_at_its_price(): void
    {
        $item = $this->alfonso();
        $bottle = $item->allVariants->firstWhere('name', 'PER BOTTLE');

        $this->actingAs($this->admin)->post('/orders', [
            'order_type' => 'takeout',
            'items' => [['menu_item_id' => $item->id, 'menu_item_variant_id' => $bottle->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $line = Order::latest('id')->firstOrFail()->items()->firstOrFail();
        $this->assertSame($bottle->id, $line->menu_item_variant_id);
        $this->assertSame('780.00', (string) $line->unit_price);
    }
}
