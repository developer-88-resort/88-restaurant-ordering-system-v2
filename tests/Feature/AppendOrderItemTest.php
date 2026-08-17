<?php

namespace Tests\Feature;

use App\Enums\LineType;
use App\Enums\OrderItemAdjustmentReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use App\Services\OrderAppender;
use App\Support\WeighAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Appending lines to an order that already exists — the basis of the "one
 * receipt per table" rule — and the weighed line itself: the counter scale
 * now supplies BOTH the weight and the amount, so the server's job is no
 * longer to compute the charge but to check it against what the reference
 * rate implies and refuse to let the two drift apart quietly.
 */
class AppendOrderItemTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $supervisor;

    private MenuItem $adobo;

    private MenuItem $bangus;

    private CookingStyle $inihaw;

    private Space $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $this->supervisor = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $spaceCategory = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $this->table = Space::create([
            'area_id' => $area->id,
            'category_id' => $spaceCategory->id,
            'name' => 'Table 1',
            'status' => 'available',
            'sort_order' => 1,
        ]);

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
        $this->bangus->cookingStyles()->attach($this->inihaw->id);
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '0.00',
            'created_by' => $this->staff->id,
        ], $attributes));
    }

    private function appendFixed(Order $order, array $overrides = [])
    {
        return $this->actingAs($this->staff)->post("/orders/{$order->id}/items", array_merge([
            'menu_item_id' => $this->adobo->id,
            'quantity' => 2,
        ], $overrides));
    }

    /** 600 g @ ₱295/kg computes to exactly ₱177.00 — the spec's own reference case. */
    private function appendWeighed(Order $order, array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post("/orders/{$order->id}/items", array_merge([
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'net_grams' => 600,
            'amount_charged' => '177.00',
            'cooking_style_id' => $this->inihaw->id,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Appending a fixed line
    // ---------------------------------------------------------------

    public function test_a_fixed_line_can_be_appended_to_an_open_order(): void
    {
        $order = $this->makeOrder();

        $this->appendFixed($order)->assertRedirect();

        $order->refresh();
        $this->assertCount(1, $order->items);
        $this->assertSame('360.00', (string) $order->total_amount);
    }

    /** The acceptance case: a second line lands on the SAME order. */
    public function test_appending_keeps_everything_on_one_order(): void
    {
        $order = $this->makeOrder();

        $this->appendFixed($order);
        $this->appendWeighed($order);

        $order->refresh();

        $this->assertSame(1, Order::count(), 'Appending must not create a second order.');
        $this->assertCount(2, $order->items);
        // 2 × ₱180 + ₱177.00 (600 g @ ₱295/kg)
        $this->assertSame('537.00', (string) $order->total_amount);
    }

    /** The reason the fixed path used to bill per-kilo items at ₱0.00. */
    public function test_a_per_kilo_item_on_the_quantity_path_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->appendFixed($order, ['menu_item_id' => $this->bangus->id, 'quantity' => 1])
            ->assertSessionHasErrors('menu_item_id');

        $this->assertCount(0, $order->fresh()->items);
    }

    // ---------------------------------------------------------------
    // The weighed line itself — the scale supplies both numbers
    // ---------------------------------------------------------------

    public function test_a_weighed_line_within_tolerance_passes_silently(): void
    {
        $order = $this->makeOrder();

        // 600 g @ ₱295/kg = ₱177.00 exactly — no variance at all.
        $this->appendWeighed($order)->assertRedirect();

        $item = $order->fresh()->items->first();

        $this->assertTrue($item->isWeighed());
        $this->assertSame(600, $item->netWeightGrams());
        $this->assertSame('177.00', (string) $item->subtotal);
        $this->assertSame('295.00', (string) $item->price_per_kilo_snapshot);
        // A weighed line is always one piece of food, never a countable qty.
        $this->assertSame(1, $item->quantity);
        $this->assertNull($item->activeWeighing()->variance_reason);
    }

    public function test_the_spec_boundary_case_of_250g_computes_73_75(): void
    {
        $order = $this->makeOrder();

        $this->appendWeighed($order, ['net_grams' => 250, 'amount_charged' => '73.75'])
            ->assertSessionHasNoErrors();

        $weighing = $order->fresh()->items->first()->activeWeighing();

        $this->assertSame('73.75', (string) $weighing->computed_amount);
        $this->assertSame('73.75', (string) $weighing->amount_charged);
    }

    public function test_437_grams_is_accepted_with_no_step_snapping(): void
    {
        $order = $this->makeOrder();

        // 437 g @ ₱295/kg = ₱128.92 (rounded).
        $this->appendWeighed($order, ['net_grams' => 437, 'amount_charged' => '128.92'])
            ->assertSessionHasNoErrors();

        $this->assertSame(437, $order->fresh()->items->first()->netWeightGrams());
    }

    public function test_below_minimum_is_rejected_under_the_block_setting(): void
    {
        $order = $this->makeOrder();

        $response = $this->appendWeighed($order, ['net_grams' => 200, 'amount_charged' => '59.00']);

        $response->assertSessionHasErrors('net_grams');
        // Never suggests a smaller number than the minimum itself — the
        // only way forward is a heavier fish, not a lower floor.
        $message = session('errors')->get('net_grams')[0];
        $this->assertDoesNotMatchRegularExpression('/\b0\s*g\b/', $message);
        $this->assertStringContainsString('250 g', $message);
        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_below_minimum_bills_at_the_minimum_when_settings_allow_it(): void
    {
        Setting::current()->update(['weighed_below_minimum_behavior' => 'bill_at_minimum']);
        $order = $this->makeOrder();

        // 200 g is short of the 250 g floor; expected is priced AT the
        // floor (250 g @ ₱295/kg = ₱73.75), not at the 200 g actually read.
        $this->appendWeighed($order, ['net_grams' => 200, 'amount_charged' => '73.75'])
            ->assertSessionHasNoErrors();

        $item = $order->fresh()->items->first();
        $this->assertTrue($item->flagged_for_review);
        $this->assertSame('73.75', (string) $item->activeWeighing()->computed_amount);
    }

    public function test_tare_grams_in_the_payload_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->appendWeighed($order, ['tare_grams' => 50])->assertSessionHasErrors('tare_grams');
    }

    public function test_a_cooking_style_is_required_on_a_weighed_line(): void
    {
        $order = $this->makeOrder();

        $this->appendWeighed($order, ['cooking_style_id' => null])
            ->assertSessionHasErrors('cooking_style_id');
    }

    public function test_a_cooking_style_the_item_does_not_offer_is_rejected(): void
    {
        $other = CookingStyle::create(['name' => 'Paksiw', 'surcharge' => 0, 'sort_order' => 2, 'is_active' => true]);

        $order = $this->makeOrder();

        $this->appendWeighed($order, ['cooking_style_id' => $other->id])
            ->assertSessionHasErrors('cooking_style_id');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_a_cooking_surcharge_is_charged_per_piece_on_top_of_the_scale_amount(): void
    {
        $this->inihaw->update(['surcharge' => '20.00']);

        $order = $this->makeOrder();
        $this->appendWeighed($order, ['pieces' => 3]);

        // ₱177.00 off the scale + ₱20 × 3 pieces = ₱237.00.
        $this->assertSame('237.00', (string) $order->fresh()->items->first()->subtotal);
    }

    /** The reference rate is always resolved server-side; a client cannot post it. */
    public function test_the_reference_rate_is_resolved_server_side(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order, ['price_per_kilo_snapshot' => '1.00']);

        $this->assertSame('295.00', (string) $order->fresh()->items->first()->price_per_kilo_snapshot);
    }

    public function test_appending_returns_the_new_line_and_total_as_json(): void
    {
        $order = $this->makeOrder();

        $response = $this->actingAs($this->staff)->postJson("/orders/{$order->id}/items", [
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'net_grams' => 500,
            'amount_charged' => '147.50',
            'cooking_style_id' => $this->inihaw->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('item.line_type', 'weighed')
            ->assertJsonPath('item.net_grams', 500)
            ->assertJsonPath('item.amount_charged', '147.50')
            ->assertJsonPath('order.total_amount', '147.50');
    }

    // ---------------------------------------------------------------
    // Variance
    // ---------------------------------------------------------------

    public function test_a_small_difference_within_tolerance_needs_no_reason(): void
    {
        $order = $this->makeOrder();

        // Expected ₱177.00; ₱178.00 is within max(₱1.00, 1% of 177 = ₱1.77).
        $this->appendWeighed($order, ['amount_charged' => '178.00'])->assertSessionHasNoErrors();
    }

    public function test_a_difference_past_tolerance_requires_a_reason(): void
    {
        $order = $this->makeOrder();

        // ₱8.00 over ₱177.00 = 4.5%, past the 1%/₱1 tolerance but well
        // under the 10% hard ceiling — a reason is enough.
        $this->appendWeighed($order, ['amount_charged' => '185.00'])
            ->assertSessionHasErrors('variance_reason');

        $this->assertCount(0, $order->fresh()->items);

        $this->appendWeighed($order, [
            'amount_charged' => '185.00',
            'variance_reason' => 'Customer asked for a bigger cut.',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(
            Activity::where('event', WeighAudit::VARIANCE_FLAGGED)->first(),
            'A tolerance-breaching amount must leave a WEIGH_VARIANCE_FLAGGED entry.'
        );
    }

    public function test_a_difference_past_the_ceiling_needs_supervisor_override(): void
    {
        $order = $this->makeOrder();

        // ₱500.00 vs ₱177.00 expected is ~182% — well past the 10% ceiling.
        $this->appendWeighed($order, [
            'amount_charged' => '500.00',
            'variance_reason' => 'Manager approved a special cut.',
        ])->assertSessionHasErrors('amount_charged');

        $this->assertCount(0, $order->fresh()->items);

        $this->appendWeighed($order, [
            'amount_charged' => '500.00',
            'variance_reason' => 'Manager approved a special cut.',
        ], as: $this->supervisor)->assertSessionHasNoErrors();

        $this->assertSame('500.00', (string) $order->fresh()->items->first()->activeWeighing()->amount_charged);
        $this->assertNotNull(Activity::where('event', WeighAudit::VARIANCE_FLAGGED)->first());
    }

    // ---------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------

    public function test_appending_to_a_paid_order_is_blocked(): void
    {
        $order = $this->makeOrder(['payment_status' => PaymentStatus::Paid]);

        $this->appendFixed($order)->assertSessionHas('error');

        $this->assertCount(0, $order->fresh()->items);
        $this->assertSame('0.00', (string) $order->fresh()->total_amount);
    }

    public function test_appending_to_a_cancelled_order_is_blocked(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Cancelled]);

        $this->appendFixed($order)->assertSessionHas('error');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_appending_to_a_completed_order_is_blocked(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Completed]);

        $this->appendFixed($order)->assertSessionHas('error');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_appending_to_a_closed_table_session_is_blocked(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $order = $this->makeOrder(['space_session_id' => $session->id]);

        $session->close();

        $this->appendFixed($order)->assertSessionHas('error');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_the_json_api_reports_a_blocked_append_as_422(): void
    {
        $order = $this->makeOrder(['payment_status' => PaymentStatus::Paid]);

        $this->actingAs($this->staff)
            ->postJson("/orders/{$order->id}/items", [
                'menu_item_id' => $this->adobo->id,
                'quantity' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_a_weighed_line_needs_a_weight_and_an_amount(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->staff)->post("/orders/{$order->id}/items", [
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
        ])->assertSessionHasErrors(['net_grams', 'amount_charged']);
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    public function test_the_same_idempotency_key_twice_creates_only_one_line(): void
    {
        $order = $this->makeOrder();
        $key = '11111111-2222-4333-8444-555555555555';

        $this->actingAs($this->staff)->postJson("/orders/{$order->id}/items", [
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'net_grams' => 600,
            'amount_charged' => '177.00',
            'cooking_style_id' => $this->inihaw->id,
        ], ['Idempotency-Key' => $key])->assertCreated();

        $this->actingAs($this->staff)->postJson("/orders/{$order->id}/items", [
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'net_grams' => 600,
            'amount_charged' => '177.00',
            'cooking_style_id' => $this->inihaw->id,
        ], ['Idempotency-Key' => $key])->assertCreated();

        $this->assertCount(1, $order->fresh()->items);
    }

    // ---------------------------------------------------------------
    // Session / table resolution
    // ---------------------------------------------------------------

    public function test_find_open_order_for_session_returns_the_billable_order(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $order = $this->makeOrder(['space_id' => $this->table->id, 'space_session_id' => $session->id]);

        $this->assertTrue(OrderAppender::findOpenOrderForSession($session)->is($order));
    }

    public function test_find_open_order_for_session_ignores_paid_and_cancelled_orders(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $this->makeOrder(['space_id' => $this->table->id, 'space_session_id' => $session->id, 'payment_status' => PaymentStatus::Paid]);
        $this->makeOrder(['space_id' => $this->table->id, 'space_session_id' => $session->id, 'status' => OrderStatus::Cancelled]);

        $this->assertNull(OrderAppender::findOpenOrderForSession($session));
    }

    public function test_a_session_with_no_open_order_gets_one_started(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);

        $order = OrderAppender::resolveOrder($this->table, [
            'space_id' => $this->table->id,
            'space_session_id' => $session->id,
        ], $session);

        $this->assertSame($session->id, $order->space_session_id);
        $this->assertSame('0.00', (string) $order->total_amount);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_starting_an_order_for_a_session_reuses_the_open_one(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $existing = $this->makeOrder(['space_id' => $this->table->id, 'space_session_id' => $session->id]);

        $resolved = OrderAppender::resolveOrder($this->table, [
            'space_id' => $this->table->id,
            'space_session_id' => $session->id,
        ], $session);

        $this->assertTrue($resolved->is($existing));
        $this->assertSame(1, Order::count());
    }

    /** FIX-7: a staff-created bill with no QR session must still be found. */
    public function test_find_open_orders_for_table_sees_a_staff_created_order_with_no_session(): void
    {
        $order = $this->makeOrder(['space_id' => $this->table->id]);

        $found = OrderAppender::findOpenOrdersForTable($this->table);

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($order));
    }

    public function test_find_open_orders_for_table_returns_every_open_bill(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $walkIn = $this->makeOrder(['space_id' => $this->table->id]);
        $qrOrder = $this->makeOrder(['space_id' => $this->table->id, 'space_session_id' => $session->id]);

        $found = OrderAppender::findOpenOrdersForTable($this->table);

        $this->assertCount(2, $found);
        $this->assertTrue($found->contains(fn ($o) => $o->is($walkIn)));
        $this->assertTrue($found->contains(fn ($o) => $o->is($qrOrder)));
    }

    // ---------------------------------------------------------------
    // Kitchen ticket
    // ---------------------------------------------------------------

    /**
     * A line added after the kitchen already plated the earlier ones has to
     * reopen the ticket, or the new dish is billed but never cooked.
     */
    public function test_appending_to_a_ready_order_reopens_the_kitchen_ticket(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Ready]);

        $this->appendFixed($order);

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_a_pending_order_stays_pending_when_appended_to(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Pending]);

        $this->appendFixed($order);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_the_kitchen_board_shows_the_appended_weighed_line(): void
    {
        $order = $this->makeOrder(['status' => OrderStatus::Pending]);
        $this->appendWeighed($order, ['net_grams' => 1000, 'amount_charged' => '295.00']);

        $this->actingAs($this->staff)
            ->get('/kitchen')
            ->assertOk()
            // Grams, not a "1×" — a weighed line has no countable quantity.
            ->assertSee('1,000g')
            ->assertSee('Bangus')
            ->assertSee('Inihaw');
    }

    // ---------------------------------------------------------------
    // Correcting a weighed line
    // ---------------------------------------------------------------

    public function test_correcting_a_weighed_line_writes_a_new_revision_and_keeps_the_old_one(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order);
        $item = $order->fresh()->items->first();
        $original = $item->activeWeighing();

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'net_grams' => 900,
            'amount_charged' => '265.50',
            'cooking_style_id' => $this->inihaw->id,
            'reason' => 'Re-weighed with the customer; first reading was misread.',
        ])->assertRedirect();

        $item->refresh();
        $current = $item->activeWeighing();

        $this->assertSame(2, $current->revision);
        $this->assertSame($original->id, $current->supersedes_id);
        $this->assertNotNull($original->fresh(), 'Revision 1 must still exist.');
        $this->assertSame(900, $item->weight_grams);
        $this->assertSame('265.50', (string) $item->subtotal);
        $this->assertSame('265.50', (string) $order->fresh()->total_amount);
    }

    public function test_a_weight_correction_requires_a_reason(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order);
        $item = $order->fresh()->items->first();

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'net_grams' => 900,
            'amount_charged' => '265.50',
        ])->assertSessionHasErrors('reason');
    }

    public function test_a_fixed_line_has_no_weight_to_correct(): void
    {
        $order = $this->makeOrder();
        $this->appendFixed($order);
        $item = $order->fresh()->items->first();

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'net_grams' => 900,
            'amount_charged' => '265.50',
            'reason' => 'Nope.',
        ])->assertSessionHas('error');
    }

    public function test_a_weight_correction_on_a_paid_order_is_blocked(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order);
        $item = $order->fresh()->items->first();

        $order->update(['payment_status' => PaymentStatus::Paid]);

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'net_grams' => 5000,
            'amount_charged' => '1475.00',
            'reason' => 'Trying it on.',
        ])->assertSessionHas('error');

        $this->assertSame(600, $item->fresh()->weight_grams);
    }

    // ---------------------------------------------------------------
    // Void
    // ---------------------------------------------------------------

    public function test_voiding_a_weighed_line_excludes_it_from_the_total_but_keeps_the_row(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order);
        $item = $order->fresh()->items->first();
        $weighing = $item->activeWeighing();

        $this->actingAs($this->staff)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => OrderItemAdjustmentReason::cases()[0]->value,
            'notes' => 'Customer changed their mind.',
        ])->assertRedirect();

        $this->assertSame('0.00', (string) $order->fresh()->total_amount);
        $this->assertNotNull($weighing->fresh(), 'The weighing row must not be deleted.');
        $this->assertNotNull($weighing->fresh()->voided_at);
    }
}
