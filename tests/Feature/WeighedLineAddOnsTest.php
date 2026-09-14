<?php

namespace Tests\Feature;

use App\Enums\LineType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddOn;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Add-ons on weighed order lines — confirmed unsupported before this: a
 * weighed line is recorded through WeighedLineRecorder, a standalone
 * single-INSERT path that never touched the sibling-row machinery
 * OrderCreator/OrderAppender use for a fixed line's add-ons.
 */
class WeighedLineAddOnsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private MenuItem $bangus;

    private CookingStyle $inihaw;

    private MenuItemAddOn $crispyPata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $spaceCategory = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        Space::create(['area_id' => $area->id, 'category_id' => $spaceCategory->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $category = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);

        $this->bangus = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'min_weight_grams' => 250,
            'counter_only' => true,
            'availability_status' => 'available',
        ]);

        $this->inihaw = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);
        $this->bangus->cookingStyles()->attach($this->inihaw->id);

        $this->crispyPata = MenuItemAddOn::create([
            'menu_item_id' => $this->bangus->id,
            'name' => 'Crispy Pata',
            'price' => '900.00',
            'sort_order' => 0,
        ]);
    }

    private function makeOrder(): Order
    {
        return Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '0.00',
            'created_by' => $this->staff->id,
        ]);
    }

    /** 600 g @ ₱295/kg computes to exactly ₱177.00. */
    private function appendWeighed(Order $order, array $overrides = [])
    {
        return $this->actingAs($this->staff)->post("/orders/{$order->id}/items", array_merge([
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'net_grams' => 600,
            'amount_charged' => '177.00',
            'cooking_style_id' => $this->inihaw->id,
        ], $overrides));
    }

    public function test_a_weighed_line_can_carry_an_add_on_as_its_own_sibling_row(): void
    {
        $order = $this->makeOrder();

        $this->appendWeighed($order, [
            'add_ons' => [['id' => $this->crispyPata->id, 'quantity' => 1]],
        ])->assertRedirect();

        $order->refresh();
        $this->assertCount(2, $order->items);

        $parent = $order->items->firstWhere('line_type', LineType::Weighed);
        $addOnLine = $order->items->firstWhere('line_type', LineType::Fixed);

        $this->assertNotNull($parent);
        $this->assertNotNull($addOnLine);
        $this->assertSame($parent->id, $addOnLine->parent_order_item_id);
        $this->assertSame('+ Crispy Pata', $addOnLine->item_name);
        $this->assertSame('900.00', (string) $addOnLine->subtotal);

        // The weight-derived amount on the parent is untouched — the
        // add-on is summed in as its own line, never folded into it.
        $this->assertSame('177.00', (string) $parent->subtotal);
    }

    public function test_the_order_total_includes_the_add_on_on_top_of_the_weighed_amount(): void
    {
        $order = $this->makeOrder();

        $this->appendWeighed($order, [
            'add_ons' => [['id' => $this->crispyPata->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertSame('1977.00', (string) $order->fresh()->total_amount); // 177.00 + (900.00 * 2)
    }

    public function test_an_add_on_the_item_does_not_offer_is_rejected(): void
    {
        $otherItem = MenuItem::create([
            'menu_category_id' => $this->bangus->menu_category_id,
            'name' => 'Adobo', 'price' => '180.00', 'availability_status' => 'available',
        ]);
        $foreignAddOn = MenuItemAddOn::create(['menu_item_id' => $otherItem->id, 'name' => 'Extra Rice', 'price' => '30.00', 'sort_order' => 0]);

        $order = $this->makeOrder();

        $this->appendWeighed($order, [
            'add_ons' => [['id' => $foreignAddOn->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('add_ons.0.id');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_a_fixed_line_through_the_same_endpoint_also_gains_add_on_support(): void
    {
        $adobo = MenuItem::create([
            'menu_category_id' => $this->bangus->menu_category_id,
            'name' => 'Adobo', 'price' => '180.00', 'availability_status' => 'available',
        ]);
        $extraRice = MenuItemAddOn::create(['menu_item_id' => $adobo->id, 'name' => 'Extra Rice', 'price' => '30.00', 'sort_order' => 0]);

        $order = $this->makeOrder();

        $this->actingAs($this->staff)->post("/orders/{$order->id}/items", [
            'menu_item_id' => $adobo->id,
            'quantity' => 1,
            'add_ons' => [['id' => $extraRice->id, 'quantity' => 1]],
        ])->assertRedirect();

        $order->refresh();
        $this->assertCount(2, $order->items);
        $this->assertSame('210.00', (string) $order->total_amount); // 180.00 + 30.00
    }
}
