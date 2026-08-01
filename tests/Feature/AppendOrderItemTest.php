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
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use App\Services\OrderAppender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Appending lines to an order that already exists — the basis of the "one
 * receipt per table" rule, and the prerequisite for the weigh station.
 */
class AppendOrderItemTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private MenuItem $adobo;

    private MenuItem $bangus;

    private CookingStyle $inihaw;

    private Space $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

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
            'price_per_kilo' => '450.00',
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

    private function appendWeighed(Order $order, array $overrides = [])
    {
        return $this->actingAs($this->staff)->post("/orders/{$order->id}/items", array_merge([
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'weight_grams' => 1000,
            'cooking_style_id' => $this->inihaw->id,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Appending
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
        // 2 × ₱180 + 1000 g @ ₱450/kg = 360 + 450
        $this->assertSame('810.00', (string) $order->total_amount);
    }

    public function test_a_weighed_line_is_priced_by_the_shared_pricer(): void
    {
        $order = $this->makeOrder();

        // 1,250 g gross − 250 g tare = 1,000 g net @ ₱450/kg = ₱450.00
        $this->appendWeighed($order, ['weight_grams' => 1250, 'tare_grams' => 250]);

        $item = $order->fresh()->items->first();

        $this->assertTrue($item->isWeighed());
        $this->assertSame(1000, $item->netWeightGrams());
        $this->assertSame('450.00', (string) $item->subtotal);
        // A weighed line is always one piece of food, never a countable qty.
        $this->assertSame(1, $item->quantity);
    }

    /** Tomorrow's market price must never reprice today's line. */
    public function test_the_per_kilo_rate_is_snapshotted_onto_the_line(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order);

        $this->bangus->update(['price_per_kilo' => '900.00']);

        $item = $order->fresh()->items->first();

        $this->assertSame('450.00', (string) $item->price_per_kilo_snapshot);
        $this->assertSame('450.00', (string) $item->subtotal);
    }

    public function test_a_cooking_surcharge_is_charged_per_piece(): void
    {
        $this->inihaw->update(['surcharge' => '50.00']);

        $order = $this->makeOrder();
        $this->appendWeighed($order, ['weight_grams' => 1000, 'pieces' => 3]);

        // 1000 g @ ₱450/kg = 450.00, plus ₱50 × 3 pieces = 150.00
        $this->assertSame('600.00', (string) $order->fresh()->items->first()->subtotal);
    }

    public function test_a_cooking_style_the_item_does_not_offer_is_rejected(): void
    {
        $other = CookingStyle::create(['name' => 'Paksiw', 'surcharge' => 0, 'sort_order' => 2, 'is_active' => true]);

        $order = $this->makeOrder();

        $this->appendWeighed($order, ['cooking_style_id' => $other->id])
            ->assertSessionHasErrors('cooking_style_id');

        $this->assertCount(0, $order->fresh()->items);
    }

    /** The price is always re-derived; a submitted one is simply ignored. */
    public function test_a_client_supplied_price_is_ignored(): void
    {
        $order = $this->makeOrder();

        $this->appendFixed($order, ['quantity' => 1, 'unit_price' => '1.00', 'subtotal' => '1.00', 'total_amount' => '1.00']);

        $this->assertSame('180.00', (string) $order->fresh()->total_amount);
    }

    public function test_appending_returns_the_new_line_and_total_as_json(): void
    {
        $order = $this->makeOrder();

        $response = $this->actingAs($this->staff)->postJson("/orders/{$order->id}/items", [
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'weight_grams' => 500,
            'cooking_style_id' => $this->inihaw->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('item.line_type', 'weighed')
            ->assertJsonPath('item.net_weight_grams', 500)
            ->assertJsonPath('item.subtotal', '225.00')
            ->assertJsonPath('order.total_amount', '225.00');
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

    public function test_a_weighed_line_needs_a_weight(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->staff)->post("/orders/{$order->id}/items", [
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
        ])->assertSessionHasErrors('weight_grams');
    }

    public function test_a_tare_at_or_above_the_scale_reading_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->appendWeighed($order, ['weight_grams' => 500, 'tare_grams' => 500])
            ->assertSessionHasErrors('tare_grams');
    }

    // ---------------------------------------------------------------
    // Session resolution
    // ---------------------------------------------------------------

    public function test_find_open_order_for_session_returns_the_billable_order(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $order = $this->makeOrder(['space_session_id' => $session->id]);

        $this->assertTrue(OrderAppender::findOpenOrderForSession($session)->is($order));
    }

    public function test_find_open_order_for_session_ignores_paid_and_cancelled_orders(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $this->makeOrder(['space_session_id' => $session->id, 'payment_status' => PaymentStatus::Paid]);
        $this->makeOrder(['space_session_id' => $session->id, 'status' => OrderStatus::Cancelled]);

        $this->assertNull(OrderAppender::findOpenOrderForSession($session));
    }

    public function test_a_session_with_no_open_order_gets_one_started(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);

        $order = OrderAppender::findOrStartOrderForSession($session);

        $this->assertSame($session->id, $order->space_session_id);
        $this->assertSame('0.00', (string) $order->total_amount);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_starting_an_order_for_a_session_reuses_the_open_one(): void
    {
        $session = SpaceSession::create(['space_id' => $this->table->id, 'category_id' => $this->table->category_id, 'status' => 'active']);
        $existing = $this->makeOrder(['space_session_id' => $session->id]);

        $resolved = OrderAppender::findOrStartOrderForSession($session);

        $this->assertTrue($resolved->is($existing));
        $this->assertSame(1, Order::count());
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
        $this->appendWeighed($order, ['weight_grams' => 1250, 'tare_grams' => 250]);

        $this->actingAs($this->staff)
            ->get('/kitchen')
            ->assertOk()
            // Grams, not a "1×" — a weighed line has no countable quantity.
            ->assertSee('1,000g')
            ->assertSee('Bangus')
            ->assertSee('Inihaw');
    }

    // ---------------------------------------------------------------
    // Weight correction
    // ---------------------------------------------------------------

    public function test_a_weighed_line_can_be_corrected_with_a_reason(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order, ['weight_grams' => 1250, 'tare_grams' => 250]);
        $item = $order->fresh()->items->first();

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'weight_grams' => 900,
            'tare_grams' => 0,
            'reason' => 'Re-weighed with the customer; first reading included the tray.',
        ])->assertRedirect();

        $item->refresh();

        $this->assertSame(900, $item->weight_grams);
        $this->assertSame('405.00', (string) $item->subtotal);
        $this->assertSame('Re-weighed with the customer; first reading included the tray.', $item->price_override_reason);
        $this->assertSame($this->staff->id, $item->price_overridden_by_user_id);
        $this->assertSame('405.00', (string) $order->fresh()->total_amount);
    }

    public function test_a_weight_correction_requires_a_reason(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order);
        $item = $order->fresh()->items->first();

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'weight_grams' => 900,
        ])->assertSessionHasErrors('reason');
    }

    /** Correcting a typo must not silently move the line onto today's rate. */
    public function test_a_correction_reprices_against_the_lines_own_snapshot(): void
    {
        $order = $this->makeOrder();
        $this->appendWeighed($order, ['weight_grams' => 1000]);
        $item = $order->fresh()->items->first();

        $this->bangus->update(['price_per_kilo' => '900.00']);

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'weight_grams' => 2000,
            'reason' => 'Corrected reading.',
        ]);

        // 2 kg at the line's frozen ₱450/kg, not today's ₱900/kg.
        $this->assertSame('900.00', (string) $item->fresh()->subtotal);
    }

    public function test_a_fixed_line_has_no_weight_to_correct(): void
    {
        $order = $this->makeOrder();
        $this->appendFixed($order);
        $item = $order->fresh()->items->first();

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/items/{$item->id}/weight", [
            'weight_grams' => 900,
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
            'weight_grams' => 5000,
            'reason' => 'Trying it on.',
        ])->assertSessionHas('error');

        $this->assertSame(1000, $item->fresh()->weight_grams);
    }
}
