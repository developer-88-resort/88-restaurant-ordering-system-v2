<?php

namespace Tests\Feature;

use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DiscountRule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\Setting;
use App\Models\User;
use App\Services\LateDiscountApplier;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The night's hand tally came out ₱100 lower than the system: one order's
 * discount was never entered at checkout. Adding it afterwards has to bring
 * the report for THAT night down to match — not file the correction under
 * the day it was fixed, which is what Void Payment + paying again does.
 */
class LateDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $staff;

    private MenuItem $pasta;

    private Carbon $lastNight;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DiscountRuleSeeder::class);

        $this->manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true, 'password' => bcrypt('manager-pass')]);
        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        // Admin and Staff sign in with a PIN and are sent to set one up first.
        $this->manager->setPin('246810');
        $this->staff->setPin('135790');

        $menu = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->pasta = MenuItem::create(['menu_category_id' => $menu->id, 'name' => 'Seafood Pasta', 'price' => '1000.00', 'availability_status' => 'available']);

        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);

        $this->lastNight = now()->subDay()->setTime(21, 30);
    }

    private function rule(string $code): DiscountRule
    {
        return DiscountRule::where('code', $code)->firstOrFail();
    }

    private function order(string $total = '1000.00'): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'LD-'.uniqid(),
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total_amount' => $total,
        ]);

        $order->items()->create([
            'menu_item_id' => $this->pasta->id,
            'item_name' => 'Seafood Pasta',
            'unit_price' => $total,
            'quantity' => 1,
            'subtotal' => $total,
            'line_type' => 'fixed',
        ]);

        return $order->fresh('items');
    }

    /** Paid last night, the discount forgotten. */
    private function paidLastNight(Order $order, array $payments, array $discounts = []): Order
    {
        $this->travelTo($this->lastNight);

        $this->actingAs($this->manager)
            ->patch(route('orders.mark-as-paid', $order), ['payments' => $payments, 'discounts' => $discounts])
            ->assertSessionHasNoErrors();

        $this->travelBack();
        // The session's last activity is now "last night", which the idle
        // sign-out would treat as abandoned.
        $this->flushSession();

        return $order->fresh();
    }

    private function lateDiscount(Order $order, array $discounts, ?User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->manager)
            ->from(route('orders.show', $order))
            ->post(route('orders.late-discount', $order), ['discounts' => $discounts] + $extra);
    }

    private function reportFor(Carbon $day): array
    {
        return $this->actingAs($this->manager)
            ->get(route('superadmin.reports.index', ['date' => $day->format('Y-m-d')]))
            ->assertOk()
            ->original
            ->getData();
    }

    public function test_a_forgotten_discount_corrects_the_night_it_was_paid_not_today(): void
    {
        $order = $this->paidLastNight($this->order(), [['method' => 'cash', 'amount' => '1000.00']]);
        $this->assertSame(1000.0, $this->reportFor($this->lastNight)['paymentMethodsTotal']);

        $this->lateDiscount($order, [['rule_id' => $this->rule('custom_amount')->id, 'entered_value' => '100.00']], null, ['note' => 'Forgot the promo'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $snapshot = $order->currentInvoiceSnapshot;

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('900.00', $snapshot->total_amount_due);
        $this->assertSame('100.00', $snapshot->discount_amount);
        $this->assertSame($this->lastNight->format('Y-m-d H:i'), $snapshot->computed_at->format('Y-m-d H:i'), 'The invoice stays on the night of the sale.');
        $this->assertSame($this->lastNight->format('Y-m-d H:i'), $order->paid_at->format('Y-m-d H:i'));

        $payment = $order->payments()->where('status', OrderPaymentStatus::Recorded)->sole();
        $this->assertSame('900.00', $payment->amount);
        $this->assertSame($this->lastNight->format('Y-m-d H:i'), $payment->received_at->format('Y-m-d H:i'));

        $lastNightReport = $this->reportFor($this->lastNight);
        $this->assertSame(900.0, $lastNightReport['paymentMethodsTotal'], "Last night's tally now matches the hand count.");
        $this->assertSame(900.0, (float) $lastNightReport['taxSummary']['netAmountCollected']);
        $this->assertSame(0.0, $this->reportFor(now())['paymentMethodsTotal'], 'Nothing lands on today.');
    }

    public function test_the_replaced_invoice_and_payment_stay_on_record_as_voided(): void
    {
        $order = $this->paidLastNight($this->order(), [['method' => 'cash', 'amount' => '1000.00']]);
        $old = $order->currentInvoiceSnapshot;

        $this->lateDiscount($order, [['rule_id' => $this->rule('custom_amount')->id, 'entered_value' => '100.00']])->assertSessionHasNoErrors();

        $this->assertSame(InvoiceSnapshotStatus::Voided, $old->fresh()->status);
        $this->assertNotSame($old->invoice_number, $order->fresh()->receipt_number, 'A new receipt number is issued.');
        $this->assertSame(2, OrderInvoiceSnapshot::where('order_id', $order->id)->count());
        $voided = $order->payments()->where('status', OrderPaymentStatus::Voided)->sole();
        $this->assertSame('1000.00', $voided->amount);
        $this->assertStringContainsString('discount added after payment', $voided->void_reason);

        $this->assertDatabaseHas('activity_log', ['event' => LateDiscountApplier::AUDIT_EVENT, 'subject_id' => $order->id]);
    }

    public function test_the_discount_comes_off_the_cash_and_other_methods_keep_their_amounts(): void
    {
        $order = $this->paidLastNight($this->order(), [
            ['method' => 'cash', 'amount' => '600.00'],
            ['method' => 'gcash', 'amount' => '400.00', 'reference' => 'GC-778'],
        ]);

        $this->lateDiscount($order, [['rule_id' => $this->rule('custom_amount')->id, 'entered_value' => '100.00']])->assertSessionHasNoErrors();

        $byMethod = $order->payments()->where('status', OrderPaymentStatus::Recorded)->get()->keyBy(fn ($p) => $p->payment_method->value);
        $this->assertSame('400.00', $byMethod['gcash']->amount);
        $this->assertSame('GC-778', $byMethod['gcash']->reference);
        $this->assertSame('500.00', $byMethod['cash']->amount);
    }

    public function test_a_room_charge_keeps_its_room_reference_and_stays_out_of_the_drawer_total(): void
    {
        $order = $this->paidLastNight($this->order(), [
            ['method' => 'room_charge', 'amount' => '1000.00', 'charged_to' => 'Room 204', 'settled_via' => 'gcash', 'reference' => 'GC-5521'],
        ]);

        $this->lateDiscount($order, [['rule_id' => $this->rule('custom_amount')->id, 'entered_value' => '100.00']])->assertSessionHasNoErrors();

        $report = $this->reportFor($this->lastNight);
        $this->assertSame(900.0, $report['roomChargesTotal']);
        $this->assertSame('Room 204', $report['roomCharges']->sole()->charged_to);
        $this->assertSame('GC-5521', $report['roomCharges']->sole()->reference);
        $this->assertSame(PaymentMethod::Gcash, $report['roomCharges']->sole()->settled_via, 'How it is paid carries over too.');
        $this->assertSame(0.0, $report['paymentMethodsTotal']);
    }

    public function test_a_discount_already_on_the_bill_is_kept_when_another_is_added(): void
    {
        $senior = $this->rule('senior_citizen');
        $pwd = $this->rule('pwd');

        $order = $this->paidLastNight(
            $this->order(),
            [['method' => 'cash', 'amount' => '900.00']],
            [['rule_id' => $senior->id, 'qualified_name' => 'Lola Nena', 'id_number' => 'SC-1', 'eligible_amount' => '500.00']],
        );
        $this->assertSame('900.00', $order->currentInvoiceSnapshot->total_amount_due);

        // The form sends the full set: the Senior line pre-ticked, plus the PWD one that was missed.
        $this->lateDiscount($order, [
            ['rule_id' => $senior->id, 'qualified_name' => 'Lola Nena', 'id_number' => 'SC-1', 'eligible_amount' => '500.00'],
            ['rule_id' => $pwd->id, 'qualified_name' => 'Tito Ben', 'id_number' => 'PWD-9', 'eligible_amount' => '250.00'],
        ])->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->currentInvoiceSnapshot;
        $this->assertSame('850.00', $snapshot->total_amount_due, '1,000 less 20% of 500 less 20% of 250.');
        $this->assertEqualsCanonicalizing(['senior_citizen', 'pwd'], $snapshot->discounts->pluck('rule_code')->all());
    }

    public function test_staff_need_a_manager_to_change_a_paid_bill(): void
    {
        $order = $this->paidLastNight($this->order(), [['method' => 'cash', 'amount' => '1000.00']]);
        $senior = ['rule_id' => $this->rule('senior_citizen')->id, 'qualified_name' => 'Lola Nena', 'id_number' => 'SC-1', 'eligible_amount' => '500.00'];

        $this->lateDiscount($order, [$senior], $this->staff)->assertSessionHasErrors('manager_email');
        $this->assertSame('1000.00', $order->fresh()->currentInvoiceSnapshot->total_amount_due);

        $this->lateDiscount($order, [$senior], $this->staff, [
            'manager_email' => $this->manager->email,
            'manager_password' => 'manager-pass',
        ])->assertSessionHasNoErrors();
        $this->assertSame('900.00', $order->fresh()->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_an_unpaid_order_gets_its_discount_at_checkout_not_here(): void
    {
        $order = $this->order();

        $this->lateDiscount($order, [['rule_id' => $this->rule('custom_amount')->id, 'entered_value' => '100.00']])
            ->assertSessionHasErrors('discounts');
    }

    public function test_nothing_changes_if_the_new_bill_would_be_higher_than_what_was_paid(): void
    {
        $amount = $this->rule('custom_amount');
        $order = $this->paidLastNight($this->order(), [['method' => 'cash', 'amount' => '900.00']], [['rule_id' => $amount->id, 'entered_value' => '100.00']]);
        $old = $order->currentInvoiceSnapshot;

        // Swapping the ₱100 off for ₱50 off would raise the bill.
        $this->lateDiscount($order, [['rule_id' => $amount->id, 'entered_value' => '50.00']])->assertSessionHasErrors();

        $order->refresh();
        $this->assertSame($old->id, $order->current_invoice_snapshot_id, 'Rolled back: the old invoice is still the live one.');
        $this->assertSame(InvoiceSnapshotStatus::Active, $old->fresh()->status);
        $this->assertSame(1, $order->payments()->where('status', OrderPaymentStatus::Recorded)->count());
    }

    public function test_the_order_page_offers_it_on_a_paid_bill_with_its_discounts_pre_ticked(): void
    {
        $senior = $this->rule('senior_citizen');
        $order = $this->paidLastNight(
            $this->order(),
            [['method' => 'cash', 'amount' => '900.00']],
            [['rule_id' => $senior->id, 'qualified_name' => 'Lola Nena', 'id_number' => 'SC-1', 'eligible_amount' => '500.00']],
        );

        $this->actingAs($this->manager)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Add a missed discount')
            ->assertSee(route('orders.late-discount', $order), false)
            ->assertSee('Lola Nena');
    }
}
