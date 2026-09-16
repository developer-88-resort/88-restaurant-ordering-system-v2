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
 * (not whoever clicks Print), while never leaking a price/total, and that
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

    public function test_the_slip_never_shows_prices_amounts_or_totals(): void
    {
        $response = $this->actingAs($this->cookWhoPrints)->get(route('orders.kitchen-slip.print', $this->order));

        $response->assertOk();
        $response->assertDontSee('₱');
        $response->assertDontSee('360.00');
        $response->assertDontSee('390.00');
        $response->assertDontSee(__('Total'));
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
