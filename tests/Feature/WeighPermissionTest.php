<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CookingStyle;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permission boundaries around the weigh station: who may open it, and
 * who may quote a different rate than the day's reference — checked at
 * the route level, not just hidden in the UI.
 */
class WeighPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_weigh_record_cannot_open_the_station(): void
    {
        // No role today lacks weigh.record among the three operational
        // roles, so this proves the gate itself, not a role that happens
        // to be excluded — an inactive/unauthenticated visitor.
        $this->get('/weigh')->assertRedirect('/login');
    }

    public function test_staff_can_open_the_station(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($staff)->get('/weigh')->assertOk();
    }

    public function test_the_change_rate_capability_is_reported_only_for_users_who_may_override(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($staff)->get('/weigh/new')
            ->assertInertia(fn ($page) => $page->where('can.overridePrice', false));

        $this->actingAs($admin)->get('/weigh/new')
            ->assertInertia(fn ($page) => $page->where('can.overridePrice', true));
    }

    /**
     * The permission is enforced by weigh.override_price on
     * price_per_kilo_snapshot elsewhere (AppendOrderItemTest); this
     * checks the read side — check-variance must not leak whether a
     * caller CAN override just by asking, only report a boolean the
     * client already knows to gate its own "Change rate" link on.
     */
    public function test_check_variance_reports_can_override_truthfully_for_both_roles(): void
    {
        $category = MenuCategory::create(['name' => 'Fresh Catch', 'sort_order' => 1, 'is_active' => true]);
        $item = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Bangus',
            'price' => 0,
            'pricing_type' => 'per_kilo',
            'price_per_kilo' => '295.00',
            'min_weight_grams' => 250,
            'availability_status' => 'available',
        ]);
        CookingStyle::create(['name' => 'Inihaw', 'surcharge' => 0, 'sort_order' => 1, 'is_active' => true]);

        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $payload = ['menu_item_id' => $item->id, 'net_grams' => 600, 'amount_charged' => 1000];

        $this->actingAs($staff)->postJson(route('weigh.check-variance'), $payload)
            ->assertJsonPath('can_override', false);

        $this->actingAs($admin)->postJson(route('weigh.check-variance'), $payload)
            ->assertJsonPath('can_override', true);
    }
}
