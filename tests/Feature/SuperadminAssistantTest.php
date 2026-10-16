<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\MenuItemAvailability;
use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Superadmin's floating assistant: Gemini (faked here) answers from the
 * system's data through tools, and a change it wants only happens once the
 * Superadmin confirms it.
 */
class SuperadminAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $boss;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
        $this->boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
    }

    private function gemini(array ...$turns): void
    {
        $sequence = Http::sequence();
        foreach ($turns as $parts) {
            $sequence->push(['candidates' => [['content' => ['role' => 'model', 'parts' => $parts]]]]);
        }
        Http::fake(['generativelanguage.googleapis.com/*' => $sequence]);
    }

    public function test_a_superadmin_and_an_admin_get_the_assistant_but_staff_do_not(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'department' => Department::Restaurant, 'is_active' => true]);
        $staff = User::factory()->create(['role' => UserRole::Staff, 'department' => Department::Restaurant, 'is_active' => true]);

        $this->actingAs($this->boss)->get(route('orders.index'))->assertSee('id="assistant-root"', false);
        $this->actingAs($admin)->get(route('orders.index'))->assertSee('id="assistant-root"', false);
        $this->actingAs($staff)->get(route('orders.index'))->assertDontSee('id="assistant-root"', false);
        $this->actingAs($staff)->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'hi']]])->assertForbidden();
    }

    public function test_an_admin_only_gets_the_tools_of_their_own_pages(): void
    {
        $restaurant = User::factory()->create(['role' => UserRole::Admin, 'department' => Department::Restaurant, 'is_active' => true]);
        $massage = User::factory()->create(['role' => UserRole::Admin, 'department' => Department::Services, 'is_active' => true]);

        $tools = fn (User $user) => array_column(\App\Services\Assistant\AssistantTools::declarationsFor($user), 'name');
        $this->assertContains('users_list', $tools($this->boss));
        $this->assertContains('list_orders', $tools($restaurant));
        $this->assertNotContains('users_list', $tools($restaurant));
        $this->assertNotContains('system_status', $tools($restaurant));
        $this->assertNotContains('set_massage_service_availability', $tools($restaurant));
        $this->assertContains('set_massage_service_availability', $tools($massage));
        $this->assertNotContains('list_orders', $tools($massage));
        $this->assertNotContains('set_menu_item_availability', $tools($massage));

        // A tool the model calls anyway is refused, and nothing is proposed.
        $category = MenuCategory::create(['name' => 'PORK', 'sort_order' => 1, 'is_active' => true]);
        $item = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Adobo', 'price' => '450.00']);
        $this->gemini(
            [['functionCall' => ['name' => 'set_menu_item_availability', 'args' => ['item_name' => 'adobo', 'availability' => 'out_of_stock']]]],
            [['text' => 'A Superadmin handles that.']],
        );

        $this->actingAs($massage)
            ->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'Out of stock ang Adobo']]])
            ->assertOk()
            ->assertJson(['reply' => 'A Superadmin handles that.', 'pending' => []]);
        Http::assertSent(fn ($request) => str_contains($request->body(), 'cannot see or change this'));
        $this->assertSame(MenuItemAvailability::Available, $item->fresh()->availability_status);
    }

    public function test_it_answers_from_the_system_through_a_tool(): void
    {
        $this->gemini(
            [['functionCall' => ['name' => 'system_status', 'args' => []]]],
            [['text' => 'Maintenance Mode is off.']],
        );

        $this->actingAs($this->boss)
            ->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'Naka-maintenance ba?']]])
            ->assertOk()
            ->assertJson(['reply' => 'Maintenance Mode is off.', 'pending' => []]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains(json_encode($request->data()), 'functionResponse'));
        // The call is echoed back with args as {} (Gemini answers 400 to []).
        Http::assertSent(fn ($request) => str_contains($request->body(), '"functionCall":{"name":"system_status","args":{}}'));
    }

    public function test_an_action_waits_for_confirm_and_cancel_changes_nothing(): void
    {
        $category = MenuCategory::create(['name' => 'PORK', 'sort_order' => 1, 'is_active' => true]);
        $item = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Adobo', 'price' => '450.00']);
        $call = [['functionCall' => ['name' => 'set_menu_item_availability', 'args' => ['item_name' => 'adobo', 'availability' => 'out_of_stock']]]];

        $this->gemini($call, [['text' => 'Press Confirm below.']], $call, [['text' => 'Press Confirm below.']]);

        $first = $this->actingAs($this->boss)
            ->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'Out of stock ang Adobo']]])
            ->assertOk()
            ->assertJsonPath('pending.0.summary', 'Set "Adobo" to Out of Stock (now Available).');
        $this->assertSame(MenuItemAvailability::Available, $item->fresh()->availability_status, 'nothing changes before Confirm');

        $this->postJson(route('superadmin.assistant.confirm'), ['token' => $first->json('pending.0.token'), 'decision' => 'cancel'])->assertOk();
        $this->assertSame(MenuItemAvailability::Available, $item->fresh()->availability_status);

        $second = $this->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'Out of stock ang Adobo']]]);
        $this->postJson(route('superadmin.assistant.confirm'), ['token' => $second->json('pending.0.token'), 'decision' => 'confirm'])
            ->assertOk()
            ->assertJson(['reply' => 'Done: "Adobo" is now Out of Stock.']);
        $this->assertSame(MenuItemAvailability::OutOfStock, $item->fresh()->availability_status);

        // A token works once.
        $this->postJson(route('superadmin.assistant.confirm'), ['token' => $second->json('pending.0.token'), 'decision' => 'confirm'])->assertStatus(410);
    }

    private function slip(string $number, int $minutesAgo = 0): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => $number,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => '690.00',
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);

        return $order->fresh();
    }

    public function test_an_answer_gets_buttons_that_the_model_never_sees(): void
    {
        $order = $this->slip('ORD-1014-001');
        $this->gemini(
            [['functionCall' => ['name' => 'get_order', 'args' => ['order_number' => $order->orderNumber()]]]],
            [['text' => 'Here it is.']],
        );

        $this->actingAs($this->boss)
            ->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'Show '.$order->orderNumber()]]])
            ->assertOk()
            ->assertJsonPath('links.0.url', route('orders.show', $order))
            ->assertJsonPath('links.0.label', 'Open '.$order->orderNumber());

        Http::assertNotSent(fn ($request) => str_contains($request->body(), '_links'));
    }

    public function test_alerts_count_pending_kitchen_and_unpaid_orders_and_their_poll_is_not_activity(): void
    {
        $this->slip('ORD-LATE', 35);
        $this->slip('ORD-NEW', 2);
        Order::find($this->slip('ORD-COOKING')->id)->update(['status' => 'preparing']);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'department' => Department::Restaurant, 'is_active' => true]);
        $admin->setPin('2468');
        $staff = User::factory()->create(['role' => UserRole::Staff, 'department' => Department::Restaurant, 'is_active' => true]);

        $lastActivity = now()->subMinutes(2)->timestamp;
        $this->actingAs($admin)
            ->withSession([\App\Http\Middleware\EnforceIdleTimeout::SESSION_KEY => $lastActivity])
            ->getJson(route('superadmin.assistant.alerts'))
            ->assertOk()
            ->assertJsonPath('alerts.0.text', 'There are 2 pending orders.')
            ->assertJsonPath('alerts.1.text', 'There are 3 slips in the kitchen.')
            ->assertJsonPath('alerts.2.text', 'There are 3 unpaid orders.')
            ->assertJsonCount(3, 'alerts')
            ->assertSessionHas(\App\Http\Middleware\EnforceIdleTimeout::SESSION_KEY, $lastActivity);

        $this->actingAs($staff)->getJson(route('superadmin.assistant.alerts'))->assertForbidden();
    }

    public function test_unpaid_orders_are_the_ones_the_reminder_counts(): void
    {
        $this->actingAs($this->boss);
        $old = $this->slip('ORD-OLD-DONE', 60 * 24 * 18);
        $old->update(['status' => 'completed']);
        $this->slip('ORD-OPEN');
        Order::find($this->slip('ORD-CANCELLED')->id)->update(['status' => 'cancelled']);

        $listed = collect(\App\Services\Assistant\AssistantTools::lookup('list_orders', ['payment' => 'unpaid'])['orders'])->pluck('order_number');

        $this->assertEqualsCanonicalizing([$old->orderNumber(), Order::where('order_number', 'ORD-OPEN')->first()->orderNumber()], $listed->all());
        $this->assertSame('There are 2 unpaid orders.', collect(\App\Services\Assistant\AssistantTools::alertsFor($this->boss))->firstWhere('key', 'unpaid-2')['text']);
    }

    public function test_a_photo_goes_to_gemini_with_the_question_and_other_files_are_refused(): void
    {
        // A real 1×1 PNG: the type is checked on the bytes, not the name.
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $this->gemini([['text' => 'It is a tiny white square.']]);

        $this->actingAs($this->boss)
            ->postJson(route('superadmin.assistant.chat'), [
                'messages' => [['role' => 'user', 'text' => 'Ano ito?']],
                'attachment' => ['name' => 'photo.png', 'data' => $png],
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'It is a tiny white square.');
        Http::assertSent(fn ($request) => ($request->data()['contents'][0]['parts'][0]['inlineData']['mimeType'] ?? null) === 'image/png'
            && $request->data()['contents'][0]['parts'][1]['text'] === 'Ano ito?');

        $this->postJson(route('superadmin.assistant.chat'), [
            'messages' => [['role' => 'user', 'text' => 'Ano ito?']],
            'attachment' => ['name' => 'photo.png', 'data' => base64_encode('just some text, not a photo')],
        ])->assertStatus(422);
        Http::assertSentCount(1);
    }

    public function test_a_busy_gemini_is_retried_then_reported_politely(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'high demand']], 503)]);

        $this->actingAs($this->boss)
            ->postJson(route('superadmin.assistant.chat'), ['messages' => [['role' => 'user', 'text' => 'hi']]])
            ->assertStatus(502)
            ->assertJsonPath('error', 'The assistant is not available right now. Please try again.');

        Http::assertSentCount(4); // main, fallback, then each once more
    }
}
