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
use App\Models\SpaceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A party's whole visit across every channel — QR, the weigh counter, an
 * advance order — lands on ONE tab for the table, as one slip per
 * submission. Adding to an existing slip still works, but only when staff
 * pick that slip explicitly.
 */
class TableTabSlipsAcrossChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_whole_visit_across_qr_weigh_and_quotation_becomes_numbered_slips_on_one_tab(): void
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

        $weighedLine = [
            'menu_item_id' => $bangus->id,
            'line_type' => 'weighed',
            'net_grams' => 600,
            'amount_charged' => 240,
            'pieces' => 1,
            'cooking_style_id' => $style->id,
            'confirmation_status' => 'confirmed',
        ];

        // Slips 1 and 2: two guests at the table order through QR.
        $this->post("/order/{$table->qr_token}", [
            'idempotency_key' => 'round-1',
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 1]],
        ])->assertRedirect();
        $this->post("/order/{$table->qr_token}", [
            'idempotency_key' => 'round-2',
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 2]],
        ])->assertRedirect();

        // Slip 3: the counter weighs a fish — "New slip" is the default.
        $this->actingAs($staff)
            ->postJson(route('weigh.tables.new-slip', $table), $weighedLine, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()
            ->assertJsonPath('order.slip_label', 'Slip #3');

        // Slip 4: a same-day advance order, "new slip" chosen.
        $this->actingAs($staff)->post(route('quotations.store'), [
            'space_id' => $table->id,
            'target' => 'new',
            'request_id' => (string) Str::uuid(),
            'customer_name' => 'Chairman Guest',
            'scheduled_for' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $tabs = SpaceSession::where('space_id', $table->id)->get();
        $this->assertCount(1, $tabs, 'Every channel shares the table\'s one tab.');

        $slips = Order::orderBy('slip_number')->get();
        $this->assertSame([1, 2, 3, 4], $slips->pluck('slip_number')->all());
        $this->assertSame([$tabs->first()->id], $slips->pluck('space_session_id')->unique()->values()->all());
        $this->assertSame($slips->last()->id, Quotation::latest('id')->first()->converted_order_id);

        // Staff can still deliberately add to an existing slip — no new one.
        $slipOne = $slips->first();

        $this->actingAs($staff)
            ->postJson(route('orders.items.store', $slipOne), $weighedLine, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated();

        $this->actingAs($staff)->post(route('quotations.store'), [
            'space_id' => $table->id,
            'target' => $slipOne->id,
            'request_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $rice->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, Order::count(), 'Explicitly adding to a slip never opens another.');
        $this->assertSame([1, 2, 3], $slipOne->items()->pluck('batch_number')->unique()->sort()->values()->all());
    }
}
