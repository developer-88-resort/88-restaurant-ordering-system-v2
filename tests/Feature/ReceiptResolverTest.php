<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The end-to-end proof this whole refactor exists for: a guest who ordered
 * through QR, then had fish weighed at the counter, then had a same-day
 * quotation converted for them, must walk away with exactly ONE receipt —
 * not three.
 */
class ReceiptResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_whole_visit_across_qr_weigh_and_quotation_stays_on_one_order(): void
    {
        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $category = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $table = Space::create(['area_id' => $area->id, 'category_id' => $category->id, 'name' => 'Table 9', 'status' => 'available', 'sort_order' => 1]);

        $menuCategory = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $rice = MenuItem::create(['menu_category_id' => $menuCategory->id, 'name' => 'Rice', 'price' => '50.00', 'availability_status' => 'available']);
        $bangus = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '400.00',
            'min_weight_grams' => 250,
            'counter_only' => true,
            'availability_status' => 'available',
        ]);
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);
        $bangus->cookingStyles()->attach($style->id);

        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        // Round 1: a guest scans the table QR and orders.
        $this->post("/order/{$table->qr_token}", [
            'idempotency_key' => 'round-1',
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 1]],
        ])->assertRedirect();

        // Round 2: a second guest at the same table (a different device, no
        // cookie carried) also orders via QR.
        $this->post("/order/{$table->qr_token}", [
            'idempotency_key' => 'round-2',
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertSame(1, Order::count(), 'Two QR rounds on one table must stay one order.');
        $order = Order::firstOrFail();

        // Round 3: staff weighs a fish for the table mid-meal.
        $weighResponse = $this->actingAs($staff)->postJson(route('orders.items.store', $order), [
            'menu_item_id' => $bangus->id,
            'line_type' => 'weighed',
            'net_grams' => 600,
            'amount_charged' => 240,
            'pieces' => 1,
            'cooking_style_id' => $style->id,
            'confirmation_status' => 'confirmed',
        ]);
        $weighResponse->assertCreated();

        $this->assertSame(1, Order::count(), 'Weighing must land on the same order, not a new one.');

        // Round 4: a same-day (not future-scheduled) advance order joins the
        // same table's already-open receipt — explicitly, in one submit.
        $this->actingAs($staff)->postJson(route('quotations.store'), [
            'space_id' => $table->id,
            'target' => $order->id,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'customer_name' => 'Chairman Guest',
            'scheduled_for' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
        $quotation = Quotation::latest('id')->firstOrFail();

        $this->assertSame(1, Order::count(), 'A same-day quotation must also join the same order — exactly one receipt for the whole visit.');

        $order->refresh();
        $batches = $order->items()->pluck('batch_number')->unique()->sort()->values();
        $this->assertSame([1, 2, 3, 4], $batches->all(), 'Four distinct rounds, four distinct batch numbers, one order.');
        $this->assertSame($order->id, $quotation->fresh()->converted_order_id);
    }
}
