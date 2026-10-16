<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\UserRole;
use App\Http\Controllers\OrderController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order Management ships every open order but only the last few days of
 * finished ones (the page had grown to ~11 MB), with "Show older orders"
 * (?all=1) for the rest and the status buttons still showing full totals.
 */
class OrderManagementHistoryWindowTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status, int $daysAgo): Order
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'HIST-'.uniqid(),
            'status' => $status,
            'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'total_amount' => '100.00',
        ]);
        $at = now()->subDays($daysAgo);
        $order->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $order;
    }

    public function test_old_finished_orders_wait_behind_show_older_orders(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Staff, 'department' => Department::Restaurant, 'is_active' => true]);
        $oldDone = $this->order('completed', OrderController::HISTORY_DAYS + 5);
        $oldCancelled = $this->order('cancelled', OrderController::HISTORY_DAYS + 3);
        $oldOpen = $this->order('pending', OrderController::HISTORY_DAYS + 9);
        $recentDone = $this->order('completed', 1);

        $response = $this->actingAs($cashier)->get(route('orders.index'))->assertOk();
        $shown = $response->viewData('orders')->pluck('id')->all();

        $this->assertContains($oldOpen->id, $shown, 'an open order always shows, however old');
        $this->assertContains($recentDone->id, $shown);
        $this->assertNotContains($oldDone->id, $shown);
        $this->assertNotContains($oldCancelled->id, $shown);
        $this->assertSame(2, $response->viewData('olderCount'));
        $this->assertSame(4, $response->viewData('totalOrders'));
        $response->assertSee('Show older orders');

        $all = $this->actingAs($cashier)->get(route('orders.index', ['all' => 1]))->assertOk();
        $this->assertCount(4, $all->viewData('orders'));
        $all->assertSee('Show recent orders only');
    }
}
