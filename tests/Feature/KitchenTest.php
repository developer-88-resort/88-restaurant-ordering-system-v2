<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Quotation;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenTest extends TestCase
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

    public function test_a_future_scheduled_converted_order_shows_immediately_in_the_pending_lane(): void
    {
        // No more holding area for advance orders — they land straight in
        // the same lane as everything else the moment they're converted,
        // regardless of how far out their scheduled time is. The
        // order-card badge (not lane placement) is what tells staff it's
        // an advance order.
        $quotation = $this->convertQuotation(now()->addDay());

        $response = $this->actingAs($this->staff)->get('/kitchen');

        $response->assertOk();
        $response->assertViewHas('pending', fn ($pending) => $pending->contains('id', $quotation->refresh()->converted_order_id));
    }

    public function test_kitchen_board_shows_an_advance_order_badge_with_the_quotation_number(): void
    {
        $quotation = $this->convertQuotation(now()->addDay());

        $response = $this->actingAs($this->staff)->get('/kitchen');

        $response->assertOk();
        $response->assertSee(__('Advance Order'));
        $response->assertSee($quotation->quotation_number);
    }

    private function convertQuotation(\Illuminate\Support\Carbon $scheduledFor): Quotation
    {
        $this->actingAs($this->staff)->postJson(route('quotations.store'), [
            'space_id' => $this->space->id,
            'target' => 'new',
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'customer_name' => 'Chairman Guest',
            'scheduled_for' => $scheduledFor->format('Y-m-d H:i:s'),
            'items' => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        return Quotation::latest('id')->firstOrFail();
    }
}
