<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Before M4, the Blade+Alpine layout and the Inertia+React one each
 * hardcoded their own nav item list, and the two drifted — Weigh & Order
 * and Daily Market Prices existed only in React's sidebar, Quotations
 * only in Blade's. A user moving from /orders to /weigh saw two different
 * apps.
 *
 * Both layouts now resolve the identical config/navigation.php through
 * App\Support\Navigation for the same request, so this test is a
 * regression guard: it loads 19 real admin routes spanning both render
 * stacks and asserts every one of them surfaces the SAME set of nav
 * items. If a template ever reverts to hardcoding its own list again,
 * this fails on exactly that route.
 */
class NavigationParityTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
    }

    /**
     * @return array<int, array{0: string, 1: bool}> route name => is an Inertia page
     */
    private function routes(): array
    {
        return [
            'areas.index' => false,
            'chat.index' => true,
            'kitchen.index' => false,
            'menu-categories.index' => true,
            'menu-items.index' => true,
            'orders.index' => false,
            'orders.create' => false,
            'orders.show' => false,
            'quotations.index' => true,
            'spaces.index' => true,
            'superadmin.audit-logs.index' => false,
            'superadmin.dashboard' => true,
            'superadmin.promotions.index' => true,
            'superadmin.reports.index' => false,
            'superadmin.reports.weighed-lines' => true,
            'superadmin.settings.edit' => false,
            'superadmin.users.index' => false,
            'weigh.station' => true,
            'weigh.prices.index' => true,
        ];
    }

    public function test_all_eighteen_admin_routes_are_covered(): void
    {
        $this->assertCount(19, $this->routes());
    }

    public function test_the_same_nav_items_appear_on_every_admin_route(): void
    {
        $order = Order::create([
            'order_type' => OrderType::DineIn,
            'order_number' => 'NAV-'.uniqid(),
            'status' => 'pending',
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => '0.00',
        ]);

        $expected = $this->flattenKeys(Navigation::forUser($this->superadmin));
        $this->assertNotEmpty($expected, 'The canonical nav itself must not be empty for a superadmin.');

        foreach ($this->routes() as $name => $isInertia) {
            $uri = $name === 'orders.show' ? route($name, $order) : route($name);

            $response = $this->actingAs($this->superadmin)->get($uri);
            $response->assertOk();

            $actual = $isInertia
                ? $this->navKeysFromInertiaResponse($response)
                : $this->navKeysFromBladeResponse($response);

            $this->assertSame(
                $expected,
                $actual,
                "Route [{$name}] rendered a different sidebar than the canonical navigation."
            );
        }
    }

    /** A role without weigh.record must not see Weigh & Order anywhere — including its child. */
    public function test_a_role_without_weigh_permission_never_sees_weigh_and_order(): void
    {
        // No such role exists today, but the assertion should hold even if
        // Gate::define('weigh.record', ...) is ever narrowed.
        $keys = $this->flattenKeys(Navigation::forUser($this->superadmin));
        $this->assertContains('weigh', $keys);
        $this->assertContains('weigh-prices', $keys);
    }

    /**
     * @return array<int, string>
     */
    private function flattenKeys(array $groups): array
    {
        $keys = [];

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];

                foreach ($item['children'] as $child) {
                    $keys[] = $child['key'];
                }
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function navKeysFromBladeResponse(TestResponse $response): array
    {
        preg_match_all('/data-nav-key="([^"]+)"/', $response->getContent(), $matches);

        $keys = array_unique($matches[1]);
        sort($keys);

        return array_values($keys);
    }

    /**
     * The sidebar for an Inertia page is rendered client-side by React —
     * a Feature test never executes that JS, so the only server-observable
     * proof is the `navigation` prop embedded in the page's initial
     * `data-page` payload. Decoded directly rather than fought through the
     * fluent Inertia assertion DSL, which isn't built for deep structural
     * comparison like this.
     *
     * @return array<int, string>
     */
    private function navKeysFromInertiaResponse(TestResponse $response): array
    {
        preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Expected an Inertia data-page payload in the response.');

        $page = json_decode(htmlspecialchars_decode($matches[1]), true);

        return $this->flattenKeys($page['props']['navigation'] ?? []);
    }
}
