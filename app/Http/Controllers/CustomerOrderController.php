<?php

namespace App\Http\Controllers;

use App\Enums\MenuItemAvailability;
use App\Enums\OrderSource;
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
use App\Models\Promotion;
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
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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
    public function show(Request $request, Space $space): View|InertiaResponse
    {
        if (! $this->isOrderable($space)) {
            return view('customer.space-unavailable', ['space' => $space]);
        }

        $session = TableSessionManager::findOrOpenFor($space);
        $guest = TableSessionManager::tryResolveGuestFromCookie($request, $session);

        if (! $guest) {
            return $this->renderIdentify($space, $session, route('customer.spaces.identify', $space));
        }

        return $this->renderMenu($request, $space, $session, $guest);
    }

    /**
     * Shareable child-QR entry point: joins the table's ACTIVE dining
     * session by its own token. Unlike the master QR it never opens a new
     * session — a dead link after the bill is settled is exactly the
     * point.
     */
    public function join(Request $request, string $token): View|InertiaResponse
    {
        $session = SpaceSession::where('public_token', $token)->with('space')->first();

        if (! $session || ! $session->isActive() || ! $session->space) {
            return view('customer.session-closed');
        }

        if (! $this->isOrderable($session->space)) {
            return view('customer.space-unavailable', ['space' => $session->space]);
        }

        $guest = TableSessionManager::tryResolveGuestFromCookie($request, $session);

        if (! $guest) {
            return $this->renderIdentify($session->space, $session, route('customer.session.identify', $session->public_token));
        }

        return $this->renderMenu($request, $session->space, $session, $guest);
    }

    /**
     * Who-are-you screen shown whenever the guest cookie doesn't resolve —
     * a brand-new device, or the same one via a scanner/browser context
     * that didn't carry the cookie forward (the whole reason this exists:
     * that behavior differs across QR-scanning apps in ways this app can't
     * detect or control). Offers every currently active guest at this
     * table to confirm against by name, plus a fresh name (or "Guest N" if
     * left blank) for someone genuinely new.
     */
    protected function renderIdentify(Space $space, SpaceSession $session, string $identifyUrl): InertiaResponse
    {
        return Inertia::render('Customer/Identify', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'area_name' => $space->area->name,
            ],
            'existing_guests' => $session->guestSessions()
                ->where('status', 'active')
                ->orderBy('guest_number')
                ->get()
                ->map(fn (GuestSession $g) => [
                    'token' => $g->public_token,
                    'label' => $g->displayLabel(),
                ])->values(),
            'identify_url' => $identifyUrl,
        ]);
    }

    /**
     * Master-QR side of the identify screen's submit — see
     * {@see identifyForSession()} for the child-QR side. Both funnel into
     * {@see completeIdentify()}; only how the table session is located
     * differs.
     */
    public function identifyForSpace(Request $request, Space $space): RedirectResponse
    {
        if (! $this->isOrderable($space)) {
            return redirect()->route('customer.spaces.show', $space);
        }

        $session = TableSessionManager::findOrOpenFor($space);
        $this->completeIdentify($request, $session);

        return redirect()->route('customer.spaces.show', $space);
    }

    public function identifyForSession(Request $request, string $token): RedirectResponse
    {
        $session = SpaceSession::where('public_token', $token)->with('space')->first();

        if (! $session || ! $session->isActive() || ! $session->space || ! $this->isOrderable($session->space)) {
            return redirect()->route('customer.session.join', $token);
        }

        $this->completeIdentify($request, $session);

        return redirect()->route('customer.session.join', $token);
    }

    /**
     * Either resumes the specific guest the customer confirmed they are
     * (by public_token — falls through to minting a fresh one if it's gone
     * stale, e.g. closed out between page load and submit) or mints a new
     * guest carrying the name they typed, if any.
     */
    protected function completeIdentify(Request $request, SpaceSession $session): void
    {
        $existingToken = $request->string('guest_token')->toString();

        if ($existingToken !== '') {
            $guest = $session->guestSessions()->where('public_token', $existingToken)->where('status', 'active')->first();

            if ($guest) {
                TableSessionManager::resumeGuest($session, $guest);

                return;
            }
        }

        TableSessionManager::createNamedGuest($session, $request->string('name')->toString() ?: null);
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
                // Always null, never auth()->id() — this controller is
                // public and unauthenticated by design (see the class doc
                // comment). A staff member happening to have their own
                // session active in the same browser must never get
                // credited as the "waiter" for what is actually a
                // customer's own self-order.
                'created_by' => null,
                'order_source' => OrderSource::Qr,
                'notes' => $request->string('notes')->toString() ?: null,
                'customer_name' => $request->string('customer_name')->toString() ?: $guest->displayLabel(),
            ], $session);

            $bundles = collect($request->input('items'))
                ->map(function (array $line) use ($guest) {
                    $menuItem = MenuItem::findOrFail($line['menu_item_id']);
                    $bundle = OrderCreator::fixedLine($menuItem, $line);

                    $bundle['parent']['ordered_by_guest_id'] = $guest->id;

                    // Required, not cosmetic: previousOrdersPayload() sums
                    // OrderItem::where('ordered_by_guest_id', ...) to show
                    // a round's total in "your previous orders" — an
                    // add-on row without this guest id would silently
                    // under-count that total even though the order's real
                    // total_amount is correct.
                    $bundle['addOns'] = collect($bundle['addOns'])
                        ->map(fn ($addOn) => $addOn + ['ordered_by_guest_id' => $guest->id])
                        ->all();

                    return $bundle;
                })
                ->all();

            OrderAppender::appendBatch($order, $bundles, null, $idempotencyKey);

            return $order;
        });

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());

        return redirect()->route('customer.orders.status', $order->public_token);
    }

    public function status(string $token): InertiaResponse
    {
        $order = Order::where('public_token', $token)
            ->with(['items.adjustments', 'items.cookingStyle', 'space', 'guestSession', 'payments', 'currentInvoiceSnapshot.discounts'])
            ->firstOrFail();

        // The same read-model the staff view and the receipt use, so the
        // customer can never be shown a different number than the cashier
        // is looking at.
        $totals = \App\Services\OrderTotals::for($order);
        $invoice = $order->currentInvoiceSnapshot;

        return Inertia::render('Customer/Status', [
            'order' => [
                'public_token' => $order->public_token,
                'number' => $order->orderNumber(),
                'batch_number' => $order->batch_number,
                'guest_label' => $order->guestSession?->displayLabel(),
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'notes' => $order->notes,
                'has_receipt' => (bool) $order->receipt_number,
                'receipt_url' => route('customer.orders.receipt', $order->public_token),
                'order_again_url' => $order->space ? route('customer.spaces.show', $order->space) : null,
                'location_label' => $order->locationLabel(),
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'name' => $item->item_name,
                    'notes' => $item->notes,
                    'subtotal' => (float) $item->subtotal,
                    'is_fully_cancelled' => $item->isFullyCancelled(),
                    'cancelled_quantity' => $item->cancelledQuantity(),
                ])->values(),
            ],
            'totals' => [
                'original_subtotal' => (float) $totals->originalSubtotal,
                'cancelled_amount' => (float) $totals->cancelledAmount,
                'active_subtotal' => (float) $totals->activeSubtotal,
                'has_cancellations' => $totals->hasCancellations(),
                'payable_total' => (float) $totals->payableTotal(),
                'amount_paid' => (float) $totals->amountPaid,
                'has_refund_due' => $totals->hasRefundDue(),
                'refund_due' => (float) $totals->refundDue,
            ],
            'invoice' => $invoice ? [
                'discount_amount' => (float) $invoice->discount_amount,
                'is_vat' => $invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat,
                'vatable_sales' => (float) $invoice->vatable_sales,
                'tax_rate' => (float) $invoice->tax_rate,
                'vat_amount' => (float) $invoice->vat_amount,
            ] : null,
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

    protected function renderMenu(Request $request, Space $space, SpaceSession $session, GuestSession $guest): InertiaResponse
    {
        return Inertia::render('Customer/Menu', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'area_name' => $space->area->name,
            ],
            'submit_url' => route('customer.orders.store', $space),
            'categories' => $this->activeMenuPayload(),
            'promotions' => $this->activePromotionsPayload(),
            // Only present when arriving via the Welcome (lobby QR) flow's
            // "Choose a Seat" picker — a direct per-table QR scan has none.
            'customer_name' => $request->string('name')->toString() ?: null,
            'guest_label' => $guest->displayLabel(),
            'join_url' => route('customer.session.join', $session->public_token),
            'join_qr_url' => route('customer.session.qr', $session->public_token),
            'previous_orders' => $this->previousOrdersPayload($guest),
            'session_order_count' => $session->orders()->count(),
            'session_total' => (float) $session->orders()->sum('total_amount'),
        ]);
    }

    /**
     * Only currently-live banners with an uploaded image belong on the
     * customer menu. CTA traffic goes through a public redirect endpoint so
     * the Promotions dashboard can count clicks without exposing write APIs.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function activePromotionsPayload(): array
    {
        return Promotion::featuredLive()
            ->whereNotNull('image_path')
            ->get()
            ->map(fn (Promotion $promotion) => [
                'id' => $promotion->id,
                'code' => $promotion->code,
                'image_url' => $promotion->image_url,
                'mobile_image_url' => $promotion->mobile_image_url ?: $promotion->image_url,
                'has_cta' => filled($promotion->banner_cta_url),
                'click_url' => filled($promotion->banner_cta_url)
                    ? route('customer.promotions.click', $promotion)
                    : null,
                'view_url' => route('customer.promotions.view', $promotion),
            ])
            ->values()
            ->all();
    }

    /**
     * Flattens the active menu into plain, camelCase-free arrays for the
     * React page — computed methods like isPerKilo()/hasVariants() don't
     * survive Eloquent's automatic JSON serialization, so they're resolved
     * here explicitly, mirroring MenuItemController::index()'s payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function activeMenuPayload(): array
    {
        return $this->activeMenu()->map(fn (MenuCategory $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'items' => $category->menuItems->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'description_text' => $item->plainDescription(),
                'price' => (float) $item->price,
                'availability_status' => $item->availability_status->value,
                'is_per_kilo' => $item->isPerKilo(),
                'has_variants' => $item->hasVariants(),
                'price_range_label' => $item->priceRangeLabel(),
                'effective_price_per_kilo' => $item->isPerKilo() ? $item->effectivePricePerKilo() : null,
                'min_weight_grams' => $item->isPerKilo() ? (int) $item->min_weight_grams : null,
                'weighed_sort_order' => $item->weighed_sort_order,
                'counter_only' => $item->counter_only,
                'cooking_styles' => $item->isPerKilo()
                    ? $item->resolvedCookingStyles()->map(fn ($style) => ['id' => $style->id, 'name' => $style->name])->values()
                    : [],
                'primary_image_url' => $item->primaryImageUrl(),
                'variants' => $item->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'description' => $variant->description,
                    'price' => (float) $variant->price,
                    'image_url' => $variant->imageUrl(),
                    'is_default' => (bool) $variant->is_default,
                ])->values(),
                'add_ons' => $item->addOns->map(fn ($addOn) => [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'description' => $addOn->description,
                    'price' => (float) $addOn->price,
                ])->values(),
            ])->values(),
        ])->values()->all();
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
