<?php

namespace App\Http\Controllers;

use App\Enums\MenuItemAvailability;
use App\Enums\OrderType;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Events\StaffAssistanceRequested;
use App\Http\Requests\StoreCustomerTakeoutOrderRequest;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Services\OrderCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The "lobby QR" welcome flow — a second, general entry point distinct
 * from a table's own QR code (CustomerOrderController), for a QR posted
 * somewhere general (entrance, reception) rather than at a specific
 * table. A guest types their name, then picks one of: order takeout,
 * pick an available seat (which hands off into the normal per-table
 * flow), call a staff member over, or just browse the menu.
 *
 * Public, unauthenticated, same philosophy as CustomerOrderController:
 * no auth middleware, every action reachable by an anonymous guest.
 */
class CustomerWelcomeController extends Controller
{
    public function show(): View
    {
        return view('customer.welcome');
    }

    /**
     * "Choose a Seat" — only areas/categories with individually numbered
     * spaces are listed (each has its own QR token the customer can be
     * hopped into); pure capacity-pool categories with no numbered spaces
     * have no customer-facing equivalent yet, so they're left out here.
     */
    public function seats(Request $request): View
    {
        $areas = Area::where('is_active', true)
            ->with(['categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $areas->flatMap(fn (Area $area) => $area->categories)->each(function ($category) {
            $category->setRelation('spaces', $category->spaces()->orderBy('sort_order')->orderBy('name')->get());
        });

        $areas = $areas->filter(fn (Area $area) => $area->categories->flatMap->spaces->isNotEmpty())->values();

        return view('customer.welcome-seats', [
            'areas' => $areas,
            'customerName' => $this->name($request),
        ]);
    }

    public function takeoutMenu(Request $request): View
    {
        return view('customer.welcome-takeout', [
            'categories' => $this->activeMenu(),
            'customerName' => $this->name($request),
        ]);
    }

    public function storeTakeout(StoreCustomerTakeoutOrderRequest $request): RedirectResponse
    {
        $order = OrderCreator::create($request->input('items'), [
            'order_type' => OrderType::Takeout,
            'area_id' => null,
            'space_category_id' => null,
            'space_id' => null,
            'space_session_id' => null,
            'created_by' => auth()->id(),
            'notes' => $request->string('notes')->toString() ?: null,
            'customer_name' => $request->string('customer_name')->toString() ?: null,
        ], null);

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());

        return redirect()->route('customer.orders.status', $order->public_token);
    }

    public function menu(Request $request): View
    {
        return view('customer.welcome-menu-preview', [
            'categories' => $this->activeMenu(),
            'customerName' => $this->name($request),
        ]);
    }

    public function callStaffForm(Request $request): View
    {
        return view('customer.welcome-call-staff', [
            'customerName' => $this->name($request),
        ]);
    }

    public function callStaff(Request $request): View
    {
        $name = $request->string('customer_name')->toString() ?: null;

        broadcast(new StaffAssistanceRequested($name));

        return view('customer.welcome-call-staff-sent', ['customerName' => $name]);
    }

    protected function name(Request $request): ?string
    {
        return $request->string('name')->toString() ?: null;
    }

    /**
     * Out-of-stock items stay visible in their normal alphabetical slot
     * (customers should see the full menu, not a shrinking or reshuffling
     * one) — the view marks them "Out of Stock" and blocks adding them.
     */
    protected function activeMenu(): Collection
    {
        return MenuCategory::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query->with([
                'images', 'variants', 'addOns',
                'cookingStyles' => fn ($q) => $q->where('is_active', true),
                'cookingStyleSet.cookingStyles' => fn ($q) => $q->where('is_active', true),
            ])
                ->where('availability_status', '!=', MenuItemAvailability::Hidden->value)
                ->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->menuItems->isNotEmpty())
            ->values();
    }
}
