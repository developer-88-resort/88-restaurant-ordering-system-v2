<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\DiscountRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use App\Support\RoomChargePicker;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomChargeTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Resto', 'slug' => 'resto', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'KR', 'slug' => 'kr', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'KR 1', 'status' => 'available', 'sort_order' => 1]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);
    }

    public function test_the_migration_seeds_the_57_front_desk_rooms(): void
    {
        $this->assertSame(57, Room::count());
        $this->assertSame(['101 VR', '902 EXR'], [
            Room::inFrontDeskOrder()->with('roomType')->first()->label(),
            Room::inFrontDeskOrder()->with('roomType')->get()->last()->label(),
        ]);
        $this->assertNull(Room::where('room_no', '201')->first());
        $this->assertSame('Pension House', $this->room('511')->roomType->name);
    }

    public function test_a_room_charge_records_the_room_guest_and_generated_charged_to(): void
    {
        $order = $this->makeOrder('1250.00');

        $this->pay($order, [$this->roomCharge('511', '1250.00', 'Juan Dela Cruz', 'REG 32669')])
            ->assertRedirect()->assertSessionHasNoErrors();

        $payment = $order->fresh()->payments->sole();
        $this->assertSame(PaymentMethod::RoomCharge, $payment->payment_method);
        $this->assertSame($this->room('511')->id, $payment->room_id);
        $this->assertSame('511', $payment->room_no);
        $this->assertSame('PH', $payment->room_type_code);
        $this->assertSame('511 PH', $payment->roomLabel());
        $this->assertSame('Juan Dela Cruz', $payment->guest_name);
        $this->assertSame('REG 32669', $payment->guest_ref);
        $this->assertSame('RM 511 PH — Juan Dela Cruz', $payment->charged_to);
        $this->assertNull($payment->settled_via, 'Nothing is paid at the outlet — no "paid through" mode.');
        $this->assertSame('To be settled at front desk', $payment->settlementLabel());
    }

    public function test_the_guest_name_is_optional(): void
    {
        $order = $this->makeOrder('300.00');

        $this->pay($order, [$this->roomCharge('204', '300.00')])->assertSessionHasNoErrors();

        $this->assertSame('RM 204 BD', $order->fresh()->payments->sole()->charged_to);
    }

    public function test_a_room_charge_requires_a_room(): void
    {
        $order = $this->makeOrder('500.00');

        // Neither a free-text room nor a "paid through" mode stands in for one.
        $this->pay($order, [['method' => 'room_charge', 'amount' => '500.00', 'charged_to' => 'RM 511', 'settled_via' => 'other']])
            ->assertSessionHasErrors('payments');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_an_inactive_room_is_refused(): void
    {
        $this->room('517')->update(['active' => false]);
        $order = $this->makeOrder('500.00');

        $this->pay($order, [$this->roomCharge('517', '500.00')])->assertSessionHasErrors('payments');

        $this->assertSame(0, OrderPayment::count());
    }

    public function test_charging_two_rooms_is_two_rows(): void
    {
        $order = $this->makeOrder('1000.00');

        $this->pay($order, [
            $this->roomCharge('203', '600.00', 'Ana'),
            $this->roomCharge('204', '400.00', 'Ben'),
        ])->assertSessionHasNoErrors();

        $payments = $order->fresh()->payments->sortBy('room_no')->values();
        $this->assertCount(2, $payments);
        $this->assertSame(['203', '204'], $payments->pluck('room_no')->all());
        $this->assertSame(['600.00', '400.00'], $payments->pluck('amount')->all());
        $this->assertSame(['RM 203 BD — Ana', 'RM 204 BD — Ben'], $payments->pluck('charged_to')->all());
    }

    public function test_a_late_discount_carries_the_room_fields_to_the_new_row(): void
    {
        $this->seed(DiscountRuleSeeder::class);
        $order = $this->makeOrder('1000.00');
        $this->pay($order, [$this->roomCharge('511', '1000.00', 'Juan', 'REG 1')])->assertSessionHasNoErrors();
        $original = $order->fresh()->payments->sole();
        $this->room('511')->update(['active' => false]); // switched off since — must not matter

        $rule = DiscountRule::where('code', 'custom_percent')->firstOrFail();
        $this->actingAs($this->admin)->post(route('orders.late-discount', $order), [
            'discounts' => [['rule_id' => $rule->id, 'entered_value' => '10', 'reason' => 'Late promo']],
        ])->assertSessionHasNoErrors();

        $payments = $order->fresh()->payments;
        $this->assertSame(OrderPaymentStatus::Voided, $payments->firstWhere('id', $original->id)->status);

        $new = $payments->firstWhere('status', OrderPaymentStatus::Recorded);
        $this->assertNotSame($original->id, $new->id);
        $this->assertSame('900.00', $new->amount);
        $this->assertSame([$original->room_id, '511', 'PH', 'Juan', 'REG 1', 'RM 511 PH — Juan'],
            [$new->room_id, $new->room_no, $new->room_type_code, $new->guest_name, $new->guest_ref, $new->charged_to]);
        $this->assertEquals($original->received_at, $new->received_at, 'The corrected charge stays on the day it was taken.');
    }

    public function test_a_legacy_free_text_room_charge_still_carries_over(): void
    {
        $this->seed(DiscountRuleSeeder::class);
        $order = $this->makeOrder('1000.00');
        $this->pay($order, [['method' => 'cash', 'amount' => '1000.00']])->assertSessionHasNoErrors();
        // Rewrite it as an older room charge: free text, a mode, no room.
        $order->fresh()->payments->sole()->update(['payment_method' => PaymentMethod::RoomCharge, 'settled_via' => PaymentMethod::Other, 'charged_to' => 'rm512 Juan']);

        $rule = DiscountRule::where('code', 'custom_percent')->firstOrFail();
        $this->actingAs($this->admin)->post(route('orders.late-discount', $order), [
            'discounts' => [['rule_id' => $rule->id, 'entered_value' => '10', 'reason' => 'Late promo']],
        ])->assertSessionHasNoErrors();

        $new = $order->fresh()->payments->firstWhere('status', OrderPaymentStatus::Recorded);
        $this->assertSame('rm512 Juan', $new->charged_to);
        $this->assertNull($new->room_no);
        $this->assertTrue($new->isLegacyRoomCharge());
    }

    public function test_the_receipt_prints_an_authorization_per_room_charge_with_a_front_desk_copy(): void
    {
        $order = $this->makeOrder('1000.00');
        $this->pay($order, [
            $this->roomCharge('511', '600.00', 'Juan'),
            ['method' => 'cash', 'amount' => '400.00'],
        ])->assertSessionHasNoErrors();
        $order->refresh();

        $html = $this->actingAs($this->admin)->get(route('orders.receipt', $order))->assertOk()->getContent();

        // One room charge × (outlet copy + front desk copy).
        $this->assertSame(2, substr_count($html, 'ROOM CHARGE AUTHORIZATION'));
        $this->assertStringContainsString('FRONT DESK COPY', $html);
        $this->assertStringContainsString('ROOM 511 PH (Pension House)', $html);
        $this->assertStringContainsString($order->orderNumber(), $html);
        $this->assertStringContainsString($order->receipt_number, $html);
        $this->assertStringContainsString('Guest signature', $html);

        Setting::current()->update(['room_charge_front_desk_copy' => false]);
        $html = $this->get(route('orders.receipt', $order))->getContent();
        $this->assertSame(1, substr_count($html, 'ROOM CHARGE AUTHORIZATION'));
        $this->assertStringNotContainsString('FRONT DESK COPY', $html);
    }

    public function test_a_receipt_without_a_room_charge_has_no_authorization(): void
    {
        $order = $this->makeOrder('500.00');
        $this->pay($order, [['method' => 'cash', 'amount' => '500.00']])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('orders.receipt', $order->fresh()))
            ->assertOk()->assertDontSee('ROOM CHARGE AUTHORIZATION');
    }

    public function test_the_report_groups_room_charges_per_room_with_subtotals_and_lists_legacy_ones(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $a = $this->makeOrder('500.00');
        $this->pay($a, [$this->roomCharge('511', '500.00', 'Juan')]);
        $b = $this->makeOrder('250.00');
        $this->pay($b, [$this->roomCharge('511', '250.00')]);
        $c = $this->makeOrder('300.00');
        $this->pay($c, [$this->roomCharge('101', '300.00', 'Ana')]);
        $legacy = $this->makeOrder('120.00');
        $this->pay($legacy, [['method' => 'cash', 'amount' => '120.00']]);
        $legacy->fresh()->payments->sole()->update(['payment_method' => PaymentMethod::RoomCharge, 'settled_via' => PaymentMethod::Other, 'charged_to' => 'ROOM 203-204 Old Guest']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.reports.index', ['range' => 'today']))->assertOk();

        $response->assertViewHas('roomChargesByRoom', function ($rooms) {
            // VR before PH, whatever order they were charged in.
            return $rooms->pluck('label')->all() === ['101 VR', '511 PH']
                && $rooms->pluck('total')->all() === [300.0, 750.0]
                && $rooms->pluck('count')->all() === [1, 2];
        });
        $response->assertViewHas('legacyRoomChargesTotal', 120.0);
        $response->assertViewHas('roomChargesTotal', 1170.0);
        $response->assertSee('Room 511 PH subtotal')
            ->assertSee('ROOM 203-204 Old Guest')
            ->assertSee('Legacy (free text');

        $this->get(route('superadmin.reports.pdf', ['range' => 'today']))->assertOk();
    }

    public function test_the_picker_offers_rooms_charged_today_and_the_last_guest_name(): void
    {
        $order = $this->makeOrder('500.00');
        $this->pay($order, [$this->roomCharge('702', '500.00', 'Maria')]);

        $config = RoomChargePicker::config();
        $room = $this->room('702');

        $this->assertSame(['VR', 'BD', 'STD', 'PH', 'BS', 'GR', 'EXR'], array_column($config['groups'], 'code'));
        $this->assertSame(1, $config['chargedToday']->{$room->id});
        $this->assertSame('Maria', $config['lastGuest']->{$room->id});
    }

    public function test_superadmin_can_add_and_switch_off_rooms(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $ph = $this->room('511')->room_type_id;

        $this->actingAs($superadmin)->get(route('superadmin.rooms.index'))->assertOk()->assertSee('511 PH');

        $this->post(route('superadmin.rooms.store'), ['room_no' => '518', 'room_type_id' => $ph])->assertSessionHasNoErrors();
        $this->assertTrue(Room::where('room_no', '518')->value('active'));

        $this->post(route('superadmin.rooms.store'), ['room_no' => '518', 'room_type_id' => $ph])->assertSessionHasErrors('room_no', null, 'createRoom');

        $this->patch(route('superadmin.rooms.update', $this->room('518')), ['active' => 0]);
        $this->assertFalse($this->room('518')->active);
        $this->assertNotContains('518', collect(RoomChargePicker::config()['groups'])->flatMap(fn ($g) => array_column($g['rooms'], 'no'))->all());
    }

    public function test_only_superadmin_manages_rooms(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.rooms.index'))->assertForbidden();
        $this->post(route('superadmin.rooms.store'), ['room_no' => '999', 'room_type_id' => 1])->assertForbidden();
    }

    private function room(string $roomNo): Room
    {
        return Room::with('roomType')->where('room_no', $roomNo)->firstOrFail();
    }

    private function roomCharge(string $roomNo, string $amount, ?string $guest = null, ?string $ref = null): array
    {
        return ['method' => 'room_charge', 'amount' => $amount, 'room_id' => $this->room($roomNo)->id, 'guest_name' => $guest, 'guest_ref' => $ref];
    }

    private function pay(Order $order, array $payments)
    {
        return $this->actingAs($this->admin)->patch("/orders/{$order->id}/mark-as-paid", ['payments' => $payments]);
    }

    private function makeOrder(string $totalAmount): Order
    {
        static $counter = 0;
        $counter++;

        $order = Order::create([
            'order_number' => sprintf('88-RC-%03d', $counter),
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
