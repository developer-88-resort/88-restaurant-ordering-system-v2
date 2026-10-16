<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archiving a category together with its items (e.g. FRIED) must not break
 * Menu Management's Archived tab: the items still list, with their category.
 */
class MenuItemArchivedCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_archived_tab_lists_items_whose_category_is_archived_too(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $category = MenuCategory::create(['name' => 'FRIED', 'sort_order' => 1, 'is_active' => true]);
        $item = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Pampano', 'price' => '350.00']);
        $item->delete();
        $category->delete();

        $this->actingAs($admin)->get(route('menu-items.index', ['archived' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.0.name', 'Pampano')
                ->where('items.0.category_name', 'FRIED'));
    }
}
