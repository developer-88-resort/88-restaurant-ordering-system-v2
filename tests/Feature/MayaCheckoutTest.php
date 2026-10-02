<?php

namespace Tests\Feature;

use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\DiscountRule;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Database\Seeders\DiscountRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MayaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://pg-sandbox.paymaya.com';

    private Area $area;

    private SpaceCategory $category;

    private Space $space;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.maya.enabled' => true,
            'services.maya.base_url' => self::BASE,
            'services.maya.public_key' => 'pk-test',
        ]);

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        Setting::current()->update(['tax_registration_type' => 'non_vat', 'service_charge_enabled' => false]);
        $this->seed(DiscountRuleSeeder::class);
    }

    public function test_starting_a_checkout_charges_the_server_priced_total_and_leaves_the_bill_unpaid(): void
    {
        $this->fakeMaya('PENDING_TOKEN');
        $order = $this->makeOrder('1000.00');
        $rule = DiscountRule::where('code', 'custom_percent')->firstOrFail();

        $response = $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout", [
            'discounts' => [['rule_id' => $rule->id, 'entered_value' => '10', 'reason' => 'Promo']],
        ]);

        $response->assertRedirect('https://payments-web-sandbox.maya.ph/v2/checkout?id=chk-123');

        $online = OnlinePayment::sole();
        $this->assertSame(OnlinePaymentStatus::Pending, $online->status);
        $this->assertSame('900.00', $online->amount);
        $this->assertSame('chk-123', $online->checkout_id);
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertFalse((bool) OrderItem::where('order_id', $order->id)->value('is_discount_eligible'));

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE.'/checkout/v1/checkouts'
            && $request['totalAmount']['value'] == 900
            && $request['requestReferenceNumber'] === $online->request_reference_number
            && str_contains($request['redirectUrl']['success'], "/online-payments/{$online->id}/maya/return")
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('pk-test:')));
    }

    public function test_a_confirmed_payment_finalizes_the_bill_once_with_a_maya_entry(): void
    {
        $order = $this->makeOrder('1000.00');
        $rule = DiscountRule::where('code', 'custom_percent')->firstOrFail();
        $this->fakeMaya('PAYMENT_SUCCESS');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout", [
            'discounts' => [['rule_id' => $rule->id, 'entered_value' => '10', 'reason' => 'Promo']],
        ]);
        $online = OnlinePayment::sole();

        $this->get("/online-payments/{$online->id}/maya/return?result=success")
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('status');

        // A webhook arriving after the guest's return changes nothing.
        $this->postJson('/api/webhooks/maya', ['id' => 'chk-123', 'status' => 'PAYMENT_SUCCESS'])->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('900.00', $order->currentInvoiceSnapshot->total_amount_due);
        $this->assertCount(1, $order->payments);
        $payment = $order->payments->first();
        $this->assertSame(PaymentMethod::Maya, $payment->payment_method);
        $this->assertSame('900.00', $payment->amount);
        $this->assertSame('chk-123', $payment->reference);
        $this->assertSame(OnlinePaymentStatus::Paid, $online->fresh()->status);
    }

    public function test_the_return_redirect_alone_never_marks_a_bill_paid(): void
    {
        $order = $this->makeOrder('500.00');
        $this->fakeMaya('PENDING_PAYMENT');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout");
        $online = OnlinePayment::sole();

        $this->get("/online-payments/{$online->id}/maya/return?result=success")->assertRedirect();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame(OnlinePaymentStatus::Pending, $online->fresh()->status);
    }

    public function test_a_failed_payment_keeps_the_bill_open_for_another_try(): void
    {
        $order = $this->makeOrder('500.00');
        $this->fakeMaya('PAYMENT_FAILED');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout");
        $online = OnlinePayment::sole();
        $this->get("/online-payments/{$online->id}/maya/return?result=failure")->assertSessionHas('error');

        $this->assertSame(OnlinePaymentStatus::Failed, $online->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);

        // Nothing is pending any more, so a new checkout can start.
        $this->post("/orders/{$order->id}/maya-checkout")->assertRedirect();
        $this->assertSame(2, OnlinePayment::count());
    }

    public function test_money_for_a_bill_that_changed_meanwhile_is_flagged_not_applied(): void
    {
        $order = $this->makeOrder('500.00');
        $this->fakeMaya('PAYMENT_SUCCESS');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout");
        $online = OnlinePayment::sole();

        // A line is added while the guest is on Maya's page.
        OrderItem::create(['order_id' => $order->id, 'item_name' => 'Late drink', 'unit_price' => '100.00', 'quantity' => 1, 'subtotal' => '100.00']);

        $this->get("/online-payments/{$online->id}/maya/return?result=success")->assertSessionHas('error');

        $this->assertSame(OnlinePaymentStatus::NeedsReview, $online->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertCount(0, $order->fresh()->payments);
    }

    public function test_only_one_checkout_can_wait_on_an_order(): void
    {
        $order = $this->makeOrder('500.00');
        $this->fakeMaya('PENDING_TOKEN');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout");
        $this->post("/orders/{$order->id}/maya-checkout")->assertSessionHasErrors('payments');

        $this->assertSame(1, OnlinePayment::count());
    }

    public function test_cancelling_rechecks_maya_first(): void
    {
        $order = $this->makeOrder('500.00');
        $this->fakeMaya('PENDING_TOKEN');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout");
        $online = OnlinePayment::sole();
        $this->post("/online-payments/{$online->id}/cancel")->assertRedirect();

        $this->assertSame(OnlinePaymentStatus::Cancelled, $online->fresh()->status);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payments/v1/payments/chk-123/status'));
    }

    public function test_a_staff_checkout_keeps_the_managers_approval_without_storing_the_password(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $manager = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true, 'email' => 'manager@example.com', 'password' => 'secret-pass']);
        $order = $this->makeOrder('1000.00');
        $rule = DiscountRule::where('code', 'custom_percent')->firstOrFail();
        $this->fakeMaya('PAYMENT_SUCCESS');

        $this->actingAs($staff)->post("/orders/{$order->id}/maya-checkout", [
            'discounts' => [['rule_id' => $rule->id, 'entered_value' => '10', 'reason' => 'Promo']],
            'manager_email' => 'manager@example.com',
            'manager_password' => 'secret-pass',
        ])->assertRedirect();

        $online = OnlinePayment::sole();
        $this->assertSame($manager->id, $online->approved_by);
        $this->assertStringNotContainsString('secret-pass', json_encode($online->checkout_data));

        $this->get("/online-payments/{$online->id}/maya/return?result=success");

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame($manager->id, $order->currentInvoiceSnapshot->discounts()->first()->approved_by);
    }

    public function test_the_option_is_off_unless_enabled(): void
    {
        config(['services.maya.enabled' => false]);
        $order = $this->makeOrder('500.00');

        $this->actingAs($this->admin)->post("/orders/{$order->id}/maya-checkout")->assertNotFound();
        $this->get("/orders/{$order->id}")->assertOk()->assertDontSee('Maya Checkout (Online)');
    }

    private function fakeMaya(string $status): void
    {
        // The first checkout is chk-123; any later one gets its own id, as Maya's would.
        $created = 0;
        Http::fake([
            self::BASE.'/checkout/v1/checkouts' => function () use (&$created) {
                $id = $created++ === 0 ? 'chk-123' : 'chk-'.(123 + $created);

                return Http::response([
                    'checkoutId' => $id,
                    'redirectUrl' => 'https://payments-web-sandbox.maya.ph/v2/checkout?id='.$id,
                ]);
            },
            self::BASE.'/payments/v1/payments/*/status' => Http::response(['id' => 'chk-123', 'status' => $status]),
        ]);
    }

    private function makeOrder(string $totalAmount): Order
    {
        static $counter = 0;
        $counter++;

        $order = Order::create([
            'order_number' => sprintf('88-MAYA-%03d', $counter),
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
