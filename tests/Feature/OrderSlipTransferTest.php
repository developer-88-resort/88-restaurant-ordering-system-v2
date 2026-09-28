<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use App\Services\TableSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "They moved to another kubo after we already opened the slip."
 *
 * Before this the only way to serve them was to open a second slip on the
 * new table, which left the first one on record against a table the party
 * never sat at — and both counted. The same order row moves instead.
 */
class OrderSlipTransferTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SpaceCategory $category;

    private Space $kubo1;

    private Space $kubo2;

    private MenuItem $sisig;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Kubo', 'slug' => 'kubo', 'sort_order' => 1, 'is_active' => true]);
        $this->category = SpaceCategory::create(['area_id' => $this->area->id, 'name' => 'Kubo', 'slug' => 'kubo-cat', 'is_active' => true]);
        $this->kubo1 = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'KUBO 1', 'status' => 'available', 'sort_order' => 1]);
        $this->kubo2 = Space::create(['area_id' => $this->area->id, 'category_id' => $this->category->id, 'name' => 'KUBO 2', 'status' => 'available', 'sort_order' => 2]);

        $menu = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->sisig = MenuItem::create(['menu_category_id' => $menu->id, 'name' => 'Sisig', 'price' => '220.00', 'availability_status' => 'available']);

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
    }

    private function openSlip(Space $space, array $attributes = []): Order
    {
        $session = TableSessionManager::findOrOpenFor($space);
        $space->setStatusWithSharedTables(SpaceStatus::Occupied);

        $order = Order::create(array_merge([
            'order_type' => 'dine_in',
            'order_number' => 'TR-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => '220.00',
            'area_id' => $space->area_id,
            'space_category_id' => $space->category_id,
            'space_id' => $space->id,
            'space_session_id' => $session->id,
        ], $attributes));

        $order->items()->create([
            'menu_item_id' => $this->sisig->id,
            'item_name' => 'Sisig',
            'unit_price' => '220.00',
            'quantity' => 1,
            'subtotal' => '220.00',
            'line_type' => 'fixed',
        ]);

        return $order->fresh();
    }

    private function move(Order $order, Space $to, ?Order $mergeInto = null)
    {
        return $this->actingAs($this->staff)->patch(route('orders.location.update', $order), array_filter([
            'space_id' => $to->id,
            'merge_into_order_id' => $mergeInto?->id,
        ]));
    }

    public function test_moving_a_slip_relocates_the_same_order_instead_of_opening_a_second_one(): void
    {
        $order = $this->openSlip($this->kubo1);

        $this->move($order, $this->kubo2)->assertRedirect();

        $order->refresh();

        $this->assertSame($this->kubo2->id, $order->space_id, 'The same row moved.');
        $this->assertSame($this->category->id, $order->space_category_id);
        $this->assertSame(1, Order::whereNull('merged_into_order_id')->count(), 'No second slip was created.');
        $this->assertSame(1, $order->slip_number, 'It is the new table\'s first slip.');

        $this->assertSame(SpaceStatus::Available, $this->kubo1->fresh()->status, 'The table they left is free again.');
        $this->assertSame(SpaceStatus::Occupied, $this->kubo2->fresh()->status);
    }

    public function test_the_table_they_left_stays_occupied_when_another_slip_is_still_open_on_it(): void
    {
        $staying = $this->openSlip($this->kubo1);
        $moving = $this->openSlip($this->kubo1);

        $this->move($moving, $this->kubo2)->assertRedirect();

        $this->assertSame(SpaceStatus::Occupied, $this->kubo1->fresh()->status);
        $this->assertSame($this->kubo1->id, $staying->fresh()->space_id);
    }

    public function test_merging_moves_the_lines_and_closes_the_emptied_slip(): void
    {
        $moving = $this->openSlip($this->kubo1);
        $destination = $this->openSlip($this->kubo2);

        $this->move($moving, $this->kubo2, $destination)->assertRedirect();

        $moving->refresh();
        $destination->refresh();

        $this->assertSame($destination->id, $moving->merged_into_order_id);
        $this->assertSame(OrderStatus::Completed, $moving->status);
        $this->assertSame(0, $moving->items()->count(), 'Its lines went across.');
        $this->assertSame('0.00', $moving->total_amount, 'The emptied slip carries no money.');

        $this->assertSame(2, $destination->items()->count());
        $this->assertSame('440.00', $destination->total_amount, 'Both lines are billed once, on one slip.');

        $this->assertSame(SpaceStatus::Available, $this->kubo1->fresh()->status);
    }

    public function test_a_paid_slip_can_still_be_moved(): void
    {
        $order = $this->openSlip($this->kubo1, ['status' => 'served', 'payment_status' => 'paid']);

        $this->move($order, $this->kubo2)->assertRedirect();

        $this->assertSame($this->kubo2->id, $order->fresh()->space_id);
    }

    public function test_a_completed_slip_can_still_be_re_filed_without_re_occupying_the_table(): void
    {
        $order = $this->openSlip($this->kubo1, ['status' => 'completed']);

        $this->move($order, $this->kubo2)->assertRedirect();

        $this->assertSame($this->kubo2->id, $order->fresh()->space_id);
        $this->assertSame(
            SpaceStatus::Available,
            $this->kubo2->fresh()->status,
            'Nobody is sitting there — a finished slip only gets re-filed.'
        );
    }

    public function test_a_slip_with_a_recorded_payment_cannot_be_merged_into_another(): void
    {
        $moving = $this->openSlip($this->kubo1, ['status' => 'served', 'payment_status' => 'paid']);
        OrderPayment::create([
            'order_id' => $moving->id,
            'payment_method' => PaymentMethod::Cash,
            'status' => OrderPaymentStatus::Recorded,
            'amount' => '220.00',
            'received_at' => now(),
        ]);

        $destination = $this->openSlip($this->kubo2);

        $this->move($moving, $this->kubo2, $destination)->assertSessionHas('error');

        $moving->refresh();

        $this->assertNull($moving->merged_into_order_id);
        $this->assertSame(1, $moving->items()->count(), 'Its invoiced lines stayed put.');
        $this->assertSame($this->kubo1->id, $moving->space_id);
    }

    public function test_a_merged_slip_is_left_out_of_the_report_counts(): void
    {
        $moving = $this->openSlip($this->kubo1);
        $destination = $this->openSlip($this->kubo2);

        $this->move($moving, $this->kubo2, $destination);

        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $data = $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk()
            ->original
            ->getData();

        $this->assertSame(1, $data['openOrderCount'], 'One party, one open slip — not two.');
    }
}
