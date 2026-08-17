<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItemWeighing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for two bugs found in the sales report:
 *
 * 1. Best-Selling Items / Sales by Category / Sales by Area all divided by
 *    the same order-level total (tax/service-charge inclusive), which
 *    doesn't match a SUM(order_items.subtotal) basis — percentages summed
 *    to 116% instead of 100%. Fixed by dividing each table by its own
 *    row-sum.
 * 2. Best-Selling Items' Qty column mixed kilos and pieces in one number
 *    for weighed lines. Fixed with a per-row unit label; the controller
 *    exposes the raw kg/qty split so the view can render "X kg" vs "X pc".
 */
class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
    }

    public function test_each_breakdown_table_sums_to_one_hundred_percent(): void
    {
        $areaA = Area::create(['name' => 'Poolside', 'sort_order' => 1, 'is_active' => true]);
        $areaB = Area::create(['name' => 'Garden', 'sort_order' => 2, 'is_active' => true]);

        $mains = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);
        $drinks = MenuCategory::create(['name' => 'Drinks', 'sort_order' => 2, 'is_active' => true]);

        $adobo = MenuItem::create([
            'menu_category_id' => $mains->id, 'name' => 'Adobo', 'price' => '150.00',
            'pricing_type' => 'fixed', 'availability_status' => 'available',
        ]);
        $tea = MenuItem::create([
            'menu_category_id' => $drinks->id, 'name' => 'Iced Tea', 'price' => '50.00',
            'pricing_type' => 'fixed', 'availability_status' => 'available',
        ]);
        $sinigang = MenuItem::create([
            'menu_category_id' => $mains->id, 'name' => 'Sinigang', 'price' => '100.00',
            'pricing_type' => 'fixed', 'availability_status' => 'available',
        ]);

        // total_amount (tax/service-charge inclusive) deliberately does NOT
        // equal the sum of its line subtotals — this mismatch is exactly
        // what made the old shared-denominator math overshoot 100%.
        $orderA = Order::create([
            'order_type' => 'dine_in', 'order_number' => 'RPT-A-'.uniqid(), 'status' => 'served',
            'payment_status' => 'paid', 'total_amount' => '220.00', 'area_id' => $areaA->id,
        ]);
        $orderA->items()->create(['menu_item_id' => $adobo->id, 'item_name' => 'Adobo', 'unit_price' => '150.00', 'quantity' => 1, 'subtotal' => '150.00', 'line_type' => 'fixed']);
        $orderA->items()->create(['menu_item_id' => $tea->id, 'item_name' => 'Iced Tea', 'unit_price' => '50.00', 'quantity' => 1, 'subtotal' => '50.00', 'line_type' => 'fixed']);

        $orderB = Order::create([
            'order_type' => 'dine_in', 'order_number' => 'RPT-B-'.uniqid(), 'status' => 'served',
            'payment_status' => 'paid', 'total_amount' => '110.00', 'area_id' => $areaB->id,
        ]);
        $orderB->items()->create(['menu_item_id' => $sinigang->id, 'item_name' => 'Sinigang', 'unit_price' => '100.00', 'quantity' => 1, 'subtotal' => '100.00', 'line_type' => 'fixed']);

        $response = $this->actingAs($this->admin())->get(route('superadmin.reports.index', ['range' => 'all']));
        $response->assertOk();

        foreach (['bestSellers', 'categorySales', 'areaSales'] as $table) {
            $percentSum = round((float) $response->viewData($table)->sum('percent'), 1);
            $this->assertSame(100.0, $percentSum, "{$table} percentages must sum to 100%, got {$percentSum}%.");
        }
    }

    public function test_best_selling_items_separates_weighed_kilos_from_piece_counts(): void
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $bangus = MenuItem::create([
            'menu_category_id' => $category->id, 'name' => 'Bangus', 'price' => 0,
            'pricing_type' => 'per_kilo', 'price_per_kilo' => '300.00', 'availability_status' => 'available',
        ]);
        $eggroll = MenuItem::create([
            'menu_category_id' => $category->id, 'name' => 'Eggroll', 'price' => '60.00',
            'pricing_type' => 'fixed', 'availability_status' => 'available',
        ]);

        $order = Order::create([
            'order_type' => 'dine_in', 'order_number' => 'RPT-C-'.uniqid(), 'status' => 'served',
            'payment_status' => 'paid', 'total_amount' => '360.00',
        ]);
        // Two separate weigh-ins of the same item: 600g + 417g = 1.017 kg total.
        $order->items()->create(['menu_item_id' => $bangus->id, 'item_name' => 'Bangus', 'unit_price' => '180.00', 'quantity' => 1, 'subtotal' => '180.00', 'line_type' => 'weighed', 'weight_grams' => 600, 'tare_grams' => 0]);
        $order->items()->create(['menu_item_id' => $bangus->id, 'item_name' => 'Bangus', 'unit_price' => '125.10', 'quantity' => 1, 'subtotal' => '125.10', 'line_type' => 'weighed', 'weight_grams' => 417, 'tare_grams' => 0]);
        $order->items()->create(['menu_item_id' => $eggroll->id, 'item_name' => 'Eggroll', 'unit_price' => '60.00', 'quantity' => 4, 'subtotal' => '240.00', 'line_type' => 'fixed']);

        $response = $this->actingAs($this->admin())->get(route('superadmin.reports.index', ['range' => 'all']));
        $response->assertOk();

        $bestSellers = $response->viewData('bestSellers')->keyBy('item_name');

        $this->assertSame('weighed', $bestSellers['Bangus']->line_type);
        $this->assertSame(1017, (int) $bestSellers['Bangus']->total_net_grams, 'Two weigh-ins should sum to 1017g, not a 2-line count.');

        $this->assertSame('fixed', $bestSellers['Eggroll']->line_type);
        $this->assertSame(4, (int) $bestSellers['Eggroll']->total_qty);
    }

    /**
     * The Weighed Items rollup (Overview tab) and the Weighed Lines table
     * (its own tab) are built on the same App\Support\WeighedLineQuery base
     * join specifically so they can't independently drift on what counts as
     * a "current" weighed line — this locks that in with a mixed set of
     * line states (active, voided, awaiting customer confirmation).
     *
     * Both views agree here because every order below is PAID: the rollup
     * is a revenue view (paid orders only) while the Weighed Lines tab is
     * an unfiltered operational ledger, so an unpaid/still-open order would
     * appear in Weighed Lines but not in this rollup — a known, deliberate
     * difference, not something this test is asserting away.
     */
    public function test_weighed_items_rollup_matches_weighed_lines_totals_for_the_same_paid_item(): void
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $bangus = MenuItem::create([
            'menu_category_id' => $category->id, 'name' => 'Bangus', 'price' => 0,
            'pricing_type' => 'per_kilo', 'price_per_kilo' => '300.00', 'availability_status' => 'available',
        ]);

        $weigh = function (int $grams, array $overrides = []) use ($bangus) {
            $order = Order::create([
                'order_type' => 'dine_in', 'order_number' => 'RPT-'.uniqid(), 'status' => 'served',
                'payment_status' => 'paid', 'total_amount' => '300.00',
            ]);
            $item = $order->items()->create(array_merge([
                'menu_item_id' => $bangus->id, 'item_name' => 'Bangus', 'unit_price' => '300.00',
                'quantity' => 1, 'subtotal' => '300.00', 'line_type' => 'weighed', 'weight_grams' => $grams,
            ], $overrides));

            return OrderItemWeighing::create([
                'order_item_id' => $item->id, 'net_grams' => $grams,
                'amount_charged' => bcdiv((string) ($grams * 300), '1000', 2),
                'reference_price_per_kilo' => '300.00',
                'computed_amount' => bcdiv((string) ($grams * 300), '1000', 2),
                'variance_amount' => '0.00', 'variance_percent' => '0.00',
                'revision' => 1, 'weighed_at' => now(),
            ]);
        };

        // Active — counted everywhere.
        $weigh(1000);
        // Voided — excluded from both views.
        $weigh(500)->void('Testing');
        // Awaiting customer confirmation — not voided, so still counted.
        $weigh(800, ['confirmation_status' => 'pending_customer']);

        $admin = $this->admin();

        $reportResponse = $this->actingAs($admin)->get(route('superadmin.reports.index', ['range' => 'all']));
        $reportResponse->assertOk();
        $rollup = $reportResponse->viewData('weighedItems')->keyBy('item_name');

        $linesResponse = $this->actingAs($admin)->get(route('superadmin.reports.weighed-lines', ['range' => 'all']));
        $linesResponse->assertOk();

        // 1000g + 800g = 1.8 kg; the voided 500g line is excluded from both.
        $this->assertEqualsWithDelta(1.8, (float) $rollup['Bangus']->total_kg, 0.001);
        $linesResponse->assertInertia(
            fn ($page) => $this->assertEqualsWithDelta(1.8, (float) $page->toArray()['props']['totals']['total_kg'], 0.001)
        );
    }
}
