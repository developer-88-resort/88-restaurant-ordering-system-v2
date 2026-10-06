<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A slip is Completed only once it is paid, and then on its own the moment
 * the payment is saved. Before this, a slip could be marked Completed while
 * still Unpaid and drop off every open list with the bill never collected.
 */
class OrderCompletesWhenSettledTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private MenuItem $sinigang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $menu = MenuCategory::create(['name' => 'Soup', 'is_active' => true]);
        $this->sinigang = MenuItem::create([
            'menu_category_id' => $menu->id,
            'name' => 'Sinigang na Hipon',
            'price' => '690.00',
            'availability_status' => 'available',
        ]);

        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);
    }

    private function order(string $status): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'ST-'.uniqid(),
            'status' => $status,
            'payment_status' => 'unpaid',
            'total_amount' => '690.00',
        ]);

        $order->items()->create([
            'menu_item_id' => $this->sinigang->id,
            'item_name' => 'Sinigang na Hipon',
            'unit_price' => '690.00',
            'quantity' => 1,
            'subtotal' => '690.00',
            'line_type' => 'fixed',
        ]);

        return $order;
    }

    private function payFor(Order $order)
    {
        return $this->actingAs($this->staff)->patch(route('orders.mark-as-paid', $order), [
            'payments' => [['method' => 'cash', 'amount' => '690.00']],
        ]);
    }

    private function setStatus(Order $order, string $status)
    {
        return $this->actingAs($this->staff)->patch(route('orders.update-status', $order), ['status' => $status]);
    }

    public function test_an_unpaid_slip_cannot_be_marked_completed(): void
    {
        $order = $this->order('served');

        $this->setStatus($order, 'completed')->assertSessionHas('error');

        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    public function test_paying_a_served_slip_completes_it(): void
    {
        $order = $this->order('served');

        $this->payFor($order)->assertSessionHas('status', __('Order :number marked as paid and completed.', ['number' => $order->orderNumber()]));

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_paying_completes_it_right_away_even_while_still_cooking(): void
    {
        foreach (['pending', 'preparing', 'ready'] as $status) {
            $order = $this->order($status);

            $this->payFor($order)->assertSessionHasNoErrors();

            $this->assertSame(OrderStatus::Completed, $order->fresh()->status, "Paid while {$status}.");
        }
    }

    public function test_marking_an_unpaid_slip_served_leaves_it_open_for_payment(): void
    {
        $order = $this->order('ready');

        $this->setStatus($order, 'served');

        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    public function test_an_unpaid_slip_can_still_be_cancelled(): void
    {
        $order = $this->order('pending');

        $this->setStatus($order, 'cancelled')->assertSessionMissing('error');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_the_order_page_greys_out_completed_until_it_is_paid(): void
    {
        $order = $this->order('served');

        $this->actingAs($this->staff)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Completed — collect payment first')
            ->assertSee('Completes on its own once it is paid.');
    }
}
