<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Order;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spaces had been created with free-typed prefixes — "Korean resto -R1 1"
 * inside KUBO, "KUBO OT 18 1" inside KR — so a table's name no longer said
 * where it was. Every space in a category now shares that category's prefix,
 * which may differ from the category's own name ("Korean-OLDTB" → "KOLD-R").
 */
class SpaceNamingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SpaceCategory $kubo;

    private SpaceCategory $kr;

    private SpaceCategory $oldTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->kubo = $this->category('KUBO');
        $this->kr = $this->category('KR');
        $this->oldTables = $this->category('Korean-OLDTB');

        foreach ([1, 2, 3] as $n) {
            $this->space($this->kubo, "KUBO {$n}", $n);
            $this->space($this->oldTables, "KOLD-R {$n}", $n);
        }
        $this->space($this->kr, 'KR 1', 1);
    }

    private function category(string $name): SpaceCategory
    {
        $area = Area::create(['name' => $name, 'slug' => strtolower($name), 'sort_order' => 1, 'is_active' => true]);

        return SpaceCategory::create(['area_id' => $area->id, 'name' => $name, 'slug' => strtolower($name), 'is_active' => true]);
    }

    private function space(SpaceCategory $category, string $name, int $sort): Space
    {
        return Space::create(['area_id' => $category->area_id, 'category_id' => $category->id, 'name' => $name, 'status' => 'available', 'sort_order' => $sort]);
    }

    public function test_bulk_create_uses_the_categorys_prefix_and_ignores_a_typed_one(): void
    {
        $this->actingAs($this->admin)
            ->post(route('spaces.store-bulk'), ['category_id' => $this->kubo->id, 'prefix' => 'Korean resto -R1', 'start' => 4, 'count' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['KUBO 1', 'KUBO 2', 'KUBO 3', 'KUBO 4', 'KUBO 5'],
            Space::where('category_id', $this->kubo->id)->orderBy('sort_order')->pluck('name')->all()
        );
        $this->assertFalse(Space::where('name', 'like', 'Korean%')->exists());
    }

    public function test_a_category_whose_tables_use_their_own_prefix_keeps_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('spaces.store-bulk'), ['category_id' => $this->oldTables->id, 'start' => 4, 'count' => 1])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Space::where('category_id', $this->oldTables->id)->where('name', 'KOLD-R 4')->exists());

        $this->actingAs($this->admin)
            ->post(route('spaces.store'), ['category_id' => $this->oldTables->id, 'name' => 'Korean-OLDTB 5'])
            ->assertSessionHasErrors('name');
    }

    public function test_bulk_create_skips_numbers_already_in_use(): void
    {
        $this->actingAs($this->admin)
            ->post(route('spaces.store-bulk'), ['category_id' => $this->kubo->id, 'start' => 2, 'count' => 3])
            ->assertSessionHas('status', fn ($message) => str_contains($message, '2 already existed'));

        $this->assertSame(1, Space::where('name', 'KUBO 4')->count());
        $this->assertSame(1, Space::where('name', 'KUBO 2')->count());
    }

    public function test_an_empty_category_names_its_first_spaces_but_not_with_another_categorys_prefix(): void
    {
        $minibar = $this->category('MINIBAR-MN');

        $this->actingAs($this->admin)
            ->post(route('spaces.store-bulk'), ['category_id' => $minibar->id, 'start' => 1, 'count' => 2])
            ->assertSessionHasErrors('prefix');

        $this->actingAs($this->admin)
            ->post(route('spaces.store-bulk'), ['category_id' => $minibar->id, 'prefix' => 'kubo', 'start' => 1, 'count' => 2])
            ->assertSessionHasErrors('prefix');

        $this->actingAs($this->admin)
            ->post(route('spaces.store-bulk'), ['category_id' => $minibar->id, 'prefix' => ' MN-T ', 'start' => 1, 'count' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(['MN-T 1', 'MN-T 2'], Space::where('category_id', $minibar->id)->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_a_single_space_must_follow_its_categorys_prefix(): void
    {
        $this->actingAs($this->admin)
            ->post(route('spaces.store'), ['category_id' => $this->kr->id, 'name' => 'KUBO OT 18 1'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('spaces.store'), ['category_id' => $this->kubo->id, 'name' => 'kubo  2'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('spaces.store'), ['category_id' => $this->kr->id, 'name' => 'kr 16'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Space::where('category_id', $this->kr->id)->where('name', 'KR 16')->where('sort_order', 16)->exists());
    }

    public function test_renaming_must_follow_the_categorys_prefix(): void
    {
        $space = Space::where('name', 'KUBO 3')->first();
        $payload = fn (string $name) => ['name' => $name, 'status' => 'available'];

        $this->actingAs($this->admin)->put(route('spaces.update', $space), $payload('cottage 18'))->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->put(route('spaces.update', $space), $payload('KUBO 2'))->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->put(route('spaces.update', $space), $payload('kubo 21'))->assertSessionHasNoErrors();
        $this->assertSame('KUBO 21', $space->fresh()->name);
        $this->assertSame(21, $space->fresh()->sort_order);
    }

    public function test_an_old_off_pattern_space_can_still_be_saved_without_renaming(): void
    {
        $legacy = $this->space($this->kubo, 'Korean resto -R1 1', 101);

        $this->actingAs($this->admin)
            ->put(route('spaces.update', $legacy), ['name' => 'Korean resto -R1 1', 'status' => 'maintenance', 'capacity' => 4])
            ->assertSessionHasNoErrors();

        $this->assertSame('Korean resto -R1 1', $legacy->fresh()->name);
        $this->assertSame(4, $legacy->fresh()->capacity);
    }

    public function test_the_create_form_locks_the_prefix_and_suggests_the_next_number(): void
    {
        $this->actingAs($this->admin)
            ->get(route('spaces.create', ['category_id' => $this->oldTables->id]))
            ->assertOk()
            ->assertSee('KOLD-R')
            ->assertSee('start: 4', false)
            ->assertDontSee('name="prefix"', false);
    }

    public function test_deleting_a_space_archives_it_and_its_past_orders_keep_the_table(): void
    {
        $legacy = $this->space($this->kubo, 'Korean resto -R1 1', 101);
        $order = Order::create([
            'order_type' => 'dine_in', 'order_number' => 'ARCH-1', 'status' => 'completed', 'payment_status' => 'paid',
            'total_amount' => '100.00', 'area_id' => $legacy->area_id, 'space_id' => $legacy->id,
        ]);

        $this->actingAs($this->admin)->delete(route('spaces.destroy', $legacy))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($legacy);
        $this->assertFalse($this->kubo->spaces()->whereKey($legacy->id)->exists());
        $this->assertSame($legacy->id, $order->fresh()->space_id);
        $this->assertSame('Korean resto -R1 1', $order->fresh()->space->name);
    }
}
