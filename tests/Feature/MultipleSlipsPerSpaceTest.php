<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrinterJobStatus;
use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PrinterJob;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultipleSlipsPerSpaceTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $cottage;

    private MenuItem $sisig;

    private MenuItem $rice;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        config(['printing.bridge_token' => 'bridge-secret']);

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottage', 'slug' => 'cottage', 'is_active' => true]);
        $this->cottage = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Cottage 3', 'status' => 'available', 'sort_order' => 1]);

        $menu = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->sisig = MenuItem::create(['menu_category_id' => $menu->id, 'name' => 'Sisig', 'price' => '220.00', 'availability_status' => 'available']);
        $this->rice = MenuItem::create(['menu_category_id' => $menu->id, 'name' => 'Garlic Rice', 'price' => '50.00', 'availability_status' => 'available']);

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
    }

    public function test_each_new_order_for_an_occupied_table_becomes_its_own_numbered_slip(): void
    {
        $first = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);

        $this->assertSame(1, $first->slip_number);
        $this->assertSame(SpaceStatus::Occupied, $this->cottage->fresh()->status);
        $this->assertNotNull($first->space_session_id, 'A table order opens the table tab.');

        // The table is Occupied now — and still takes a new order.
        $second = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 2]]);

        $this->assertNotSame($first->id, $second->id, 'A second submission is a new slip, not lines on the first.');
        $this->assertSame(2, $second->slip_number);
        $this->assertSame($first->space_session_id, $second->space_session_id);
        $this->assertSame('Cottages - Cottage 3 — Slip #2', $second->slipLocationLabel());

        $this->assertSame(1, $first->items()->count(), 'Slip #1 is untouched.');
        $this->assertSame('220.00', $first->fresh()->total_amount);
        $this->assertSame('100.00', $second->total_amount);
    }

    /**
     * Placing an order never prints by itself (the user's call, 2026-09-17):
     * a slip goes to paper only when someone presses Print or Direct Print on
     * the Kitchen tab.
     */
    public function test_placing_orders_never_prints_until_direct_print_is_pressed_on_the_kitchen_tab(): void
    {
        $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);
        $second = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);

        $this->assertSame(0, PrinterJob::count());

        $this->actingAs($this->staff)
            ->postJson(route('orders.kitchen-slip.print-thermal', $second))
            ->assertOk()
            ->assertJson(['queued' => true]);

        $job = PrinterJob::sole();
        $this->assertSame($second->id, $job->order_id);
        $this->assertContains(['Slip', '#2'], $job->payload['meta']);
        $this->assertStringContainsString('#2', $job->payload['html']);
    }

    public function test_staff_can_deliberately_add_to_an_open_slip_instead(): void
    {
        $slipOne = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);

        $this->actingAs($this->staff)->post(route('orders.store'), $this->orderPayload(
            [['menu_item_id' => $this->rice->id, 'quantity' => 2]],
            ['target_order_id' => $slipOne->id, 'notes' => 'Extra rice for kids'],
        ))->assertRedirect(route('orders.show', $slipOne))->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count(), 'Adding to a slip never opens another.');
        $slipOne->refresh();
        $this->assertSame('320.00', $slipOne->total_amount);
        $this->assertSame([1, 2], $slipOne->items()->pluck('batch_number')->unique()->sort()->values()->all());
        $this->assertSame('Extra rice for kids', $slipOne->notes);
        $this->assertSame(0, PrinterJob::count(), 'Adding a round does not print.');
    }

    public function test_a_slip_that_can_no_longer_take_items_is_rejected_as_a_target(): void
    {
        $slipOne = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);
        $slipOne->update(['payment_status' => PaymentStatus::Paid]);

        $otherTable = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Cottage 4', 'status' => 'available', 'sort_order' => 2]);
        $otherSlip = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]], $otherTable);

        foreach ([$slipOne->id, $otherSlip->id] as $targetId) {
            $this->actingAs($this->staff)
                ->post(route('orders.store'), $this->orderPayload([['menu_item_id' => $this->rice->id, 'quantity' => 1]], ['target_order_id' => $targetId]))
                ->assertSessionHasErrors('target_order_id');
        }

        $this->assertSame(2, Order::count());
    }

    public function test_reserved_and_maintenance_tables_still_refuse_new_orders(): void
    {
        foreach ([SpaceStatus::Reserved, SpaceStatus::Maintenance, SpaceStatus::Disabled] as $status) {
            $this->cottage->update(['status' => $status]);

            $this->actingAs($this->staff)
                ->post(route('orders.store'), $this->orderPayload([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]))
                ->assertSessionHasErrors('space_id');
        }

        $this->assertSame(0, Order::count());
    }

    public function test_qr_rounds_and_staff_orders_share_the_tab_and_keep_counting(): void
    {
        $this->post("/order/{$this->cottage->qr_token}", [
            'idempotency_key' => 'qr-round-1',
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 1]],
        ])->assertRedirect();

        // A double-tap of the same QR submit stays one slip.
        $this->post("/order/{$this->cottage->qr_token}", [
            'idempotency_key' => 'qr-round-1',
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 1]],
        ])->assertRedirect();

        $staffSlip = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);

        $this->assertSame(2, Order::count());
        $this->assertSame(2, $staffSlip->slip_number);
        $this->assertCount(1, SpaceSession::where('space_id', $this->cottage->id)->get());
        $this->assertSame(0, PrinterJob::count(), 'QR orders do not print by themselves either.');
    }

    public function test_the_kitchen_shows_each_slip_as_its_own_card_with_slip_number_and_time(): void
    {
        $first = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);
        $second = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);
        $first->update(['status' => OrderStatus::Preparing]);

        $response = $this->actingAs($this->staff)->get(route('kitchen.index'))->assertOk();

        $response->assertSeeInOrder([__('New Orders'), $second->orderNumber(), 'Slip #2', __('In Progress'), $first->orderNumber(), 'Slip #1']);
        $response->assertSee(__('Submitted').' '.$second->created_at->format('g:i A'));
        // Each card names the table's other open slip.
        $response->assertSee('Slip #1 (Preparing)');
        $response->assertSee('Slip #2 (Pending)');
    }

    public function test_finishing_one_slip_keeps_the_table_occupied_until_the_last_one_is_done(): void
    {
        $first = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);
        $second = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);

        $this->actingAs($this->staff)->patch(route('orders.update-status', $first), ['status' => 'completed']);
        $this->assertSame(SpaceStatus::Occupied, $this->cottage->fresh()->status, 'Slip #2 is still open.');
        $this->assertTrue($first->spaceSession->fresh()->isActive());

        $this->actingAs($this->staff)->patch(route('orders.update-status', $second), ['status' => 'cancelled']);
        $this->assertSame(SpaceStatus::Available, $this->cottage->fresh()->status);
        $this->assertFalse($first->spaceSession->fresh()->isActive(), 'Releasing the table closes its tab.');
    }

    public function test_order_management_lists_open_slips_by_table_and_new_order_offers_them(): void
    {
        $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);
        $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);

        $this->actingAs($this->staff)->get(route('orders.index'))
            ->assertOk()
            ->assertSee(__('Open slips by table'))
            ->assertSee('Cottages - Cottage 3 — Slip #2')
            ->assertSee(route('orders.create', ['space' => $this->cottage->id]), false);

        $this->actingAs($this->staff)->get(route('orders.create', ['space' => $this->cottage->id]))
            ->assertOk()
            ->assertSee('2 open slips')
            ->assertSee('Slip #1')
            ->assertViewHas('preselect', [
                'areaId' => $this->area->id,
                'categoryId' => $this->category->id,
                'spaceId' => $this->cottage->id,
            ]);
    }

    public function test_weigh_and_order_new_slip_opens_a_slip_with_the_weighed_line_once(): void
    {
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);
        $tilapia = MenuItem::create([
            'menu_category_id' => $this->sisig->menu_category_id,
            'name' => 'Tilapia',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '300.00',
            'min_weight_grams' => 200,
            'counter_only' => true,
            'availability_status' => 'available',
        ]);
        $tilapia->cookingStyles()->attach($style->id);

        $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);

        $key = (string) Str::uuid();
        $line = [
            'menu_item_id' => $tilapia->id,
            'line_type' => 'weighed',
            'net_grams' => 500,
            'amount_charged' => 150,
            'pieces' => 1,
            'cooking_style_id' => $style->id,
            'confirmation_status' => 'confirmed',
        ];

        $this->actingAs($this->staff)->getJson(route('weigh.tables.session', $this->cottage))
            ->assertOk()
            ->assertJsonPath('next_slip_number', 2)
            ->assertJsonPath('open_orders.0.slip_label', 'Slip #1');

        $this->actingAs($this->staff)
            ->postJson(route('weigh.tables.new-slip', $this->cottage), $line, ['Idempotency-Key' => $key])
            ->assertCreated()
            ->assertJsonPath('order.slip_label', 'Slip #2');

        // A retry of the same submit replays instead of opening Slip #3.
        $this->actingAs($this->staff)
            ->postJson(route('weigh.tables.new-slip', $this->cottage), $line, ['Idempotency-Key' => $key])
            ->assertCreated()
            ->assertJsonPath('order.slip_label', 'Slip #2');

        $this->assertSame(2, Order::count());
        $slip = Order::where('slip_number', 2)->firstOrFail();
        $this->assertSame(1, $slip->items()->count());
        $this->assertSame(0, PrinterJob::count(), 'A weighed item does not print by itself.');
    }

    /**
     * Found in local testing 2026-09-17: a later-today advance order added
     * to Slip #2 made Slip #2 vanish from Weigh & Order (and every other
     * open-slip list) until the advance order's time came.
     */
    public function test_a_slip_that_carries_a_later_advance_order_is_still_listed_everywhere(): void
    {
        $slipOne = $this->placeOrder([['menu_item_id' => $this->sisig->id, 'quantity' => 1]]);
        $slipTwo = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);

        $this->actingAs($this->staff)->post(route('quotations.store'), [
            'space_id' => $this->cottage->id,
            'target' => $slipTwo->id,
            'request_id' => (string) Str::uuid(),
            'scheduled_for' => now()->addHour()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($slipTwo->id, $slipTwo->fresh()->sourceQuotation->converted_order_id);

        $this->actingAs($this->staff)->getJson(route('weigh.tables.session', $this->cottage))
            ->assertOk()
            ->assertJsonCount(2, 'open_orders')
            ->assertJsonPath('open_orders.1.slip_label', 'Slip #2');

        $this->actingAs($this->staff)->getJson(route('quotations.table-receipts', $this->cottage))
            ->assertOk()
            ->assertJsonCount(2, 'open_orders');

        $this->actingAs($this->staff)->get(route('orders.create'))
            ->assertOk()
            ->assertViewHas('openSlipsBySpace', fn ($slips) => $slips->get($this->cottage->id)?->pluck('id')->all() === [$slipOne->id, $slipTwo->id]);

        // Still addable from New Order.
        $this->actingAs($this->staff)
            ->post(route('orders.store'), $this->orderPayload([['menu_item_id' => $this->rice->id, 'quantity' => 1]], ['target_order_id' => $slipTwo->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('orders.show', $slipTwo));
    }

    /**
     * The advance-order label belongs to the slip only when the advance
     * order opened it. Added to a slip already in use, it is labelled on
     * its own lines — the slip is still the table party's.
     */
    public function test_an_advance_order_added_to_a_slip_is_labelled_on_its_lines_not_the_slip_header(): void
    {
        $slip = $this->placeOrder([['menu_item_id' => $this->rice->id, 'quantity' => 1]]);

        $this->actingAs($this->staff)->post(route('quotations.store'), [
            'space_id' => $this->cottage->id,
            'target' => $slip->id,
            'request_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
        $joined = \App\Models\Quotation::latest('id')->firstOrFail();

        // A standalone advance order at another table, for comparison.
        $otherTable = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Cottage 5', 'status' => 'available', 'sort_order' => 3]);
        $this->actingAs($this->staff)->post(route('quotations.store'), [
            'space_id' => $otherTable->id,
            'target' => 'new',
            'request_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
        $standalone = \App\Models\Quotation::latest('id')->firstOrFail();

        $this->assertNull($slip->fresh()->openingQuotation());
        $this->assertTrue($standalone->convertedOrder->openingQuotation()->is($standalone));

        $board = $this->actingAs($this->staff)->get(route('kitchen.index'))->assertOk();
        $board->assertDontSee('Advance Order · '.$joined->quotation_number);
        $board->assertSee('Advance order '.$joined->quotation_number);
        $board->assertSee('Advance Order · '.$standalone->quotation_number);

        $this->actingAs($this->staff)->get(route('orders.kitchen-slip.print', $slip))
            ->assertOk()
            ->assertDontSee('Advance Order &middot; '.$joined->quotation_number, false);

        $this->actingAs($this->staff)->get(route('orders.show', $slip))
            ->assertOk()
            ->assertDontSee('ADVANCE ORDER / QUOTATION');

        $this->actingAs($this->staff)->getJson(route('quotations.table-receipts', $this->cottage))
            ->assertJsonPath('open_orders.0.channel_label', 'Staff');
    }

    public function test_an_advance_order_that_is_only_for_later_is_still_kept_off_the_open_slips(): void
    {
        $this->actingAs($this->staff)->post(route('quotations.store'), [
            'space_id' => $this->cottage->id,
            'target' => 'new',
            'request_id' => (string) Str::uuid(),
            'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $reservation = Order::firstOrFail();
        $this->assertNull($reservation->space_session_id, "Tomorrow's reservation is not on today's tab.");
        $this->assertNull($reservation->slip_number);

        $this->actingAs($this->staff)->getJson(route('weigh.tables.session', $this->cottage))
            ->assertOk()
            ->assertJsonCount(0, 'open_orders')
            ->assertJsonPath('next_slip_number', 1);
    }

    public function test_a_rejected_weighed_line_leaves_no_empty_slip_behind(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('weigh.tables.new-slip', $this->cottage), [
                'menu_item_id' => $this->sisig->id,
                'line_type' => 'weighed',
                'net_grams' => 500,
                'amount_charged' => 150,
            ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, PrinterJob::count());
    }

    public function test_the_printer_bridge_gets_each_job_exactly_once_and_stale_claims_come_back(): void
    {
        foreach ([$this->sisig, $this->rice] as $item) {
            $slip = $this->placeOrder([['menu_item_id' => $item->id, 'quantity' => 1]]);
            $this->actingAs($this->staff)->postJson(route('orders.kitchen-slip.print-thermal', $slip))->assertOk();
        }

        $bridge = ['Authorization' => 'Bearer bridge-secret'];

        $first = $this->getJson('/api/printer-jobs', $bridge)->assertOk()->json('jobs');
        $this->assertCount(2, $first);
        $this->assertSame(['id', 'type', 'payload'], array_keys($first[0]), 'Response shape the running bridge expects.');

        // A second bridge polling right after gets nothing — no double print.
        $this->assertSame([], $this->getJson('/api/printer-jobs', $bridge)->json('jobs'));
        $this->assertSame(2, PrinterJob::where('status', PrinterJobStatus::Printing)->count());

        // One acked, one never acknowledged (the bridge PC went to sleep).
        $this->postJson("/api/printer-jobs/{$first[0]['id']}/ack", ['status' => 'printed'], $bridge)->assertOk();

        $this->travel(config('printing.claim_timeout_seconds') + 1)->seconds();

        $again = $this->getJson('/api/printer-jobs', $bridge)->json('jobs');
        $this->assertSame([$first[1]['id']], array_column($again, 'id'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function placeOrder(array $items, ?Space $space = null): Order
    {
        $before = Order::max('id') ?? 0;

        $this->actingAs($this->staff)
            ->post(route('orders.store'), $this->orderPayload($items, [], $space))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Order::where('id', '>', $before)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function orderPayload(array $items, array $extra = [], ?Space $space = null): array
    {
        $space ??= $this->cottage;

        return [
            'order_type' => 'dine_in',
            'area_id' => $space->area_id,
            'space_category_id' => $space->category_id,
            'space_id' => $space->id,
            'items' => $items,
            ...$extra,
        ];
    }
}
