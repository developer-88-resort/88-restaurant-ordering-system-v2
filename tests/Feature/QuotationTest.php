<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use App\Services\TableSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private MenuItem $item;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $category = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $area->id, 'category_id' => $category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $menuCategory = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->item = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Lechon Belly',
            'price' => '500.00',
            'availability_status' => 'available',
        ]);

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
    }

    // -----------------------------------------------------------------
    // Test 1 — a table with one open receipt
    // -----------------------------------------------------------------
    public function test_joining_the_tables_single_open_receipt_does_not_increase_the_order_count(): void
    {
        $existing = $this->openOrder(['Rice' => '200.00']);

        $response = $this->submit(['target' => $existing->id]);
        $response->assertRedirect();

        $this->assertSame(1, Order::count(), 'Must join the existing order, not create a second one.');

        $quotation = Quotation::latest('id')->firstOrFail();
        $this->assertSame(QuotationStatus::Added, $quotation->status);
        $this->assertSame($existing->id, $quotation->converted_order_id);

        $existing->refresh();
        $this->assertSame('1200.00', $existing->total_amount, '200 existing + 1000 (500x2 quoted) = 1200.');
        $this->assertSame(2, $existing->items()->max('batch_number'), 'The advance order lines land as a new batch.');
        $this->assertTrue(
            $existing->items->contains(fn ($item) => str_contains((string) $item->notes, $quotation->quotation_number)),
            'The appended lines must carry the advance-order tag.'
        );
    }

    // -----------------------------------------------------------------
    // Test 2 — a table with no open receipt
    // -----------------------------------------------------------------
    public function test_starting_a_new_receipt_increases_the_order_count_by_exactly_one(): void
    {
        $response = $this->submit(['target' => 'new']);
        $response->assertRedirect();

        $this->assertSame(1, Order::count());

        $quotation = Quotation::latest('id')->firstOrFail();
        $order = $quotation->convertedOrder;

        $this->assertSame(QuotationStatus::Added, $quotation->status);
        $this->assertSame('1000.00', $order->total_amount, '500 x 2 quoted.');
        $this->assertSame('500.00', $order->items->first()->unit_price);
        $this->assertSame(1, $order->items->first()->batch_number);

        // Menu price rising after the quote must not affect it.
        $this->item->update(['price' => '999.00']);
        $this->assertSame('500.00', $order->items->first()->refresh()->unit_price);
    }

    // -----------------------------------------------------------------
    // Test 3 — shared table, two open receipts from different parties
    // -----------------------------------------------------------------
    public function test_a_shared_table_requires_an_explicit_choice_and_only_the_chosen_receipt_changes(): void
    {
        $partyA = $this->openOrder(['Rice A' => '100.00'], customerName: 'Party A');
        $partyB = $this->openOrder(['Rice B' => '150.00'], customerName: 'Party B');

        // Omitting the choice entirely is rejected — no auto-merge.
        $missing = $this->submit(['target' => null], assertSuccess: false);
        $missing->assertInvalid('target');
        $this->assertSame(2, Order::count());

        $this->submit(['target' => $partyB->id])->assertRedirect();

        $partyA->refresh();
        $partyB->refresh();

        $this->assertSame('100.00', $partyA->total_amount, 'The untouched party must stay exactly as it was.');
        $this->assertSame('1150.00', $partyB->total_amount, '150 existing + 1000 quoted.');
        $this->assertSame(2, Order::count(), 'Still two separate receipts — never merged.');
    }

    // -----------------------------------------------------------------
    // Test 4 — a closed session's leftover order must not be joinable
    // -----------------------------------------------------------------
    public function test_a_stale_closed_session_order_is_excluded_and_cannot_be_joined(): void
    {
        $staleSession = SpaceSession::create([
            'space_id' => $this->space->id,
            'category_id' => $this->space->category_id,
            'status' => 'active',
            'public_token' => Str::random(40),
        ]);
        $staleOrder = Order::create([
            'order_number' => OrderNumberGenerator::generate(),
            'order_type' => OrderType::DineIn,
            'area_id' => $this->space->area_id,
            'space_category_id' => $this->space->category_id,
            'space_id' => $this->space->id,
            'space_session_id' => $staleSession->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '0.00',
        ]);
        $staleSession->close();

        // A brand new session opens for the same table.
        TableSessionManager::findOrOpenFor($this->space->fresh());

        $panel = $this->actingAs($this->staff)->getJson(route('quotations.table-receipts', $this->space));
        $panel->assertOk();
        $this->assertNotContains(
            $staleOrder->id,
            collect($panel->json('open_orders'))->pluck('id')->all(),
            'A closed session\'s leftover order must not appear as a joinable receipt.'
        );

        $rejected = $this->submit(['target' => $staleOrder->id], assertSuccess: false);
        $rejected->assertStatus(422);
        $this->assertSame(0, $staleOrder->items()->count(), 'Nothing was appended to the stale order.');
        $this->assertSame(0, Quotation::count(), 'The whole attempt rolled back — no orphaned quotation.');
    }

    // -----------------------------------------------------------------
    // Test 5 — a paid receipt cannot be targeted
    // -----------------------------------------------------------------
    public function test_a_paid_receipt_cannot_be_targeted(): void
    {
        $paid = $this->openOrder(['Rice' => '200.00']);
        $paid->update(['payment_status' => PaymentStatus::Paid]);

        $response = $this->submit(['target' => $paid->id], assertSuccess: false);
        $response->assertStatus(422)->assertInvalid('target');

        $this->assertSame(1, $paid->items()->count(), 'Nothing was appended to the paid order.');
    }

    // -----------------------------------------------------------------
    // Test 6 — a double-click submit must not add the batch twice
    // -----------------------------------------------------------------
    public function test_a_duplicate_submit_with_the_same_request_id_is_idempotent(): void
    {
        $requestId = (string) Str::uuid();

        $this->submit(['target' => 'new', 'request_id' => $requestId])->assertRedirect();
        $this->submit(['target' => 'new', 'request_id' => $requestId])->assertRedirect();

        $this->assertSame(1, Quotation::count());
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Order::first()->items()->count());
    }

    // -----------------------------------------------------------------
    // Test 7a — choosing "new receipt" always gets its own standalone
    // order, future-scheduled or not
    // -----------------------------------------------------------------
    public function test_choosing_new_receipt_with_a_future_schedule_still_creates_a_standalone_order(): void
    {
        $today = $this->openOrder(['Rice' => '200.00']);

        $this->submit(['target' => 'new', 'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s')])
            ->assertRedirect();

        $this->assertSame(2, Order::count());
        $quotation = Quotation::latest('id')->firstOrFail();
        $this->assertNotSame($today->id, $quotation->converted_order_id);

        $upcomingOrder = $quotation->convertedOrder;
        $this->assertSame(1, $upcomingOrder->items()->first()->batch_number);

        // Advance orders land straight in the normal pending lane the
        // moment they're converted — no separate holding area, see
        // KitchenTest for the badge that flags them as advance orders.
        $kitchen = $this->actingAs($this->staff)->get('/kitchen');
        $kitchen->assertOk();
        $kitchen->assertViewHas('pending', fn ($pending) => $pending->contains('id', $upcomingOrder->id));
    }

    // -----------------------------------------------------------------
    // Test 7b — regression test for the reported bug: explicitly choosing
    // an existing receipt must be honoured even when the schedule is a
    // little in the future. Server used to silently override the staff's
    // choice and create a second order instead.
    // -----------------------------------------------------------------
    public function test_choosing_an_existing_receipt_with_a_near_future_schedule_still_joins_it(): void
    {
        // Mirrors the exact reported repro: one ₱445 line already on the
        // table's open receipt via QR, staff explicitly picks that same
        // receipt in Hakbang 3, then adds another ₱445 line scheduled 8
        // minutes out.
        $chicaron = MenuItem::create([
            'menu_category_id' => $this->item->menu_category_id,
            'name' => 'Chicaron Baboy Bulaklak',
            'price' => '445.00',
            'availability_status' => 'available',
        ]);
        $existing = $this->openOrder(['Chicaron Baboy Bulaklak' => '445.00']);
        $scheduledFor = now()->addMinutes(8)->format('Y-m-d H:i:s');

        $this->submit([
            'target' => $existing->id,
            'scheduled_for' => $scheduledFor,
            'items' => [['menu_item_id' => $chicaron->id, 'quantity' => 1]],
        ])->assertRedirect();

        $this->assertSame(1, Order::count(), 'Must join the chosen receipt, not create a second order.');

        $existing->refresh();
        $this->assertSame('890.00', $existing->total_amount, '445 existing + 445 quoted = 890.');
        $this->assertSame(2, $existing->items()->count(), 'The seed line plus the newly appended one.');

        $newLine = $existing->items()->latest('id')->first();
        $this->assertSame(2, $newLine->batch_number);
        $this->assertNotNull($newLine->scheduled_for);
        $this->assertTrue($newLine->scheduled_for->equalTo(\Illuminate\Support\Carbon::parse($scheduledFor)));
        $this->assertStringContainsString('Advance order', (string) $newLine->notes);

        // The idempotency row must reference the RIGHT order, not a
        // phantom newly-created one.
        $this->assertSame(
            $existing->id,
            \App\Models\AppendIdempotencyKey::latest('id')->first()->order_id
        );
    }

    // -----------------------------------------------------------------
    // Explicitly choosing "BAGONG PARTIDO" on a table that already has an
    // open receipt must create its own order and leave the existing one
    // untouched — the explicit choice always wins, even when a receipt
    // was available to join instead.
    // -----------------------------------------------------------------
    public function test_choosing_new_party_on_a_table_with_an_existing_open_receipt_creates_a_separate_order(): void
    {
        $existing = $this->openOrder(['Rice' => '200.00']);

        $this->submit(['target' => 'new'])->assertRedirect();

        $this->assertSame(2, Order::count());

        $existing->refresh();
        $this->assertSame('200.00', $existing->total_amount, 'The existing receipt must be completely untouched.');
        $this->assertSame(1, $existing->items()->count());

        $quotation = Quotation::latest('id')->firstOrFail();
        $this->assertNotSame($existing->id, $quotation->converted_order_id);
    }

    // -----------------------------------------------------------------
    // A cancelled receipt cannot be targeted, same as a paid one.
    // -----------------------------------------------------------------
    public function test_a_cancelled_receipt_cannot_be_targeted(): void
    {
        $cancelled = $this->openOrder(['Rice' => '200.00']);
        $cancelled->update(['status' => \App\Enums\OrderStatus::Cancelled]);

        $response = $this->submit(['target' => $cancelled->id], assertSuccess: false);
        $response->assertStatus(422)->assertInvalid('target');

        $this->assertSame(1, $cancelled->items()->count(), 'Nothing was appended to the cancelled order.');
    }

    // -----------------------------------------------------------------
    // CRITICAL regression for the idempotency-key overflow bug: a
    // multi-line batch must produce one DISTINCT key per line. Under the
    // old packed "{uuid}:{index}" CHAR(36) column, a batch of 3+ lines
    // either 500'd (strict SQL mode) or silently collapsed every line's
    // key to the same 36 characters (relaxed mode) — which would make the
    // idempotency guard treat lines 2 and 3 as replays of line 1 and
    // silently drop them. A single-line test cannot catch this.
    // -----------------------------------------------------------------
    public function test_a_three_line_advance_order_writes_three_distinct_keys_and_three_order_items(): void
    {
        $sinigang = MenuItem::create([
            'menu_category_id' => $this->item->menu_category_id,
            'name' => 'Sinigang',
            'price' => '250.00',
            'availability_status' => 'available',
        ]);
        $rice = MenuItem::create([
            'menu_category_id' => $this->item->menu_category_id,
            'name' => 'Rice',
            'price' => '50.00',
            'availability_status' => 'available',
        ]);

        $this->submit([
            'target' => 'new',
            'items' => [
                ['menu_item_id' => $this->item->id, 'quantity' => 1],
                ['menu_item_id' => $sinigang->id, 'quantity' => 1],
                ['menu_item_id' => $rice->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $order = Quotation::latest('id')->firstOrFail()->convertedOrder;

        $this->assertSame(3, $order->items()->count());
        $this->assertSame(3, \App\Models\AppendIdempotencyKey::where('order_id', $order->id)->count());

        $keys = \App\Models\AppendIdempotencyKey::where('order_id', $order->id)
            ->get()
            ->map(fn ($k) => "{$k->request_uuid}:{$k->line_index}")
            ->unique();
        $this->assertCount(3, $keys, 'Each line must have its own distinct (request_uuid, line_index) pair.');
        $this->assertSame([0, 1, 2], \App\Models\AppendIdempotencyKey::where('order_id', $order->id)->orderBy('line_index')->pluck('line_index')->all());
    }

    // -----------------------------------------------------------------
    // Test 8 — a weight-priced (counter-only) item can never be quoted
    // -----------------------------------------------------------------
    public function test_a_counter_only_item_is_rejected_with_the_exact_required_message(): void
    {
        $bangus = MenuItem::create([
            'menu_category_id' => $this->item->menu_category_id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '450.00',
            'availability_status' => 'available',
        ]);
        $this->assertTrue($bangus->counter_only, 'Sanity check: per-kilo items auto-flag counter_only.');

        $response = $this->actingAs($this->staff)->postJson(route('quotations.store'), [
            'space_id' => $this->space->id,
            'target' => 'new',
            'request_id' => (string) Str::uuid(),
            'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $bangus->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            'it cannot be ordered unless youve call the staff or thru personal ordering',
            $response->json('errors')['items.0.menu_item_id'][0] ?? null
        );
        $this->assertSame(0, Quotation::count());
    }

    // -----------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------
    public function test_quotation_pages_render_via_inertia(): void
    {
        $this->submit(['target' => 'new'])->assertRedirect();
        $quotation = Quotation::latest('id')->firstOrFail();

        $this->actingAs($this->staff)->get('/quotations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Index')
                ->has('quotations', 1)
                ->where('quotations.0.quotation_number', $quotation->quotation_number));

        $this->actingAs($this->staff)->get('/quotations/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Create')
                ->has('areas')
                ->has('categories'));

        $this->actingAs($this->staff)->get("/quotations/{$quotation->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Show')
                ->where('quotation.status', 'converted')
                ->where('quotation.items.0.item_name', 'Lechon Belly'));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * @param  array<string, string>  $items  item name => unit price
     */
    private function openOrder(array $items, ?string $customerName = null): Order
    {
        $order = Order::create([
            'order_number' => OrderNumberGenerator::generate(),
            'order_type' => OrderType::DineIn,
            'area_id' => $this->space->area_id,
            'space_category_id' => $this->space->category_id,
            'space_id' => $this->space->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '0.00',
            'customer_name' => $customerName,
        ]);

        $total = '0.00';
        foreach ($items as $name => $price) {
            $order->items()->create([
                'item_name' => $name,
                'unit_price' => $price,
                'quantity' => 1,
                'subtotal' => $price,
                'line_type' => 'fixed',
                'batch_number' => 1,
            ]);
            $total = bcadd($total, $price, 2);
        }
        $order->update(['total_amount' => $total]);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = [], bool $assertSuccess = true)
    {
        $payload = array_merge([
            'space_id' => $this->space->id,
            'target' => 'new',
            'request_id' => (string) Str::uuid(),
            'customer_name' => 'Chairman Guest',
            // In the past by the time it's evaluated — i.e. not a future
            // reservation, so it's eligible to join whatever's already open.
            'scheduled_for' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        ], $overrides);

        $response = $this->actingAs($this->staff)->postJson(route('quotations.store'), $payload);

        if ($assertSuccess) {
            $response->assertSessionHasNoErrors();
        }

        return $response;
    }
}
