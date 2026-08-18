<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItemWeighing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Weighed Lines tab of Reports (formerly the standalone /weigh/log
 * page — see the redirect tests below): a per-line ledger of every weighed
 * item, gated the same way as Daily Market Prices and the rest of Reports
 * (manager-tier) since it exposes cross-staff variance/financial data.
 * /weigh/log never had a wider audience than Reports itself — both were
 * already manager-tier only — so moving it in didn't change who can see it.
 */
class WeighLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $bangus;

    protected function setUp(): void
    {
        parent::setUp();

        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $this->bangus = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '300.00',
            'availability_status' => 'available',
        ]);
    }

    private function weighedLine(array $weighingOverrides = []): OrderItemWeighing
    {
        $order = Order::create([
            'order_type' => 'dine_in', 'order_number' => 'WLOG-'.uniqid(), 'status' => 'served',
            'payment_status' => 'paid', 'total_amount' => '300.00',
        ]);

        $item = $order->items()->create([
            'menu_item_id' => $this->bangus->id, 'item_name' => 'Bangus', 'unit_price' => '300.00',
            'quantity' => 1, 'subtotal' => '300.00', 'line_type' => 'weighed', 'weight_grams' => 1000,
        ]);

        return OrderItemWeighing::create(array_merge([
            'order_item_id' => $item->id,
            'net_grams' => 1000,
            'amount_charged' => '300.00',
            'reference_price_per_kilo' => '300.00',
            'computed_amount' => '300.00',
            'variance_amount' => '0.00',
            'variance_percent' => '0.00',
            'revision' => 1,
            'weighed_at' => now(),
        ], $weighingOverrides));
    }

    public function test_staff_cannot_view_the_weighed_lines_tab(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($staff)->get(route('superadmin.reports.weighed-lines'))->assertForbidden();
    }

    public function test_an_admin_can_view_the_weighed_lines_tab(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->weighedLine();

        $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Weigh/Log')
                ->has('logs.data', 1)
                ->where('logs.data.0.item_name', 'Bangus')
            );
    }

    public function test_a_variance_within_tolerance_is_not_flagged(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        // Default settings: tolerance = max(₱1.00, 1% of computed) — for a
        // ₱300 expected amount that's ₱3.00, so a ₱0.50 variance passes.
        $this->weighedLine(['amount_charged' => '300.50', 'computed_amount' => '300.00', 'variance_amount' => '0.50']);

        $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines'))
            ->assertInertia(fn ($page) => $page->where('logs.data.0.over_tolerance', false));
    }

    public function test_a_variance_beyond_tolerance_is_flagged(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->weighedLine(['amount_charged' => '320.00', 'computed_amount' => '300.00', 'variance_amount' => '20.00']);

        $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines'))
            ->assertInertia(fn ($page) => $page->where('logs.data.0.over_tolerance', true));
    }

    public function test_voided_lines_are_hidden_by_default_and_shown_with_the_toggle(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $weighing = $this->weighedLine();
        $weighing->void('Customer changed their mind.');

        $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines'))
            ->assertInertia(fn ($page) => $page->has('logs.data', 0));

        $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines', ['show_voided' => 1]))
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.status', 'voided')
                ->where('logs.data.0.void_reason', 'Customer changed their mind.')
            );
    }

    public function test_csv_export_reflects_the_active_filters(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->weighedLine();
        $voided = $this->weighedLine();
        $voided->void('Testing');

        $response = $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines.export-csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        // Default export (no show_voided) should contain exactly one data
        // row for Bangus, not the voided one.
        $this->assertSame(1, substr_count($csv, 'Bangus'));
    }

    /**
     * Old /weigh/log bookmarks and printed links must keep working
     * permanently, with whatever filters were in the URL, once the page
     * itself moved to Reports.
     */
    public function test_the_old_weigh_log_url_redirects_to_weighed_lines_preserving_the_query_string(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('weigh.log.index', ['show_voided' => 1, 'item_id' => 5]));

        $response->assertRedirect(route('superadmin.reports.weighed-lines', ['show_voided' => 1, 'item_id' => 5]));
        $response->assertStatus(301);
    }

    public function test_the_old_export_urls_redirect_too(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)->get(route('weigh.log.export-csv', ['range' => 'week']))
            ->assertRedirect(route('superadmin.reports.weighed-lines.export-csv', ['range' => 'week']))
            ->assertStatus(301);

        $this->actingAs($admin)->get(route('weigh.log.export-pdf', ['range' => 'week']))
            ->assertRedirect(route('superadmin.reports.weighed-lines.export-pdf', ['range' => 'week']))
            ->assertStatus(301);
    }

    /** A role without weigh.set_daily_price gets a 403 on the old URL too, not a redirect into a page it still can't see. */
    public function test_staff_cannot_use_the_old_weigh_log_url_either(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($staff)->get(route('weigh.log.index'))->assertForbidden();
    }
}
