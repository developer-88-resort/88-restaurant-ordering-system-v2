<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $todaysSales = Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('total_amount');

        $yesterdaysSales = Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->sum('total_amount');

        // Null (not 0%) when yesterday had no sales, so the UI shows "no
        // comparison available" instead of a misleading +/-infinite delta.
        $salesDeltaPercent = $yesterdaysSales > 0
            ? round((($todaysSales - $yesterdaysSales) / $yesterdaysSales) * 100, 1)
            : null;

        $salesTrend = Order::where('payment_status', PaymentStatus::Paid)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $salesTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($salesTrend) {
            $date = now()->subDays($daysAgo);

            return [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D'),
                'total' => (float) ($salesTrend[$date->toDateString()] ?? 0),
            ];
        })->values();

        $activeStatuses = [
            OrderStatus::Pending,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::Served,
        ];

        $orderStatusBreakdown = Order::whereIn('status', $activeStatuses)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $orderStatusBreakdown = collect($activeStatuses)->map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($orderStatusBreakdown[$status->value] ?? 0),
            'color' => $status->dotClasses(),
        ])->values();

        $activeOrders = Order::whereIn('status', $activeStatuses)->count();

        $pendingOrders = Order::where('status', OrderStatus::Pending)->count();

        $unpaidOrders = Order::where('payment_status', PaymentStatus::Unpaid)->count();

        $popularThisWeek = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereBetween('orders.created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->select(
                'order_items.item_name',
                'menu_categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as total_qty')
            )
            ->groupBy('order_items.item_name', 'menu_categories.name')
            ->orderByDesc('total_qty')
            ->first();

        $totalSpaces = Space::count();
        $occupiedSpaces = Space::where('status', SpaceStatus::Occupied)->count();

        $recentOrders = Order::with(['area', 'spaceCategory', 'space'])->latest()->limit(5)->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->orderNumber(),
                'location_label' => $order->locationLabel(),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_badge_classes' => $order->status->badgeClasses(),
                'total_amount' => (float) $order->total_amount,
                'placed_human' => $order->created_at->diffForHumans(),
                'show_url' => route('orders.show', $order),
            ]);

        return Inertia::render('Superadmin/Dashboard', [
            'todaysSales' => (float) $todaysSales,
            'yesterdaysSales' => (float) $yesterdaysSales,
            'salesDeltaPercent' => $salesDeltaPercent,
            'salesTrend' => $salesTrend,
            'orderStatusBreakdown' => $orderStatusBreakdown,
            'activeOrders' => $activeOrders,
            'pendingOrders' => $pendingOrders,
            'unpaidOrders' => $unpaidOrders,
            'popularThisWeek' => $popularThisWeek,
            'totalSpaces' => $totalSpaces,
            'occupiedSpaces' => $occupiedSpaces,
            'recentOrders' => $recentOrders,
            'adminCount' => User::where('role', UserRole::Admin)->count(),
            'staffCount' => User::where('role', UserRole::Staff)->count(),
        ]);
    }
}
