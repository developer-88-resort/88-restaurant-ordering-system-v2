<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Cancel Item" stayed live after the bill was settled, so a line could be
 * taken off an order whose invoice had already frozen it — the receipt
 * would then describe food nobody was charged for.
 *
 * Voiding the payment reopens the order, and with it the line actions:
 * that is the supported way to correct a settled bill.
 */
class CancelBlockedOnPaidOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private MenuItem $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $menu = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->pasta = MenuItem::create([
            'menu_category_id' => $menu->id,
            'name' => 'Seafood Pasta',
            'price' => '450.00',
            'availability_status' => 'available',
        ]);
    }

    private function order(PaymentStatus $paymentStatus): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'PD-'.uniqid(),
            'status' => OrderStatus::Served,
            'payment_status' => $paymentStatus,
            'total_amount' => '450.00',
        ]);

        $order->items()->create([
            'menu_item_id' => $this->pasta->id,
            'item_name' => 'Seafood Pasta',
            'unit_price' => '450.00',
            'quantity' => 1,
            'subtotal' => '450.00',
            'line_type' => 'fixed',
        ]);

        return $order->fresh('items');
    }

    private function cancelLine(Order $order)
    {
        $item = $order->items->first();

        return $this->actingAs($this->manager)->post("/orders/{$order->id}/items/{$item->id}/cancel", [
            'quantity' => 1,
            'reason_code' => 'customer_complaint',
            'notes' => 'Changed their mind',
        ]);
    }

    public function test_a_line_cannot_be_cancelled_once_the_order_is_paid(): void
    {
        $order = $this->order(PaymentStatus::Paid);

        $this->cancelLine($order)->assertForbidden();

        $this->assertSame(0, $order->items->first()->adjustments()->count(), 'Nothing was reversed.');
        $this->assertSame('450.00', $order->fresh()->total_amount);
    }

    public function test_voiding_the_payment_makes_the_line_cancellable_again(): void
    {
        $order = $this->order(PaymentStatus::Paid);

        $this->cancelLine($order)->assertForbidden();

        // The supported correction path: void first, then edit.
        $order->update(['payment_status' => PaymentStatus::Voided]);

        $this->cancelLine($order->fresh('items'))->assertRedirect();

        $this->assertSame(1, $order->items->first()->adjustments()->count());
        $this->assertSame('0.00', $order->fresh()->total_amount);
    }

    public function test_an_unpaid_order_is_unaffected(): void
    {
        $order = $this->order(PaymentStatus::Unpaid);

        $this->cancelLine($order)->assertRedirect();

        $this->assertSame(1, $order->items->first()->adjustments()->count());
    }

    public function test_the_order_page_offers_no_cancel_button_while_paid(): void
    {
        $paid = $this->order(PaymentStatus::Paid);

        // The Alpine method openCancel() is always defined on the page; what
        // must be gone is any button wired to call it with a line.
        $wiredButton = 'openCancel(JSON.parse(';

        $this->actingAs($this->manager)
            ->get(route('orders.show', $paid))
            ->assertOk()
            ->assertSee('Paid — void payment to edit', false)
            ->assertDontSee($wiredButton, false);

        $unpaid = $this->order(PaymentStatus::Unpaid);

        $this->actingAs($this->manager)
            ->get(route('orders.show', $unpaid))
            ->assertOk()
            ->assertSee($wiredButton, false);
    }
}
