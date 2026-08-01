<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\DiscountRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DiscountRuleSeeder::class);

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);
    }

    public function test_cancelling_a_served_meal_preserves_the_original_line_and_adds_a_reversal(): void
    {
        $order = $this->makeOrder([['name' => 'Seafood Pasta', 'price' => '450.00', 'qty' => 1]], OrderStatus::Served);
        $item = $order->items->first();

        $response = $this->actingAs($this->admin)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'food_contamination',
            'notes' => 'Fly found in the dish',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();
        $item->refresh();

        // Original line untouched, reversal row added, total recomputed.
        $this->assertSame('450.00', $item->subtotal);
        $this->assertSame(1, $item->adjustments()->count());

        $adjustment = $item->adjustments()->first();
        $this->assertSame('450.00', $adjustment->reversed_amount);
        $this->assertFalse($adjustment->inventory_restored, 'Served/contaminated food must never auto-restock.');
        $this->assertSame($this->admin->id, $adjustment->requested_by);
        $this->assertSame($this->admin->id, $adjustment->approved_by, 'Past-Pending cancellation needs an approver; admins approve their own.');

        $this->assertSame('0.00', $order->total_amount);
    }

    public function test_partial_quantity_cancellation_recomputes_the_total(): void
    {
        $order = $this->makeOrder([['name' => 'Halo-halo', 'price' => '150.00', 'qty' => 3]], OrderStatus::Served);
        $item = $order->items->first();

        $this->actingAs($this->admin)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'wrong_item_served',
            'notes' => 'One was supposed to be mango shake',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('300.00', $order->total_amount);
        $this->assertSame(2, $order->items->first()->activeQuantity());
    }

    public function test_cancelled_items_are_excluded_from_discount_calculations(): void
    {
        $order = $this->makeOrder([
            ['name' => 'Dish A', 'price' => '500.00', 'qty' => 1],
            ['name' => 'Dish B', 'price' => '500.00', 'qty' => 1],
        ], OrderStatus::Served);

        $itemA = $order->items->firstWhere('item_name', 'Dish A');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/items/{$itemA->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'customer_complaint',
            'notes' => 'Dish was cold and replaced off the bill',
        ])->assertSessionHasNoErrors();

        $rule = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [['rule_id' => $rule->id]],
            'payments' => [['method' => 'cash', 'amount' => '400.00']],
        ])->assertSessionHasNoErrors();

        $order->refresh();

        // 20% of the remaining 500 - never 20% of the original 1000.
        $this->assertSame('100.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('400.00', $order->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_staff_cannot_cancel_a_served_item_without_manager_credentials(): void
    {
        $order = $this->makeOrder([['name' => 'Bulalo', 'price' => '600.00', 'qty' => 1]], OrderStatus::Served);
        $item = $order->items->first();

        $response = $this->actingAs($this->staff)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'food_contamination',
            'notes' => 'Customer complaint',
        ]);

        $response->assertSessionHasErrors('manager_email');
        $this->assertSame(0, $item->adjustments()->count());

        $approved = $this->actingAs($this->staff)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'food_contamination',
            'notes' => 'Customer complaint',
            'manager_email' => $this->admin->email,
            'manager_password' => 'password',
        ]);

        $approved->assertSessionHasNoErrors();
        $adjustment = $item->adjustments()->first();
        $this->assertSame($this->staff->id, $adjustment->requested_by);
        $this->assertSame($this->admin->id, $adjustment->approved_by);
    }

    public function test_cancelling_an_already_paid_item_creates_an_adjustment_without_touching_the_payment(): void
    {
        $order = $this->makeOrder([['name' => 'Sinigang', 'price' => '400.00', 'qty' => 1]], OrderStatus::Served);

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [['method' => 'cash', 'amount' => '400.00']],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $paidSnapshotId = $order->current_invoice_snapshot_id;

        $item = $order->items->first();
        $this->actingAs($this->admin)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'food_contamination',
            'notes' => 'Reported after payment',
        ])->assertSessionHasNoErrors();

        $order->refresh();

        // The adjustment exists and links back to the item; the finalized
        // payment and invoice are not silently edited.
        $this->assertSame(1, $order->itemAdjustments()->count());
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame($paidSnapshotId, $order->current_invoice_snapshot_id);
        $this->assertSame('400.00', $order->currentInvoiceSnapshot->total_amount_due);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_cannot_cancel_more_than_the_remaining_active_quantity(): void
    {
        $order = $this->makeOrder([['name' => 'Lumpia', 'price' => '100.00', 'qty' => 2]], OrderStatus::Served);
        $item = $order->items->first();

        $response = $this->actingAs($this->admin)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 3,
            'reason_code' => 'duplicate_order',
            'notes' => 'Too many',
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, $item->adjustments()->count());
    }

    public function test_order_show_page_renders_with_cancellation_ui(): void
    {
        $order = $this->makeOrder([['name' => 'Seafood Pasta', 'price' => '450.00', 'qty' => 1]], OrderStatus::Served);
        $item = $order->items->first();

        $this->actingAs($this->admin)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'food_contamination',
            'notes' => 'Fly in the food',
        ]);

        $response = $this->actingAs($this->admin)->get("/orders/{$order->id}");

        $response->assertOk();
        $response->assertSee('CANCELLED');
        $response->assertSee('Food contamination');
    }

    /**
     * @param  array<int, array{name: string, price: string, qty: int}>  $items
     */
    private function makeOrder(array $items, OrderStatus $status): Order
    {
        static $counter = 0;
        $counter++;

        $total = '0.00';
        foreach ($items as $line) {
            $total = bcadd($total, bcmul($line['price'], (string) $line['qty'], 2), 2);
        }

        $order = Order::create([
            'order_number' => sprintf('88-CANC-%03d', $counter),
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => $total,
        ]);

        foreach ($items as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'item_name' => $line['name'],
                'unit_price' => $line['price'],
                'quantity' => $line['qty'],
                'subtotal' => bcmul($line['price'], (string) $line['qty'], 2),
            ]);
        }

        return $order->fresh(['items']);
    }
}
