<?php

namespace Tests\Feature;

use App\Enums\SpaceStatus;
use App\Models\Area;
use App\Models\GuestSession;
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

    /**
     * POSTs to the master-QR identify endpoint and returns the cookie the
     * response set for the confirmed/created guest — the standard way
     * these tests establish "a guest with a known cookie" before checking
     * what a follow-up visit does with it.
     */
    private function identifyAndGetCookie(?string $name = null, ?string $guestToken = null): array
    {
        $response = $this->post("/order/{$this->space->qr_token}/identify", array_filter([
            'name' => $name,
            'guest_token' => $guestToken,
        ], fn ($v) => $v !== null));

        $response->assertRedirect("/order/{$this->space->qr_token}");

        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();
        $cookieName = \App\Services\TableSessionManager::cookieName($session);
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === $cookieName);
        $this->assertNotNull($cookie, 'Identify must issue the guest cookie.');

        return [$cookieName, $cookie->getValue(), $session];
    }

    public function test_a_fresh_scan_with_no_cookie_shows_the_identify_screen_not_the_menu(): void
    {
        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertOk();

        $session = SpaceSession::where('space_id', $this->space->id)->first();
        $this->assertNotNull($session, 'The table session itself opens on first contact, before any guest is identified.');
        $this->assertSame('active', $session->status);
        $this->assertNotNull($session->public_token);
        $this->assertSame(40, strlen($session->public_token));

        // No guest is minted just by landing here — only by identifying.
        $this->assertSame(0, $session->guestSessions()->count());

        $response->assertInertia(fn ($page) => $page
            ->component('Customer/Identify')
            ->where('existing_guests', [])
            ->where('identify_url', route('customer.spaces.identify', $this->space))
        );
    }

    public function test_rescanning_the_master_qr_reuses_the_same_active_session(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $this->get("/order/{$this->space->qr_token}");

        $this->assertSame(1, SpaceSession::where('space_id', $this->space->id)->count());
    }

    public function test_identifying_with_a_name_lands_on_the_menu_as_that_named_guest(): void
    {
        $this->disableCookieEncryption();

        [$cookieName, $cookieValue, $session] = $this->identifyAndGetCookie(name: 'Juan');

        $guest = $session->guestSessions()->firstOrFail();
        $this->assertSame('Juan', $guest->display_name);
        $this->assertSame(1, $guest->guest_number);

        $this->withCookie($cookieName, $cookieValue)
            ->get("/order/{$this->space->qr_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customer/Menu')
                ->where('guest_label', 'Juan')
            );
    }

    public function test_skipping_the_name_falls_back_to_the_plain_guest_number_label(): void
    {
        $this->disableCookieEncryption();

        [$cookieName, $cookieValue, $session] = $this->identifyAndGetCookie(name: '');

        $guest = $session->guestSessions()->firstOrFail();
        $this->assertNull($guest->display_name);

        $this->withCookie($cookieName, $cookieValue)
            ->get("/order/{$this->space->qr_token}")
            ->assertInertia(fn ($page) => $page->where('guest_label', 'Guest 1'));
    }

    public function test_a_valid_cookie_skips_the_identify_screen_entirely(): void
    {
        $this->disableCookieEncryption();

        [$cookieName, $cookieValue] = $this->identifyAndGetCookie(name: 'Juan');

        $this->withCookie($cookieName, $cookieValue)
            ->get("/order/{$this->space->qr_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Customer/Menu'));
    }

    public function test_a_second_device_sees_the_first_guest_as_a_confirmable_option(): void
    {
        $this->identifyAndGetCookie(name: 'Juan');

        // A different phone (no cookie at all) opens the same master QR.
        $response = $this->get("/order/{$this->space->qr_token}");

        $response->assertInertia(fn ($page) => $page
            ->component('Customer/Identify')
            ->has('existing_guests', 1)
            ->where('existing_guests.0.label', 'Juan')
        );
    }

    public function test_confirming_an_existing_guest_by_token_resumes_it_without_creating_a_duplicate(): void
    {
        $this->disableCookieEncryption();

        [, , $session] = $this->identifyAndGetCookie(name: 'Juan');
        $originalGuest = $session->guestSessions()->firstOrFail();

        // A second device confirms "yes, that's me" instead of typing a
        // name — exactly what the identify screen's guest list drives.
        [$cookieName, $cookieValue] = $this->identifyAndGetCookie(guestToken: $originalGuest->public_token);

        $this->assertSame(1, $session->guestSessions()->count(), 'Confirming an existing guest must not mint a second one.');
        $this->assertSame($originalGuest->id, $session->guestSessions()->first()->id);

        $this->withCookie($cookieName, $cookieValue)
            ->get("/order/{$this->space->qr_token}")
            ->assertInertia(fn ($page) => $page->where('guest_label', 'Juan'));
    }

    public function test_confirming_a_stale_guest_token_falls_back_to_minting_a_new_guest(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();

        $response = $this->post("/order/{$this->space->qr_token}/identify", [
            'guest_token' => 'this-token-does-not-exist-anywhere',
        ]);

        $response->assertRedirect("/order/{$this->space->qr_token}");
        $this->assertSame(1, $session->guestSessions()->count(), 'An unresolvable confirmation must still land the guest somewhere, not error out.');
    }

    public function test_three_devices_scanning_the_child_qr_become_three_separate_guests(): void
    {
        $this->identifyAndGetCookie(name: 'Juan'); // master scan = Guest 1
        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();

        // Each device without a guest cookie sees the identify screen on
        // the child QR too, and becomes the next guest once identified.
        $this->post("/table/{$session->public_token}/identify", ['name' => 'Maria'])->assertRedirect();
        $this->post("/table/{$session->public_token}/identify", [])->assertRedirect();

        $guests = $session->guestSessions()->orderBy('guest_number')->get();
        $this->assertCount(3, $guests);
        $this->assertSame([1, 2, 3], $guests->pluck('guest_number')->all());
        $this->assertSame(['Juan', 'Maria', null], $guests->pluck('display_name')->all());
    }

    public function test_the_child_qr_also_shows_identify_until_confirmed(): void
    {
        $this->get("/order/{$this->space->qr_token}");
        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();

        $this->get("/table/{$session->public_token}")
            ->assertInertia(fn ($page) => $page
                ->component('Customer/Identify')
                ->where('identify_url', route('customer.session.identify', $session->public_token))
            );
    }

    public function test_guest_orders_are_grouped_under_one_table_session_with_sequential_batches(): void
    {
        $orderPayload = fn (string $key) => [
            'idempotency_key' => $key,
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ];

        // Two different guests (no shared cookies) submit orders directly —
        // store() never blocks behind the identify screen.
        $this->post("/order/{$this->space->qr_token}", $orderPayload('key-guest-1'))->assertRedirect();
        $this->post("/order/{$this->space->qr_token}", $orderPayload('key-guest-2'))->assertRedirect();

        $session = SpaceSession::where('space_id', $this->space->id)->firstOrFail();
        $orders = $session->orders()->get();

        // One shared receipt for the table, not one per submission —
        // batching now lives on the order's items, not on separate order
        // rows.
        $this->assertCount(1, $orders);

        $order = $orders->first();
        $this->assertSame($this->space->id, $order->space_id);

        $items = $order->items()->orderBy('batch_number')->get();
        $this->assertSame([1, 2], $items->pluck('batch_number')->all());

        $guestIds = $items->pluck('ordered_by_guest_id')->unique()->values();
        $this->assertCount(2, $guestIds, "Each device's line is attributed to its own guest.");
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

    public function test_a_closed_session_qr_shows_a_clear_message_and_accepts_no_orders(): void
    {
        $this->identifyAndGetCookie(name: 'Juan');
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

        // ...and a different device (Guest B) identifies and opens the menu.
        $this->disableCookieEncryption();
        [$cookieName, $cookieValue] = $this->identifyAndGetCookie(name: 'Maria');

        $response = $this->withCookie($cookieName, $cookieValue)->get("/order/{$this->space->qr_token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('previous_orders', [])
            // The shared table-level info (combined total) IS visible.
            ->where('session_order_count', 1)
        );
    }

    public function test_kitchen_tickets_show_the_table_batch_and_guest_labels(): void
    {
        $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        // A second round (a different device/guest, no cookie carried) so
        // the ticket has more than one batch — the board only labels
        // batches when there's more than one to tell apart.
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

    /**
     * Regression test for the production incident (2026-09-14): the guest
     * cookie's NAME used to embed the session's own random public_token,
     * so every new session opened on the same physical table QR left
     * behind a brand-new, never-cleaned-up cookie. A phone re-scanning the
     * same table across many sessions over time accumulated an
     * ever-growing pile of them, until the cumulative Cookie header was
     * large enough to trip PHP-FPM/nginx's response header buffer
     * ("upstream sent too big header"). Cookie names must stay stable per
     * table (space), reused across however many sessions that table goes
     * through, not minted fresh every time.
     */
    public function test_the_guest_cookie_name_stays_the_same_across_a_tables_successive_sessions(): void
    {
        $this->disableCookieEncryption();

        [$firstCookieName] = $this->identifyAndGetCookie(name: 'Juan');
        $firstSession = SpaceSession::where('space_id', $this->space->id)->firstOrFail();

        // An order (occupying the table) then staff freeing it is what
        // actually closes a session — see test_releasing_the_table_closes_its_dining_session().
        $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);
        $this->space->refresh()->setStatusWithSharedTables(SpaceStatus::Available);
        $this->assertFalse($firstSession->fresh()->isActive());

        // ...and a brand-new party scans the SAME physical QR, opening a
        // new session with its own new random public_token.
        [$secondCookieName] = $this->identifyAndGetCookie(name: 'Maria');
        $secondSession = SpaceSession::where('space_id', $this->space->id)->where('status', 'active')->firstOrFail();

        $this->assertNotSame($firstSession->public_token, $secondSession->public_token, 'A new session must not reuse the old one\'s token.');
        $this->assertSame($firstCookieName, $secondCookieName, 'The cookie NAME must stay stable per table across successive sessions, not mint a new one every time.');
    }
}
