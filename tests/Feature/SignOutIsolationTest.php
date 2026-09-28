<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing out of a shared tablet ends what was on it: Back can't replay a
 * signed-in page, and an unfinished cart can't be placed under the next
 * person who signs in.
 */
class SignOutIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Space $cottage;

    private MenuItem $sisig;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'Cottages', 'slug' => 'cottages', 'sort_order' => 1, 'is_active' => true]);
        $category = SpaceCategory::create(['area_id' => $area->id, 'name' => 'Cottage', 'slug' => 'cottage', 'is_active' => true]);
        $this->cottage = Space::create(['area_id' => $area->id, 'category_id' => $category->id, 'name' => 'Cottage 3', 'status' => 'available', 'sort_order' => 1]);

        $menu = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->sisig = MenuItem::create(['menu_category_id' => $menu->id, 'name' => 'Sisig', 'price' => '220.00', 'availability_status' => 'available']);

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
    }

    public function test_signed_in_pages_are_never_kept_for_back_navigation(): void
    {
        $response = $this->actingAs($this->staff)->get(route('orders.create'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_login_screen_is_never_kept_for_back_navigation_either(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_after_signing_out_the_order_screen_sends_back_to_login(): void
    {
        $this->actingAs($this->staff)->post(route('logout'))->assertRedirect(route('login'));

        $this->get(route('orders.create'))->assertRedirect(route('login'));
    }

    public function test_the_order_page_carries_the_signed_in_user_for_its_cart(): void
    {
        $this->actingAs($this->staff)
            ->get(route('orders.create'))
            ->assertSee('name="cart_owner_id" value="'.$this->staff->id.'"', false)
            ->assertSee('name="auth-user" content="'.$this->staff->id.'"', false);
    }

    public function test_a_cart_started_by_someone_else_is_refused_not_placed(): void
    {
        $previous = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($this->staff)
            ->from(route('orders.create'))
            ->post(route('orders.store'), $this->payload(['cart_owner_id' => $previous->id]))
            ->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors('cart_owner_id');

        $this->assertSame(0, Order::count());
    }

    public function test_the_signed_in_users_own_cart_is_placed(): void
    {
        $this->actingAs($this->staff)
            ->post(route('orders.store'), $this->payload(['cart_owner_id' => $this->staff->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->staff->id, Order::sole()->created_by);
    }

    private function payload(array $extra): array
    {
        return [
            'order_type' => 'dine_in',
            'area_id' => $this->cottage->area_id,
            'space_category_id' => $this->cottage->category_id,
            'space_id' => $this->cottage->id,
            'items' => [['menu_item_id' => $this->sisig->id, 'quantity' => 1]],
            ...$extra,
        ];
    }
}
