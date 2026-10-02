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

        // Every method still has its line, all of them at zero.
        $this->assertTrue(collect($data['paymentMethods'])->every(fn ($row) => $row->entry_count === 0 && $row->total_amount === 0.0));
        $this->assertSame(0.0, $data['paymentMethodsTotal']);
    }

    public function test_every_collected_method_has_a_line_even_when_unused(): void
    {
        $this->payment($this->paidOrder('795.00'), PaymentMethod::Cash, '795.00');

        $data = $this->reportData($this->boss());
        $byMethod = collect($data['paymentMethods'])->keyBy('payment_method');

        $this->assertCount(count(PaymentMethod::cases()) - 1, $data['paymentMethods'], 'Every method but Room Charge.');
        $this->assertSame(0.0, $byMethod['gcash']->total_amount);
        $this->assertArrayNotHasKey('room_charge', $byMethod->all());
        $this->assertSame('cash', collect($data['paymentMethods'])->first()->payment_method, 'Methods with money come first.');
    }

    /**
     * The cashiers tally their drawer against the Grand Total. A room
     * charge never reached the drawer, so it stays out of it and is
     * counted on its own.
     */
    public function test_room_charges_stay_out_of_the_collected_grand_total(): void
    {
        $order = $this->paidOrder('2000.00');
        $this->payment($order, PaymentMethod::Cash, '800.00');
        $this->payment($order, PaymentMethod::RoomCharge, '1200.00', ['reference' => 'Room 204']);

        $data = $this->reportData($this->boss());

        $this->assertSame(800.0, $data['paymentMethodsTotal']);
        $this->assertSame(1, $data['paymentMethodsCount']);
        $this->assertSame(1200.0, $data['roomChargesTotal']);
        $this->assertSame(1, $data['roomChargesCount']);
    }

    public function test_room_charges_are_listed_with_their_room_reference_for_the_front_desk(): void
    {
        $this->payment($this->paidOrder('1200.00'), PaymentMethod::RoomCharge, '1200.00', ['reference' => 'Room 204 - Santos']);
        $this->payment($this->paidOrder('300.00'), PaymentMethod::RoomCharge, '300.00', [
            'reference' => 'Room 105',
            'status' => OrderPaymentStatus::Voided,
            'voided_at' => now(),
        ]);
        $this->payment($this->paidOrder('500.00'), PaymentMethod::Cash, '500.00');

        $boss = $this->boss();
        $data = $this->reportData($boss);

        $this->assertCount(1, $data['roomCharges'], 'Voided room charges and other methods stay out.');
        $this->assertSame('Room 204 - Santos', $data['roomCharges']->first()->reference);
        $this->assertSame(1200.0, $data['roomChargesTotal']);
        $this->assertSame(1, $data['roomChargesCount']);
        $this->assertSame(500.0, $data['paymentMethodsTotal'], 'Only the cash is in the collected total.');

        $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertSee('Room Charges')
            ->assertSee('Room 204 - Santos')
            ->assertDontSee('Room 105');
    }

    public function test_room_charges_have_a_total_per_mode_of_payment_and_an_overall_total(): void
    {
        $this->payment($this->paidOrder('1200.00'), PaymentMethod::RoomCharge, '1200.00', ['charged_to' => 'Room 204', 'settled_via' => PaymentMethod::Gcash]);
        $this->payment($this->paidOrder('300.00'), PaymentMethod::RoomCharge, '300.00', ['charged_to' => 'Room 105', 'settled_via' => PaymentMethod::Gcash]);
        $this->payment($this->paidOrder('800.00'), PaymentMethod::RoomCharge, '800.00', ['charged_to' => 'Kubo 3', 'settled_via' => PaymentMethod::Cash]);
        // From before the mode was asked for.
        $this->payment($this->paidOrder('100.00'), PaymentMethod::RoomCharge, '100.00', ['reference' => 'Room 9']);

        $data = $this->reportData($this->boss());
        $byMode = collect($data['roomChargesByMode'])->keyBy('mode');

        $this->assertSame(1500.0, $byMode['gcash']->total_amount);
        $this->assertSame(2, $byMode['gcash']->entry_count);
        $this->assertSame(800.0, $byMode['cash']->total_amount);
        $this->assertSame(100.0, $byMode['']->total_amount);
        $this->assertSame('To be settled at front desk', $byMode['']->label);
        $this->assertSame('gcash', $data['roomChargesByMode']->first()->mode, 'Biggest first.');

        $this->assertSame(2400.0, $data['roomChargesTotal'], 'Overall, every mode together.');
        $this->assertSame(4, $data['roomChargesCount']);

        $this->actingAs($this->boss())
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertSee('Totals by mode of payment')
            ->assertSee('Overall total')
            ->assertSee('&#8369;1,500.00', false);
    }

    public function test_the_pdf_report_includes_room_charges(): void
    {
        $this->payment($this->paidOrder('1200.00'), PaymentMethod::RoomCharge, '1200.00', ['reference' => 'Room 204 - Santos']);

        $this->actingAs($this->boss())
            ->get(route('superadmin.reports.pdf', ['range' => 'month']))
            ->assertOk();
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
