<?php

namespace App\Http\Controllers;

use App\Enums\MenuItemAvailability;
use App\Enums\OrderType;
use App\Enums\SpaceStatus;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Space;
use App\Services\OrderCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public, unauthenticated customer self-service ordering — reached only by
 * scanning a Space's QR code. No auth middleware anywhere in this
 * controller; every action is intentionally reachable by an anonymous
 * guest.
 */
class CustomerOrderController extends Controller
{
    public function show(Request $request, Space $space): View
    {
        if (! $this->isOrderable($space)) {
            return view('customer.space-unavailable', ['space' => $space]);
        }

        return view('customer.menu', [
            'space' => $space,
            'categories' => $this->activeMenu(),
            // Only present when arriving via the Welcome (lobby QR) flow's
            // "Choose a Seat" picker — a direct per-table QR scan has none.
            'customerName' => $request->string('name')->toString() ?: null,
        ]);
    }

    public function store(StoreCustomerOrderRequest $request, Space $space): RedirectResponse
    {
        if (! $this->isOrderable($space)) {
            return redirect()->route('customer.spaces.show', $space);
        }

        $order = OrderCreator::create($request->input('items'), [
            'order_type' => OrderType::DineIn,
            'area_id' => $space->area_id,
            'space_category_id' => $space->category_id,
            'space_id' => $space->id,
            'space_session_id' => null,
            'created_by' => auth()->id(),
            'notes' => $request->string('notes')->toString() ?: null,
            'customer_name' => $request->string('customer_name')->toString() ?: null,
        ], $space);

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());

        return redirect()->route('customer.orders.status', $order->public_token);
    }

    public function status(string $token): View
    {
        $order = Order::where('public_token', $token)->with(['items', 'space'])->firstOrFail();

        return view('customer.status', ['order' => $order]);
    }

    public function receipt(string $token): View
    {
        $order = Order::where('public_token', $token)->with(['items', 'creator', 'voidedBy', 'currentInvoiceSnapshot'])->firstOrFail();

        abort_unless($order->receipt_number, 404);

        return view('customer.receipt', ['order' => $order]);
    }

    /**
     * Occupied is fine — that's usually the customer themselves already
     * seated, placing a second round. Maintenance/Disabled/Reserved, or a
     * whole Area taken offline for service, block ordering.
     */
    protected function isOrderable(Space $space): bool
    {
        if (! $space->area->is_active) {
            return false;
        }

        return ! in_array($space->status, [
            SpaceStatus::Maintenance,
            SpaceStatus::Disabled,
            SpaceStatus::Reserved,
        ], true);
    }

    /**
     * Out-of-stock items stay visible in their normal alphabetical slot
     * (customers should see the full menu, not a shrinking or reshuffling
     * one) — the view marks them "Out of Stock" and blocks adding them.
     */
    protected function activeMenu(): Collection
    {
        return MenuCategory::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query->with(['images', 'variants'])
                ->where('availability_status', '!=', MenuItemAvailability::Hidden->value)
                ->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->menuItems->isNotEmpty())
            ->values();
    }
}
