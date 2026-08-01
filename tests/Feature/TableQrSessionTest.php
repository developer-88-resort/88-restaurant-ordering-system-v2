<?php

namespace Tests\Feature;

use App\Enums\SpaceStatus;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableQrSessionTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $category = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $area->id, 'category_id' => $category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $menuCategory = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->item = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Bulalo',
            'price' => '450.00',
            'availability_status' => 'available',
        ]);
    }

    public function test_scanning_the_master_qr_opens_a_table_session_with_a_secure_token(): void
    {
        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertOk();

        $session = SpaceSession::where('space_id', $this->space->id)->first();
        $this->assertNotNull($session);
        $this->assertSame('active', $session->status);
        $this->assertNotNull($session->public_token);
        $this->assertSame(40, strlen($session->public_token));

        // The join URL/QR for companions is embedded on the page, and no
        // raw internal ID is exposed in it.
        $response->assertSee($session->public_token);
        $response->assertSee('Share Table QR');
    }

    public function test_rescanning_the_master_qr_reuses_the_same_active_session(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $this->get("/order/{$this->space->qr_token}");

        $this->assertSame(1, SpaceSession::where('space_id', $this->space->id)->count());
    }

    public function test_three_devices_scanning_the_child_qr_become_three_separate_guests(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $session = SpaceSession::where('space_id', $this->space->id)->first();

        // Each request without a guest cookie is a fresh device.
        $this->get("/table/{$session->public_token}")->assertOk();
        $this->get("/table/{$session->public_token}")->assertOk();

        $guests = $session->guestSessions()->orderBy('guest_number')->get();
        $this->assertCount(3, $guests, 'Master scan is Guest 1; each cookie-less child scan is a new guest.');
        $this->assertSame([1, 2, 3], $guests->pluck('guest_number')->all());
    }

    public function test_guest_orders_are_grouped_under_one_table_session_with_sequential_batches(): void
    {
        $orderPayload = fn (string $key) => [
            'idempotency_key' => $key,
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ];

        // Two different guests (no shared cookies) submit orders.
        $this->post("/order/{$this->space->qr_token}", $orderPayload('key-guest-1'))->assertRedirect();
        $this->post("/order/{$this->space->qr_token}", $orderPayload('key-guest-2'))->assertRedirect();

        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();
        $orders = $session->orders()->orderBy('batch_number')->get();

        $this->assertCount(2, $orders);
        $this->assertSame([1, 2], $orders->pluck('batch_number')->all());
        $this->assertSame($this->space->id, $orders->first()->space_id);
        $this->assertNotNull($orders->first()->guest_session_id);
        $this->assertNotSame(
            $orders->first()->guest_session_id,
            $orders->last()->guest_session_id,
            'Each device gets its own guest identity.'
        );
    }

    public function test_duplicate_submission_with_the_same_idempotency_key_creates_only_one_order(): void
    {
        $payload = [
            'idempotency_key' => 'double-tap-123',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ];

        $first = $this->post("/order/{$this->space->qr_token}", $payload);
        $second = $this->post("/order/{$this->space->qr_token}", $payload);

        $this->assertSame(1, Order::count());

        // Both submissions land on the SAME order's status page.
        $order = Order::firstOrFail();
        $first->assertRedirect(route('customer.orders.status', $order->public_token));
        $second->assertRedirect(route('customer.orders.status', $order->public_token));
    }

    public function test_the_same_device_resumes_its_guest_session_instead_of_duplicating(): void
    {
        $this->disableCookieEncryption();

        $first = $this->get("/order/{$this->space->qr_token}");
        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();
        $guest = $session->guestSessions()->firstOrFail();

        $cookieName = 'gs_'.substr($session->public_token, 0, 20);
        $cookie = collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === $cookieName);
        $this->assertNotNull($cookie, 'The guest cookie must be issued on first visit.');

        $this->withCookie($cookieName, $cookie->getValue())
            ->get("/order/{$this->space->qr_token}")
            ->assertOk();

        $this->assertSame(1, $session->guestSessions()->count(), 'Reopening on the same device must not create a second guest.');
        $this->assertSame($guest->id, $session->guestSessions()->first()->id);
    }

    public function test_a_closed_session_qr_shows_a_clear_message_and_accepts_no_orders(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();
        $session->close();

        $response = $this->get("/table/{$session->public_token}");

        $response->assertOk();
        $response->assertSee('This table session has ended');

        // And every guest token under it was deactivated with it.
        $this->assertSame(0, $session->guestSessions()->where('status', 'active')->count());
    }

    public function test_releasing_the_table_closes_its_dining_session(): void
    {
        $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();
        $this->assertTrue($session->isActive());

        // Staff frees the table (e.g. after settling the bill).
        $this->space->refresh()->setStatusWithSharedTables(SpaceStatus::Available);

        $this->assertFalse($session->fresh()->isActive());
    }

    public function test_a_guest_only_sees_their_own_previous_orders(): void
    {
        // Guest A orders (no cookie carried, so this is one device)...
        $this->post("/order/{$this->space->qr_token}", [
            'idempotency_key' => 'guest-a-order',
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        ]);

        // ...and a different device (Guest B) opens the menu.
        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertOk();
        $response->assertViewHas('previousOrders', fn ($orders) => $orders === []);
        // The shared table-level info (combined total) IS visible.
        $response->assertViewHas('sessionOrderCount', 1);
    }

    public function test_kitchen_tickets_show_the_table_batch_and_guest_labels(): void
    {
        $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        $staff = User::factory()->create(['role' => \App\Enums\UserRole::Staff, 'is_active' => true]);

        $response = $this->actingAs($staff)->get('/kitchen');

        $response->assertOk();
        $response->assertSee('Batch');
        $response->assertSee('Guest 1');
        $response->assertSee('Table 1');
    }

    public function test_the_child_qr_image_endpoint_serves_an_svg(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();

        $response = $this->get("/table/{$session->public_token}/qr.svg");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_guessing_an_invalid_session_token_shows_the_closed_page_not_an_error(): void
    {
        $response = $this->get('/table/definitely-not-a-real-token-000000000');

        $response->assertOk();
        $response->assertSee('This table session has ended');
    }
}
