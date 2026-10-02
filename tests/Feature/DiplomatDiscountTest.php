<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
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

class DiplomatDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private User $staff;

    private DiscountRule $diplomat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);
        // Staff on purpose: the diplomat discount needs no manager.
        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        Setting::current()->update([
            'tax_registration_type' => 'vat',
            'tax_rate' => 12,
            'prices_include_vat' => true,
            'service_charge_enabled' => false,
        ]);

        $this->seed(DiscountRuleSeeder::class);
        $this->diplomat = DiscountRule::where('code', 'diplomat')->firstOrFail();
    }

    public function test_one_diplomat_in_a_group_of_five_pays_their_share_without_vat(): void
    {
        // ₱5,000 for 5 persons → ₱1,000 each; the diplomat's ₱1,000 becomes
        // ₱1,000 / 1.12 = ₱892.86, the other four pay ₱4,000 as normal.
        $order = $this->makeOrder('5000.00');

        $this->pay($order, totalPersons: 5, qualifiedPersons: 1, due: '4892.86')
            ->assertRedirect()->assertSessionHasNoErrors();

        $snapshot = $order->fresh()->currentInvoiceSnapshot;
        $this->assertSame('4892.86', $snapshot->total_amount_due);
        $this->assertSame('107.14', $snapshot->vat_exemption_amount);
        $this->assertSame('892.86', $snapshot->vat_exempt_sales);
        $this->assertSame('0.00', $snapshot->discount_amount, 'A diplomat gets no percentage off — only the VAT goes.');

        $line = $snapshot->discounts()->sole();
        $this->assertSame(DiscountType::Diplomat, $line->statutory_type);
        $this->assertSame('1000.00', $line->eligible_amount);
        $this->assertSame('107.14', $line->vat_exemption_amount);
        $this->assertSame('0.00', $line->calculated_amount);
        $this->assertSame(5, $line->total_persons);
        $this->assertSame(1, $line->qualified_persons);
        $this->assertSame('Amb. Test', $line->qualified_name);
    }

    public function test_two_diplomats_double_the_vat_exempt_share(): void
    {
        $order = $this->makeOrder('5000.00');

        // 2 × ₱1,000 → ₱1,785.71 without VAT, + ₱3,000 for the rest.
        $this->pay($order, totalPersons: 5, qualifiedPersons: 2, due: '4785.71')->assertSessionHasNoErrors();

        $this->assertSame('4785.71', $order->fresh()->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_an_uneven_split_rounds_the_share_to_the_centavo(): void
    {
        $order = $this->makeOrder('1000.00');

        // ₱1,000 ÷ 3 = ₱333.33 eligible → ₱297.62 + ₱666.67.
        $this->pay($order, totalPersons: 3, qualifiedPersons: 1, due: '964.29')->assertSessionHasNoErrors();

        $line = $order->fresh()->currentInvoiceSnapshot->discounts()->sole();
        $this->assertSame('333.33', $line->eligible_amount);
        $this->assertSame('964.29', $order->fresh()->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_more_diplomats_than_persons_is_refused(): void
    {
        $order = $this->makeOrder('5000.00');

        $this->pay($order, totalPersons: 2, qualifiedPersons: 3, due: '5000.00')->assertSessionHasErrors('discounts');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_the_headcount_is_required(): void
    {
        $order = $this->makeOrder('5000.00');

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [['rule_id' => $this->diplomat->id, 'qualified_name' => 'Amb. Test', 'id_number' => 'DPL-1']],
            'payments' => [['method' => 'cash', 'amount' => '5000.00']],
        ])->assertSessionHasErrors('discounts');
    }

    public function test_a_non_vat_business_has_no_vat_to_take_off(): void
    {
        Setting::current()->update(['tax_registration_type' => 'non_vat']);
        $order = $this->makeOrder('5000.00');

        $this->pay($order, totalPersons: 5, qualifiedPersons: 1, due: '5000.00')->assertSessionHasNoErrors();

        $this->assertSame('5000.00', $order->fresh()->currentInvoiceSnapshot->total_amount_due);
    }

    public function test_the_legacy_single_discount_shape_never_takes_a_diplomat(): void
    {
        $order = $this->makeOrder('5000.00');

        $this->actingAs($this->staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'payment_method' => 'cash',
            'amount_received' => '5000.00',
            'discount_type' => 'diplomat',
            'discount_qualified_name' => 'Amb. Test',
            'discount_id_number' => 'DPL-1',
            'discount_eligibility_method' => 'amount_based',
            'discount_eligible_amount' => '1000.00',
        ])->assertSessionHasErrors('discount_type');
    }

    public function test_the_kitchen_slip_does_not_offer_it(): void
    {
        $this->actingAs($this->staff)->get('/kitchen')
            ->assertOk()
            ->assertViewHas('slipDiscountRules', fn ($rules) => ! $rules->contains('code', 'diplomat'));
    }

    private function pay(Order $order, int $totalPersons, int $qualifiedPersons, string $due)
    {
        return $this->actingAs($this->staff)->patch("/orders/{$order->id}/mark-as-paid", [
            'discounts' => [[
                'rule_id' => $this->diplomat->id,
                'qualified_name' => 'Amb. Test',
                'id_number' => 'DPL-1',
                'total_persons' => $totalPersons,
                'qualified_persons' => $qualifiedPersons,
            ]],
            'payments' => [['method' => 'cash', 'amount' => $due]],
        ]);
    }

    private function makeOrder(string $totalAmount): Order
    {
        static $counter = 0;
        $counter++;

        $order = Order::create([
            'order_number' => sprintf('88-DPL-%03d', $counter),
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
            'item_name' => 'Group meal',
            'unit_price' => $totalAmount,
            'quantity' => 1,
            'subtotal' => $totalAmount,
        ]);

        return $order->fresh(['items']);
    }
}
