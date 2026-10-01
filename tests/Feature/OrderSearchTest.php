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
 * Order Management's search box and location filter work in the browser
 * over an index the page carries (resources/js/lib/orders-browser.js). This
 * checks what goes into that index — every order findable by its number,
 * table, price and who created it, filed under its area — and that the
 * component reaches the page intact.
 */
class OrderSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $darlene;

    private Area $kubo;

    private Space $kubo7;

    protected function setUp(): void
    {
        parent::setUp();

        $this->darlene = User::factory()->create(['name' => 'Darlene', 'role' => UserRole::Staff, 'is_active' => true]);
        $this->darlene->setPin('135790');

        $this->kubo = Area::create(['name' => 'KUBO', 'slug' => 'kubo', 'sort_order' => 1, 'is_active' => true]);
        $category = SpaceCategory::create(['area_id' => $this->kubo->id, 'name' => 'KUBO', 'slug' => 'kubo', 'is_active' => true]);
        $this->kubo7 = Space::create(['area_id' => $this->kubo->id, 'category_id' => $category->id, 'name' => 'KUBO 7', 'status' => 'available', 'sort_order' => 7]);
    }

    private function order(array $attributes): Order
    {
        return Order::create($attributes + [
            'order_number' => '88-0930-'.str_pad((string) (Order::count() + 1), 3, '0', STR_PAD_LEFT),
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => '1665.00',
        ]);
    }

    private function index(): array
    {
        return $this->actingAs($this->darlene)
            ->get(route('orders.index'))
            ->assertOk()
            ->original
            ->getData()['orderIndex']
            ->all();
    }

    public function test_an_order_is_searchable_by_number_table_price_and_who_created_it(): void
    {
        $order = $this->order([
            'order_type' => 'dine_in',
            'area_id' => $this->kubo->id,
            'space_category_id' => $this->kubo7->category_id,
            'space_id' => $this->kubo7->id,
            'created_by' => $this->darlene->id,
        ]);

        $entry = $this->index()[$order->id];

        foreach (['darlene', '88-0930-001', '#88-0930-001', 'kubo 7', '1665.00', '1,665.00', '1665'] as $needle) {
            $this->assertStringContainsString($needle, $entry['text'], "Searchable by \"{$needle}\".");
        }
        $this->assertContains('kubo 7', $entry['fields'], 'The table name alone is an exact match.');
        $this->assertContains('darlene', $entry['fields'], 'So is the creator.');
        $this->assertSame((string) $this->kubo->id, $entry['area']);
        $this->assertSame('completed', $entry['status']);
    }

    public function test_a_takeout_order_files_under_take_out(): void
    {
        $order = $this->order(['order_type' => 'takeout']);

        $this->assertSame('takeout', $this->index()[$order->id]['area']);
    }

    public function test_the_page_carries_the_filter_the_areas_and_every_order_row(): void
    {
        $first = $this->order(['order_type' => 'takeout']);
        $second = $this->order(['order_type' => 'dine_in', 'area_id' => $this->kubo->id, 'space_id' => $this->kubo7->id]);

        $html = $this->actingAs($this->darlene)->get(route('orders.index'))->assertOk()->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $component = $xpath->query('//*[starts-with(@x-data, "ordersBrowser(")]')->item(0);
        $this->assertNotNull($component, 'The filter component is on the page.');
        $this->assertStringEndsWith(')', rtrim($component->getAttribute('x-data')), 'Its x-data was not cut short.');

        $this->assertSame(1, $xpath->query('//input[@data-orders-search]')->length, 'One search box.');
        $this->assertStringContainsString('KUBO', $html);
        $this->assertStringContainsString('All locations', $html);

        foreach ([$first, $second] as $order) {
            // Desktop row and mobile card.
            $this->assertSame(2, $xpath->query('//*[@data-order-row="'.$order->id.'"]')->length);
        }
        $this->assertSame(2, $xpath->query('//*[@data-order-list]')->length);
    }
}
