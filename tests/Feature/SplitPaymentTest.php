<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);
    }

    public function test_a_1000_bill_settles_with_600_card_and_400_cash(): void
    {
        $order = $this->makeOrder('1000.00');

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => '600.00',
                    'card_brand' => 'Visa',
                    'card_last_four' => '1234',
                    'terminal_reference' => 'TXN-123456',
                    'approval_code' => 'APPR-01',
                    'terminal_id' => 'TERM-1',
                ],
                ['method' => 'cash', 'amount' => '400.00', 'tendered_amount' => '500.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertCount(2, $order->payments);

        $card = $order->payments->firstWhere('payment_method', \App\Enums\PaymentMethod::Card);
        $this->assertSame('600.00', $card->amount);
        $this->assertSame('TXN-123456', $card->terminal_reference);
        $this->assertSame('1234', $card->card_last_four);
        $this->assertSame('Visa', $card->card_brand);

        $cash = $order->payments->firstWhere('payment_method', \App\Enums\PaymentMethod::Cash);
        $this->assertSame('400.00', $cash->amount);
        $this->assertSame('100.00', $cash->change_amount, 'Change must come only from the cash portion.');

        // Order-level rollups: received = 600 card + 500 cash tendered.
        $this->assertSame('1100.00', $order->amount_received);
        $this->assertSame('100.00', $order->change_amount);
    }

    public function test_the_table_cannot_be_closed_while_a_balance_remains(): void
    {
        $order = $this->makeOrder('1000.00');

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '600.00', 'terminal_reference' => 'TXN-SHORT'],
            ],
        ]);

        $response->assertSessionHasErrors('payments');
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_a_card_payment_requires_the_terminal_reference(): void
    {
        $order = $this->makeOrder('500.00');

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '500.00'],
            ],
        ]);

        $response->assertSessionHasErrors('payments');
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_duplicate_terminal_references_are_rejected(): void
    {
        $paidOrder = $this->makeOrder('300.00');
        $this->actingAs($this->admin)->patch("/orders/{$paidOrder->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '300.00', 'terminal_reference' => 'TXN-DUP-1'],
            ],
        ])->assertSessionHasNoErrors();

        $order = $this->makeOrder('400.00');
        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '400.00', 'terminal_reference' => 'TXN-DUP-1'],
            ],
        ]);

        $response->assertSessionHasErrors('payments');
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_full_card_numbers_and_cvv_are_never_stored(): void
    {
        // Schema-level guarantee: no column exists that could hold a full
        // PAN or CVV, and the request shape has no such field either.
        $columns = Schema::getColumnListing('order_payments');

        $this->assertContains('card_last_four', $columns);
        $this->assertNotContains('card_number', $columns);
        $this->assertNotContains('cvv', $columns);

        // And an attempt to smuggle one in is simply ignored (not fillable,
        // not validated, no column).
        $order = $this->makeOrder('200.00');
        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => '200.00',
                    'terminal_reference' => 'TXN-SEC',
                    'card_number' => '4111111111111111',
                    'cvv' => '123',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $payment = $order->fresh()->payments()->first();
        $this->assertNull($payment->card_last_four);
        $this->assertStringNotContainsString('4111111111111111', json_encode($payment->getAttributes()));
    }

    public function test_voiding_one_payment_component_keeps_the_other_entries(): void
    {
        $order = $this->makeOrder('1000.00');
        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '600.00', 'terminal_reference' => 'TXN-VOID-1'],
                ['method' => 'cash', 'amount' => '400.00'],
            ],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $cash = $order->payments->firstWhere('payment_method', \App\Enums\PaymentMethod::Cash);

        $response = $this->actingAs($this->admin)->post("/orders/{$order->id}/payments/{$cash->id}/void", [
            'void_reason' => 'Cash was counted wrong',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame(OrderPaymentStatus::Voided, $cash->fresh()->status);
        // The card entry row survives untouched on the (now voided) invoice.
        $card = $order->payments->firstWhere('payment_method', \App\Enums\PaymentMethod::Card);
        $this->assertSame(OrderPaymentStatus::Recorded, $card->status);
        // The order drops out of Paid — it must be re-finalized correctly.
        $this->assertSame(PaymentStatus::Voided, $order->payment_status);
    }

    public function test_after_an_invoice_void_the_same_terminal_reference_can_be_reentered(): void
    {
        $order = $this->makeOrder('500.00');
        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '500.00', 'terminal_reference' => 'TXN-REDO'],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/void-payment", [
            'void_reason' => 'Wrong discount, redoing checkout',
        ])->assertSessionHasNoErrors();

        // Re-finalize with the SAME card slip — the charge only ever
        // happened once on the terminal.
        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '500.00', 'terminal_reference' => 'TXN-REDO'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_the_receipt_shows_the_split_payment_breakdown(): void
    {
        $order = $this->makeOrder('1000.00');
        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '600.00', 'card_last_four' => '1234', 'terminal_reference' => 'TXN-RCPT-1'],
                ['method' => 'cash', 'amount' => '400.00', 'tendered_amount' => '400.00'],
            ],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt");

        $response->assertOk();
        $response->assertSee('Card ending in 1234');
        $response->assertSee('TXN-RCPT-1');
        $response->assertSee('600.00');
        $response->assertSee('400.00');
    }

    private function makeOrder(string $totalAmount): Order
    {
        static $counter = 0;
        $counter++;

        $order = Order::create([
            'order_number' => sprintf('88-SPLIT-%03d', $counter),
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
