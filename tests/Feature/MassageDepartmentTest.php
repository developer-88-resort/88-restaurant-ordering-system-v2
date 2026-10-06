<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\MassageOrderStatus;
use App\Enums\UserRole;
use App\Models\MassageOrder;
use App\Models\MassageService;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Massage department's own pages: its services (Services Management),
 * orders with a room number, payment taken exactly like the restaurant's
 * checkout, and its money in Reports as its own section.
 */
class MassageDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $therapist;

    private User $admin;

    private MassageService $swedish;

    private MassageService $signature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->therapist = User::factory()->create(['name' => 'Massage', 'role' => UserRole::Staff, 'department' => Department::Services, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'department' => Department::Services, 'is_active' => true]);
        $this->swedish = MassageService::create(['name' => 'M-SWEDDISH', 'price' => '800.00', 'duration_minutes' => 60]);
        $this->signature = MassageService::create(['name' => 'M-SIGNATURE', 'price' => '1000.00']);
    }

    private function newOrder(array $fields = []): MassageOrder
    {
        $this->actingAs($this->therapist)->post(route('massage.orders.store'), $fields + [
            'room_number' => '204',
            'items' => [
                ['service_id' => $this->swedish->id, 'quantity' => 2],
                ['service_id' => $this->signature->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        return MassageOrder::latest('id')->firstOrFail();
    }

    private function payFor(MassageOrder $order, array $payments)
    {
        return $this->actingAs($this->therapist)->post(route('massage.orders.pay', $order), ['payments' => $payments]);
    }

    public function test_massage_has_its_own_sidebar_and_lands_on_its_overview(): void
    {
        $this->actingAs($this->therapist);
        $keys = collect(Navigation::forUser($this->therapist))->flatMap(fn ($group) => array_column($group['items'], 'key'))->all();

        $this->assertSame(['massage-overview', 'chat', 'massage-orders', 'massage-services'], $keys);
        $this->assertSame('massage.dashboard', $this->therapist->homeRouteName());

        foreach (['massage.dashboard', 'massage.orders.index', 'massage.orders.create', 'massage.services.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_restaurant_staff_cannot_open_massage_pages(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Staff, 'department' => Department::Restaurant, 'is_active' => true]);

        $this->actingAs($cashier)->get(route('massage.dashboard'))->assertForbidden();
        $this->actingAs($cashier)->get(route('massage.orders.index'))->assertForbidden();
    }

    public function test_an_order_is_priced_from_the_services_with_the_room_number(): void
    {
        $order = $this->newOrder();

        $this->assertMatchesRegularExpression('/^MS-\d{4}-001$/', $order->order_number);
        $this->assertSame('204', $order->room_number);
        $this->assertSame('2600.00', (string) $order->total_amount);
        $this->assertSame(MassageOrderStatus::Pending, $order->status);
        $this->assertSame(['M-SWEDDISH', 'M-SIGNATURE'], $order->items->pluck('name')->all());

        $this->assertMatchesRegularExpression('/-002$/', $this->newOrder()->order_number);
    }

    public function test_an_order_needs_a_service_and_a_room_or_guest_name(): void
    {
        $this->actingAs($this->therapist)->post(route('massage.orders.store'), ['items' => []])
            ->assertSessionHasErrors(['items', 'room_number']);

        $this->actingAs($this->therapist)->post(route('massage.orders.store'), [
            'guest_name' => 'Walk-in Ana',
            'items' => [['service_id' => $this->swedish->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->swedish->update(['is_available' => false]);
        $this->actingAs($this->therapist)->post(route('massage.orders.store'), [
            'room_number' => '101',
            'items' => [['service_id' => $this->swedish->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.service_id');
    }

    public function test_cash_payment_with_change_closes_the_order(): void
    {
        $order = $this->newOrder();

        $this->payFor($order, [['method' => 'cash', 'amount' => '2600', 'tendered_amount' => '3000']])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(MassageOrderStatus::Paid, $order->status);
        $payment = $order->payments->sole();
        $this->assertSame('2600.00', (string) $payment->amount);
        $this->assertSame('400.00', (string) $payment->change_amount);
    }

    public function test_card_and_room_charge_follow_the_restaurant_rules(): void
    {
        $order = $this->newOrder();

        $this->payFor($order, [['method' => 'card', 'amount' => '2600']])->assertSessionHasErrors('payments');
        $this->payFor($order, [['method' => 'room_charge', 'amount' => '2600', 'settled_via' => 'gcash', 'reference' => 'GC-1']])->assertSessionHasErrors('payments');
        $this->assertSame(MassageOrderStatus::Pending, $order->fresh()->status);

        $this->payFor($order, [
            ['method' => 'card', 'amount' => '1000', 'card_brand' => 'Visa', 'reference' => 'REF-77', 'approval_code' => 'AP-1'],
            ['method' => 'room_charge', 'amount' => '1600', 'settled_via' => 'cash', 'charged_to' => 'Room 204'],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(MassageOrderStatus::Paid, $order->status);
        $this->assertSame(['card', 'room_charge'], $order->payments->map(fn ($p) => $p->payment_method->value)->all());
        $this->assertSame('Room 204', $order->payments[1]->charged_to);
    }

    public function test_short_payment_is_refused_and_a_paid_order_cannot_be_paid_twice(): void
    {
        $order = $this->newOrder();

        $this->payFor($order, [['method' => 'gcash', 'amount' => '1000', 'reference' => 'GC-9']])->assertSessionHasErrors('payments');
        $this->payFor($order, [['method' => 'gcash', 'amount' => '2600', 'reference' => 'GC-9']])->assertSessionHasNoErrors();
        $this->payFor($order, [['method' => 'cash', 'amount' => '2600']])->assertSessionHasErrors('payments');

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_only_an_admin_voids_a_payment_and_the_order_opens_again(): void
    {
        $order = $this->newOrder();
        $this->payFor($order, [['method' => 'cash', 'amount' => '2600']]);

        $this->actingAs($this->therapist)->post(route('massage.orders.void-payment', $order))->assertForbidden();
        $this->actingAs($this->admin)->post(route('massage.orders.void-payment', $order))->assertRedirect();

        $this->assertSame(MassageOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame('voided', $order->payments()->first()->status->value);
    }

    public function test_an_unpaid_order_can_be_cancelled(): void
    {
        $order = $this->newOrder();

        $this->actingAs($this->therapist)->post(route('massage.orders.cancel', $order))->assertRedirect();

        $this->assertSame(MassageOrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_services_management_staff_switch_availability_and_admins_edit(): void
    {
        $this->actingAs($this->therapist)->patch(route('massage.services.availability', $this->swedish), ['is_available' => 0])->assertRedirect();
        $this->assertFalse($this->swedish->fresh()->is_available);
        $this->actingAs($this->therapist)->get(route('massage.services.create'))->assertForbidden();

        $this->actingAs($this->admin)->post(route('massage.services.store'), ['name' => 'M-FOOT THERAPY', 'price' => '600', 'is_available' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('massage.services.store'), ['name' => 'M-FOOT THERAPY', 'price' => '600'])->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->delete(route('massage.services.destroy', $this->signature))->assertRedirect();
        $this->assertSoftDeleted($this->signature);
        $this->actingAs($this->therapist)->get(route('massage.orders.create'))
            ->assertViewHas('services', fn ($services) => ! $services->contains('name', 'M-SIGNATURE'));
    }

    public function test_the_service_form_is_new_menu_items_layout_with_photos_and_rich_text(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($this->admin)->get(route('massage.services.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Massage/ServiceForm')->where('service', null)->where('nextSortOrder', 1));

        $this->actingAs($this->admin)->post(route('massage.services.store'), [
            'name' => 'M-HOT STONE',
            'price' => '1500',
            'duration_minutes' => 90,
            'description' => '<p>Warm <strong>basalt</strong> stones.</p>',
            'is_available' => 1,
            'sort_order' => 3,
            'images' => [\Illuminate\Http\UploadedFile::fake()->image('stone.jpg'), \Illuminate\Http\UploadedFile::fake()->image('room.jpg')],
        ])->assertRedirect(route('massage.services.index'));

        $service = MassageService::where('name', 'M-HOT STONE')->firstOrFail();
        $this->assertSame(3, $service->sort_order);
        $this->assertSame('Warm basalt stones.', $service->descriptionText());
        $this->assertCount(2, $service->images);
        $this->assertTrue($service->images->first()->is_primary);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($service->images->first()->path);

        // Edit: drop the first photo; the other becomes the primary.
        $first = $service->images->first();
        $this->actingAs($this->admin)->get(route('massage.services.edit', $service))
            ->assertInertia(fn ($page) => $page->component('Massage/ServiceForm')->has('service.images', 2));
        $this->actingAs($this->admin)->post(route('massage.services.update', $service), [
            '_method' => 'put', 'name' => 'M-HOT STONE', 'price' => '1600', 'is_available' => 0, 'remove_images' => [$first->id],
        ])->assertRedirect(route('massage.services.index'));

        $service->refresh()->load('images');
        $this->assertSame('1600.00', (string) $service->price);
        $this->assertFalse($service->is_available);
        $this->assertCount(1, $service->images);
        $this->assertTrue($service->images->first()->is_primary);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($first->path);

        $this->actingAs($this->therapist)->get(route('massage.services.index'))->assertOk()->assertSee('Warm basalt stones.')->assertDontSee('<strong>basalt', false);
    }

    public function test_a_service_keeps_variants_and_add_ons_like_a_menu_item(): void
    {
        $this->actingAs($this->admin)->post(route('massage.services.store'), [
            'name' => 'SWEDISH MASSAGE',
            'price' => '',
            'is_available' => 1,
            'variants' => [
                ['name' => '30 mins', 'price' => '500', 'duration_minutes' => 30],
                ['name' => '1 hr', 'price' => '800', 'duration_minutes' => 60],
                ['name' => 'Couple', 'price' => ''],
            ],
            'default_variant_index' => 1,
            'add_ons' => [['name' => 'Hot Stone', 'price' => '300'], ['name' => 'Aromatherapy oil', 'price' => '']],
        ])->assertRedirect(route('massage.services.index'));

        $service = MassageService::where('name', 'SWEDISH MASSAGE')->with(['variants', 'addOns'])->firstOrFail();
        $this->assertSame(['30 mins', '1 hr', 'Couple'], $service->variants->pluck('name')->all());
        $this->assertSame('1 hr', $service->variants->firstWhere('is_default', true)->name);
        $this->assertNull($service->variants[2]->price, 'A blank price is listed but not sold.');
        $this->assertSame('₱500.00 – ₱800.00', $service->priceLabel());
        $this->assertSame(['Hot Stone', 'Aromatherapy oil'], $service->addOns->pluck('name')->all());
        $this->assertSame('0.00', (string) $service->addOns[1]->price);

        // Edit: keep 1 hr (renamed price), drop the rest, drop an add-on.
        $oneHour = $service->variants[1];
        $this->actingAs($this->admin)->post(route('massage.services.update', $service), [
            '_method' => 'put', 'name' => 'SWEDISH MASSAGE', 'price' => '', 'is_available' => 1,
            'variants' => [['id' => $oneHour->id, 'name' => '1 hr', 'price' => '850', 'duration_minutes' => 60]],
            'add_ons' => [['id' => $service->addOns[0]->id, 'name' => 'Hot Stone', 'price' => '300']],
        ])->assertRedirect(route('massage.services.index'));

        $service->refresh()->load(['variants', 'addOns']);
        $this->assertSame([$oneHour->id], $service->variants->pluck('id')->all(), 'Same row updated, the others deleted.');
        $this->assertSame('850.00', (string) $service->variants[0]->price);
        $this->assertTrue($service->variants[0]->is_default);
        $this->assertCount(1, $service->addOns);

        // No price and no priced variant: refused.
        $this->actingAs($this->admin)->post(route('massage.services.store'), ['name' => 'NO PRICE', 'price' => '', 'variants' => [['name' => 'x', 'price' => '']]])
            ->assertSessionHasErrors('price');
    }

    public function test_an_order_is_priced_from_the_chosen_variant_and_add_ons(): void
    {
        $service = MassageService::create(['name' => 'SWEDISH MASSAGE', 'price' => '0']);
        $thirty = $service->variants()->create(['name' => '30 mins', 'price' => '500', 'duration_minutes' => 30]);
        $hour = $service->variants()->create(['name' => '1 hr', 'price' => '800', 'duration_minutes' => 60, 'is_default' => true]);
        $stone = $service->addOns()->create(['name' => 'Hot Stone', 'price' => '300']);
        $otherAddOn = $this->swedish->addOns()->create(['name' => 'Foot Scrub', 'price' => '100']);

        // 2 × (1 hr ₱800 + Hot Stone ₱300) + 1 × 30 mins ₱500 + plain M-SWEDDISH ₱800 = ₱3,500
        $this->actingAs($this->therapist)->post(route('massage.orders.store'), [
            'room_number' => '204',
            'items' => [
                ['service_id' => $service->id, 'variant_id' => $hour->id, 'quantity' => 2, 'add_ons' => [['id' => $stone->id, 'quantity' => 1]]],
                ['service_id' => $service->id, 'variant_id' => $thirty->id, 'quantity' => 1],
                ['service_id' => $this->swedish->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $order = MassageOrder::with('items.addOns')->latest('id')->firstOrFail();
        $this->assertSame('3500.00', (string) $order->total_amount);
        $line = $order->items->firstWhere('variant_name', '1 hr');
        $this->assertSame('SWEDISH MASSAGE — 1 hr', $line->label());
        $this->assertSame('2200.00', (string) $line->subtotal);
        $this->assertSame(2, $line->addOns->sole()->quantity, 'One Hot Stone per massage, two massages.');
        $this->assertSame('600.00', (string) $line->addOns->sole()->subtotal);

        // A service sold in variants must name one; an add-on must be the service's own.
        $this->actingAs($this->therapist)->post(route('massage.orders.store'), ['room_number' => '1', 'items' => [['service_id' => $service->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('items.0.variant_id');
        $this->actingAs($this->therapist)->post(route('massage.orders.store'), ['room_number' => '1', 'items' => [['service_id' => $service->id, 'variant_id' => $hour->id, 'quantity' => 1, 'add_ons' => [['id' => $otherAddOn->id, 'quantity' => 1]]]]])
            ->assertSessionHasErrors('items.0.add_ons');

        $this->actingAs($this->therapist)->get(route('massage.orders.show', $order))->assertOk()
            ->assertSee('SWEDISH MASSAGE — 1 hr')->assertSee('+ Hot Stone');
    }

    public function test_links_from_the_services_list_to_the_react_form_load_the_full_page(): void
    {
        // The list is a Blade (Turbo) page and the form an Inertia one: a
        // Turbo visit swaps in a page React never starts on — a white screen,
        // then a page inside a page on Back. data-turbo="false" prevents it.
        $html = $this->actingAs($this->admin)->get(route('massage.services.index'))->assertOk()->getContent();

        foreach ([route('massage.services.create'), route('massage.services.edit', $this->swedish)] as $url) {
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote($url, '#').'" data-turbo="false"#', $html, $url);
        }
    }

    public function test_massage_sales_show_in_reports_as_their_own_section(): void
    {
        $order = $this->newOrder();
        $this->payFor($order, [
            ['method' => 'cash', 'amount' => '1000'],
            ['method' => 'room_charge', 'amount' => '1600', 'settled_via' => 'cash', 'charged_to' => 'Room 204'],
        ]);
        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $response = $this->actingAs($boss)->get(route('superadmin.reports.index', ['range' => 'today']))->assertOk();

        $massage = $response->viewData('separateSections')->firstWhere('key', 'massage');
        $this->assertSame(1000.0, $massage['paymentMethodsTotal']);
        $this->assertSame(1600.0, $massage['roomChargesTotal']);
        $this->assertSame(2600.0, $massage['grandTotal']);
        $response->assertSee('Massage orders, counted separately from the restaurant.')->assertSee($order->order_number);

        $this->actingAs($this->admin)->get(route('superadmin.reports.index'))->assertOk();
    }
}
