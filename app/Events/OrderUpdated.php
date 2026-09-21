<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "This order just changed" for the Order Management side — the cashier's
 * list and an open order page — the counterpart of KitchenUpdated for the
 * kitchen. Carries only enough to decide whether the open page cares; the
 * page refetches for the actual numbers.
 */
class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Order $order, public string $action) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderUpdated';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->orderNumber(),
            'status' => $this->order->status->value,
            'action' => $this->action,
        ];
    }
}
