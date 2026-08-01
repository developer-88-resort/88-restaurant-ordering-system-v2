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
 * matters is that a weighed line lands on the party's EXISTING bill, and
 * that an underweight or off-step reading is refused with a reason.
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
            'weight_step_grams' => 10,
            'allow_tare' => true,
            'availability_status' => 'available',
        ]);

        $this->inihaw = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);
        $this->bangus->cookingStyles()->attach($this->inihaw->id);
    }

    private function seatedTable(): array
    {
        $session = TableSessionManager::findOrOpenFor($this->table);
        $guest = $session->guestSessions()->create(['guest_number' => 1, 'display_name' => 'Test Guest']);
        $order = OrderAppender::findOrStartOrderForSession($session, ['created_by' => $this->staff->id]);

        return [$session, $guest, $order];
    }

    private function record(Order $order, array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post("/orders/{$order->id}/items", array_merge([
            'menu_item_id' => $this->bangus->id,
            'line_type' => LineType::Weighed->value,
            'weight_grams' => 680,
            'tare_grams' => 0,
            'pieces' => 1,
            'cooking_style_id' => $this->inihaw->id,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------

    public function test_the_station_loads_for_operational_staff(): void
    {
        $this->actingAs($this->staff)
            ->get('/weigh')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Weigh/Station')
                ->has('categories', 1)
                ->where('categories.0.items.0.name', 'Bangus')
                ->where('categories.0.items.0.price_per_kilo', 450)
                ->has('areas', 1)
                ->where('can.overridePrice', false)
                ->where('requiresCustomerConfirmation', false));
    }

    public function test_a_manager_may_override_the_price(): void
    {
        $this->actingAs($this->admin)
            ->get('/weigh')
            ->assertInertia(fn ($page) => $page->where('can.overridePrice', true));
    }

    public function test_the_station_shows_todays_market_rate_not_the_standing_one(): void
    {
        DailyMarketPrice::create([
            'menu_item_id' => $this->bangus->id,
            'price_per_kilo' => '480.00',
            'effective_date' => Carbon::today()->toDateString(),
            'set_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)
            ->get('/weigh')
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.items.0.price_per_kilo', 480)
                ->where('categories.0.items.0.default_price_per_kilo', 450));
    }

    // ---------------------------------------------------------------
    // Step 5 — table lookup
    // ---------------------------------------------------------------

    public function test_a_table_with_no_session_reports_none(): void
    {
        $this->actingAs($this->staff)
            ->getJson(route('weigh.tables.session', $this->table->id))
            ->assertOk()
            ->assertJsonPath('session', null);
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
        $this->record($order, ['weight_grams' => 1000]);

        $this->actingAs($this->staff)
            ->getJson(route('weigh.tables.session', $this->table->id))
            ->assertJsonPath('order.total_amount', 450)
            ->assertJsonPath('order.items.0.is_weighed', true)
            ->assertJsonPath('order.items.0.subtotal', 450);
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
        // 0.680 kg × ₱450/kg = ₱306.00
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

        $response = $this->record($order, ['weight_grams' => 200]);

        $response->assertSessionHasErrors('weight_grams');

        $errors = session('errors')->get('weight_grams');
        $this->assertStringContainsString('250', $errors[0]);

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_a_weight_that_is_not_a_multiple_of_the_step_is_blocked(): void
    {
        [, , $order] = $this->seatedTable();

        // 685 g is above the minimum but not a multiple of the 10 g step.
        $this->record($order, ['weight_grams' => 685])
            ->assertSessionHasErrors('weight_grams');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_the_minimum_is_measured_on_the_net_weight_not_the_gross(): void
    {
        [, , $order] = $this->seatedTable();

        // 400 g gross − 200 g tare = 200 g net, under the 250 g minimum.
        $this->record($order, ['weight_grams' => 400, 'tare_grams' => 200])
            ->assertSessionHasErrors('weight_grams');

        $this->assertCount(0, $order->fresh()->items);
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
            ->get('/weigh')
            ->assertInertia(fn ($page) => $page->where('requiresCustomerConfirmation', true));

        [, , $order] = $this->seatedTable();
        $this->record($order, ['confirmation_status' => 'pending_customer']);

        $this->assertSame(
            OrderItemConfirmationStatus::PendingCustomer,
            $order->fresh()->items->first()->confirmation_status,
        );
    }

    // ---------------------------------------------------------------
    // Price override permission
    // ---------------------------------------------------------------

    public function test_staff_cannot_override_the_price(): void
    {
        [, , $order] = $this->seatedTable();

        $this->record($order, [
            'price_per_kilo_snapshot' => '100.00',
            'price_override_reason' => 'Trying it on',
        ])->assertSessionHasErrors('price_per_kilo_snapshot');

        $this->assertCount(0, $order->fresh()->items);
    }

    public function test_a_manager_can_override_the_price_with_a_reason(): void
    {
        [, , $order] = $this->seatedTable();

        $this->record($order, [
            'weight_grams' => 1000,
            'price_per_kilo_snapshot' => '400.00',
            'price_override_reason' => 'Damaged tail, agreed with the customer',
        ], $this->admin)->assertRedirect();

        $item = $order->fresh()->items->first();

        $this->assertSame('400.00', (string) $item->price_per_kilo_snapshot);
        $this->assertSame('400.00', (string) $item->subtotal);
        $this->assertSame('Damaged tail, agreed with the customer', $item->price_override_reason);
        $this->assertSame($this->admin->id, $item->price_overridden_by_user_id);
    }

    public function test_a_price_override_without_a_reason_is_rejected(): void
    {
        [, , $order] = $this->seatedTable();

        $this->record($order, ['price_per_kilo_snapshot' => '400.00'], $this->admin)
            ->assertSessionHasErrors('price_override_reason');
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
