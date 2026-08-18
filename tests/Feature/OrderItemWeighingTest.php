<?php

namespace Tests\Feature;

use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWeighing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A weighing is the evidence behind a weighed line, so it cannot be
 * changed in place — only voided, or superseded by a new revision. These
 * tests exercise the model's own guard directly, independent of whichever
 * HTTP flow happens to write it.
 */
class OrderItemWeighingTest extends TestCase
{
    use RefreshDatabase;

    private function weighing(array $overrides = []): OrderItemWeighing
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $item = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'availability_status' => 'available',
        ]);
        $style = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);

        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => '0.00',
        ]);

        $orderItem = $order->items()->create([
            'menu_item_id' => $item->id,
            'item_name' => $item->name,
            'unit_price' => '177.00',
            'quantity' => 1,
            'subtotal' => '177.00',
            'line_type' => 'weighed',
            'weight_grams' => 600,
        ]);

        return OrderItemWeighing::create(array_merge([
            'order_item_id' => $orderItem->id,
            'net_grams' => 600,
            'amount_charged' => '177.00',
            'reference_price_per_kilo' => '295.00',
            'computed_amount' => '177.00',
            'cooking_style_id' => $style->id,
            'revision' => 1,
        ], $overrides));
    }

    public function test_a_weighing_cannot_be_updated_directly(): void
    {
        $weighing = $this->weighing();

        $this->expectException(RuntimeException::class);

        $weighing->update(['amount_charged' => '999.00']);
    }

    public function test_voiding_is_the_one_permitted_mutation(): void
    {
        $weighing = $this->weighing();

        $weighing->void('Customer changed their mind.');

        $this->assertNotNull($weighing->fresh()->voided_at);
        $this->assertSame('Customer changed their mind.', $weighing->fresh()->void_reason);
        // The figures that were actually charged are untouched.
        $this->assertSame('177.00', (string) $weighing->fresh()->amount_charged);
    }

    public function test_supersede_creates_a_new_row_and_leaves_the_original_untouched(): void
    {
        $original = $this->weighing();

        $revised = $original->supersede([
            'net_grams' => 900,
            'amount_charged' => '265.50',
            'reference_price_per_kilo' => '295.00',
            'computed_amount' => '265.50',
        ]);

        $this->assertSame(2, $revised->revision);
        $this->assertSame($original->id, $revised->supersedes_id);
        $this->assertSame(600, $original->fresh()->net_grams, 'The original revision must be untouched.');
        $this->assertSame(900, $revised->net_grams);
        $this->assertSame(2, OrderItemWeighing::where('order_item_id', $original->order_item_id)->count());
    }

    public function test_active_weighing_follows_the_newest_non_voided_revision(): void
    {
        $original = $this->weighing();
        $revised = $original->supersede([
            'net_grams' => 900,
            'amount_charged' => '265.50',
            'reference_price_per_kilo' => '295.00',
            'computed_amount' => '265.50',
        ]);

        $item = OrderItem::find($original->order_item_id);

        $this->assertTrue($item->activeWeighing()->is($revised));
    }

    public function test_has_variance_is_false_when_charged_matches_computed(): void
    {
        $weighing = $this->weighing(['amount_charged' => '177.00', 'computed_amount' => '177.00', 'variance_amount' => '0.00']);

        $this->assertFalse($weighing->hasVariance());
    }

    public function test_has_variance_is_true_when_they_differ(): void
    {
        $weighing = $this->weighing(['amount_charged' => '200.00', 'computed_amount' => '177.00', 'variance_amount' => '23.00']);

        $this->assertTrue($weighing->hasVariance());
    }
}
