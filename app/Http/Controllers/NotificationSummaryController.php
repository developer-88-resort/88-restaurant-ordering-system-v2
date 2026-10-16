<?php

namespace App\Http\Controllers;

use App\Enums\Department;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the browser tab shows as "(5) Order Management" plus a dot on the
 * favicon (resources/js/lib/tab-notifier.js): unread chat for everyone, and
 * for the restaurant's own accounts the new orders waiting (Pending — the
 * same count as the sidebar badge) and the orders the kitchen has Ready to
 * serve. Refetched whenever one of those changes over the websocket.
 */
class NotificationSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true)
            && $user->worksIn(Department::Restaurant);

        return response()->json([
            'user_id' => $user->id,
            'restaurant' => $restaurant,
            'counts' => [
                'chat' => Message::unreadCountForUser($user->id),
                'orders' => $restaurant ? Order::where('status', OrderStatus::Pending)->count() : 0,
                'kitchen' => $restaurant
                    ? Order::where('status', OrderStatus::Ready)->whereNull('merged_into_order_id')->count()
                    : 0,
            ],
            'links' => [
                'chat' => route('chat.index'),
                'orders' => route('orders.index'),
                'kitchen' => route('kitchen.index'),
            ],
        ]);
    }
}
