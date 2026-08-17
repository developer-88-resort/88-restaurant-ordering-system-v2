<?php

namespace Tests\Feature;

use App\Enums\LineType;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan weigh:audit-legacy-lines` — finds per-kilo lines billed
 * before the weighing record existed, and reports them for a manager to
 * decide. It must never delete, void, or otherwise change what a bill
 * says; it only flags.
 */
class AuditLegacyWeighedLinesTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $bangus;

    private Space $table;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $spaceCategory = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Cottage', 'slug' => 'cottage', 'is_active' => true]);
        $this->table = Space::create([
            'area_id' => $area->id,
            'category_id' => $spaceCategory->id,
            'name' => 'Cottage 😎',
            'status' => 'available',
            'sort_order' => 1,
        ]);

        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $this->bangus = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'availability_status' => 'available',
        ]);
    }

    private function legacyOrder(): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'space_id' => $this->table->id,
            'order_number' => '88-0801-002',
            'status' => 'preparing',
            'payment_status' => 'unpaid',
            'total_amount' => '0.00',
        ]);

        // Priced before the weighing table existed: a quantity line at
        // ₱0.00, with no weighing record behind it at all.
        $order->items()->create([
            'menu_item_id' => $this->bangus->id,
            'item_name' => 'Bangus',
            'unit_price' => '0.00',
            'quantity' => 2,
            'subtotal' => '0.00',
            'line_type' => LineType::Fixed,
        ]);

        return $order;
    }

    public function test_it_flags_a_zero_priced_line_with_no_weighing_record(): void
    {
        $order = $this->legacyOrder();

        $this->artisan('weigh:audit-legacy-lines')->assertSuccessful();

        $this->assertTrue($order->fresh()->items->first()->flagged_for_review);
    }

    public function test_it_does_not_touch_the_price_or_delete_the_line(): void
    {
        $order = $this->legacyOrder();
        $item = $order->items->first();

        $this->artisan('weigh:audit-legacy-lines');

        $this->assertNotNull($item->fresh());
        $this->assertSame('0.00', (string) $item->fresh()->subtotal);
        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_a_properly_weighed_line_is_not_flagged(): void
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => '0.00',
        ]);
        $item = $order->items()->create([
            'menu_item_id' => $this->bangus->id,
            'item_name' => 'Bangus',
            'unit_price' => '177.00',
            'quantity' => 1,
            'subtotal' => '177.00',
            'line_type' => LineType::Weighed,
            'weight_grams' => 600,
        ]);
        $item->weighings()->create([
            'net_grams' => 600,
            'amount_charged' => '177.00',
            'reference_price_per_kilo' => '295.00',
            'computed_amount' => '177.00',
            'revision' => 1,
        ]);

        $this->artisan('weigh:audit-legacy-lines');

        $this->assertFalse($item->fresh()->flagged_for_review);
    }

    public function test_unflag_clears_the_flag_without_touching_anything_else(): void
    {
        $order = $this->legacyOrder();
        $this->artisan('weigh:audit-legacy-lines');
        $this->assertTrue($order->fresh()->items->first()->flagged_for_review);

        $this->artisan('weigh:audit-legacy-lines --unflag')->assertSuccessful();

        $this->assertFalse($order->fresh()->items->first()->flagged_for_review);
    }
}
