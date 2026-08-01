<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private MenuItem $item;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Main', 'slug' => 'main', 'sort_order' => 1, 'is_active' => true]);
        $category = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Tables', 'slug' => 'tables', 'is_active' => true]);
        $this->space = Space::create(['area_id' => $area->id, 'category_id' => $category->id, 'name' => 'Table 1', 'status' => 'available', 'sort_order' => 1]);

        $menuCategory = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->item = MenuItem::create([
            'menu_category_id' => $menuCategory->id,
            'name' => 'Lechon Belly',
            'price' => '500.00',
            'availability_status' => 'available',
        ]);

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
    }

    public function test_a_draft_quotation_does_not_create_an_order_or_affect_any_amount_due(): void
    {
        $quotation = $this->makeQuotation();

        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertSame('1000.00', $quotation->subtotal);
        $this->assertSame(0, Order::count(), 'A quotation must never be an order until converted.');

        // It never reaches the kitchen board either.
        $kitchen = $this->actingAs($this->staff)->get('/kitchen');
        $kitchen->assertOk();
        $kitchen->assertDontSee('Lechon Belly');
    }

    public function test_an_accepted_quotation_converts_exactly_once_at_the_frozen_quoted_prices(): void
    {
        $quotation = $this->makeQuotation();
        $this->walkTo($quotation, ['sent', 'accepted']);

        // Menu price rises AFTER the quote — the conversion must ignore it.
        $this->item->update(['price' => '999.00']);

        $convert = $this->actingAs($this->staff)->post("/quotations/{$quotation->id}/convert");
        $convert->assertRedirect();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Converted, $quotation->status);
        $this->assertNotNull($quotation->converted_order_id);

        $order = $quotation->convertedOrder;
        $this->assertSame('1000.00', $order->total_amount, 'Converted order must use the quoted 500×2, not the new 999 price.');
        $this->assertSame('500.00', $order->items->first()->unit_price);
        $this->assertSame($this->space->id, $order->space_id);
        $this->assertStringContainsString($quotation->quotation_number, $order->notes);

        // Converting a second time is refused and creates nothing new.
        $again = $this->actingAs($this->staff)->post("/quotations/{$quotation->id}/convert");
        $again->assertRedirect();
        $again->assertSessionHas('error');
        $this->assertSame(1, Order::count());
    }

    public function test_a_cancelled_quotation_cannot_be_converted(): void
    {
        $quotation = $this->makeQuotation();
        $this->walkTo($quotation, ['cancelled']);

        $response = $this->actingAs($this->staff)->post("/quotations/{$quotation->id}/convert");

        $response->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_conversion_joins_the_tables_active_dining_session_when_one_exists(): void
    {
        // A guest already opened the table's QR session with one order.
        MenuItem::create([
            'menu_category_id' => $this->item->menu_category_id,
            'name' => 'Sinigang',
            'price' => '350.00',
            'availability_status' => 'available',
        ]);
        $this->post("/order/{$this->space->qr_token}", [
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ]);

        $quotation = $this->makeQuotation();
        $this->walkTo($quotation, ['sent', 'accepted']);
        $this->actingAs($this->staff)->post("/quotations/{$quotation->id}/convert")->assertRedirect();

        $converted = $quotation->refresh()->convertedOrder;
        $session = $this->space->sessions()->whereNotNull('public_token')->first();

        $this->assertSame($session->id, $converted->space_session_id);
        $this->assertSame(2, $converted->batch_number, 'The advance order becomes the next batch under the same table session.');
    }

    public function test_quotation_pages_render(): void
    {
        $quotation = $this->makeQuotation();

        $this->actingAs($this->staff)->get('/quotations')->assertOk()->assertSee($quotation->quotation_number);
        $this->actingAs($this->staff)->get('/quotations/create')->assertOk()->assertSee('Quoted Items');
        $this->actingAs($this->staff)->get("/quotations/{$quotation->id}")
            ->assertOk()
            ->assertSee('ADVANCE ORDER / QUOTATION')
            ->assertSee('Lechon Belly');
    }

    private function makeQuotation(): Quotation
    {
        $this->actingAs($this->staff)->post('/quotations', [
            'space_id' => $this->space->id,
            'customer_name' => 'Chairman Guest',
            'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        return Quotation::latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function walkTo(Quotation $quotation, array $statuses): void
    {
        foreach ($statuses as $status) {
            $this->actingAs($this->staff)->patch("/quotations/{$quotation->id}/status", ['status' => $status])
                ->assertSessionHasNoErrors();
        }

        $quotation->refresh();
    }
}
