<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\KitchenUpdated;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerOrderingTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Table 1', 'status' => SpaceStatus::Available, 'sort_order' => 1]);

        $menuCategory = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);
        $this->item = MenuItem::create(['menu_category_id' => $menuCategory->id, 'name' => 'Samgyupsal', 'price' => 350]);
    }

    public function test_available_space_serves_the_menu(): void
    {
        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Customer/Menu')
            ->where('categories.0.items.0.name', 'Samgyupsal')
        );
    }

    public function test_occupied_space_still_serves_the_menu(): void
    {
        $this->space->update(['status' => SpaceStatus::Occupied]);

        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertOk()->assertInertia(fn ($page) => $page->component('Customer/Menu'));
    }

    public function test_maintenance_disabled_and_reserved_spaces_block_ordering(): void
    {
        foreach ([SpaceStatus::Maintenance, SpaceStatus::Disabled, SpaceStatus::Reserved] as $status) {
            $this->space->update(['status' => $status]);

            $response = $this->get("/order/{$this->space->qr_token}");

            $response->assertOk()->assertViewIs('customer.space-unavailable');
        }
    }

    public function test_an_inactive_area_blocks_ordering(): void
    {
        $this->area->update(['is_active' => false]);

        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertOk()->assertViewIs('customer.space-unavailable');
    }

    public function test_a_full_guest_order_submission_creates_a_correctly_shaped_order(): void
    {
        $response = $this->post("/order/{$this->space->qr_token}", [
            'notes' => 'no ice please',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        ]);

        $order = Order::latest()->first();

        $this->assertNotNull($order);
        $this->assertMatchesRegularExpression('/^88-\d{4}-\d{3}$/', $order->order_number);
        $this->assertNull($order->created_by);
        $this->assertSame($this->space->id, $order->space_id);
        $this->assertSame(OrderType::DineIn, $order->order_type);
        $this->assertSame('700.00', $order->total_amount);
        $this->assertSame('no ice please', $order->notes);
        $this->assertNotNull($order->public_token);
        $response->assertRedirect(route('customer.orders.status', $order->public_token));
        $this->assertSame(SpaceStatus::Occupied, $this->space->fresh()->status);
    }

    public function test_submitting_an_unavailable_menu_item_fails_validation_and_creates_no_order(): void
    {
        $this->item->update(['availability_status' => 'out_of_stock']);

        $response = $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('items.0.menu_item_id');
        $this->assertSame(0, Order::count());
    }

    public function test_status_page_404s_for_an_unknown_token_and_200s_for_a_real_one(): void
    {
        $order = $this->createOrder();

        $this->get("/order/status/{$order->public_token}")->assertOk()->assertInertia(fn ($page) => $page->component('Customer/Status'));
        $this->get('/order/status/not-a-real-token')->assertNotFound();
    }

    public function test_updating_order_status_dispatches_both_broadcast_events(): void
    {
        Event::fake([CustomerOrderStatusUpdated::class, KitchenUpdated::class]);

        $order = $this->createOrder();
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($staff)
            ->patch("/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertRedirect();

        Event::assertDispatched(KitchenUpdated::class);
        Event::assertDispatched(CustomerOrderStatusUpdated::class, fn ($event) => $event->order->is($order));
    }

    public function test_receipt_page_404s_until_the_order_is_marked_as_paid(): void
    {
        $order = $this->createOrder();
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->get("/order/receipt/{$order->public_token}")->assertNotFound();

        $this->actingAs($staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'payment_method' => 'cash',
            'amount_received' => $order->total_amount,
        ]);

        $response = $this->get("/order/receipt/{$order->public_token}");

        $response->assertOk()->assertViewIs('customer.receipt')
            ->assertSee($order->fresh()->receipt_number)
            ->assertSee($order->orderNumber())
            ->assertSee('Cash');

        $this->get('/order/receipt/not-a-real-token')->assertNotFound();
    }

    public function test_marking_as_paid_and_voiding_both_dispatch_the_customer_broadcast(): void
    {
        Event::fake([CustomerOrderStatusUpdated::class]);

        $order = $this->createOrder();
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'payment_method' => 'cash',
            'amount_received' => $order->total_amount,
        ]);

        Event::assertDispatched(CustomerOrderStatusUpdated::class, fn ($event) => $event->order->is($order));

        $this->actingAs($staff)->patch("/orders/{$order->id}/void-payment", [
            'void_reason' => 'Test void',
        ]);

        Event::assertDispatched(CustomerOrderStatusUpdated::class, 2);
    }

    public function test_staff_order_creation_still_works_after_the_order_creator_extraction(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $response = $this->actingAs($staff)->post('/orders', [
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        $order = Order::latest()->first();

        $this->assertNotNull($order);
        $this->assertMatchesRegularExpression('/^88-\d{4}-\d{3}$/', $order->order_number);
        $this->assertSame($staff->id, $order->created_by);
        $this->assertSame($this->space->id, $order->space_id);
        $this->assertSame(SpaceStatus::Occupied, $this->space->fresh()->status);
        $response->assertRedirect(route('orders.show', $order));
    }

    public function test_a_per_kilo_item_cannot_be_added_as_a_fixed_line_via_staff_order_creation(): void
    {
        $menuCategory = MenuCategory::create(['name' => 'Seafood', 'sort_order' => 2, 'is_active' => true]);
        $bangus = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => 320,
        ]);
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $response = $this->actingAs($staff)->post('/orders', [
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'items' => [['menu_item_id' => $bangus->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    public function test_a_per_kilo_item_cannot_be_added_as_a_fixed_line_via_customer_ordering(): void
    {
        $menuCategory = MenuCategory::create(['name' => 'Seafood', 'sort_order' => 2, 'is_active' => true]);
        $bangus = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => 320,
        ]);

        $response = $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $bangus->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    public function test_new_order_screen_shows_per_kilo_items_in_their_own_section_with_no_flat_price(): void
    {
        $menuCategory = MenuCategory::create(['name' => 'Seafood', 'sort_order' => 2, 'is_active' => true]);
        MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => 320,
        ]);
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $response = $this->actingAs($staff)->get('/orders/create');

        $response->assertOk()
            ->assertSee('Fresh / By the Kilo')
            ->assertSee('Bangus')
            ->assertSee('Weigh &amp; Order', false);

        // The per-kilo item must never render inside the normal
        // fixed-price "Add to order" button markup.
        $response->assertDontSeeText('Bangus₱0.00');
    }

    public function test_a_zero_total_order_is_rejected(): void
    {
        $menuCategory = MenuCategory::create(['name' => 'Promo', 'sort_order' => 3, 'is_active' => true]);
        $freeItem = MenuItem::create(['menu_category_id' => $menuCategory->id, 'name' => 'Freebie', 'price' => 0]);
        $staff = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $response = $this->actingAs($staff)->post('/orders', [
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'items' => [['menu_item_id' => $freeItem->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    private function createOrder(): Order
    {
        $response = $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        return Order::latest()->first();
    }
}
