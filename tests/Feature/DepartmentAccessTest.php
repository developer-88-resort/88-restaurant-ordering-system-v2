<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin/Staff accounts belong to a department. Restaurant (Korean
 * Restaurant, Ihawan, Minibar) runs the ordering system; Services
 * (Massage, Souvenir) must not see or change the restaurant's pages, so
 * they can't add their services into Menu Management again. Reports stay
 * combined for every Admin; a Superadmin sees everything.
 */
class DepartmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private const RESTAURANT_PAGES = ['superadmin.dashboard', 'orders.index', 'orders.create', 'kitchen.index', 'menu-items.index', 'spaces.index', 'quotations.index'];

    private function member(UserRole $role, ?Department $department): User
    {
        return User::factory()->create(['role' => $role, 'department' => $department, 'is_active' => true]);
    }

    private function navKeys(User $user): array
    {
        $this->actingAs($user);

        return collect(Navigation::forUser($user))->flatMap(fn ($group) => array_column($group['items'], 'key'))->all();
    }

    public function test_services_staff_cannot_open_any_restaurant_page(): void
    {
        $staff = $this->member(UserRole::Staff, Department::Services);

        foreach (self::RESTAURANT_PAGES as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
        $this->actingAs($staff)->get(route('weigh.station'))->assertForbidden();
    }

    public function test_a_services_admin_cannot_add_menu_categories_or_items(): void
    {
        $admin = $this->member(UserRole::Admin, Department::Services);

        $this->actingAs($admin)->get(route('menu-categories.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('menu-categories.store'), ['name' => 'MASSAGE'])->assertForbidden();
        $this->actingAs($admin)->get(route('menu-items.create'))->assertForbidden();

        $this->assertDatabaseMissing('menu_categories', ['name' => 'MASSAGE']);
    }

    public function test_services_keep_chat_and_their_account_and_admins_keep_reports(): void
    {
        $staff = $this->member(UserRole::Staff, Department::Services);
        $admin = $this->member(UserRole::Admin, Department::Services);

        $this->actingAs($staff)->get(route('chat.index'))->assertOk();
        $this->actingAs($staff)->get(route('profile.edit'))->assertOk();
        $this->actingAs($admin)->get(route('superadmin.reports.index'))->assertOk();
    }

    public function test_the_sidebar_hides_restaurant_pages_from_services(): void
    {
        $keys = $this->navKeys($this->member(UserRole::Admin, Department::Services));

        $this->assertContains('chat', $keys);
        $this->assertContains('reports', $keys);
        foreach (['overview', 'orders', 'kitchen', 'menu-items', 'spaces', 'quotations', 'promotions', 'weigh'] as $hidden) {
            $this->assertNotContains($hidden, $keys);
        }
    }

    public function test_restaurant_staff_and_superadmins_are_unchanged(): void
    {
        $staff = $this->member(UserRole::Staff, Department::Restaurant);
        $boss = $this->member(UserRole::Superadmin, null);

        foreach (self::RESTAURANT_PAGES as $route) {
            $this->actingAs($staff)->get(route($route))->assertOk();
            $this->actingAs($boss)->get(route($route))->assertOk();
        }

        $this->assertContains('menu-items', $this->navKeys($staff));
        $this->assertContains('menu-items', $this->navKeys($boss));
    }

    public function test_services_land_on_the_massage_overview_instead_of_the_restaurant_one(): void
    {
        $this->assertSame('massage.dashboard', $this->member(UserRole::Staff, Department::Services)->homeRouteName());
        $this->assertSame('superadmin.dashboard', $this->member(UserRole::Staff, Department::Restaurant)->homeRouteName());
    }

    public function test_the_superadmin_picks_a_department_for_admin_and_staff(): void
    {
        $boss = $this->member(UserRole::Superadmin, null);
        $this->actingAs($boss)->withSession(['auth.password_confirmed_at' => time()]);

        $this->get(route('superadmin.users.create'))->assertOk()->assertSee(__('Department'));

        $this->post(route('superadmin.users.store'), [
            'name' => 'Masahista Mia', 'role' => 'staff', 'department' => 'services', 'pin' => '482913', 'pin_confirmation' => '482913',
        ])->assertSessionHasNoErrors();
        $mia = User::where('name', 'Masahista Mia')->firstOrFail();
        $this->assertSame(Department::Services, $mia->department);

        $this->post(route('superadmin.users.store'), ['name' => 'No Dept', 'role' => 'staff', 'pin' => '482913', 'pin_confirmation' => '482913'])
            ->assertSessionHasErrors('department');

        $this->put(route('superadmin.users.update', $mia), ['name' => 'Masahista Mia', 'role' => 'staff', 'department' => 'restaurant'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Department::Restaurant, $mia->fresh()->department);

        $this->get(route('superadmin.users.index'))->assertOk()->assertSee(__('Restaurant'));
    }
}
