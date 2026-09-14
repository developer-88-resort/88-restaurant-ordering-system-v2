<?php

namespace Tests\Feature;

use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CookingStyle;
use App\Models\DailyMarketPrice;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use App\Services\OrderAppender;
use App\Services\TableSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The weigh station: the tablet wizard at the counter. The behaviour that
 * matters is that a weighed line lands on the party's EXISTING bill, that
 * the reference rate shown is today's, and that an underweight reading is
 * refused with a reason. Recording itself — the scale supplying both the
 * weight and the amount, and how far apart they may sit — is exercised
 * exhaustively in AppendOrderItemTest; here only the station-specific
 * wiring (session lookup, walk-ins, confirmation) is covered.
 */
class WeighStationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    private MenuItem $bangus;

    private CookingStyle $inihaw;

    private Space $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $spaceCategory = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Cottage', 'slug' => 'cottage', 'is_active' => true]);
        $this->table = Space::create([
            'area_id' => $area->id,
            'category_id' => $spaceCategory->id,
            'name' => 'Cottage 1',
            'status' => 'available',
            'sort_order' => 1,
        ]);

        $menuCategory = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $this->bangus = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '450.00',
            'min_weight_grams' => 250,
            'counter_only' => true,
            'availability_status' => 'available',
        ]);

        $this->inihaw = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);
        $this->bangus->cookingStyles()->attach($this->inihaw->id);
    }

    private function seatedTable(): array
    {
        $session = TableSessionManager::findOrOpenFor($this->table);
        $guest = $session->guestSessions()->create(['guest_number' => 1, 'display_name' => 'Test Guest']);
        $order = OrderAppender::resolveOrder($this->table, [
            'order_type' => \App\Enums\OrderType::DineIn,
            'area_id' => $this->table->area_id,
            'space_category_id' => $this->table->category_id,
            'space_id' => $this->table->id,
            'space_session_id' => $session->id,
            'created_by' => $this->staff->id,
        ], $session);

        return [$session, $guest, $order];
    }

    /** 680 g @ ₱450/kg = ₱306.00 — read straight off the counter scale. */
    private function record(Order $order, array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post("/orders/{$order->id}/items", array_merge([
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'net_grams' => 680,
            'amount_charged' => '306.00',
            'pieces' => 1,
            'cooking_style_id' => $this->inihaw->id,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------

    /** /weigh is the landing page; /weigh/new is the wizard itself. */
    public function test_the_landing_page_loads_for_operational_staff(): void
    {
        $this->actingAs($this->staff)
            ->get('/weigh')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Weigh/Index')
                ->has('stats.pendingConfirmationCount')
                ->has('stats.weighedTodayKg'));
    }

    public function test_the_wizard_loads_for_operational_staff(): void
    {
        $this->actingAs($this->staff)
            ->get('/weigh/new')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Weigh/Station')
                ->has('categories', 1)
                ->where('categories.0.items.0.name', 'Bangus')
                ->where('categories.0.items.0.price_per_kilo', 450)
                ->where('categories.0.items.0.needs_setup', false)
                ->has('areas', 1)
                ->where('can.overridePrice', false)
                ->where('requiresCustomerConfirmation', false)
                ->has('weighed'));
    }

    public function test_a_manager_may_override_variance(): void
    {
        $this->actingAs($this->admin)
            ->get('/weigh/new')
            ->assertInertia(fn ($page) => $page->where('can.overridePrice', true));
    }

    public function test_the_wizard_shows_todays_market_rate_not_the_standing_one(): void
    {
        DailyMarketPrice::create([
            'menu_item_id' => $this->bangus->id,
            'price_per_kilo' => '480.00',
            'effective_date' => Carbon::today()->toDateString(),
            'set_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)
            ->get('/weigh/new')
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.items.0.price_per_kilo', 480)
                ->where('categories.0.items.0.default_price_per_kilo', 450));
    }

    /**
     * A per-kilo item missing a cooking style ships disabled, not hidden —
     * and its fix link points at Weighted Items (where a set is assigned),
     * not the item edit page, which can't fix a missing style assignment.
     */
    public function test_an_item_missing_a_cooking_style_ships_flagged_needs_setup(): void
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch 2', 'sort_order' => 2, 'is_active' => true]);
        MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Tanguingue',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '400.00',
            'availability_status' => 'available',
        ]);

        $this->actingAs($this->staff)
            ->get('/weigh/new')
            ->assertInertia(fn ($page) => $page
                ->where('categories.1.items.0.name', 'Tanguingue')
                ->where('categories.1.items.0.needs_setup', true)
                ->where('categories.1.items.0.setup_reason', 'No cooking styles assigned')
                ->where('categories.1.items.0.setup_url', route('weigh.items.index')));
    }

    // ---------------------------------------------------------------
    // Live variance check
    // ---------------------------------------------------------------

    public function test_check_variance_reports_the_expected_amount_and_passes_within_tolerance(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('weigh.check-variance'), [
                'menu_item_id' => $this->bangus->id,
                'net_grams' => 680,
                'amount_charged' => 306.00,
            ])
            ->assertOk()
            ->assertJsonPath('passes', true)
            ->assertJsonPath('computed_amount', '306.00')
            ->assertJsonPath('requires_reason', false)
            ->assertJsonPath('requires_override', false);
    }

    public function test_check_variance_flags_a_large_difference_as_needing_override(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('weigh.check-variance'), [
                'menu_item_id' => $this->bangus->id,
                'net_grams' => 680,
                'amount_charged' => 1000,
            ])
            ->assertOk()
            ->assertJsonPath('passes', false)
            ->assertJsonPath('requires_override', true)
            ->assertJsonPath('can_override', false);
    }

    // ---------------------------------------------------------------
    // Step 5 — table lookup
    // ---------------------------------------------------------------

    public function test_a_table_with_no_session_reports_none(): void
    {
        $this->actingAs($this->staff)
            ->getJson(route('weigh.tables.session', $this->table->id))
            ->assertOk()
            ->assertJsonPath('session', null)
            ->assertJsonPath('open_orders', []);
    }

    public function test_a_seated_table_lists_its_guests_with_their_welcome_names(): void
    {
        [, , $order] = $this->seatedTable();

        $this->actingAs($this->staff)
            ->getJson(route('weigh.tables.session', $this->table->id))
            ->assertOk()
            ->assertJsonPath('session.guests.0.label', 'Guest 1 — Test Guest')
            ->assertJsonPath('order.order_number', $order->orderNumber());
    }

    public function test_the_running_order_preview_lists_what_is_already_on_the_bill(): void
    {
        [, , $order] = $this->seatedTable();
        $this->record($order);

        $this->actingAs($this->staff)
            ->getJson(route('weigh.tables.session', $this->table->id))
            ->assertJsonPath('order.total_amount', 306)
            ->assertJsonPath('order.items.0.is_weighed', true)
            ->assertJsonPath('order.items.0.subtotal', 306);
    }

    /** FIX-7: a walk-in bill on the table must surface even with no QR session. */
    public function test_a_staff_created_walk_in_order_on_the_table_is_found_with_no_session(): void
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'space_id' => $this->table->id,
            'order_number' => 'TEST-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => '0.00',
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->getJson(route('weigh.tables.session', $this->table->id))
            ->assertOk()
            ->assertJsonPath('order.order_number', $order->orderNumber());
    }

    public function test_opening_a_session_from_the_station_seats_the_table_and_starts_a_bill(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('weigh.tables.open-session', $this->table->id))
            ->assertOk()
            ->assertJsonPath('order.total_amount', 0);

        $this->assertNotNull(TableSessionManager::activeSessionFor($this->table->fresh()));
    }

    public function test_a_walk_in_order_can_be_started_without_a_table(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('weigh.walk-in'), ['customer_name' => 'Ana'])
            ->assertOk()
            ->assertJsonPath('order.total_amount', 0);

        $this->assertSame('Ana', Order::latest('id')->first()->customer_name);
    }

    // ---------------------------------------------------------------
    // Recording — the acceptance case
    // ---------------------------------------------------------------

    /**
     * The M4 acceptance case: the wizard's result is a new LINE on the
     * guest's existing order, not a second order.
     */
    public function test_recording_appends_to_the_guests_existing_order(): void
    {
        [, $guest, $order] = $this->seatedTable();

        $this->record($order, ['ordered_by_guest_id' => $guest->id])->assertRedirect();

        $this->assertSame(1, Order::count(), 'The weigh station must not open a second order.');

        $item = $order->fresh()->items->first();

        $this->assertTrue($item->isWeighed());
        $this->assertSame(680, $item->weight_grams);
        // 0.680 kg × ₱450/kg = ₱306.00, keyed straight off the scale.
        $this->assertSame('306.00', (string) $item->subtotal);
        $this->assertSame('306.00', (string) $order->fresh()->total_amount);
        $this->assertSame($guest->id, $item->ordered_by_guest_id);
    }

    public function test_the_recorder_and_time_are_stamped_on_the_line(): void
    {
        [, , $order] = $this->seatedTable();

        $this->record($order);

        $item = $order->fresh()->items->first();

        $this->assertSame($this->staff->id, $item->weighed_by_user_id);
        $this->assertNotNull($item->weighed_at);
    }

    /** The acceptance case for the guard: 200 g is under the 250 g minimum. */
    public function test_recording_200g_is_blocked_with_a_clear_reason(): void
    {
        [, , $order] = $this->seatedTable();

        $response = $this->record($order, ['net_grams' => 200, 'amount_charged' => '90.00']);

        $response->assertSessionHasErrors('net_grams');

        $errors = session('errors')->get('net_grams');
        $this->assertStringContainsString('250', $errors[0]);

        $this->assertCount(0, $order->fresh()->items);
    }

    /**
     * There is no step snapping any more: whatever the scale reads is what
     * gets billed, so an "awkward" 437 g fish goes through untouched.
     */
    public function test_an_arbitrary_scale_reading_is_accepted(): void
    {
        [, , $order] = $this->seatedTable();

        // 0.437 kg × ₱450/kg = ₱196.65 — keyed exactly as the scale showed it.
        $this->record($order, ['net_grams' => 437, 'amount_charged' => '196.65'])->assertRedirect();

        $item = $order->fresh()->items->first();

        $this->assertSame(437, $item->weight_grams);
        $this->assertSame('196.65', (string) $item->subtotal);
    }

    /**
     * Under the minimum only blocks while the admin says it should — the
     * behaviour is a setting, not a constant in the validator.
     */
    public function test_below_minimum_can_be_allowed_through_by_setting(): void
    {
        Setting::current()->update(['weighed_below_minimum_behavior' => 'bill_at_minimum']);

        [, , $order] = $this->seatedTable();

        // Priced against the 250 g floor (250 g @ ₱450/kg = ₱112.50), not
        // the 200 g actually read — so the keyed amount matches THAT.
        $this->record($order, ['net_grams' => 200, 'amount_charged' => '112.50'])->assertRedirect();

        $item = $order->fresh()->items->first();
        $this->assertCount(1, $order->fresh()->items);
        $this->assertTrue($item->flagged_for_review);
    }

    // ---------------------------------------------------------------
    // Customer confirmation
    // ---------------------------------------------------------------

    public function test_a_line_is_confirmed_immediately_when_the_setting_is_off(): void
    {
        [, , $order] = $this->seatedTable();

        $this->record($order, ['confirmation_status' => 'confirmed']);

        $this->assertSame(
            OrderItemConfirmationStatus::Confirmed,
            $order->fresh()->items->first()->confirmation_status,
        );
    }

    public function test_a_line_waits_for_the_customer_when_the_setting_is_on(): void
    {
        Setting::current()->update(['weigh_customer_confirmation_enabled' => true]);

        $this->actingAs($this->staff)
            ->get('/weigh/new')
            ->assertInertia(fn ($page) => $page->where('requiresCustomerConfirmation', true));

        [, , $order] = $this->seatedTable();
        $this->record($order, ['confirmation_status' => 'pending_customer']);

        $this->assertSame(
            OrderItemConfirmationStatus::PendingCustomer,
            $order->fresh()->items->first()->confirmation_status,
        );
    }

    // ---------------------------------------------------------------
    // Variance override permission, exercised through the station's own flow
    // ---------------------------------------------------------------

    public function test_staff_cannot_record_a_large_variance_without_override_permission(): void
    {
        [, , $order] = $this->seatedTable();

        // ₱1000 vs an expected ₱306.00 is far past the 10% ceiling.
        $this->record($order, [
            'amount_charged' => '1000.00',
            'variance_reason' => 'Bigger fish, customer agreed.',
        ])->assertSessionHasErrors('amount_charged');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_a_manager_can_record_a_large_variance_with_a_reason(): void
    {
        [, , $order] = $this->seatedTable();

        $this->record($order, [
            'amount_charged' => '1000.00',
            'variance_reason' => 'Bigger fish, customer agreed.',
        ], $this->admin)->assertRedirect();

        $item = $order->fresh()->items->first();

        $this->assertSame('1000.00', (string) $item->subtotal);
        $this->assertSame('Bigger fish, customer agreed.', $item->activeWeighing()->variance_reason);
    }

    // ---------------------------------------------------------------
    // The /weigh landing page
    // ---------------------------------------------------------------

    public function test_the_landing_page_counts_lines_waiting_on_the_customer(): void
    {
        Setting::current()->update(['weigh_customer_confirmation_enabled' => true]);
        [, , $order] = $this->seatedTable();
        $this->record($order, ['confirmation_status' => 'pending_customer']);

        $this->actingAs($this->staff)
            ->get('/weigh')
            ->assertInertia(fn ($page) => $page->where('stats.pendingConfirmationCount', 1));
    }

    public function test_the_landing_page_totals_todays_weighing(): void
    {
        [, , $order] = $this->seatedTable();
        $this->record($order, ['net_grams' => 680, 'amount_charged' => '306.00']);

        $this->actingAs($this->staff)
            ->get('/weigh')
            ->assertInertia(fn ($page) => $page
                ->where('stats.weighedTodayKg', 0.68)
                ->where('stats.weighedTodayAmount', '306.00'));
    }

    public function test_filter_pending_lists_the_concrete_waiting_lines(): void
    {
        Setting::current()->update(['weigh_customer_confirmation_enabled' => true]);
        [, , $order] = $this->seatedTable();
        $this->record($order, ['confirmation_status' => 'pending_customer']);

        $this->actingAs($this->staff)
            ->get('/weigh?filter=pending')
            ->assertInertia(fn ($page) => $page
                ->where('filter', 'pending')
                ->has('pendingItems', 1)
                ->where('pendingItems.0.item_name', 'Bangus')
                ->where('pendingItems.0.order_number', $order->orderNumber()));
    }

    public function test_without_the_filter_pending_items_is_not_loaded(): void
    {
        $this->actingAs($this->staff)
            ->get('/weigh')
            ->assertInertia(fn ($page) => $page->where('pendingItems', null));
    }

    // ---------------------------------------------------------------
    // Route permissions
    // ---------------------------------------------------------------

    public function test_the_daily_prices_page_kept_its_route_name_after_the_move(): void
    {
        $this->assertSame(url('/weigh/prices'), route('weigh.prices.index'));

        $this->actingAs($this->admin)->get(route('weigh.prices.index'))->assertOk();
    }

    public function test_staff_still_cannot_reach_the_daily_prices_page(): void
    {
        $this->actingAs($this->staff)->get('/weigh/prices')->assertForbidden();
    }
}
