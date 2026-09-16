<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where `orders.created_by`/`order_source` get set at the moment an order
 * is first created, across every channel that can create one — this is
 * what the Kitchen Order Slip's "Waiter" vs "Ordered By" distinction
 * ultimately depends on (see Order::isStaffCreated()), so it has to be
 * right at the source, not patched up at print time.
 */
class OrderCreationSourceTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $spaceCategory;

    private Space $space;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $this->spaceCategory = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $this->space = Space::create([
            'area_id' => $this->area->id,
            'category_id' => $this->spaceCategory->id,
            'name' => 'Table 1',
            'code' => 'T1',
            'qr_token' => str()->random(40),
            'status' => 'available',
            'sort_order' => 1,
        ]);

        $category = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);
        $this->item = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);
    }

    public function test_a_staff_manual_order_records_the_logged_in_creator_and_a_staff_source(): void
    {
        $waiter = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true, 'name' => 'Ken']);

        $response = $this->actingAs($waiter)->post('/orders', [
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->spaceCategory->id,
            'space_id' => $this->space->id,
            'items' => [
                ['menu_item_id' => $this->item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order = Order::latest('id')->first();

        $this->assertSame($waiter->id, $order->created_by);
        $this->assertSame(OrderSource::Staff, $order->order_source);
        $this->assertTrue($order->isStaffCreated());
    }

    /**
     * The actual bug this feature fixes: CustomerOrderController is public
     * and carries no `auth` middleware, but it used to write
     * `auth()->id()` into `created_by` anyway — so a QR order submitted
     * from a browser that happened to ALSO have a staff session active
     * (easy to hit while testing, or a staff member assisting a table)
     * silently got attributed to that staff member instead of staying a
     * customer self-order. Proven here by deliberately acting as a logged-in
     * staff user while hitting the customer-facing endpoint.
     */
    public function test_a_qr_order_never_records_the_incidentally_logged_in_user_as_its_creator(): void
    {
        $staffBrowsingTheQrPageWhileLoggedIn = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $response = $this->actingAs($staffBrowsingTheQrPageWhileLoggedIn)->post("/order/{$this->space->qr_token}", [
            'customer_name' => 'John',
            'items' => [
                ['menu_item_id' => $this->item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order = Order::latest('id')->first();

        $this->assertNull($order->created_by);
        $this->assertSame(OrderSource::Qr, $order->order_source);
        $this->assertFalse($order->isStaffCreated());
    }

    public function test_a_qr_order_from_a_genuinely_anonymous_guest_also_gets_a_null_creator_and_qr_source(): void
    {
        $response = $this->post("/order/{$this->space->qr_token}", [
            'customer_name' => 'Anna',
            'items' => [
                ['menu_item_id' => $this->item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order = Order::latest('id')->first();

        $this->assertNull($order->created_by);
        $this->assertSame(OrderSource::Qr, $order->order_source);
    }

    public function test_a_welcome_lobby_takeout_order_never_records_the_incidentally_logged_in_user_as_its_creator(): void
    {
        $staffBrowsingTheKioskWhileLoggedIn = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $response = $this->actingAs($staffBrowsingTheKioskWhileLoggedIn)->post('/welcome/takeout', [
            'customer_name' => 'Maria',
            'items' => [
                ['menu_item_id' => $this->item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order = Order::latest('id')->first();

        $this->assertNull($order->created_by);
        $this->assertSame(OrderSource::Qr, $order->order_source);
    }
}
