<?php

namespace Tests\Feature;

use App\Enums\OrderItemAdjustmentSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Events\KitchenUpdated;
use App\Events\OrderUpdated;
use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use App\Services\OrderTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class KitchenItemCancellationTest extends TestCase
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

        $this->area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Cottages', 'slug' => 'cottages', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'Cottage 3', 'status' => SpaceStatus::Occupied, 'sort_order' => 1]);

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
    }

    public function test_staff_can_cancel_a_whole_line_from_the_kitchen_and_the_total_is_recalculated(): void
    {
        $order = $this->makeOrder([
            ['name' => 'Sinigang', 'price' => '400.00', 'qty' => 1],
            ['name' => 'Rice', 'price' => '50.00', 'qty' => 2],
        ], OrderStatus::Pending);
        $sinigang = $order->items->firstWhere('item_name', 'Sinigang');

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $sinigang), [
                'quantity' => 1,
                'reason_code' => 'out_of_stock',
            ])
            ->assertOk()
            ->assertJson(['order_status' => 'pending', 'total_amount' => '100.00']);

        $order->refresh();
        $this->assertSame('100.00', $order->total_amount);
        $this->assertSame(OrderStatus::Pending, $order->status);

        // Never hard-deleted: the line and its original charge are still there.
        $sinigang->refresh()->load('adjustments');
        $this->assertSame('400.00', $sinigang->subtotal);
        $this->assertTrue($sinigang->isFullyCancelled());

        $adjustment = $sinigang->adjustments->first();
        $this->assertSame(OrderItemAdjustmentSource::Kitchen, $adjustment->source);
        $this->assertSame($this->staff->id, $adjustment->requested_by);
        $this->assertNull($adjustment->approved_by, 'A New slip needs no manager sign-off.');
        $this->assertNotNull($adjustment->created_at);

        // The billing read-model nets it out without being told to.
        $totals = OrderTotals::for($order->fresh());
        $this->assertSame('500.00', $totals->originalSubtotal);
        $this->assertSame('400.00', $totals->cancelledAmount);
        $this->assertSame('100.00', $totals->activeSubtotal);
    }

    public function test_adjusting_quantity_from_three_to_two_cancels_one_unit(): void
    {
        $order = $this->makeOrder([['name' => 'Halo-halo', 'price' => '150.00', 'qty' => 3]], OrderStatus::Preparing);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), [
                'quantity' => 1,
                'reason_code' => 'customer_request',
            ])
            ->assertOk();

        $item->refresh()->load('adjustments');
        $this->assertSame(3, $item->quantity, 'The ordered quantity is history and never rewritten.');
        $this->assertSame(1, $item->cancelledQuantity());
        $this->assertSame(2, $item->activeQuantity());
        $this->assertSame('300.00', $order->fresh()->total_amount);
    }

    public function test_other_requires_a_written_reason_but_presets_do_not(): void
    {
        $order = $this->makeOrder([['name' => 'Kare-kare', 'price' => '500.00', 'qty' => 2]], OrderStatus::Pending);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notes');

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'other', 'notes' => 'Guest left early'])
            ->assertOk();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'wrong_order'])
            ->assertOk();

        $this->assertSame(2, $item->adjustments()->count());
        $this->assertSame('Guest left early', $item->adjustments()->oldest('id')->first()->notes);
    }

    public function test_cancelling_every_line_cancels_the_slip_and_takes_it_off_the_board(): void
    {
        $order = $this->makeOrder([
            ['name' => 'Lechon Kawali', 'price' => '350.00', 'qty' => 1],
            ['name' => 'Iced Tea', 'price' => '60.00', 'qty' => 1],
        ], OrderStatus::Preparing);

        $this->actingAs($this->staff)->get('/kitchen')->assertOk()->assertSee($order->orderNumber());

        foreach ($order->items as $item) {
            $this->actingAs($this->staff)
                ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'customer_request'])
                ->assertOk();
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('0.00', $order->total_amount);
        $this->assertSame(SpaceStatus::Available, $this->space->fresh()->status, 'An emptied slip frees its table like any cancelled order.');

        $this->actingAs($this->staff)->get('/kitchen')->assertOk()->assertDontSee($order->orderNumber());
    }

    public function test_a_partly_cancelled_slip_stays_on_the_board_with_the_line_marked(): void
    {
        $order = $this->makeOrder([
            ['name' => 'Pancit Canton', 'price' => '250.00', 'qty' => 1],
            ['name' => 'Buko Juice', 'price' => '80.00', 'qty' => 1],
        ], OrderStatus::Pending);
        $pancit = $order->items->firstWhere('item_name', 'Pancit Canton');

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $pancit), ['quantity' => 1, 'reason_code' => 'out_of_stock'])
            ->assertOk();

        $this->actingAs($this->staff)->get('/kitchen')
            ->assertOk()
            ->assertSee($order->orderNumber())
            ->assertSee('Pancit Canton')
            ->assertSee('Cancelled')
            ->assertSee('Out of stock');
    }

    public function test_a_ready_slip_needs_manager_approval_from_staff(): void
    {
        $order = $this->makeOrder([['name' => 'Bulalo', 'price' => '600.00', 'qty' => 1]], OrderStatus::Ready);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'wrong_order'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('manager_email');
        $this->assertSame(0, $item->adjustments()->count());

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), [
                'quantity' => 1,
                'reason_code' => 'wrong_order',
                'manager_email' => $this->admin->email,
                'manager_password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('manager_email');

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), [
                'quantity' => 1,
                'reason_code' => 'wrong_order',
                'manager_email' => $this->admin->email,
                'manager_password' => 'password',
            ])
            ->assertOk();

        $adjustment = $item->adjustments()->first();
        $this->assertSame($this->staff->id, $adjustment->requested_by);
        $this->assertSame($this->admin->id, $adjustment->approved_by);
    }

    public function test_a_manager_can_approve_with_their_pin_instead_of_a_password(): void
    {
        $this->admin->setPin('4829');
        $order = $this->makeOrder([['name' => 'Kare-Kare', 'price' => '650.00', 'qty' => 1]], OrderStatus::Ready);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->get(route('kitchen.index'))
            ->assertSee($this->admin->name);

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), [
                'quantity' => 1,
                'reason_code' => 'wrong_order',
                'manager_id' => $this->admin->id,
                'manager_pin' => '4829',
            ])
            ->assertOk();

        $adjustment = $item->adjustments()->first();
        $this->assertSame($this->staff->id, $adjustment->requested_by);
        $this->assertSame($this->admin->id, $adjustment->approved_by);
    }

    public function test_a_managers_pin_cannot_be_guessed_through_the_cancel_dialog(): void
    {
        $this->admin->setPin('4829');
        $order = $this->makeOrder([['name' => 'Sisig', 'price' => '350.00', 'qty' => 1]], OrderStatus::Ready);
        $item = $order->items->first();
        $attempt = fn (string $pin) => $this->actingAs($this->staff)->postJson($this->cancelUrl($order, $item), [
            'quantity' => 1,
            'reason_code' => 'wrong_order',
            'manager_id' => $this->admin->id,
            'manager_pin' => $pin,
        ]);

        $attempt('0001')->assertStatus(422)->assertJsonValidationErrors(['manager_pin' => 'Manager PIN is not correct.']);

        $failure = Activity::where('event', 'failed_pin_login')->sole();
        $this->assertSame($this->admin->id, $failure->subject_id);
        $this->assertSame($this->staff->id, $failure->causer_id);
        $this->assertSame('approval', $failure->properties['context']);

        foreach (range(2, 5) as $n) {
            $attempt('0001')->assertStatus(422);
        }

        // Locked, like the sign-in screen: the right PIN doesn't get through either.
        $attempt('4829')->assertStatus(422)->assertJsonValidationErrors(['manager_pin' => 'Too many wrong PINs. Try again in 5 min.']);
        $this->assertSame(0, $item->adjustments()->count());
    }

    public function test_an_admin_approves_their_own_cancellation_on_a_ready_slip(): void
    {
        $order = $this->makeOrder([['name' => 'Crispy Pata', 'price' => '700.00', 'qty' => 1]], OrderStatus::Ready);
        $item = $order->items->first();

        $this->actingAs($this->admin)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'customer_request'])
            ->assertOk();

        $this->assertSame($this->admin->id, $item->adjustments()->first()->approved_by);
    }

    public function test_an_in_progress_slip_can_be_cancelled_by_staff_without_approval_from_order_management_too(): void
    {
        // Same rule on both screens: approval starts at Ready, not at In Progress.
        $order = $this->makeOrder([['name' => 'Tinola', 'price' => '300.00', 'qty' => 2]], OrderStatus::Preparing);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->post("/orders/{$order->id}/items/{$item->id}/cancel", ['quantity' => 1, 'reason_code' => 'customer_request'])
            ->assertSessionHasNoErrors();

        $adjustment = $item->adjustments()->first();
        $this->assertSame(OrderItemAdjustmentSource::OrderManagement, $adjustment->source);
        $this->assertNull($adjustment->approved_by);
    }

    public function test_a_paid_slip_needs_approval_even_while_in_progress(): void
    {
        $order = $this->makeOrder([['name' => 'Adobo', 'price' => '280.00', 'qty' => 1]], OrderStatus::Preparing);
        $order->update(['payment_status' => PaymentStatus::Paid]);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'customer_request'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('manager_email');
    }

    public function test_cannot_cancel_more_than_is_left_or_a_line_that_is_already_gone(): void
    {
        $order = $this->makeOrder([
            ['name' => 'Lumpia', 'price' => '100.00', 'qty' => 2],
            ['name' => 'Sago', 'price' => '40.00', 'qty' => 1],
        ], OrderStatus::Pending);
        $lumpia = $order->items->firstWhere('item_name', 'Lumpia');

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $lumpia), ['quantity' => 3, 'reason_code' => 'wrong_order'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $lumpia), ['quantity' => 2, 'reason_code' => 'wrong_order'])
            ->assertOk();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $lumpia), ['quantity' => 1, 'reason_code' => 'wrong_order'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_an_item_from_another_order_is_rejected(): void
    {
        $order = $this->makeOrder([['name' => 'A', 'price' => '10.00', 'qty' => 1]], OrderStatus::Pending);
        $other = $this->makeOrder([['name' => 'B', 'price' => '10.00', 'qty' => 1]], OrderStatus::Pending);

        $this->actingAs($this->staff)
            ->postJson(route('kitchen.items.cancel', [$order, $other->items->first()]), ['quantity' => 1, 'reason_code' => 'wrong_order'])
            ->assertNotFound();
    }

    public function test_a_line_on_a_cancelled_order_is_refused_by_the_policy(): void
    {
        $order = $this->makeOrder([['name' => 'A', 'price' => '10.00', 'qty' => 1]], OrderStatus::Cancelled);

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $order->items->first()), ['quantity' => 1, 'reason_code' => 'wrong_order'])
            ->assertForbidden();
    }

    public function test_fully_cancelling_a_dish_also_cancels_its_add_ons(): void
    {
        $order = $this->makeOrder([['name' => 'Inihaw na Liempo', 'price' => '300.00', 'qty' => 1]], OrderStatus::Pending);
        $parent = $order->items->first();
        OrderItem::create([
            'order_id' => $order->id,
            'parent_order_item_id' => $parent->id,
            'item_name' => '+ Extra rice',
            'unit_price' => '25.00',
            'quantity' => 2,
            'subtotal' => '50.00',
        ]);
        $order->update(['total_amount' => '350.00']);

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $parent), ['quantity' => 1, 'reason_code' => 'out_of_stock'])
            ->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('0.00', $order->total_amount);
        $this->assertSame(2, $order->itemAdjustments()->count());
    }

    public function test_cancellation_is_audit_logged_and_broadcast_to_order_management(): void
    {
        Event::fake([OrderUpdated::class, KitchenUpdated::class]);

        $order = $this->makeOrder([
            ['name' => 'Sisig', 'price' => '220.00', 'qty' => 2],
        ], OrderStatus::Pending);
        $item = $order->items->first();

        $this->actingAs($this->staff)
            ->postJson($this->cancelUrl($order, $item), ['quantity' => 1, 'reason_code' => 'other', 'notes' => 'Too spicy for the kid'])
            ->assertOk();

        Event::assertDispatched(OrderUpdated::class, fn (OrderUpdated $event) => $event->order->is($order) && $event->action === 'item_cancelled');
        Event::assertDispatched(KitchenUpdated::class);

        $log = Activity::where('event', 'order_item_cancelled')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->staff->id, $log->causer_id);
        $this->assertStringContainsString('Kitchen', $log->description);
        $this->assertStringContainsString('1× Sisig', $log->description);
        $this->assertStringContainsString('Too spicy for the kid', $log->description);
    }

    public function test_the_kitchen_board_shows_cancel_and_adjust_actions(): void
    {
        $order = $this->makeOrder([
            ['name' => 'Garlic Rice', 'price' => '50.00', 'qty' => 3],
            ['name' => 'Coke', 'price' => '60.00', 'qty' => 1],
        ], OrderStatus::Pending);

        $response = $this->actingAs($this->staff)->get('/kitchen')->assertOk();

        $response->assertSee('kitchen-cancel-item', false);
        // "Qty" only on the line that has more than one to reduce.
        $this->assertSame(1, substr_count($response->getContent(), "\$dispatch('kitchen-cancel-item', ".\Illuminate\Support\Js::from([
            'mode' => 'adjust',
            'url' => route('kitchen.items.cancel', [$order, $order->items->firstWhere('item_name', 'Garlic Rice')]),
            'name' => 'Garlic Rice',
            'activeQty' => 3,
            'isWeighed' => false,
            'needsApproval' => false,
            'slipLabel' => $order->orderNumber().' · '.$order->fresh()->locationLabel(),
        ])));
    }

    private function cancelUrl(Order $order, OrderItem $item): string
    {
        return route('kitchen.items.cancel', [$order, $item]);
    }

    /**
     * @param  array<int, array{name: string, price: string, qty: int}>  $items
     */
    private function makeOrder(array $items, OrderStatus $status): Order
    {
        static $counter = 0;
        $counter++;

        $total = '0.00';
        foreach ($items as $line) {
            $total = bcadd($total, bcmul($line['price'], (string) $line['qty'], 2), 2);
        }

        $order = Order::create([
            'order_number' => sprintf('88-KCAN-%03d', $counter),
            'order_type' => 'dine_in',
            'area_id' => $this->area->id,
            'space_category_id' => $this->category->id,
            'space_id' => $this->space->id,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => $total,
        ]);

        foreach ($items as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'item_name' => $line['name'],
                'unit_price' => $line['price'],
                'quantity' => $line['qty'],
                'subtotal' => bcmul($line['price'], (string) $line['qty'], 2),
            ]);
        }

        return $order->fresh(['items']);
    }
}
