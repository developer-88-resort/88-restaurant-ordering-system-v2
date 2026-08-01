<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DailyMarketPrice;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The Daily Market Prices page: a manager sets each weighed item's ₱/kg
 * rate once a day, and everything downstream resolves through that rate
 * instead of letting staff type a price at the scale.
 */
class DailyMarketPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MenuItem $bangus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);

        $this->bangus = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '450.00',
            'availability_status' => 'available',
        ]);
    }

    private function savePrices(array $prices, ?string $date = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/weigh/prices', array_filter([
            'date' => $date,
            'prices' => $prices,
        ]));
    }

    /**
     * The M2 acceptance case: once today's rate is set, the resolver stops
     * returning the menu item's standing rate.
     */
    public function test_setting_todays_price_overrides_the_menu_default(): void
    {
        $this->assertSame('450.00', $this->bangus->effectivePricePerKilo());

        $this->savePrices([
            ['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00'],
        ])->assertRedirect();

        $this->assertSame('480.00', $this->bangus->fresh()->effectivePricePerKilo());
        $this->assertDatabaseHas('daily_market_prices', [
            'menu_item_id' => $this->bangus->id,
            'price_per_kilo' => '480.00',
            'effective_date' => Carbon::today()->toDateString(),
            'set_by_user_id' => $this->admin->id,
        ]);
    }

    /** Yesterday's rate must not leak into today's resolution. */
    public function test_a_price_set_for_another_day_does_not_apply_today(): void
    {
        DailyMarketPrice::create([
            'menu_item_id' => $this->bangus->id,
            'price_per_kilo' => '900.00',
            'effective_date' => Carbon::yesterday()->toDateString(),
            'set_by_user_id' => $this->admin->id,
        ]);

        $this->assertSame('450.00', $this->bangus->effectivePricePerKilo());
        $this->assertSame('900.00', $this->bangus->effectivePricePerKilo(Carbon::yesterday()));
    }

    public function test_saving_twice_for_the_same_day_upserts_instead_of_duplicating(): void
    {
        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']]);
        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '505.00']]);

        $this->assertSame(1, DailyMarketPrice::where('menu_item_id', $this->bangus->id)->count());
        $this->assertSame('505.00', $this->bangus->fresh()->effectivePricePerKilo());
    }

    public function test_a_blank_price_leaves_the_item_untouched(): void
    {
        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => null]]);

        $this->assertSame(0, DailyMarketPrice::count());
        $this->assertSame('450.00', $this->bangus->fresh()->effectivePricePerKilo());
    }

    public function test_the_sanity_cap_is_enforced_on_this_page_too(): void
    {
        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '9.99']])
            ->assertSessionHasErrors('prices.0.price_per_kilo');

        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '10000.01']])
            ->assertSessionHasErrors('prices.0.price_per_kilo');

        $this->assertSame(0, DailyMarketPrice::count());
    }

    public function test_every_change_is_audit_logged_with_the_old_and_new_rate(): void
    {
        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']]);

        $activity = Activity::where('log_name', 'audit')
            ->where('event', 'daily_market_price_set')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('DAILY MARKET PRICE SET', $activity->description);
        $this->assertStringContainsString('Bangus', $activity->description);
        $this->assertSame('450.00', $activity->properties['old_price_per_kilo']);
        $this->assertSame('480.00', $activity->properties['new_price_per_kilo']);
        $this->assertSame(Carbon::today()->toDateString(), $activity->properties['effective_date']);
        $this->assertSame($this->admin->id, $activity->causer_id);
    }

    /** Re-saving an unchanged rate shouldn't spam the audit trail. */
    public function test_resaving_an_unchanged_price_logs_nothing(): void
    {
        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']]);
        $countAfterFirst = Activity::where('event', 'daily_market_price_set')->count();

        $this->savePrices([['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']]);

        $this->assertSame($countAfterFirst, Activity::where('event', 'daily_market_price_set')->count());
    }

    public function test_past_days_are_read_only(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $this->savePrices(
            [['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']],
            $yesterday,
        )->assertSessionHas('error');

        $this->assertSame(0, DailyMarketPrice::count());
    }

    public function test_the_page_lists_per_kilo_items_with_yesterdays_price(): void
    {
        DailyMarketPrice::create([
            'menu_item_id' => $this->bangus->id,
            'price_per_kilo' => '430.00',
            'effective_date' => Carbon::yesterday()->toDateString(),
            'set_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/weigh/prices')
            ->assertInertia(fn ($page) => $page
                ->component('WeighPrices/Index')
                ->where('isEditable', true)
                ->where('date', Carbon::today()->toDateString())
                ->has('items', 1)
                ->where('items.0.name', 'Bangus')
                // JSON drops the trailing .0, and ->where() compares strictly.
                ->where('items.0.yesterday_price', 430)
                ->where('items.0.yesterday_was_set', true)
                ->where('items.0.price', null));
    }

    /** Fixed-price items have no market rate and must not appear. */
    public function test_fixed_price_items_are_excluded(): void
    {
        MenuItem::create([
            'menu_category_id' => $this->bangus->menu_category_id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);

        $this->actingAs($this->admin)
            ->get('/weigh/prices')
            ->assertInertia(fn ($page) => $page->has('items', 1)->where('items.0.name', 'Bangus'));
    }

    /**
     * A fixed-price item smuggled into the payload must not gain a market
     * rate just because its id was posted.
     */
    public function test_a_fixed_price_item_in_the_payload_is_ignored(): void
    {
        $adobo = MenuItem::create([
            'menu_category_id' => $this->bangus->menu_category_id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);

        $this->savePrices([['menu_item_id' => $adobo->id, 'price_per_kilo' => '500.00']]);

        $this->assertSame(0, DailyMarketPrice::where('menu_item_id', $adobo->id)->count());
    }

    public function test_staff_cannot_reach_or_set_daily_prices(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($staff)->get('/weigh/prices')->assertForbidden();

        $this->actingAs($staff)->post('/weigh/prices', [
            'prices' => [['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']],
        ])->assertForbidden();

        $this->assertSame(0, DailyMarketPrice::count());
    }

    public function test_a_superadmin_may_set_daily_prices(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($superadmin)->post('/weigh/prices', [
            'prices' => [['menu_item_id' => $this->bangus->id, 'price_per_kilo' => '480.00']],
        ])->assertRedirect();

        $this->assertSame('480.00', $this->bangus->fresh()->effectivePricePerKilo());
    }

    public function test_the_gate_answers_per_role(): void
    {
        $this->assertTrue($this->admin->can('weigh.set_daily_price'));
        $this->assertTrue(User::factory()->create(['role' => UserRole::Superadmin])->can('weigh.set_daily_price'));
        $this->assertFalse(User::factory()->create(['role' => UserRole::Staff])->can('weigh.set_daily_price'));
    }

    /** A malformed or future ?date= falls back to today rather than 500-ing. */
    public function test_a_bad_date_parameter_falls_back_to_today(): void
    {
        foreach (['not-a-date', '2026-13-45', Carbon::tomorrow()->toDateString()] as $bad) {
            $this->actingAs($this->admin)
                ->get('/weigh/prices?date='.$bad)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('date', Carbon::today()->toDateString()));
        }
    }
}
