<?php

namespace Tests\Feature;

use App\Enums\OrderItemAdjustmentReason;
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
use App\Services\OrderTotals;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins down what the order total actually is at each stage, so the
 * "cancelled item still counted" class of bug can't reappear silently.
 * Figures mirror a real reported order: 340 + 380 + 860 = 1,580 gross,
 * one 340 line cancelled, a 20% total-bill discount applied.
 */
class OrderTotalConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DiscountRuleSeeder::class);

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        // Mirror the live resort config: VAT-registered, 12% inclusive.
        Setting::current()->update([
            'tax_registration_type' => 'vat',
            'tax_rate' => '12.00',
            'prices_include_vat' => true,
            'service_charge_enabled' => false,
        ]);
    }

    public function test_cancelling_a_line_lowers_the_stored_order_total(): void
    {
        $order = $this->makeOrder();

        $this->assertSame('1580.00', $order->total_amount, 'Gross before any cancellation.');

        $this->cancelFirstItem($order);

        $this->assertSame('1240.00', $order->fresh()->total_amount, '1,580 - 340 cancelled.');
    }

    public function test_a_cancelled_line_is_excluded_before_the_discount_is_applied(): void
    {
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);

        $this->payWithTwentyPercent($order, '992.00');

        $snapshot = $order->fresh()->currentInvoiceSnapshot;

        // The whole point: the discount base is the ACTIVE subtotal.
        $this->assertSame('1240.00', $snapshot->gross_sales, 'Active subtotal, not the 1,580 original.');
        $this->assertSame('248.00', $snapshot->discount_amount, '20% of 1,240 - not of 1,580.');
        $this->assertSame('992.00', $snapshot->total_amount_due);
        $this->assertSame('992.00', $order->fresh()->payments()->first()->amount);
    }

    public function test_every_surface_reports_the_same_total(): void
    {
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);
        $this->payWithTwentyPercent($order, '992.00');

        $order->refresh();
        $due = $order->currentInvoiceSnapshot->total_amount_due;

        $this->assertSame('992.00', $due);
        $this->assertSame('992.00', $order->payments()->first()->amount);
        $this->assertSame('1240.00', $order->total_amount, 'Order total stays net-of-cancellation.');

        // Customer tracking page, staff order page and receipt all render
        // from those same stored figures.
        $this->get("/order/status/{$order->public_token}")->assertOk()->assertSee('992.00');
        $this->actingAs($this->admin)->get("/orders/{$order->id}")->assertOk()->assertSee('992.00');
        $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt")->assertOk()->assertSee('992.00');
    }

    public function test_multiple_cancellations_all_leave_the_active_subtotal(): void
    {
        $order = $this->makeOrder();

        foreach ($order->items->take(2) as $item) {
            $this->cancelItem($order, $item);
        }

        // 1,580 - 340 - 380 = 860.
        $this->assertSame('860.00', $order->fresh()->total_amount);
    }

    public function test_a_fully_cancelled_order_owes_nothing(): void
    {
        $order = $this->makeOrder();

        foreach ($order->items as $item) {
            $this->cancelItem($order, $item);
        }

        $this->assertSame('0.00', $order->fresh()->total_amount);
    }

    public function test_an_order_with_no_discount_is_simply_the_active_subtotal(): void
    {
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [['method' => 'cash', 'amount' => '1240.00']],
        ])->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->currentInvoiceSnapshot;
        $this->assertSame('1240.00', $snapshot->gross_sales);
        $this->assertSame('0.00', $snapshot->discount_amount);
        $this->assertSame('1240.00', $snapshot->total_amount_due);
    }

    public function test_vat_is_recomputed_from_the_active_subtotal_not_the_original(): void
    {
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);
        $this->payWithTwentyPercent($order, '992.00');

        $snapshot = $order->fresh()->currentInvoiceSnapshot;

        // 1,240 inclusive of 12% VAT - net 1,107.14, VAT 132.86.
        // (The 1,580 original would have given 1,410.71 / 169.29.)
        $this->assertSame('1107.14', $snapshot->vatable_sales);
        $this->assertSame('132.86', $snapshot->vat_amount);
    }

    public function test_a_fixed_amount_discount_also_applies_after_cancellation(): void
    {
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);

        $rule = $this->makeDiscountRule(['calculation_mode' => 'fixed', 'value' => null, 'is_custom_value' => true, 'requires_reason' => true, 'requires_manager_approval' => true]);

        if (! $rule) {
            $this->markTestSkipped('No fixed-amount discount rule is seeded.');
        }

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [['rule_id' => $rule->id, 'entered_value' => '100', 'reason' => 'Manager courtesy']],
            'payments' => [['method' => 'cash', 'amount' => '1140.00']],
        ])->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->currentInvoiceSnapshot;
        $this->assertSame('1240.00', $snapshot->gross_sales, 'Fixed discounts also work off the active subtotal.');
        $this->assertSame('1140.00', $snapshot->total_amount_due, '1,240 - 100.');
    }

    public function test_cancelling_after_payment_leaves_the_issued_invoice_untouched(): void
    {
        $order = $this->makeOrder();

        // Paid FIRST, on the full 1,580.
        $this->payWithTwentyPercent($order, '1264.00');
        $order->refresh();
        $issuedTotal = $order->currentInvoiceSnapshot->total_amount_due;
        $this->assertSame('1264.00', $issuedTotal);

        // ...then an item is cancelled.
        $this->cancelFirstItem($order);
        $order->refresh();

        // The live order total drops, but the ISSUED invoice is immutable
        // (a BIR invoice already handed to the customer is never rewritten).
        $this->assertSame('1240.00', $order->total_amount);
        $this->assertSame('1264.00', $order->currentInvoiceSnapshot->total_amount_due);
        $this->assertSame('1264.00', $order->payments()->first()->amount);
    }

    public function test_cancelling_after_payment_records_a_refund_due(): void
    {
        $order = $this->makeOrder();
        $this->payWithTwentyPercent($order, '1264.00');
        $this->cancelFirstItem($order);

        $totals = OrderTotals::for($order->fresh());

        $this->assertSame('1580.00', $totals->originalSubtotal);
        $this->assertSame('340.00', $totals->cancelledAmount);
        $this->assertSame('1240.00', $totals->activeSubtotal);
        $this->assertSame('1264.00', $totals->issuedTotalDue, 'The issued invoice is never rewritten.');
        $this->assertSame('992.00', $totals->correctedTotalDue, '20% re-applied to the 1,240 active subtotal.');
        $this->assertSame('1264.00', $totals->amountPaid);
        $this->assertSame('272.00', $totals->refundDue, '1,264 paid - 992 actually owed.');
        $this->assertTrue($totals->hasRefundDue());

        // The refund has to be visible to the customer AND the cashier.
        $this->get("/order/status/{$order->public_token}")->assertOk()->assertSee('272.00');
        $this->actingAs($this->admin)->get("/orders/{$order->id}")->assertOk()->assertSee('272.00');
        $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt")->assertOk()->assertSee('272.00');
    }

    public function test_an_unpaid_order_owes_no_refund(): void
    {
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);

        $totals = OrderTotals::for($order->fresh());

        $this->assertNull($totals->issuedTotalDue);
        $this->assertNull($totals->correctedTotalDue);
        $this->assertSame('0.00', $totals->refundDue);
        $this->assertSame('1240.00', $totals->payableTotal(), 'Falls back to the live active subtotal.');
    }

    public function test_a_quantity_change_flows_through_to_the_total(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->orderBy('id')->firstOrFail();

        // Two portions of the 340 line instead of one.
        $item->update(['quantity' => 2, 'subtotal' => '680.00']);
        $order->fresh()->recalculateTotal();

        $this->assertSame('1920.00', $order->fresh()->total_amount, '680 + 380 + 860.');
        $this->assertSame('1920.00', OrderTotals::for($order->fresh())->activeSubtotal);
    }

    public function test_partially_cancelling_a_multi_quantity_line_only_reverses_that_part(): void
    {
        $order = $this->makeOrder();
        $item = $order->items()->orderBy('id')->firstOrFail();
        $item->update(['quantity' => 2, 'subtotal' => '680.00']);
        $order->fresh()->recalculateTotal();

        $this->actingAs($this->admin)
            ->post("/orders/{$order->id}/items/{$item->id}/cancel", [
                'quantity' => 1,
                'reason_code' => OrderItemAdjustmentReason::cases()[0]->value,
                'notes' => 'One portion sent back',
            ])->assertSessionHasNoErrors();

        $totals = OrderTotals::for($order->fresh());
        $this->assertSame('340.00', $totals->cancelledAmount, 'Only the one cancelled portion.');
        $this->assertSame('1580.00', $totals->activeSubtotal, '1,920 - 340.');
        $this->assertSame(1, $item->fresh()->activeQuantity());
    }

    public function test_the_reported_sample_order_end_to_end(): void
    {
        // Regression for the exact figures reported from the floor.
        $order = $this->makeOrder();
        $this->cancelFirstItem($order);
        $this->payWithTwentyPercent($order, '992.00');

        $totals = OrderTotals::for($order->fresh());

        $this->assertSame('1580.00', $totals->originalSubtotal, 'Original subtotal');
        $this->assertSame('340.00', $totals->cancelledAmount, 'Cancelled amount');
        $this->assertSame('1240.00', $totals->activeSubtotal, 'Active subtotal');
        $this->assertSame('248.00', $order->fresh()->currentInvoiceSnapshot->discount_amount, 'Discount');
        $this->assertSame('992.00', $totals->correctedTotalDue, 'Final amount due');
        $this->assertSame('0.00', $totals->refundDue, 'Nothing owed back - cancelled before paying.');
    }

    private function makeOrder(): Order
    {
        $order = Order::create([
            'order_number' => '88-TOTAL-001',
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'status' => OrderStatus::Served,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '0.00',
        ]);

        foreach ([['Potato Fries', '340.00'], ['Gyeranjjim', '380.00'], ['Nilagang Bulalo', '860.00']] as [$name, $price]) {
            OrderItem::create([
                'order_id' => $order->id,
                'item_name' => $name,
                'unit_price' => $price,
                'quantity' => 1,
                'subtotal' => $price,
            ]);
        }

        $order->refresh()->recalculateTotal();

        return $order->refresh();
    }

    private function cancelFirstItem(Order $order): void
    {
        $this->cancelItem($order, $order->items()->orderBy('id')->firstOrFail());
    }

    private function cancelItem(Order $order, OrderItem $item): void
    {
        $this->actingAs($this->admin)
            ->post("/orders/{$order->id}/items/{$item->id}/cancel", [
                'quantity' => $item->quantity,
                'reason_code' => OrderItemAdjustmentReason::cases()[0]->value,
                'notes' => 'Customer complaint',
            ])
            ->assertSessionHasNoErrors();
    }

    private function payWithTwentyPercent(Order $order, string $amount): void
    {
        $rule = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);

        $this->actingAs($this->admin)
            ->patch("/orders/{$order->id}/mark-as-paid", [
                'discounts' => [['rule_id' => $rule->id]],
                'payments' => [['method' => 'cash', 'amount' => $amount]],
            ])
            ->assertSessionHasNoErrors();
    }
}
