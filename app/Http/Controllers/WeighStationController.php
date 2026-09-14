<?php

namespace App\Http\Controllers;

use App\Enums\LineType;
use App\Enums\OrderItemConfirmationStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Http\Resources\OrderResource;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWeighing;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceSession;
use App\Services\OrderAppender;
use App\Services\OrderNumberGenerator;
use App\Services\TableSessionManager;
use App\Services\WeighedItemReadiness;
use App\Support\WeighedLinePricer;
use App\Support\WeighedOrderSettings;
use App\Support\WeighVarianceChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The weigh station: the counter where staff put fish or meat on the
 * scale and turn it into a line on the customer's running bill.
 *
 * `/weigh` (index()) is the landing page — a big "start weighing" button
 * plus today's numbers, so the sidebar link lands somewhere useful even
 * when nobody's mid-weigh-in. The actual six-screen wizard is a separate
 * page (wizard()) reached from there, and always resolves WHICH order the
 * line belongs to (step 4) before recording it through the same
 * POST /orders/{order}/items endpoint everything else appends through —
 * so a weighed line lands on the table's existing bill, never a new one.
 */
class WeighStationController extends Controller
{
    /**
     * The landing page: start weighing, or see today's numbers.
     *
     * Accepts ?filter=pending so a future Dashboard card can link straight
     * to the concrete list behind the "Awaiting customer confirmation"
     * count instead of just the number.
     */
    public function index(Request $request): Response
    {
        $filter = $request->query('filter');

        return Inertia::render('Weigh/Index', [
            'stats' => $this->todayStats(),
            'filter' => $filter,
            'pendingItems' => $filter === 'pending' ? $this->pendingConfirmationItems() : null,
        ]);
    }

    /**
     * The six-step wizard itself.
     */
    public function wizard(Request $request): Response
    {
        $setting = Setting::current();

        return Inertia::render('Weigh/Station', [
            'categories' => $this->perKiloItemsByCategory(),
            'areas' => $this->areasWithTables(),
            'can' => [
                'overridePrice' => $request->user()->can('weigh.override_price'),
            ],
            // Off by default — at the counter the customer is standing there
            // watching the scale, so the extra confirmation step is opt-in.
            'requiresCustomerConfirmation' => (bool) $setting->weigh_customer_confirmation_enabled,
            // The admin-set rules, so the tablet applies exactly what the
            // server will enforce.
            'weighed' => WeighedOrderSettings::current()->toArray(),
        ]);
    }

    /**
     * Step 5's lookup: who is at this table right now, and which bills are
     * already open on it.
     *
     * Every still-billable order on the table is returned, not only the
     * ones a QR session opened — a staff-created walk-in bill is invisible
     * to a session-scoped lookup, and the counter would then start a
     * SECOND bill for a party that already had one. When more than one
     * comes back the wizard asks which; `session: null` with no orders is
     * what drives the "nobody seated" branch.
     */
    public function tableSession(Space $space): JsonResponse
    {
        $session = TableSessionManager::activeSessionFor($space);
        $openOrders = OrderAppender::findOpenOrdersForTable($space);

        return response()->json([
            'space' => ['id' => $space->id, 'name' => $space->name],
            'session' => $session ? [
                'id' => $session->id,
                'started_at' => $session->started_at?->format('g:i A'),
                'guests' => $session->guestSessions()
                    ->where('status', 'active')
                    ->orderBy('guest_number')
                    ->get()
                    ->map(fn ($guest) => [
                        'id' => $guest->id,
                        'number' => $guest->guest_number,
                        // "Guest 1 — Test Guest" when they typed a name on
                        // the welcome screen, otherwise just "Guest 1".
                        'label' => $guest->display_name
                            ? __('Guest :number', ['number' => $guest->guest_number]).' — '.$guest->display_name
                            : __('Guest :number', ['number' => $guest->guest_number]),
                    ])->values(),
            ] : null,
            // The first bill, for the common single-order case the wizard
            // can skip straight past...
            'order' => $openOrders->first() ? new OrderResource($openOrders->first()) : null,
            // ...and all of them, for the case where staff must choose. Same
            // shape as `order` above — every entry carries its own items[],
            // so picking any one of them never leaves the wizard holding a
            // thinner object than the auto-selected single-order case does.
            'open_orders' => OrderResource::collection($openOrders),
        ]);
    }

    /**
     * The live "Expected ₱177.00 ✓" check behind the amount field.
     *
     * Read-only: it records nothing and changes nothing. It exists so the
     * tablet can show the verdict the server is going to reach WITHOUT a
     * second copy of the variance rules in JavaScript — a screen that
     * promises a line the server then refuses is worse than no indicator
     * at all.
     */
    public function checkVariance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'net_grams' => ['required', 'integer', 'min:0', 'max:200000'],
            'amount_charged' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $item = MenuItem::findOrFail($data['menu_item_id']);
        $rate = (string) ($item->effectivePricePerKilo() ?? '0');

