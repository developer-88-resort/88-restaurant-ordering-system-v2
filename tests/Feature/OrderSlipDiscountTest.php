<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\LineType;
use App\Enums\OrderItemAdjustmentReason;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DiscountRule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Printing\KitchenSlipPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Discounts picked on the Kitchen Display for the printed order slip. They
 * follow the receipt's rules (Senior/PWD on chosen items, a custom percent
 * that stands alone) but take a plain share of the price with no VAT, and
 * they never reach the order's own totals or the receipt.
 */
class OrderSlipDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Order $order;

    private OrderItem $sinigang;

    private OrderItem $kareKare;

    private DiscountRule $custom;

    private DiscountRule $senior;

    private DiscountRule $pwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $category = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);
        $menuItem = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Sinigang', 'price' => '390.00', 'availability_status' => 'available']);

        $this->order = Order::create([
            'order_type' => 'takeout',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '1120.00',
            'created_by' => $this->staff->id,
            'order_source' => OrderSource::Staff,
        ]);

        $this->sinigang = OrderItem::create([
            'order_id' => $this->order->id, 'menu_item_id' => $menuItem->id, 'item_name' => 'Sinigang',
            'line_type' => LineType::Fixed, 'unit_price' => '390.00', 'quantity' => 2, 'subtotal' => '780.00',
        ]);
        $this->kareKare = OrderItem::create([
            'order_id' => $this->order->id, 'menu_item_id' => $menuItem->id, 'item_name' => 'Kare-Kare',
            'line_type' => LineType::Fixed, 'unit_price' => '340.00', 'quantity' => 1, 'subtotal' => '340.00',
        ]);

        // The three rules production has, as set up in Settings.
        $this->custom = DiscountRule::create([
            'name' => 'Custom Percentage Discount', 'code' => 'CUSTOM_PCT', 'calculation_mode' => 'percent', 'value' => null, 'is_custom_value' => true,
            'scope' => 'whole_bill', 'is_stackable' => false, 'priority' => 20, 'requires_reason' => true,
            'requires_manager_approval' => true, 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->senior = DiscountRule::create([
            'name' => 'Senior Citizen Discount', 'code' => 'SENIOR', 'calculation_mode' => 'percent', 'value' => '20.00', 'is_custom_value' => false,
            'statutory_type' => DiscountType::SeniorCitizen, 'scope' => 'eligible_items', 'is_stackable' => true, 'priority' => 40,
            'requires_customer_id' => true, 'is_active' => true, 'sort_order' => 2,
        ]);
        $this->pwd = DiscountRule::create([
            'name' => 'PWD Discount', 'code' => 'PWD', 'calculation_mode' => 'percent', 'value' => '20.00', 'is_custom_value' => false,
            'statutory_type' => DiscountType::Pwd, 'scope' => 'eligible_items', 'is_stackable' => true, 'priority' => 50,
            'requires_customer_id' => true, 'is_active' => true, 'sort_order' => 3,
        ]);
    }

    private function save(array $discounts)
    {
        return $this->actingAs($this->staff)->putJson(route('kitchen.slip-discounts.update', $this->order), ['discounts' => $discounts]);
    }

    private function slip()
    {
        return $this->actingAs($this->staff)->get(route('orders.kitchen-slip.print', $this->order));
    }

    public function test_senior_is_a_plain_twenty_percent_of_the_price_with_no_vat(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [$this->sinigang->id, $this->kareKare->id], 'qualified_name' => 'Lola Nena']])
            ->assertOk()
            ->assertJsonPath('totals.subtotal', '1120.00')
            ->assertJsonPath('totals.discounts.0.amount', '224.00')
            ->assertJsonPath('totals.total', '896.00');

        // The printed slip says only "Discount" and the rate — never which
        // kind it is, nor who qualified for it.
        $this->slip()->assertOk()
            ->assertSeeInOrder([__('Subtotal'), '1,120.00', __('Discount'), '-224.00', __('TOTAL'), '896.00'])
            ->assertDontSee('VAT')
            ->assertDontSee('20%')
            ->assertDontSee('Senior')
            ->assertDontSee('PWD')
            ->assertDontSee('Lola Nena');
    }

    public function test_an_item_scoped_discount_only_takes_from_the_chosen_items(): void
    {
        $this->save([['rule_id' => $this->pwd->id, 'item_ids' => [$this->kareKare->id]]])
            ->assertOk()
            ->assertJsonPath('totals.discounts.0.amount', '68.00')
            ->assertJsonPath('totals.total', '1052.00');

        $this->slip()->assertSeeInOrder([__('Discount'), '-68.00', 'Kare-Kare', '1,052.00'])
            ->assertDontSee('PWD');
    }

    public function test_an_eligible_amount_can_stand_in_for_items(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'eligible_amount' => '500']])
            ->assertOk()
            ->assertJsonPath('totals.discounts.0.amount', '100.00')
            ->assertJsonPath('totals.total', '1020.00');
    }

    public function test_senior_and_pwd_stack_on_their_own_items(): void
    {
        $this->save([
            ['rule_id' => $this->pwd->id, 'item_ids' => [$this->kareKare->id]],
            ['rule_id' => $this->senior->id, 'item_ids' => [$this->sinigang->id]],
        ])->assertOk()
            // Printed in the rules' priority order, Senior first.
            ->assertJsonPath('totals.discounts.0.name', 'Senior Citizen Discount')
            ->assertJsonPath('totals.discounts.0.amount', '156.00')
            ->assertJsonPath('totals.discounts.1.amount', '68.00')
            ->assertJsonPath('totals.total', '896.00');
    }

    public function test_a_custom_percentage_takes_from_the_whole_slip(): void
    {
        $this->save([['rule_id' => $this->custom->id, 'value' => '10']])
            ->assertOk()
            ->assertJsonPath('totals.discounts.0.amount', '112.00')
            ->assertJsonPath('totals.total', '1008.00');

        $this->slip()->assertSeeInOrder([__('Discount'), '-112.00', '1,008.00'])
            ->assertDontSee('Custom Percentage')
            ->assertDontSee('10%');
    }

    public function test_the_rules_are_checked_like_checkout(): void
    {
        // An exclusive discount can't be combined.
        $this->save([
            ['rule_id' => $this->custom->id, 'value' => '10'],
            ['rule_id' => $this->senior->id, 'item_ids' => [$this->sinigang->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('discounts');

        // A custom value is needed, and a percent stops at 100.
        $this->save([['rule_id' => $this->custom->id]])->assertJsonValidationErrors('discounts.0.value');
        $this->save([['rule_id' => $this->custom->id, 'value' => '150']])->assertJsonValidationErrors('discounts.0.value');

        // Senior needs its items or an amount, from this order only.
        $this->save([['rule_id' => $this->senior->id]])->assertJsonValidationErrors('discounts.0.item_ids');
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [999999]]])->assertJsonValidationErrors('discounts.0.item_ids');
        $this->save([['rule_id' => $this->senior->id, 'eligible_amount' => '5000']])->assertJsonValidationErrors('discounts.0.eligible_amount');

        // A switched-off rule isn't offered.
        $this->pwd->update(['is_active' => false]);
        $this->save([['rule_id' => $this->pwd->id, 'item_ids' => [$this->kareKare->id]]])->assertJsonValidationErrors('discounts.0.rule_id');

        $this->assertNull($this->order->fresh()->slip_discounts);
    }

    public function test_it_never_touches_the_orders_totals_or_the_receipt(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [$this->sinigang->id, $this->kareKare->id]]])->assertOk();

        $order = $this->order->fresh();
        $this->assertSame('1120.00', (string) $order->total_amount);
        $this->assertNull($order->current_invoice_snapshot_id);
        $this->assertSame(0, $order->invoiceSnapshots()->count());
        $this->assertFalse($order->items()->where('is_discount_eligible', true)->exists());
    }

    public function test_a_later_cancellation_lowers_the_discount_with_the_line(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [$this->sinigang->id]]])->assertOk();

        // One of the two Sinigang is taken off the slip.
        $this->sinigang->adjustments()->create([
            'order_id' => $this->order->id,
            'quantity' => 1,
            'unit_price' => '390.00',
            'reversed_amount' => '390.00',
            'reason_code' => OrderItemAdjustmentReason::kitchenPresets()[0],
            'requested_by' => $this->staff->id,
        ]);

        $this->slip()->assertSeeInOrder([__('Subtotal'), '730.00', '-78.00', __('TOTAL'), '652.00']);
    }

    public function test_an_empty_list_takes_the_discount_off(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [$this->sinigang->id]]])->assertOk();
        $this->save([])->assertOk()->assertJsonPath('totals.total', '1120.00');

        $this->assertNull($this->order->fresh()->slip_discounts);
        $this->slip()->assertDontSee('Senior Citizen Discount');
    }

    public function test_the_direct_print_payload_carries_the_same_figures(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [$this->kareKare->id]]])->assertOk();

        $payload = KitchenSlipPayloadBuilder::build($this->order->fresh());

        $this->assertSame('780.00', $payload['batches'][0]['items'][0]['amount']);
        $this->assertContains('@ 390.00', $payload['batches'][0]['items'][0]['sub_lines']);
        $this->assertSame([[__('Subtotal'), '1,120.00'], [__('Discount'), '-68.00']], $payload['totals']);
        $this->assertSame([__('TOTAL'), '1,052.00'], $payload['total']);
    }

    public function test_the_kitchen_board_shows_the_slip_total_and_the_discount_button(): void
    {
        $this->save([['rule_id' => $this->senior->id, 'item_ids' => [$this->kareKare->id]]])->assertOk();

        $this->actingAs($this->staff)->get(route('kitchen.index'))
            ->assertOk()
            ->assertSee(__('Slip Total'))
            ->assertSee('₱1,052.00')
            ->assertSee('kitchen-slip-discount', false);
    }
}
