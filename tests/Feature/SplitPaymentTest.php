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

    public function test_a_card_payment_takes_the_card_type_reference_no_and_approval_code(): void
    {
        $order = $this->makeOrder('500.00');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '500.00', 'card_brand' => 'BancNet', 'reference' => '000123456789', 'approval_code' => 'A1B2C3'],
            ],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $card = $order->payments()->sole();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('BancNet', $card->card_brand);
        $this->assertSame('000123456789', $card->reference);
        $this->assertSame('A1B2C3', $card->approval_code);
        $this->assertNull($card->card_last_four, 'The last 4 digits are no longer asked for.');
        $this->assertSame('Card (BancNet)', $card->displayLabel());
        $this->assertSame('000123456789', $order->payment_reference);
    }

    /**
     * @dataProvider missingCardDetails
     */
    public function test_a_card_payment_missing_any_receipt_detail_is_refused(array $details): void
    {
        $order = $this->makeOrder('500.00');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [['method' => 'card', 'amount' => '500.00'] + $details],
        ])->assertSessionHasErrors('payments');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public static function missingCardDetails(): array
    {
        return [
            'no card type' => [['reference' => '000123456789', 'approval_code' => 'A1B2C3']],
            'a card type not on the list' => [['card_brand' => 'Bogus', 'reference' => '000123456789', 'approval_code' => 'A1B2C3']],
            'no reference no.' => [['card_brand' => 'Visa', 'approval_code' => 'A1B2C3']],
            'no approval code' => [['card_brand' => 'Visa', 'reference' => '000123456789']],
        ];
    }

    /**
     * One guest's card pays several order slips, each keyed with the same
     * Reference No. from the one card machine receipt.
     */
    public function test_the_same_card_reference_no_can_pay_several_order_slips(): void
    {
        $card = ['method' => 'card', 'card_brand' => 'Mastercard', 'reference' => '000999888777', 'approval_code' => 'Z9Y8X7'];

        foreach (['300.00', '450.00', '120.00'] as $total) {
            $slip = $this->makeOrder($total);

            $this->actingAs($this->admin)->patch("/orders/{$slip->id}/mark-as-paid", [
                'payments' => [$card + ['amount' => $total]],
            ])->assertSessionHasNoErrors();

            $this->assertSame(PaymentStatus::Paid, $slip->fresh()->payment_status);
        }
    }

    public function test_a_card_payment_without_an_approval_code_is_refused(): void
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

    /**
     * Staff key the receipt's Reference No. here, and it can repeat — the
     * uniqueness check used to refuse real payments (2026-09-27).
     */
    public function test_a_repeated_reference_or_approval_code_is_accepted(): void
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

        $response->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $third = $this->makeOrder('200.00');
        $this->actingAs($this->admin)->patch("/orders/{$third->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'card', 'amount' => '100.00', 'card_brand' => 'Visa', 'reference' => 'REF-1', 'approval_code' => 'SAME-1'],
                ['method' => 'card', 'amount' => '100.00', 'card_brand' => 'Visa', 'reference' => 'REF-1', 'approval_code' => 'SAME-1'],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Paid, $third->fresh()->payment_status);
    }

    public function test_a_room_charge_records_the_room_and_its_modes_own_details(): void
    {
        $order = $this->makeOrder('800.00');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'room_charge', 'amount' => '800.00', 'charged_to' => 'Room 204', 'settled_via' => 'maya', 'reference' => 'MY-7788'],
            ],
        ])->assertSessionHasNoErrors();

        $charge = $order->fresh()->payments()->sole();
        $this->assertSame(\App\Enums\PaymentMethod::RoomCharge, $charge->payment_method);
        $this->assertSame(\App\Enums\PaymentMethod::Maya, $charge->settled_via);
        $this->assertSame('Room 204', $charge->charged_to);
        $this->assertSame('MY-7788', $charge->reference, "Maya's own Reference No.");
        $this->assertSame('Room Charge (via Maya)', $charge->displayLabel());
    }

    public function test_a_room_charge_paid_by_card_takes_the_card_details(): void
    {
        $order = $this->makeOrder('800.00');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [[
                'method' => 'room_charge', 'amount' => '800.00', 'charged_to' => 'Kubo 3 - Santos', 'settled_via' => 'card',
                'card_brand' => 'Visa', 'reference' => '000111222333', 'approval_code' => 'AP-44',
            ]],
        ])->assertSessionHasNoErrors();

        $charge = $order->fresh()->payments()->sole();
        $this->assertSame('Visa', $charge->card_brand);
        $this->assertSame('000111222333', $charge->reference);
        $this->assertSame('AP-44', $charge->approval_code);
    }

    public function test_a_room_charge_paid_in_cash_gives_change_like_cash(): void
    {
        $order = $this->makeOrder('800.00');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'room_charge', 'amount' => '800.00', 'tendered_amount' => '1000.00', 'charged_to' => 'Room 105', 'settled_via' => 'cash'],
            ],
        ])->assertSessionHasNoErrors();

        $charge = $order->fresh()->payments()->sole();
        $this->assertSame('800.00', $charge->amount);
        $this->assertSame('1000.00', $charge->tendered_amount);
        $this->assertSame('200.00', $charge->change_amount);
    }

    /**
     * @dataProvider incompleteRoomCharges
     */
    public function test_a_room_charge_missing_its_room_or_mode_of_payment_is_refused(array $details): void
    {
        $order = $this->makeOrder('800.00');

        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [['method' => 'room_charge', 'amount' => '800.00'] + $details],
        ])->assertSessionHasErrors();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public static function incompleteRoomCharges(): array
    {
        return [
            'no mode of payment' => [['charged_to' => 'Room 204']],
            'no room or guest' => [['settled_via' => 'cash']],
            'room charge paid by room charge' => [['charged_to' => 'Room 204', 'settled_via' => 'room_charge']],
            'paid through GCash without its reference no.' => [['charged_to' => 'Room 204', 'settled_via' => 'gcash']],
            'paid by card without the card details' => [['charged_to' => 'Room 204', 'settled_via' => 'card']],
        ];
    }

    public function test_bank_transfer_reads_as_qr(): void
    {
        $this->assertSame('QR', \App\Enums\PaymentMethod::BankTransfer->label());
        $this->assertSame('bank_transfer', \App\Enums\PaymentMethod::BankTransfer->value, 'Older payments keep their stored method.');
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

    public function test_cash_overpayment_becomes_change_not_a_rejected_balance(): void
    {
        // The client's raw `amount` is deliberately left unclamped here
        // (as a pre-fix client, or a direct API call, would send it) — the
        // server must derive the real applied amount from what's due
        // itself, never trust this value, and never reject the request
        // just because more cash came in than the bill.
        $order = $this->makeOrder('2855.00');

        $response = $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'cash', 'amount' => '3000.00', 'tendered_amount' => '3000.00'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $cash = $order->payments->first();
        $this->assertSame('2855.00', $cash->amount, 'Amount applied must be capped at what is due.');
        $this->assertSame('3000.00', $cash->tendered_amount);
        $this->assertSame('145.00', $cash->change_amount);
        $this->assertSame('3000.00', $order->amount_received);
        $this->assertSame('145.00', $order->change_amount);
    }

    public function test_overpaid_cash_receipt_prints_tendered_and_change_without_a_negative_value(): void
    {
        $order = $this->makeOrder('2855.00');
        $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", [
            'payments' => [
                ['method' => 'cash', 'amount' => '3000.00', 'tendered_amount' => '3000.00'],
            ],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin)->get("/orders/{$order->id}/receipt");

        $response->assertOk();
        $response->assertSee('3,000.00');
        $response->assertSee('145.00');
        $response->assertDontSee('-145.00');
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