        $settings = WeighedOrderSettings::current();
        $net = (int) $data['net_grams'];
        $minimum = (int) $item->min_weight_grams;

        // Under "Bill at minimum" the expectation is the floor price, which
        // is what the customer will actually be asked for.
        $chargeable = ($net < $minimum && $settings->billsAtMinimum()) ? $minimum : $net;
        $computed = WeighedLinePricer::base($chargeable, $rate);

        $variance = WeighVarianceChecker::make($settings)->check($computed, $data['amount_charged'] ?? 0);

        return response()->json($variance->toArray() + [
            'reference_price_per_kilo' => $rate,
            'below_minimum' => $net < $minimum,
            'min_weight_grams' => $minimum,
            'can_override' => (bool) $request->user()?->can('weigh.override_price'),
        ]);
    }

    /**
     * "Open a session" — the table has nobody seated but a customer is at
     * the counter with fish. Seats them and starts their bill in one go.
     */
    public function openSession(Space $space): JsonResponse
    {
        $session = TableSessionManager::findOrOpenFor($space);
        $order = OrderAppender::resolveOrder($space, [
            'order_type' => OrderType::DineIn,
            'area_id' => $space->area_id,
            'space_category_id' => $space->category_id,
            'space_id' => $space->id,
            'space_session_id' => $session->id,
            'created_by' => auth()->id(),
        ], $session);

        return response()->json([
            'session' => ['id' => $session->id],
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * "Create a walk-in order" — a take-out customer, or someone not seated
     * at any table. No session and no table; just a bill with a name on it.
     */
    public function walkInOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ]);

        $order = DB::transaction(fn () => Order::create([
            'order_type' => OrderType::Takeout,
            'order_number' => OrderNumberGenerator::generate(),
            'status' => \App\Enums\OrderStatus::Pending,
            'payment_status' => \App\Enums\PaymentStatus::Unpaid,
            'total_amount' => '0.00',
            'created_by' => $request->user()->id,
            'customer_name' => $data['customer_name'] ?? null,
            'notes' => ! empty($data['contact_number'])
                ? __('Contact: :number', ['number' => $data['contact_number']])
                : null,
        ]));

        return response()->json(['order' => new OrderResource($order)]);
    }

    /**
     * Per-kilo items grouped by menu category, each carrying TODAY's rate —
     * the market price when one is set for the day, otherwise the item's
     * standing rate. This is what step 1 shows on each card and what step 2
     * pre-fills.
     *
     * An item missing a cooking style or a price is INCLUDED, not hidden —
     * hiding it would let staff pick it, weigh a fish, and only discover
     * the problem three steps later at the cooking screen. It ships with
     * `needs_setup: true` and an edit link instead, so step 1 disables the
     * card and says why up front.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function perKiloItemsByCategory(): array
    {
        return MenuCategory::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query
                ->where('pricing_type', PricingType::PerKilo)
                ->whereIn('availability_status', ['available', 'seasonal'])
                ->with([
                    'images',
                    'addOns',
                    'cookingStyles' => fn ($q) => $q->where('is_active', true),
                    'cookingStyleSet.cookingStyles' => fn ($q) => $q->where('is_active', true),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (MenuCategory $category) => $category->menuItems->isNotEmpty())
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'items' => $category->menuItems->map(function (MenuItem $item) {
                    $reasons = WeighedItemReadiness::reasons($item);

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'image_url' => $item->primaryImageUrl(),
                        'price_per_kilo' => (float) $item->effectivePricePerKilo(),
                        'default_price_per_kilo' => (float) $item->price_per_kilo,
                        'min_weight_grams' => (int) $item->min_weight_grams,
                        'needs_setup' => $reasons !== [],
                        'setup_reason' => $reasons[0]['label'] ?? null,
                        'setup_url' => $reasons[0]['url'] ?? route('menu-items.edit', $item),
                        'cooking_styles' => $item->resolvedCookingStyles()->map(fn ($style) => [
                            'id' => $style->id,
                            'name' => $style->name,
                            'surcharge' => (float) $style->surcharge,
                        ])->values(),
                        'add_ons' => $item->addOns->map(fn ($addOn) => [
                            'id' => $addOn->id,
                            'name' => $addOn->name,
                            'description' => $addOn->description,
                            'price' => (float) $addOn->price,
                        ])->values(),
                    ];
                })->values(),
            ])->values()->all();
    }

    /**
     * "Awaiting customer confirmation" and "Weighed today" for the /weigh
     * landing page.
     *
     * @return array<string, mixed>
     */
    protected function todayStats(): array
    {
        $today = OrderItemWeighing::query()
            ->active()
            ->whereDate('weighed_at', Carbon::today());

        return [
            'pendingConfirmationCount' => OrderItem::query()
                ->where('line_type', LineType::Weighed)
                ->where('confirmation_status', OrderItemConfirmationStatus::PendingCustomer)
                ->whereHas('order', fn ($q) => $q
                    ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
                    ->where('payment_status', '!=', PaymentStatus::Paid))
                ->count(),
            'weighedTodayKg' => round((int) (clone $today)->sum('net_grams') / 1000, 3),
            'weighedTodayAmount' => (string) (clone $today)->get()->reduce(
                fn (string $total, OrderItemWeighing $w) => bcadd($total, (string) $w->amount_charged, 2),
                '0.00',
            ),
        ];
    }

    /**
     * The concrete list behind ?filter=pending — lines weighed but not yet
     * accepted by the customer, oldest first so the ones waiting longest
     * surface at the top.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pendingConfirmationItems(): array
    {
        return OrderItem::query()
            ->where('line_type', LineType::Weighed)
            ->where('confirmation_status', OrderItemConfirmationStatus::PendingCustomer)
            ->whereHas('order', fn ($q) => $q
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
                ->where('payment_status', '!=', PaymentStatus::Paid))
            ->with(['order.space', 'weighedBy'])
            ->oldest('weighed_at')
            ->get()
            ->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'detail' => $item->weightLabel(),
                'order_id' => $item->order_id,
                'order_number' => $item->order->orderNumber(),
                'table' => $item->order->space?->name,
                'weighed_by' => $item->weighedBy?->name,
                'weighed_at' => $item->weighed_at?->format('g:i A'),
            ])->values()->all();
    }

    /**
     * "Awaiting customer confirmation" and "Weighed today" for the /weigh
     * landing page.
     *
     * @return array<string, mixed>
     */
    protected function todayStats(): array
    {
        $today = OrderItemWeighing::query()
            ->active()
            ->whereDate('weighed_at', Carbon::today());

        return [
            'pendingConfirmationCount' => OrderItem::query()
                ->where('line_type', LineType::Weighed)
                ->where('confirmation_status', OrderItemConfirmationStatus::PendingCustomer)
                ->whereHas('order', fn ($q) => $q
                    ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
                    ->where('payment_status', '!=', PaymentStatus::Paid))
                ->count(),
            'weighedTodayKg' => round((int) (clone $today)->sum('net_grams') / 1000, 3),
            'weighedTodayAmount' => (string) (clone $today)->get()->reduce(
                fn (string $total, OrderItemWeighing $w) => bcadd($total, (string) $w->amount_charged, 2),
                '0.00',
            ),
        ];
    }

    /**
     * The concrete list behind ?filter=pending — lines weighed but not yet
     * accepted by the customer, oldest first so the ones waiting longest
     * surface at the top.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pendingConfirmationItems(): array
    {
        return OrderItem::query()
            ->where('line_type', LineType::Weighed)
            ->where('confirmation_status', OrderItemConfirmationStatus::PendingCustomer)
            ->whereHas('order', fn ($q) => $q
                ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
                ->where('payment_status', '!=', PaymentStatus::Paid))
            ->with(['order.space', 'weighedBy'])
            ->oldest('weighed_at')
            ->get()
            ->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'detail' => $item->weightLabel(),
                'order_id' => $item->order_id,
                'order_number' => $item->order->orderNumber(),
                'table' => $item->order->space?->name,
                'weighed_by' => $item->weighedBy?->name,
                'weighed_at' => $item->weighed_at?->format('g:i A'),
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function areasWithTables(): array
    {
        // One query for every table's "does it have an open bill right now"
        // flag, rather than one findOpenOrdersForTable() call per tile —
        // this can render 50+ tables at once.
        $spaceIdsWithOpenOrders = self::openOrderSpaceIds();

        return Area::where('is_active', true)
            ->with(['spaces' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Area $area) => $area->spaces->isNotEmpty())
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'tables' => $area->spaces->map(fn (Space $space) => [
                    'id' => $space->id,
                    'name' => $space->name,
                    'status' => $space->status->value,
                    'has_open_order' => $spaceIdsWithOpenOrders->contains($space->id),
                ])->values(),
            ])->values()->all();
    }

    /**
     * Every space (directly, or via any of its sessions) with a still-
     * billable order right now — mirrors OrderAppender::findOpenOrdersForTable()'s
     * matching rule but as one set lookup for every table at once.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function openOrderSpaceIds(): \Illuminate\Support\Collection
    {
        $bySpace = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereDoesntHave('sourceQuotation', fn ($query) => $query->where('scheduled_for', '>', now()))
            ->whereNotNull('space_id')
            ->pluck('space_id');

        $bySession = Order::query()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereDoesntHave('sourceQuotation', fn ($query) => $query->where('scheduled_for', '>', now()))
            ->whereNotNull('space_session_id')
            ->with('spaceSession:id,space_id')
            ->get()
            ->pluck('spaceSession.space_id')
            ->filter();

        return $bySpace->merge($bySession)->unique()->values();
    }
}
