<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two reporting gaps found in the end-to-end sales test:
 *
 * 1. Cash (₱795) and GCash (₱820) were lumped into one ₱1,615 total, so
 *    the drawer could not be counted against the report at closing.
 * 2. "Total Orders 3" sat next to an average computed over the 2 paid
 *    ones — two populations shown as one.
 */
class ReportPaymentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function boss(): User
    {
        return User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
    }

    private function paidOrder(string $total, ?string $createdAt = null): Order
    {
        return Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'PM-'.uniqid(),
            'status' => 'served',
            'payment_status' => 'paid',
            'total_amount' => $total,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    private function payment(Order $order, PaymentMethod $method, string $amount, array $overrides = []): OrderPayment
    {
        return OrderPayment::create(array_merge([
            'order_id' => $order->id,
            'payment_method' => $method,
            'status' => OrderPaymentStatus::Recorded,
            'amount' => $amount,
            'received_at' => now(),
        ], $overrides));
    }

    private function reportData(User $user): array
    {
        return $this->actingAs($user)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk()
            ->original
            ->getData();
    }

    public function test_each_payment_method_gets_its_own_total(): void
    {
        $this->payment($this->paidOrder('795.00'), PaymentMethod::Cash, '795.00');
        $this->payment($this->paidOrder('820.00'), PaymentMethod::Gcash, '820.00');

        $data = $this->reportData($this->boss());

        $byMethod = collect($data['paymentMethods'])->keyBy('payment_method');

        $this->assertSame(795.0, $byMethod['cash']->total_amount);
        $this->assertSame(820.0, $byMethod['gcash']->total_amount);
        $this->assertSame(1615.0, $data['paymentMethodsTotal']);
        $this->assertSame(2, $data['paymentMethodsCount']);
    }

    public function test_a_split_payment_is_reported_against_both_methods(): void
    {
        $order = $this->paidOrder('800.00');
        $this->payment($order, PaymentMethod::Cash, '500.00');
        $this->payment($order, PaymentMethod::Gcash, '300.00');

        $data = $this->reportData($this->boss());
        $byMethod = collect($data['paymentMethods'])->keyBy('payment_method');

        $this->assertSame(500.0, $byMethod['cash']->total_amount);
        $this->assertSame(300.0, $byMethod['gcash']->total_amount);
        $this->assertSame(800.0, $data['paymentMethodsTotal']);
    }

    public function test_voided_payments_are_left_out_of_the_breakdown(): void
    {
        $order = $this->paidOrder('500.00');
        $this->payment($order, PaymentMethod::Cash, '500.00', [
            'status' => OrderPaymentStatus::Voided,
            'voided_at' => now(),
        ]);

        $data = $this->reportData($this->boss());

        $this->assertCount(0, $data['paymentMethods']);
        $this->assertSame(0.0, $data['paymentMethodsTotal']);
    }

    public function test_a_payment_is_counted_on_the_day_the_money_came_in(): void
    {
        // Order opened last month, settled today: the cash was taken today,
        // so today's drawer is what has to reconcile against it.
        $order = $this->paidOrder('600.00', now()->subMonthNoOverflow()->startOfMonth()->toDateTimeString());
        $this->payment($order, PaymentMethod::Cash, '600.00', ['received_at' => now()]);

        $data = $this->reportData($this->boss());

        $this->assertSame(600.0, $data['paymentMethodsTotal']);
    }

    public function test_order_counts_never_mix_paid_and_open_populations(): void
    {
        $this->paidOrder('800.00');
        $this->paidOrder('815.00');

        Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'PM-OPEN-'.uniqid(),
            'status' => 'served',
            'payment_status' => 'unpaid',
            'total_amount' => '400.00',
        ]);

        $data = $this->reportData($this->boss());

        $this->assertSame(2, $data['paidOrderCount']);
        $this->assertSame(1, $data['openOrderCount']);

        // The average divides by exactly the population named beside it.
        $this->assertEqualsWithDelta(
            $data['totalRevenue'] / $data['paidOrderCount'],
            $data['averageOrderValue'],
            0.001
        );
        $this->assertEqualsWithDelta(807.5, $data['averageOrderValue'], 0.001);
    }

    public function test_a_slip_merged_onto_another_table_is_not_counted_twice(): void
    {
        $absorbed = $this->paidOrder('0.00');
        $keeper = $this->paidOrder('1000.00');

        $absorbed->update(['merged_into_order_id' => $keeper->id, 'status' => 'completed']);
        $this->payment($keeper, PaymentMethod::Cash, '1000.00');

        $data = $this->reportData($this->boss());

        $this->assertSame(1, $data['paidOrderCount']);
        $this->assertSame(1000.0, (float) $data['totalRevenue']);
        $this->assertSame(1000.0, $data['paymentMethodsTotal']);
    }
}
