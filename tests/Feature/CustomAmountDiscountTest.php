<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DiscountRule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A straight peso discount at checkout — "less ₱500" — next to the
 * percentage ones. The engine always had the fixed mode; what it lacked was
 * a rule row, so cashiers could only ever discount a percentage.
 *
 * The reason is optional here, unlike its percentage sibling, and there is
 * no cap of its own: whatever the cashier keys applies, clamped only by
 * what is still owed. It has to reach the customer's receipt and the sales
 * report exactly like every other discount.
 */
class CustomAmountDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private MenuItem $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DiscountRuleSeeder::class);

        $this->manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $menu = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->pasta = MenuItem::create([
            'menu_category_id' => $menu->id,
            'name' => 'Seafood Pasta',
            'price' => '1000.00',
            'availability_status' => 'available',
        ]);

        Setting::current()->update([
            'tax_registration_type' => 'non_vat',
            'service_charge_enabled' => false,
        ]);
    }

    private function rule(): DiscountRule
    {
        return DiscountRule::where('code', 'custom_amount')->firstOrFail();
    }

    private function order(string $total = '1000.00'): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'CA-'.uniqid(),
            'status' => 'served',
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

    private function pay(Order $order, array $discount, string $amount)
    {
        return $this->actingAs($this->manager)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [$discount + ['rule_id' => $this->rule()->id]],
            'payments' => [['method' => 'cash', 'amount' => $amount]],
        ]);
    }

    public function test_the_rule_is_a_fixed_amount_with_an_optional_reason_and_no_cap(): void
    {
        $rule = $this->rule();

        $this->assertSame('fixed', $rule->calculation_mode->value);
        $this->assertTrue((bool) $rule->is_custom_value, 'The cashier keys the amount.');
        $this->assertFalse((bool) $rule->requires_reason, 'The reason is optional.');
        $this->assertNull($rule->max_discount_amount, 'Any amount is accepted.');
        $this->assertNull($rule->min_bill_amount);
        $this->assertTrue((bool) $rule->is_active);
    }

    public function test_a_peso_discount_comes_off_the_bill_without_a_reason(): void
    {
        $order = $this->order('1000.00');

        $this->pay($order, ['entered_value' => '250.00'], '750.00')->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->currentInvoiceSnapshot;

        $this->assertSame('250.00', $snapshot->discount_amount);
        $this->assertSame('750.00', $snapshot->total_amount_due, '1,000 less 250.');
    }

    public function test_an_optional_reason_is_kept_when_the_cashier_writes_one(): void
    {
        $order = $this->order('1000.00');

        $this->pay($order, ['entered_value' => '100.00', 'reason' => 'Owner said so'], '900.00')
            ->assertSessionHasNoErrors();

        $line = $order->fresh()->currentInvoiceSnapshot->discounts->first();

        $this->assertSame('Custom Amount Discount', $line->rule_name);
        $this->assertSame('fixed', $line->calculation_mode->value);
        $this->assertSame('100.00', $line->calculated_amount);
        $this->assertSame('Owner said so', $line->reason);
    }

    public function test_a_discount_larger_than_the_bill_never_pushes_the_total_negative(): void
    {
        $order = $this->order('1000.00');

        $this->pay($order, ['entered_value' => '5000.00'], '0.00')->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->currentInvoiceSnapshot;

        $this->assertSame('0.00', $snapshot->total_amount_due, 'Clamped to what was owed.');
    }

    public function test_it_is_named_on_the_customers_receipt(): void
    {
        $order = $this->order('1000.00');
        $this->pay($order, ['entered_value' => '250.00', 'reason' => 'Regular guest'], '750.00');

        $this->actingAs($this->manager)
            ->get("/orders/{$order->id}/receipt")
            ->assertOk()
            ->assertSee('Custom Amount Discount')
            ->assertSee('250.00')
            ->assertSee('Regular guest');
    }

    public function test_it_is_summed_into_the_sales_report(): void
    {
        $order = $this->order('1000.00');
        $this->pay($order, ['entered_value' => '250.00'], '750.00');

        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $data = $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk()
            ->original
            ->getData();

        $this->assertSame(250.0, $data['taxSummary']['amountDiscounts'], 'Peso discounts get their own total.');
        $this->assertSame(0.0, $data['taxSummary']['customPercentDiscounts'], 'And are not miscounted as a percentage one.');
        $this->assertSame(250.0, $data['taxSummary']['totalDiscounts'], 'The buckets add up to the total.');

        $byRule = $data['taxSummary']['discountsByRule']->firstWhere('rule_code', 'custom_amount');

        $this->assertNotNull($byRule, 'It is named in the per-rule breakdown.');
        $this->assertSame(250.0, $byRule->total_amount);
        $this->assertSame(1, $byRule->times_used);
        $this->assertSame('fixed', $byRule->calculation_mode);
    }

    public function test_every_kind_of_discount_gets_its_own_line_in_the_report(): void
    {
        // One of each, on three separate bills.
        $amountOrder = $this->order('1000.00');
        $this->pay($amountOrder, ['entered_value' => '250.00'], '750.00')->assertSessionHasNoErrors();

        $percentRule = DiscountRule::where('code', 'custom_percent')->firstOrFail();
        $percentOrder = $this->order('1000.00');
        $this->actingAs($this->manager)->patch("/orders/{$percentOrder->id}/mark-as-paid", [
            'discounts' => [[
                'rule_id' => $percentRule->id,
                'entered_value' => '10',
                'reason' => 'Regular guest',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '900.00']],
        ])->assertSessionHasNoErrors();

        $pwdRule = DiscountRule::where('code', 'pwd')->firstOrFail();
        $pwdOrder = $this->order('1000.00');
        $this->actingAs($this->manager)->patch("/orders/{$pwdOrder->id}/mark-as-paid", [
            'discounts' => [[
                'rule_id' => $pwdRule->id,
                'qualified_name' => 'Mang Tonyo',
                'id_number' => 'PWD-999',
                'item_ids' => [$pwdOrder->items->first()->id],
            ]],
            'payments' => [['method' => 'cash', 'amount' => '800.00']],
        ])->assertSessionHasNoErrors();

        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $summary = $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk()
            ->original
            ->getData()['taxSummary'];

        $this->assertSame(250.0, $summary['amountDiscounts']);
        $this->assertSame(100.0, $summary['customPercentDiscounts'], '10% of 1,000.');
        $this->assertSame(200.0, $summary['pwdDiscounts'], '20% of 1,000.');
        $this->assertSame(0.0, $summary['seniorDiscounts']);
        $this->assertSame(0.0, $summary['otherDiscounts']);

        // Nothing hides: the named buckets add up to the whole.
        $this->assertSame(550.0, $summary['totalDiscounts']);
        $this->assertSame(
            $summary['totalDiscounts'],
            $summary['seniorDiscounts'] + $summary['pwdDiscounts'] + $summary['customPercentDiscounts']
                + $summary['amountDiscounts'] + $summary['otherDiscounts'],
        );

        $this->assertCount(3, $summary['discountsByRule'], 'Each rule is named on its own row.');
    }

    public function test_the_catch_all_tile_stays_off_the_report_until_something_lands_in_it(): void
    {
        $order = $this->order('1000.00');
        $this->pay($order, ['entered_value' => '250.00'], '750.00')->assertSessionHasNoErrors();

        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $response = $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk();

        // The bucket is still computed — it is what guarantees the named
        // tiles add up — it simply has nothing to show.
        $this->assertSame(0.0, $response->original->getData()['taxSummary']['otherDiscounts']);
        $response->assertDontSee('Other Discounts');

        // The named ones are there either way.
        $response->assertSee('Custom Amount Discounts')->assertSee('Total Discounts');
    }

    public function test_the_statutory_discounts_still_report_under_their_own_names(): void
    {
        $order = $this->order('1000.00');

        $senior = DiscountRule::where('code', 'senior_citizen')->firstOrFail();

        $this->actingAs($this->manager)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [[
                'rule_id' => $senior->id,
                'qualified_name' => 'Lola Ising',
                'id_number' => 'SC-12345',
                'item_ids' => [$order->items->first()->id],
            ]],
            'payments' => [['method' => 'cash', 'amount' => '800.00']],
        ])->assertSessionHasNoErrors();

        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $data = $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk()
            ->original
            ->getData();

        $this->assertSame(200.0, $data['taxSummary']['seniorDiscounts'], '20% of 1,000.');
        $this->assertSame(0.0, $data['taxSummary']['amountDiscounts']);
    }
}
