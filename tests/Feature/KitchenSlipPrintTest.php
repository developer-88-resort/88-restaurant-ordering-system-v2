<?php

namespace Tests\Feature;

use App\Enums\LineType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CookingStyle;
use App\Models\GuestSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterJob;
use App\Models\Quotation;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Kitchen tab's "Print" button — a prep ticket, never a billing
 * document. Confirms it carries prep details (items, quantities, weights,
 * cooking styles, special instructions) and who actually took the order
 * (not whoever clicks Print), its prices and total without any VAT, and that
 * it skips the receipt flow's 58mm/80mm paper-size picker.
 */
class KitchenSlipPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $waiterWhoTookTheOrder;

    private User $cookWhoPrints;

    private MenuItem $adobo;

    private MenuItem $bangus;

    private CookingStyle $inihaw;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $spaceCategory = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $space = Space::create(['area_id' => $area->id, 'category_id' => $spaceCategory->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $this->waiterWhoTookTheOrder = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true, 'name' => 'Maria Cruz']);
        $this->cookWhoPrints = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true, 'name' => 'Juan Dela Cruz']);

        $category = MenuCategory::create(['name' => 'Mains', 'sort_order' => 1, 'is_active' => true]);
        $this->adobo = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Adobo',
            'price' => '180.00',
            'availability_status' => 'available',
        ]);
        $this->bangus = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'min_weight_grams' => 250,
            'counter_only' => true,
            'availability_status' => 'available',
        ]);
        $this->inihaw = CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);

        $this->order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '390.00',
            'area_id' => $area->id,
            'space_category_id' => $spaceCategory->id,
            'space_id' => $space->id,
            'created_by' => $this->waiterWhoTookTheOrder->id,
            'order_source' => OrderSource::Staff,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'menu_item_id' => $this->adobo->id,
            'item_name' => 'Adobo',
            'line_type' => LineType::Fixed,
            'unit_price' => '180.00',
            'quantity' => 2,
            'subtotal' => '360.00',
            'notes' => 'No sauce, extra rice',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'menu_item_id' => $this->bangus->id,
            'item_name' => 'Bangus',
            'line_type' => LineType::Weighed,
            'unit_price' => '0.00',
            'quantity' => 1,
            'subtotal' => '177.00',
            'weight_grams' => 600,
            'price_per_kilo_snapshot' => '295.00',
            'cooking_style_id' => $this->inihaw->id,
            'cooking_note' => 'Extra crispy',
        ]);
    }

    public function test_one_press_of_direct_print_queues_one_slip_however_many_times_it_is_pressed(): void
    {
        $first = $this->press()
            ->assertOk()
            ->assertJson(['queued' => true, 'already_queued' => false, 'status' => 'pending'])
            ->json('job_id');

        // An impatient second (and third) press while the slip is still waiting.
        foreach (range(1, 2) as $again) {
            $this->press()->assertOk()->assertJson(['job_id' => $first, 'already_queued' => true]);
        }

        $this->assertSame(1, PrinterJob::count(), 'Pressing again must not queue another copy.');
    }

    public function test_one_press_prints_three_copies_with_a_pause_between_them(): void
    {
        $payload = PrinterJob::findOrFail($this->press()->json('job_id'))->payload;

        $this->assertSame(3, $payload['copies'], 'One press, three slips.');
        $this->assertSame(3, $payload['copy_pause_seconds'], 'The printer rests between copies.');
        $this->assertSame(1, PrinterJob::count(), 'Still one job — the copies are the printer\'s business.');
    }

    public function test_an_advance_order_slip_prints_the_same_three_copies(): void
    {
        Quotation::create([
            'quotation_number' => 'QT-00001',
            'status' => 'converted',
            'subtotal' => '390.00',
            'converted_order_id' => $this->order->id,
            'scheduled_for' => now()->addDay(),
        ]);

        $payload = PrinterJob::findOrFail($this->press()->assertOk()->json('job_id'))->payload;

        // However the order reached the kitchen — advance order, walk-in, QR
        // or a mix — the slip comes out the same way.
        $this->assertSame(3, $payload['copies']);
        $this->assertSame(3, $payload['copy_pause_seconds']);
    }

    public function test_a_slip_already_handed_to_the_printer_is_not_queued_again(): void
    {
        $jobId = $this->press()->json('job_id');
        PrinterJob::whereKey($jobId)->update(['status' => 'printing', 'claimed_at' => now()]);

        $this->press()->assertOk()->assertJson(['job_id' => $jobId, 'already_queued' => true]);

        $this->assertSame(1, PrinterJob::count());
    }

    public function test_a_press_after_the_slip_is_done_prints_a_fresh_one(): void
    {
        $firstJob = $this->press()->json('job_id');
        PrinterJob::whereKey($firstJob)->update(['status' => 'printed', 'printed_at' => now()]);

        $secondJob = $this->press()->assertOk()->assertJson(['already_queued' => false])->json('job_id');
        $this->assertNotSame($firstJob, $secondJob);

        // Same again once a slip failed — the kitchen must be able to retry.
        PrinterJob::whereKey($secondJob)->update(['status' => 'failed', 'error_message' => 'no paper']);
        $thirdJob = $this->press()->json('job_id');

        $this->assertNotSame($secondJob, $thirdJob);
        $this->assertSame(3, PrinterJob::count());
    }

    public function test_the_kitchen_can_watch_its_slip_until_the_printer_answers(): void
    {
        $jobId = $this->press()->json('job_id');
        $statusUrl = route('orders.kitchen-slip.print-status', ['order' => $this->order, 'printerJob' => $jobId]);

        $this->actingAs($this->cookWhoPrints)->getJson($statusUrl)
            ->assertOk()
            ->assertJson(['job_id' => $jobId, 'status' => 'pending']);

        PrinterJob::whereKey($jobId)->update(['status' => 'printed', 'printed_at' => now()]);
        $this->actingAs($this->cookWhoPrints)->getJson($statusUrl)->assertJson(['status' => 'printed']);

        // A job belonging to another slip is none of this card's business.
        $otherOrder = Order::create([
            'order_type' => 'dine_in', 'order_number' => 'TEST-OTHER', 'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid, 'total_amount' => '0.00', 'order_source' => OrderSource::Staff,
        ]);
        $this->actingAs($this->cookWhoPrints)
            ->getJson(route('orders.kitchen-slip.print-status', ['order' => $otherOrder, 'printerJob' => $jobId]))
            ->assertNotFound();
    }

    public function test_the_board_keeps_direct_print_locked_on_a_card_whose_slip_is_still_printing(): void
    {
        $jobId = $this->press()->json('job_id');

        $this->actingAs($this->cookWhoPrints)
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertViewHas('activePrintJobs', [$this->order->id => $jobId]);

        PrinterJob::whereKey($jobId)->update(['status' => 'printed', 'printed_at' => now()]);

        $this->actingAs($this->cookWhoPrints)
            ->get(route('kitchen.index'))
            ->assertViewHas('activePrintJobs', []);
    }

    public function test_a_repeated_acknowledgement_never_puts_a_printed_slip_back_on_the_queue(): void
    {
        config(['printing.bridge_token' => 'bridge-secret']);
        $bridge = ['Authorization' => 'Bearer bridge-secret'];
        $jobId = $this->press()->json('job_id');

        $this->postJson("/api/printer-jobs/{$jobId}/ack", ['status' => 'printed'], $bridge)->assertOk();

        // The bridge retrying an acknowledgement it wasn't sure landed, or
        // acking a job it had already printed before a restart.
        $this->postJson("/api/printer-jobs/{$jobId}/ack", ['status' => 'failed', 'error_message' => 'timeout'], $bridge)
            ->assertOk()
            ->assertJson(['ignored' => true]);

        $job = PrinterJob::findOrFail($jobId);
        $this->assertSame('printed', $job->status->value);
        $this->assertNull($job->error_message);
        $this->assertSame(1, $job->attempts);

        // And it is never offered to a bridge again.
        $this->travel(10)->minutes();
        $this->assertSame([], $this->getJson('/api/printer-jobs', $bridge)->json('jobs'));
    }

    private function press(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->cookWhoPrints)
            ->postJson(route('orders.kitchen-slip.print-thermal', $this->order));
    }

    public function test_the_slip_shows_items_prep_details_and_who_actually_took_the_order(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();

        // Prep details.
        $response->assertSee('Adobo');
        $response->assertSee('2&times;', false);
        $response->assertSee('No sauce, extra rice');
        $response->assertSee('600g', false);
        $response->assertSee('Inihaw');
        $response->assertSee('Extra crispy');

        // The order taker, not whoever is clicking Print right now.
        $response->assertSee('Maria Cruz');
        $response->assertDontSee('Juan Dela Cruz');
    }

    public function test_a_staff_created_order_shows_a_waiter_row_with_the_original_creators_name(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertSeeInOrder([__('Waiter'), 'Maria Cruz']);
        $response->assertDontSee(__('Ordered By'));
    }

    /**
     * Scenario D from the spec: Ken (here, Maria) creates the order; later
     * an Admin/SuperAdmin reprints it. The slip must still credit the
     * ORIGINAL creator, never whoever is currently logged in and clicking
     * Print.
     */
    public function test_reprinting_by_a_different_staffer_still_shows_the_original_creator(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true, 'name' => 'Admin Reyes']);

        $response = $this->actingAs($admin)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertSee('Maria Cruz');
        $response->assertDontSee('Admin Reyes');
    }

    public function test_a_qr_customer_order_shows_ordered_by_with_the_customers_name_not_a_staff_name(): void
    {
        $space = Space::first();
        $spaceSession = SpaceSession::create([
            'space_id' => $space->id,
            'category_id' => $space->category_id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $guest = GuestSession::create([
            'space_session_id' => $spaceSession->id,
            'public_token' => str()->random(40),
            'guest_number' => 1,
            'status' => 'active',
        ]);

        $qrOrder = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '180.00',
            'space_id' => $space->id,
            'space_session_id' => $spaceSession->id,
            'guest_session_id' => $guest->id,
            'created_by' => null,
            'order_source' => OrderSource::Qr,
            'customer_name' => 'John',
        ]);
        OrderItem::create([
            'order_id' => $qrOrder->id,
            'menu_item_id' => $this->adobo->id,
            'item_name' => 'Adobo',
            'line_type' => LineType::Fixed,
            'unit_price' => '180.00',
            'quantity' => 1,
            'subtotal' => '180.00',
        ]);

        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $qrOrder));

        $response->assertOk();
        $response->assertSeeInOrder([__('Ordered By'), 'John']);
        $response->assertDontSee(__('Waiter'));
        $response->assertDontSee('Juan Dela Cruz');
        $response->assertDontSee('Maria Cruz');

        // "Ordered By: John" is the whole story for a QR order — the old
        // separate Guest/Customer rows would just repeat the same name
        // twice more ("Guest: Andrei" / "Customer: Andrei" / "Ordered By:
        // Andrei" all at once, reported as confusing on 2026-09-15).
        $response->assertDontSee(__('Guest'));
        $response->assertDontSee(__('Customer'));
    }

    public function test_a_qr_order_without_a_customer_name_falls_back_to_the_word_customer(): void
    {
        $space = Space::first();
        $spaceSession = SpaceSession::create([
            'space_id' => $space->id,
            'category_id' => $space->category_id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $qrOrder = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '180.00',
            'space_id' => $space->id,
            'space_session_id' => $spaceSession->id,
            'created_by' => null,
            'order_source' => OrderSource::Qr,
        ]);
        OrderItem::create([
            'order_id' => $qrOrder->id,
            'menu_item_id' => $this->adobo->id,
            'item_name' => 'Adobo',
            'line_type' => LineType::Fixed,
            'unit_price' => '180.00',
            'quantity' => 1,
            'subtotal' => '180.00',
        ]);

        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $qrOrder));

        $response->assertOk();
        $response->assertSeeInOrder([__('Ordered By'), __('Customer')]);
    }

    /**
     * A staff-taken walk-in order can carry BOTH a waiter and a customer
     * name (WeighStationController::walkInOrder()) — unlike the QR case,
     * these are two genuinely different pieces of information, so the
     * Customer row must still show here even though it's suppressed for
     * QR orders.
     */
    public function test_a_staff_walk_in_order_still_shows_a_separate_customer_row_alongside_the_waiter(): void
    {
        $this->order->update(['customer_name' => 'Walk-in Pedro']);

        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertSeeInOrder([__('Customer'), 'Walk-in Pedro']);
        $response->assertSeeInOrder([__('Waiter'), 'Maria Cruz']);
    }

    /**
     * Backward compatibility: an order saved before `order_source` existed
     * has no value for it at all — the slip must still render safely and
     * fall back sensibly (here, to the pre-existing "staff" assumption,
     * since only QR orders reliably carry a guest_session_id).
     */
    public function test_an_old_order_without_order_source_falls_back_safely_instead_of_erroring(): void
    {
        $this->order->forceFill(['order_source' => null])->save();

        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertSee(__('Waiter'));
        $response->assertSee('Maria Cruz');
    }

    public function test_the_slip_shows_each_lines_price_and_the_total_but_no_vat(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertSee('360.00');
        $response->assertSee('@ 180.00');
        $response->assertSee('177.00');
        $response->assertSeeInOrder([__('Subtotal'), '537.00', __('TOTAL'), '537.00']);
        $response->assertDontSee('VAT');
        $response->assertSee(__('Kitchen copy — not valid as receipt.'));
    }

    public function test_the_slip_skips_the_receipt_flows_paper_size_picker(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertDontSee(__('Paper').':', false);
        $response->assertDontSee(route('orders.print', ['order' => $this->order, 'paper' => '58mm']), false);
        $response->assertDontSee(route('orders.print', ['order' => $this->order, 'paper' => '80mm']), false);

        // afterprint sends staff back to the Kitchen tab, not a receipt page.
        $response->assertSee(route('kitchen.index'), false);
    }
}
