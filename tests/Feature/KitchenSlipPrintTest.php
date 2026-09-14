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
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Kitchen tab's "Print" button — a prep ticket, never a billing
 * document. Confirms it carries prep details (items, quantities, weights,
 * cooking styles, special instructions) and who actually took the order
 * (not whoever clicks Print), while never leaking a price/total, and that
 * it skips the receipt flow's 58mm/80mm paper-size picker.
 */
class KitchenSlipPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $waiterWhoTookTheOrder;

    private User $cookWhoPrints;

    private MenuItem $adobo;

    private MenuItem $bangus;

    private CookingStyle $inihaw;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $spaceCategory = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $space = Space::create(['area_id' => $area->id, 'category_id' => $spaceCategory->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $this->waiterWhoTookTheOrder = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true, 'name' => 'Maria Cruz']);
        $this->cookWhoPrints = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true, 'name' => 'Juan Dela Cruz']);

        $category = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);
        $this->adobo = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);
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

        $this->order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '390.00',
            'area_id' => $area->id,
            'space_category_id' => $spaceCategory->id,
            'space_id' => $space->id,
            'created_by' => $this->waiterWhoTookTheOrder->id,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'menu_item_id' => $this->adobo->id,
            'item_name' => 'Adobo',
            'line_type' => LineType::Fixed,
            'unit_price' => '180.00',
            'quantity' => 2,
            'subtotal' => '360.00',
            'notes' => 'No sauce, extra rice',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'menu_item_id' => $this->bangus->id,
            'item_name' => 'Bangus',
            'line_type' => LineType::Weighed,
            'unit_price' => '0.00',
            'quantity' => 1,
            'subtotal' => '177.00',
            'weight_grams' => 600,
            'price_per_kilo_snapshot' => '295.00',
            'cooking_style_id' => $this->inihaw->id,
            'cooking_note' => 'Extra crispy',
        ]);
    }

    public function test_the_slip_shows_items_prep_details_and_who_actually_took_the_order(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();

        // Prep details.
        $response->assertSee('Adobo');
        $response->assertSee('2&times;', false);
        $response->assertSee('No sauce, extra rice');
        $response->assertSee('600g', false);
        $response->assertSee('Inihaw');
        $response->assertSee('Extra crispy');

        // The order taker, not whoever is clicking Print right now.
        $response->assertSee('Maria Cruz');
        $response->assertDontSee('Juan Dela Cruz');
    }

    public function test_the_slip_never_shows_prices_amounts_or_totals(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertDontSee('₱');
        $response->assertDontSee('360.00');
        $response->assertDontSee('390.00');
        $response->assertDontSee(__('Total'));
    }

    public function test_the_slip_skips_the_receipt_flows_paper_size_picker(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertDontSee(__('Paper').':', false);
        $response->assertDontSee(route('orders.print', ['order' => $this->order, 'paper' => '58mm']), false);
        $response->assertDontSee(route('orders.print', ['order' => $this->order, 'paper' => '80mm']), false);

        // afterprint sends staff back to the Kitchen tab, not a receipt page.
        $response->assertSee(route('kitchen.index'), false);
    }
}
