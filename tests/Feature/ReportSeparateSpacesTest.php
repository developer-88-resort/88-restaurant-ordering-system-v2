<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Korean resto (KOLD-R tables in "Korean-OLDTB", plus its old archived
 * "Korean resto -R1 n" tables) and the minibar (MN-T tables in "MINIBAR-MN")
 * are tallied on their own. Which section a payment lands in is decided by
 * the table only — an order the resto's account rings up on a KR table
 * stays in the main tally.
 */
class ReportSeparateSpacesTest extends TestCase
{
    use RefreshDatabase;

    private Space $kr;

    private Space $kold;

    private Space $minibar;

    private Space $oldKoreanResto;

    private User $darlene;

    protected function setUp(): void
    {
        parent::setUp();

        $this->darlene = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true, 'email' => 'resto@88hotspring.com']);

        $this->kr = $this->table('KR', 'KR 1');
        $this->kold = $this->table('Korean-OLDTB', 'KOLD-R 1');
        $this->minibar = $this->table('MINIBAR-MN', 'MN-T 1');

        $this->oldKoreanResto = Space::create([
            'area_id' => $this->kr->area_id, 'category_id' => $this->kr->category_id,
            'name' => 'Korean resto -R1 1', 'status' => 'available', 'sort_order' => 101,
        ]);
    }

    private function table(string $category, string $name): Space
    {
        $area = Area::create(['name' => $category, 'slug' => strtolower($category), 'sort_order' => 1, 'is_active' => true]);
        $cat = SpaceCategory::create(['area_id' => $area->id, 'name' => $category, 'slug' => strtolower($category), 'is_active' => true]);

        return Space::create(['area_id' => $area->id, 'category_id' => $cat->id, 'name' => $name, 'status' => 'available', 'sort_order' => 1]);
    }

    private function paid(?Space $space, PaymentMethod $method, string $amount, ?User $by = null, array $overrides = []): OrderPayment
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'SP-'.uniqid(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => $amount,
            'area_id' => $space?->area_id,
            'space_id' => $space?->id,
            'created_by' => $by?->id,
        ]);

        return OrderPayment::create(array_merge([
            'order_id' => $order->id,
            'payment_method' => $method,
            'status' => OrderPaymentStatus::Recorded,
            'amount' => $amount,
            'received_at' => now(),
            'received_by' => $by?->id,
        ], $overrides));
    }

    private function reportData(): array
    {
        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        return $this->actingAs($boss)
            ->get(route('superadmin.reports.index', ['range' => 'month']))
            ->assertOk()
            ->assertSee('Korean-OLDTB')
            ->assertSee('MINIBAR-MN')
            ->original
            ->getData();
    }

    private function byMethod(array $section): \Illuminate\Support\Collection
    {
        return collect($section['paymentMethods'])->keyBy('payment_method');
    }

    public function test_each_outlet_gets_its_own_section_decided_by_the_table(): void
    {
        $this->paid($this->kr, PaymentMethod::Cash, '1000.00');
        $this->paid($this->kr, PaymentMethod::Cash, '252.00', $this->darlene); // resto account on KR → main
        $this->paid(null, PaymentMethod::Gcash, '200.00');                     // takeout → main
        $this->paid($this->kr, PaymentMethod::RoomCharge, '1530.00', null, ['charged_to' => 'Room 1']);

        $this->paid($this->kold, PaymentMethod::Cash, '350.00', $this->darlene);
        $this->paid($this->kold, PaymentMethod::BankTransfer, '180.00');
        $this->paid($this->oldKoreanResto, PaymentMethod::RoomCharge, '126.00', $this->darlene, ['charged_to' => 'Room 104']);

        $this->paid($this->minibar, PaymentMethod::Card, '500.00');

        $data = $this->reportData();

        $main = $this->byMethod(['paymentMethods' => $data['paymentMethods']]);
        $this->assertSame(1252.0, $main['cash']->total_amount);
        $this->assertSame(200.0, $main['gcash']->total_amount);
        $this->assertSame(0.0, $main['card']->total_amount);
        $this->assertSame(1452.0, $data['paymentMethodsTotal']);
        $this->assertSame(1530.0, $data['roomChargesTotal']);

        $sections = $data['separateSections'];
        $this->assertSame(['korean_oldtb', 'minibar'], $sections->keys()->all());

        $korean = $sections['korean_oldtb'];
        $this->assertSame('Korean-OLDTB', $korean['label']);
        $this->assertSame(350.0, $this->byMethod($korean)['cash']->total_amount);
        $this->assertSame(180.0, $this->byMethod($korean)['bank_transfer']->total_amount);
        $this->assertSame(126.0, $korean['roomChargesTotal']);
        $this->assertSame(656.0, $korean['grandTotal']);

        $minibar = $sections['minibar'];
        $this->assertSame(500.0, $this->byMethod($minibar)['card']->total_amount);
        $this->assertSame(500.0, $minibar['grandTotal']);
    }

    public function test_an_archived_old_korean_resto_table_still_counts_in_its_section(): void
    {
        $this->paid($this->oldKoreanResto, PaymentMethod::Cash, '164.50', $this->darlene);
        $this->oldKoreanResto->delete();

        $data = $this->reportData();

        $this->assertSame(164.5, $this->byMethod($data['separateSections']['korean_oldtb'])['cash']->total_amount);
        $this->assertSame(0.0, $this->byMethod(['paymentMethods' => $data['paymentMethods']])['cash']->total_amount);
    }

    public function test_each_tallied_day_keeps_the_rule_it_was_tallied_under(): void
    {
        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        // Sept 27 and earlier were tallied with the resto's account in main.
        $this->travelTo(\Carbon\Carbon::parse('2026-09-27 17:20:00'));
        $this->paid($this->kr, PaymentMethod::Cash, '6070.00', $this->darlene);

        $sept27 = $this->actingAs($boss)->get(route('superadmin.reports.index', ['date' => '2026-09-27']))->original->getData();

        $this->assertSame(6070.0, $sept27['paymentMethodsTotal']);
        $this->assertSame(0.0, $sept27['separateSections']['korean_oldtb']['grandTotal']);

        $this->travelTo(\Carbon\Carbon::parse('2026-09-28 20:00:00'));

        // Sept 28: the resto's account on a KR table was kept out of the
        // main tally then, so it still is.
        $this->paid($this->kr, PaymentMethod::Cash, '10120.00');
        $this->paid($this->kr, PaymentMethod::Cash, '252.00', $this->darlene);
        $this->paid($this->kr, PaymentMethod::RoomCharge, '1000.00', $this->darlene, ['charged_to' => 'Room 203']);
        $this->paid(null, PaymentMethod::Cash, '1240.00'); // guest/takeout order with no creator stays in main

        $sept28 = $this->actingAs($boss)->get(route('superadmin.reports.index', ['date' => '2026-09-28']))->original->getData();

        $this->assertSame(11360.0, $sept28['paymentMethodsTotal']);
        $this->assertSame(0.0, $sept28['roomChargesTotal']);
        $this->assertSame(1252.0, $sept28['separateSections']['korean_oldtb']['grandTotal']);

        // Sept 29 onwards: only the table decides.
        $this->travelTo(\Carbon\Carbon::parse('2026-09-29 09:00:00'));
        $this->paid($this->kr, PaymentMethod::Cash, '300.00', $this->darlene);

        $sept29 = $this->actingAs($boss)->get(route('superadmin.reports.index', ['date' => '2026-09-29']))->original->getData();

        $this->assertSame(300.0, $sept29['paymentMethodsTotal']);
        $this->assertSame(0.0, $sept29['separateSections']['korean_oldtb']['grandTotal']);
    }

    public function test_the_pdf_includes_the_outlet_sections(): void
    {
        $this->paid($this->kold, PaymentMethod::Cash, '350.00');
        $this->paid($this->minibar, PaymentMethod::Card, '500.00');

        $boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($boss)
            ->get(route('superadmin.reports.pdf', ['range' => 'month']))
            ->assertOk();
    }
}
