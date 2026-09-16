<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemDuplicateCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->category = MenuCategory::create(['name' => 'Appetizer', 'sort_order' => 1, 'is_active' => true]);
    }

    private function makeItem(string $name, array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'menu_category_id' => $this->category->id,
            'name' => $name,
            'price' => 100,
            'pricing_type' => 'fixed',
            'availability_status' => 'available',
        ], $overrides));
    }

    public function test_an_exact_case_insensitive_name_match_is_flagged(): void
    {
        $this->makeItem('Bangus Sisig');

        $response = $this->actingAs($this->admin)->getJson('/menu-items/check-duplicate?'.http_build_query(['name' => 'bangus sisig']));

        $response->assertOk();
        $matches = $response->json('matches');
        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]['is_exact']);
        $this->assertSame('Appetizer', $matches[0]['category_name']);
    }

    public function test_a_close_typo_is_flagged_as_a_near_match(): void
    {
        $this->makeItem('Crispy Pata');

        $response = $this->actingAs($this->admin)->getJson('/menu-items/check-duplicate?'.http_build_query(['name' => 'Crispy Patta']));

        $response->assertOk();
        $matches = $response->json('matches');
        $this->assertCount(1, $matches);
        $this->assertFalse($matches[0]['is_exact']);
    }

    public function test_an_unrelated_name_returns_no_matches(): void
    {
        $this->makeItem('Crispy Pata');

        $response = $this->actingAs($this->admin)->getJson('/menu-items/check-duplicate?'.http_build_query(['name' => 'Mango Shake']));

        $response->assertOk();
        $this->assertSame([], $response->json('matches'));
    }

    public function test_the_item_being_edited_is_excluded_from_its_own_matches(): void
    {
        $item = $this->makeItem('Sizzling Sisig');

        $response = $this->actingAs($this->admin)->getJson('/menu-items/check-duplicate?'.http_build_query([
            'name' => 'Sizzling Sisig',
            'exclude' => $item->id,
        ]));

        $response->assertOk();
        $this->assertSame([], $response->json('matches'));
    }

    public function test_an_archived_duplicate_links_to_the_archived_tab_instead_of_a_dead_edit_link(): void
    {
        $item = $this->makeItem('Kare-Kare');
        $item->delete();

        $response = $this->actingAs($this->admin)->getJson('/menu-items/check-duplicate?'.http_build_query(['name' => 'Kare-Kare']));

        $response->assertOk();
        $matches = $response->json('matches');
        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]['is_archived']);
        $this->assertStringContainsString('archived=1', $matches[0]['edit_url']);
    }

    public function test_a_short_query_returns_no_matches_without_erroring(): void
    {
        $this->makeItem('Bangus Sisig');

        $response = $this->actingAs($this->admin)->getJson('/menu-items/check-duplicate?'.http_build_query(['name' => 'Ba']));

        $response->assertOk();
        $this->assertSame([], $response->json('matches'));
    }
}
