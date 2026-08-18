<?php

namespace App\Http\Controllers;

use App\Enums\MenuItemAvailability;
use App\Enums\OrderType;
use App\Enums\SpaceStatus;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Models\GuestSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Space;
use App\Models\SpaceSession;
use App\Services\OrderAppender;
use App\Services\OrderCreator;
use App\Services\TableSessionManager;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Public, unauthenticated customer self-service ordering — reached only by
 * scanning a Space's QR code (the master table QR), or the shareable child
 * QR of the table's active dining session. No auth middleware anywhere in
 * this controller; every action is intentionally reachable by an anonymous
 * guest, and a guest's only credentials are the random public tokens in
 * their QR URL and their own http-only guest cookie.
 */
class CustomerOrderController extends Controller
{
    public function show(Request $request, Space $space): View
    {
        if (! $this->isOrderable($space)) {
            return view('customer.space-unavailable', ['space' => $space]);
        }

        $session = TableSessionManager::findOrOpenFor($space);
        $guest = TableSessionManager::resolveGuest($request, $session);

        return $this->renderMenu($request, $space, $session, $guest);
    }

    /**
     * Shareable child-QR entry point: joins the table's ACTIVE dining
     * session by its own token. Unlike the master QR it never opens a new
     * session — a dead link after the bill is settled is exactly the
     * point.
     */
    public function join(Request $request, string $token): View
    {
        $session = SpaceSession::where('public_token', $token)->with('space')->first();

        if (! $session || ! $session->isActive() || ! $session->space) {
            return view('customer.session-closed');
        }

        if (! $this->isOrderable($session->space)) {
            return view('customer.space-unavailable', ['space' => $session->space]);
        }

        $guest = TableSessionManager::resolveGuest($request, $session);

        return $this->renderMenu($request, $session->space, $session, $guest);
    }

    /**
     * SVG QR image of the child join-URL, embedded on the table ordering
     * page for the other guests at the table to scan.
     */
    public function joinQr(string $token): Response
    {
        $session = SpaceSession::where('public_token', $token)->firstOrFail();

        $result = (new Builder(
            writer: new SvgWriter(),
            data: route('customer.session.join', $session->public_token),
            size: 240,
            margin: 8,
        ))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }

    public function store(StoreCustomerOrderRequest $request, Space $space): RedirectResponse
    {
        if (! $this->isOrderable($space)) {
            return redirect()->route('customer.spaces.show', $space);
        }

        // Double-tap / duplicate-submit guard: the page generates one
        // idempotency key per cart submission — a retried submission
        // replays each of its lines against this same key instead of
        // inserting them again (see OrderAppender::appendBatch()).
        $idempotencyKey = $request->string('idempotency_key')->toString() ?: null;

        $session = TableSessionManager::findOrOpenFor($space);
        $guest = TableSessionManager::resolveGuest($request, $session);

        $order = DB::transaction(function () use ($request, $space, $session, $guest, $idempotencyKey) {
            // The table's one running receipt — joins whatever's already
            // open for this table/session, or starts it if this is the
            // first round. Every channel (QR, Weigh, Quotation) resolves
            // onto the same order through here.
            $order = OrderAppender::resolveOrder($space, [
                'order_type' => OrderType::DineIn,
                'area_id' => $space->area_id,
                'space_category_id' => $space->category_id,
                'space_id' => $space->id,
                'space_session_id' => $session->id,
                'guest_session_id' => $guest->id,
                'created_by' => auth()->id(),
                'notes' => $request->string('notes')->toString() ?: null,
                'customer_name' => $request->string('customer_name')->toString() ?: $guest->displayLabel(),
            ], $session);

            $lines = collect($request->input('items'))
                ->map(function (array $line) use ($guest) {
                    $menuItem = MenuItem::findOrFail($line['menu_item_id']);

                    return OrderCreator::fixedLine($menuItem, $line) + ['ordered_by_guest_id' => $guest->id];
                })
                ->all();

            OrderAppender::appendBatch($order, $lines, null, $idempotencyKey);

            return $order;
        });

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());

