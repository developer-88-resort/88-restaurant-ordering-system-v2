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

/**
 * The SC/PWD discount total was always correct (RA 9994 / RA 10754: strip
 * VAT off the eligible base, then 20% off that) — what was missing was any
 * visible line showing WHERE the VAT-exempt portion went, which made the
 * total look wrong to a cashier just subtracting the discount line from the
 * subtotal. These tests pin down that the server (InvoiceCalculator via
 * PaymentFinalizer) and the printed receipt both reconcile exactly, for a
 * single eligible item, a mixed eligible/non-eligible bill, and a fully
 * eligible bill.
 */
class StatutoryDiscountVatExemptionTest extends TestCase
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

        Setting::current()->update(['tax_registration_type' => 'vat', 'tax_rate' => '12.00', 'prices_include_vat' => true, 'service_charge_enabled' => false]);
    }

    public function test_senior_citizen_on_one_item_of_a_four_item_vat_order_reconciles_exactly(): void
    {
        // Mirrors order #88-0810-004 exactly: 395 + 395 + 450 + 490 = 1730,
        // Senior Citizen scoped to the 450 item only.
        $order = $this->makeOrderWithItems([
            'Chilli Cheese Sticks' => '395.00',
            'Lumpiang Shanghai' => '395.00',
            'Crab Stick Salad' => '450.00',
            'Kimchi Jeon' => '490.00',
        ]);
        $senior = DiscountRule::where('code', 'senior_citizen')->firstOrFail();
        $crabStick = $order->items->firstWhere('item_name', 'Crab Stick Salad');

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $senior->id, 'item_ids' => [$crabStick->id], 'qualified_name' => 'Lolo Juan', 'id_number' => 'SC-0001'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '1601.43'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $snapshot = $order->currentInvoiceSnapshot;
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('1730.00', $snapshot->gross_sales);
        $this->assertSame('401.79', $snapshot->vat_exempt_sales);
        $this->assertSame('48.21', $snapshot->vat_exemption_amount);
        $this->assertSame('80.36', $snapshot->discount_amount);
        $this->assertSame('1601.43', $snapshot->total_amount_due);

        // The three displayed lines must reconcile back to the subtotal
        // to the centavo, exactly as the panel now shows them.
        $reconciled = bcsub(bcsub($snapshot->gross_sales, $snapshot->vat_exemption_amount, 2), $snapshot->discount_amount, 2);
        $this->assertSame('1601.43', $reconciled);

        $line = $snapshot->discounts()->first();
        $this->assertSame('48.21', $line->vat_exemption_amount);
        $this->assertSame('450.00', $line->eligible_amount);
        $this->assertSame('Lolo Juan', $line->qualified_name);
    }

    public function test_receipt_prints_the_full_bir_breakdown_and_qualified_customer(): void
    {
        $order = $this->makeOrderWithItems([
            'Chilli Cheese Sticks' => '395.00',
            'Lumpiang Shanghai' => '395.00',
            'Crab Stick Salad' => '450.00',
            'Kimchi Jeon' => '490.00',
        ]);
        $senior = DiscountRule::where('code', 'senior_citizen')->firstOrFail();
        $crabStick = $order->items->firstWhere('item_name', 'Crab Stick Salad');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $senior->id, 'item_ids' => [$crabStick->id], 'qualified_name' => 'Lolo Juan', 'id_number' => 'SC-0001'],
            ],
            'payments' => [['method' => 'cash', 'amount' => '1601.43']],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt");

        $response->assertOk();
        $response->assertSeeInOrder([
            'Gross Sales',
            'VATable Sales',
            'VAT-Exempt Sales',
            'VAT (12%)',
            'VAT Exemption',
            'Senior Citizen Discount',
            'Total Amount Due',
        ]);
        $response->assertSee('1,730.00');
        $response->assertSee('401.79');
        $response->assertSee('48.21');
        $response->assertSee('80.36');
        $response->assertSee('1,601.43');
        $response->assertSee('Qualified Customer');
        $response->assertSee('Lolo Juan');
        $response->assertSee('***0001'); // masked ID number, last 4 kept
    }

    public function test_two_eligible_items_and_one_non_eligible_item_reconciles(): void
    {
        // 300 + 300 eligible, 400 non-eligible = 1000 total.
        $order = $this->makeOrderWithItems([
            'Eligible A' => '300.00',
            'Eligible B' => '300.00',
            'Not Eligible' => '400.00',
        ]);
        $senior = DiscountRule::where('code', 'senior_citizen')->firstOrFail();
        $eligibleIds = $order->items->whereIn('item_name', ['Eligible A', 'Eligible B'])->pluck('id')->all();

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $senior->id, 'item_ids' => $eligibleIds, 'qualified_name' => 'Lola Maria', 'id_number' => 'SC-0002'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '828.57'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $snapshot = $order->currentInvoiceSnapshot;
        $this->assertSame('1000.00', $snapshot->gross_sales);
        $this->assertSame('535.71', $snapshot->vat_exempt_sales);
        $this->assertSame('64.29', $snapshot->vat_exemption_amount);
        $this->assertSame('107.14', $snapshot->discount_amount);
        $this->assertSame('828.57', $snapshot->total_amount_due);

        $reconciled = bcsub(bcsub($snapshot->gross_sales, $snapshot->vat_exemption_amount, 2), $snapshot->discount_amount, 2);
        $this->assertSame('828.57', $reconciled);

        $response = $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt");
        $response->assertOk();
        $response->assertSee('64.29');
        $response->assertSee('107.14');
        $response->assertSee('828.57');
    }

    public function test_whole_bill_eligible_for_senior_citizen_reconciles(): void
    {
        $order = $this->makeOrderWithItems([
            'Item A' => '400.00',
            'Item B' => '350.00',
            'Item C' => '250.00',
        ]);
        $senior = DiscountRule::where('code', 'senior_citizen')->firstOrFail();
        $allIds = $order->items->pluck('id')->all();

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [
                ['rule_id' => $senior->id, 'item_ids' => $allIds, 'qualified_name' => 'Lolo Pedro', 'id_number' => 'SC-0003'],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '714.29'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $snapshot = $order->currentInvoiceSnapshot;
        $this->assertSame('1000.00', $snapshot->gross_sales);
        $this->assertSame('892.86', $snapshot->vat_exempt_sales);
        $this->assertSame('107.14', $snapshot->vat_exemption_amount);
        $this->assertSame('178.57', $snapshot->discount_amount);
        $this->assertSame('714.29', $snapshot->total_amount_due);
        // Nothing left VATable — the whole bill went through the
        // statutory exemption pool.
        $this->assertSame('0.00', $snapshot->vatable_sales);
        $this->assertSame('0.00', $snapshot->vat_amount);

        $reconciled = bcsub(bcsub($snapshot->gross_sales, $snapshot->vat_exemption_amount, 2), $snapshot->discount_amount, 2);
        $this->assertSame('714.29', $reconciled);
    }

    private function makeOrderWithItems(array $items): Order
    {
        static $counter = 0;
        $counter++;

        $total = '0.00';
        foreach ($items as $amount) {
            $total = bcadd($total, $amount, 2);
        }

        $order = Order::create([
            'order_number' => sprintf('88-VATSC-%03d', $counter),
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'status' => OrderStatus::Served,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => $total,
        ]);

        foreach ($items as $name => $amount) {
            OrderItem::create([
                'order_id' => $order->id,
                'item_name' => $name,
                'unit_price' => $amount,
                'quantity' => 1,
                'subtotal' => $amount,
            ]);
        }

        return $order->fresh(['items']);
    }
}
