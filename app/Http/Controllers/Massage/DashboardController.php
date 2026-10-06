<?php

namespace App\Http\Controllers\Massage;

use App\Enums\MassageOrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\MassageOrder;
use App\Models\MassagePayment;
use App\Models\MassageService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Massage Overview — the restaurant Overview's layout (Superadmin/Dashboard)
 * with the Massage department's own numbers.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $paidBetween = fn ($from, $to) => (float) MassageOrder::where('status', MassageOrderStatus::Paid)->whereBetween('paid_at', [$from, $to])->sum('total_amount');

        $todaysSales = $paidBetween(now()->startOfDay(), now()->endOfDay());
        $yesterdaysSales = $paidBetween(now()->subDay()->startOfDay(), now()->subDay()->endOfDay());

        $trend = MassageOrder::where('status', MassageOrderStatus::Paid)
            ->where('paid_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(paid_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $salesTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($trend) {
            $date = now()->subDays($daysAgo);

            return [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D'),
                'total' => (float) ($trend[$date->toDateString()] ?? 0),
            ];
        })->values();

        $todaysPayments = MassagePayment::where('status', OrderPaymentStatus::Recorded)
            ->whereBetween('received_at', [now()->startOfDay(), now()->endOfDay()])
            ->get();

        $byMethod = collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
            'method' => $method->value,
            'label' => $method->label(),
            'total' => (float) $todaysPayments->filter(fn ($payment) => $payment->payment_method === $method)->sum('amount'),
            'count' => $todaysPayments->filter(fn ($payment) => $payment->payment_method === $method)->count(),
        ])->values();

        $popular = DB::table('massage_order_items')
            ->join('massage_orders', 'massage_orders.id', '=', 'massage_order_items.massage_order_id')
            ->where('massage_orders.status', MassageOrderStatus::Paid->value)
            ->whereBetween('massage_orders.paid_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->select('massage_order_items.name', DB::raw('SUM(massage_order_items.quantity) as total_qty'))
            ->groupBy('massage_order_items.name')
            ->orderByDesc('total_qty')
            ->first();

        $unpaid = MassageOrder::where('status', MassageOrderStatus::Pending);

        return Inertia::render('Massage/Dashboard', [
            'todaysSales' => $todaysSales,
            'salesDeltaPercent' => $yesterdaysSales > 0 ? round((($todaysSales - $yesterdaysSales) / $yesterdaysSales) * 100, 1) : null,
            'salesTrend' => $salesTrend,
            'unpaidOrders' => (clone $unpaid)->count(),
            'unpaidAmount' => (float) (clone $unpaid)->sum('total_amount'),
            'ordersToday' => MassageOrder::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->where('status', '!=', MassageOrderStatus::Cancelled)->count(),
            'roomChargesToday' => (float) $todaysPayments->filter(fn ($payment) => $payment->payment_method === PaymentMethod::RoomCharge)->sum('amount'),
            'byMethod' => $byMethod,
            'popularThisWeek' => $popular ? ['name' => $popular->name, 'qty' => (int) $popular->total_qty] : null,
            'servicesAvailable' => MassageService::available()->count(),
            'servicesTotal' => MassageService::count(),
            'recentOrders' => MassageOrder::latest('id')->limit(5)->get()->map(fn (MassageOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'location_label' => $order->whoLabel(),
                'status_label' => $order->status->label(),
                'status_badge_classes' => $order->status->badgeClasses(),
                'total_amount' => (float) $order->total_amount,
                'placed_human' => $order->created_at->diffForHumans(),
                'show_url' => route('massage.orders.show', $order),
            ]),
        ]);
    }
}