        return redirect()->route('customer.orders.status', $order->public_token);
    }

    public function status(string $token): View
    {
        $order = Order::where('public_token', $token)
            ->with(['items.adjustments', 'items.cookingStyle', 'space', 'guestSession', 'payments', 'currentInvoiceSnapshot.discounts'])
            ->firstOrFail();

        return view('customer.status', [
            'order' => $order,
            // The same read-model the staff view and the receipt use, so
            // the customer can never be shown a different number than the
            // cashier is looking at.
            'totals' => \App\Services\OrderTotals::for($order),
        ]);
    }

    public function receipt(string $token): View
    {
        $order = Order::where('public_token', $token)->with(['items.adjustments', 'items.cookingStyle', 'creator', 'voidedBy', 'currentInvoiceSnapshot.discounts', 'payments'])->firstOrFail();

        abort_unless($order->receipt_number, 404);

        return view('customer.receipt', [
            'order' => $order,
            'totals' => \App\Services\OrderTotals::for($order),
        ]);
    }

    protected function renderMenu(Request $request, Space $space, SpaceSession $session, GuestSession $guest): View
    {
        return view('customer.menu', [
            'space' => $space,
            'categories' => $this->activeMenu(),
            // Only present when arriving via the Welcome (lobby QR) flow's
            // "Choose a Seat" picker — a direct per-table QR scan has none.
            'customerName' => $request->string('name')->toString() ?: null,
            'tableSession' => $session,
            'guestSession' => $guest,
            'joinUrl' => route('customer.session.join', $session->public_token),
            'joinQrUrl' => route('customer.session.qr', $session->public_token),
            'previousOrders' => $this->previousOrdersPayload($guest),
            'sessionOrderCount' => $session->orders()->count(),
            'sessionTotal' => (float) $session->orders()->sum('total_amount'),
        ]);
    }

    /**
     * This guest's own submitted rounds within the current dining session —
     * for the "your previous orders" panel and "Order Again" (which re-adds
     * the items to the cart at CURRENT prices; the historical lines are
     * never modified).
     *
     * A table's guests now typically share ONE order, so this is scoped by
     * `order_items.ordered_by_guest_id` (which line THIS guest added) and
     * grouped by (order, batch) — not by `Order` row — otherwise a guest
     * who joined an order someone else started would see an empty panel
     * despite having ordered things.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function previousOrdersPayload(GuestSession $guest): array
    {
        return OrderItem::where('ordered_by_guest_id', $guest->id)
            ->with(['order', 'menuItem', 'menuItemVariant'])
            ->get()
            ->groupBy(fn (OrderItem $item) => $item->order_id.':'.($item->batch_number ?? 0))
            ->sortByDesc(fn (Collection $items) => $items->first()->created_at)
            ->map(function (Collection $items) {
                $order = $items->first()->order;

                return [
                    'number' => $order->orderNumber(),
                    'batch' => $items->first()->batch_number,
                    'status' => $order->status->label(),
                    'statusValue' => $order->status->value,
                    'paymentStatus' => $order->payment_status->label(),
                    'total' => (float) $items->sum('subtotal'),
                    'placedAt' => $items->first()->created_at->format('g:i A'),
                    'statusUrl' => route('customer.orders.status', $order->public_token),
                    'items' => $items->map(function (OrderItem $item) {
                        $menuItem = $item->menuItem;
                        $available = $menuItem !== null
                            && in_array($menuItem->availability_status->value, ['available', 'seasonal'], true);

                        $livePrice = 0;
                        if ($available) {
                            if ($item->menu_item_variant_id) {
                                $variant = $item->menuItemVariant;
                                $available = $variant !== null && $variant->deleted_at === null;
                                $livePrice = $variant ? (float) $variant->price : 0;
                            } else {
                                $livePrice = (float) $menuItem->price;
                            }
                        }

                        return [
                            'menu_item_id' => $item->menu_item_id,
                            'variant_id' => $item->menu_item_variant_id,
                            'name' => $item->item_name,
                            'qty' => $item->quantity,
                            'price' => $livePrice,
                            'available' => $available,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
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
