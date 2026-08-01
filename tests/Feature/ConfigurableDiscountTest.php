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
use App\Services\InvoiceCalculator;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableDiscountTest extends TestCase
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

        // Clean non-VAT math keeps expected figures exact and readable.
        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);
    }

    public function test_twenty_percent_total_bill_discount_on_1000_gives_200(): void
    {
        $order = $this->makeOrder('1000.00');
        $rule = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $rule->id],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '800.00', 'tendered_amount' => '1000.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('200.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('800.00', $order->currentInvoiceSnapshot->total_amount_due);

        $line = $order->currentInvoiceSnapshot->discounts()->first();
        $this->assertSame('20% Total-Bill Discount', $line->rule_name);
        $this->assertSame('200.00', $line->calculated_amount);

        $payment = $order->payments()->first();
        $this->assertSame('800.00', $payment->amount);
        $this->assertSame('200.00', $payment->change_amount);
    }

    public function test_custom_percent_discount_calculates_correctly(): void
    {
        $order = $this->makeOrder('1000.00');
        $rule = DiscountRule::where('code', 'custom_percent')->firstOrFail();

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $rule->id, 'entered_value' => '15', 'reason' => 'Loyalty guest'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '850.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame('150.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('850.00', $order->currentInvoiceSnapshot->total_amount_due);
        // Admin approves their own manager-approval-required discount.
        $this->assertSame($this->admin->id, $order->currentInvoiceSnapshot->discounts()->first()->approved_by);
    }

    public function test_custom_fixed_discount_calculates_correctly(): void
    {
        $order = $this->makeOrder('1000.00');
        $rule = $this->makeDiscountRule(['name' => 'Custom Fixed-Amount Discount', 'calculation_mode' => 'fixed', 'value' => null, 'is_custom_value' => true, 'requires_reason' => true, 'requires_manager_approval' => true]);

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $rule->id, 'entered_value' => '100', 'reason' => 'Chairman instruction'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '900.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame('100.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('900.00', $order->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_exclusive_discount_cannot_combine_without_manager_approval(): void
    {
        $order = $this->makeOrder('1000.00');
        $general = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);
        $promo = $this->makeDiscountRule(['name' => 'Promotional Discount', 'value' => null, 'is_custom_value' => true, 'is_stackable' => true, 'requires_reason' => true]);

        $response = $this->actingAs($this->staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $general->id],
                ['rule_id' => $promo->id, 'entered_value' => '10', 'reason' => 'Promo month'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '720.00'],
            ],
        ]);

        $response->assertSessionHasErrors('manager_email');
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_exclusive_conflict_is_allowed_with_manager_override_and_the_approver_is_recorded(): void
    {
        $order = $this->makeOrder('1000.00');
        $general = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);
        $promo = $this->makeDiscountRule(['name' => 'Promotional Discount', 'value' => null, 'is_custom_value' => true, 'is_stackable' => true, 'requires_reason' => true]);

        // Sequential stacking: 20% off 1000 - 800, then 10% off 800 - 720.
        $response = $this->actingAs($this->staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $general->id],
                ['rule_id' => $promo->id, 'entered_value' => '10', 'reason' => 'Promo month'],
            ],
            'manager_email' => $this->admin->email,
            'manager_password' => 'password',
            'payments' => [
                ['method' => 'cash', 'amount' => '720.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame('280.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('720.00', $order->currentInvoiceSnapshot->total_amount_due);
        $this->assertCount(2, $order->currentInvoiceSnapshot->discounts);
        $order->currentInvoiceSnapshot->discounts->each(
            fn ($line) => $this->assertSame($this->admin->id, $line->approved_by)
        );
    }

    public function test_stackable_discounts_combine_when_configuration_allows(): void
    {
        $order = $this->makeOrder('1000.00');
        $senior = DiscountRule::where('code', 'senior_citizen')->firstOrFail();
        $promo = $this->makeDiscountRule(['name' => 'Promotional Discount', 'value' => null, 'is_custom_value' => true, 'is_stackable' => true, 'requires_reason' => true]);

        // Senior: 20% off the 500 eligible portion - 100 off, portion due 400.
        // Promo 10% then applies to the remaining 500 - 50 off.
        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $senior->id, 'eligible_amount' => '500.00', 'qualified_name' => 'Lolo Juan', 'id_number' => 'SC-123'],
                ['rule_id' => $promo->id, 'entered_value' => '10', 'reason' => 'Promo month'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '850.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame('150.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('850.00', $order->currentInvoiceSnapshot->total_amount_due);
        $this->assertCount(2, $order->currentInvoiceSnapshot->discounts);
    }

    public function test_discounts_never_reduce_the_bill_below_zero(): void
    {
        $result = InvoiceCalculator::computeWithDiscountLines([
            'gross_sales' => '1000.00',
            'tax_registration_type' => 'non_vat',
            'tax_rate' => '12',
            'prices_include_vat' => true,
            'discount_lines' => [
                ['calculation_mode' => 'fixed', 'value' => '5000.00'],
            ],
        ]);

        $this->assertSame('1000.00', $result['discount_amount'], 'The fixed discount must be clamped to the bill, not applied in full.');
        $this->assertSame('0.00', $result['total_amount_due']);
    }

    public function test_a_zero_balance_fully_discounted_bill_can_still_be_closed(): void
    {
        $order = $this->makeOrder('1000.00');
        $rule = $this->makeDiscountRule(['name' => 'Complimentary / Management Discount', 'value' => 100.00, 'scope' => 'eligible_items', 'requires_reason' => true, 'requires_manager_approval' => true]);

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $rule->id, 'item_ids' => [$order->items->first()->id], 'reason' => 'Management guest'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '0.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('1000.00', $order->currentInvoiceSnapshot->discount_amount);
        $this->assertSame('0.00', $order->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_inactive_or_out_of_window_rules_are_rejected(): void
    {
        $order = $this->makeOrder('1000.00');
        $rule = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);
        $rule->update(['active_until' => today()->subDay()]);

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $rule->id],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '800.00'],
            ],
        ]);

        $response->assertSessionHasErrors('discounts');
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_the_receipt_lists_each_applied_discount_separately(): void
    {
        $order = $this->makeOrder('1000.00');
        $general = $this->makeDiscountRule(['name' => '20% Total-Bill Discount', 'value' => 20.00]);
        $promo = $this->makeDiscountRule(['name' => 'Promotional Discount', 'value' => null, 'is_custom_value' => true, 'is_stackable' => true, 'requires_reason' => true]);

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $general->id],
                ['rule_id' => $promo->id, 'entered_value' => '10', 'reason' => 'Promo month'],
            ],
            'payments' => [['method' => 'cash', 'amount' => '720.00']],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt");

        $response->assertOk();
        $response->assertSee('20% Total-Bill Discount');
        $response->assertSee('Promotional Discount');
        $response->assertSee('200.00');
        $response->assertSee('80.00');
    }

    private function makeOrder(string $totalAmount): Order
    {
        static $counter = 0;
        $counter++;

        $order = Order::create([
            'order_number' => sprintf('88-DISC-%03d', $counter),
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'status' => OrderStatus::Served,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => $totalAmount,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_name' => 'Test Item',
            'unit_price' => $totalAmount,
            'quantity' => 1,
            'subtotal' => $totalAmount,
        ]);

        return $order->fresh(['items']);
    }
}
