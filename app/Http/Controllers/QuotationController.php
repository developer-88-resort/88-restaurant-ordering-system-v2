<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Resources\OrderResource;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Space;
use App\Services\OrderAppender;
use App\Services\TableSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Advance orders / quotations pinned to a table ahead of time. Items freeze
 * their quoted name/price at quote time. Unlike the old Sent -> Accepted ->
 * Confirmed -> Converted pipeline, this is one screen and one action: pick
 * the table, see what's already open on it, choose which receipt this joins
 * (or start a new one), pick items + schedule, submit — the Quotation row
 * and the appended order lines are created together, in one transaction.
 * `App\Services\OrderAppender` still does the actual appending; this
 * controller's only extra job over Weigh/QR is that the destination order is
 * explicitly chosen by staff, never auto-picked — see tableReceipts().
 */
class QuotationController extends Controller
{
    public function index(): Response
    {
        $quotations = Quotation::with(['space', 'area', 'creator', 'convertedOrder'])
            ->latest()
            ->get()
            ->map(fn (Quotation $quotation) => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'status' => $quotation->status->value,
                'status_label' => $quotation->status->label(),
                'badge_classes' => $quotation->status->badgeClasses(),
                'space_label' => $quotation->area && $quotation->space
                    ? "{$quotation->area->name} - {$quotation->space->name}"
                    : null,
                'customer_name' => $quotation->customer_name,
                'scheduled_for' => $quotation->scheduled_for?->toIso8601String(),
                'subtotal' => (float) $quotation->subtotal,
                'converted_order_number' => $quotation->convertedOrder?->orderNumber(),
                'created_at' => $quotation->created_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Quotations/Index', ['quotations' => $quotations]);
    }

    public function create(): Response
    {
        return Inertia::render('Quotations/Create', [
            'areas' => $this->areasWithTables(),
            'categories' => $this->menuCategoriesForPicker(),
        ]);
    }

    /**
     * Hakbang 2's panel: every open receipt on this table right now, scoped
     * to the table's CURRENT active session (or no session at all, for a
     * staff walk-in bill) so a stale order left over from an already-closed
     * session never shows up as something this advance order could join.
     */
    public function tableReceipts(Space $space): JsonResponse
    {
        $session = TableSessionManager::activeSessionFor($space);

        return response()->json([
            'space' => ['id' => $space->id, 'name' => $space->name],
            'session' => $session ? [
                'id' => $session->id,
                'started_at' => $session->started_at?->format('g:i A'),
            ] : null,
            'open_orders' => OrderResource::collection($this->joinableOrdersForTable($space)),
        ]);
    }

    /**
     * Every order on this table an advance order may legally join right
     * now: still billable (see OrderAppender::findOpenOrdersForTable) AND
     * either session-less (a staff walk-in bill) or tied to the table's
     * CURRENT active session — never a leftover from an already-closed one.
     * The single source of truth for both what Hakbang 2's panel shows and
     * what store() re-validates a chosen target against at commit time, so
     * the two can never drift apart.
     *
     * @return Collection<int, Order>
     */
    protected function joinableOrdersForTable(Space $space): Collection
    {
        $session = TableSessionManager::activeSessionFor($space);

        return OrderAppender::findOpenOrdersForTable($space)
            ->filter(fn (Order $order) => $order->space_session_id === null || $order->space_session_id === $session?->id)
            ->values();
    }

    /**
     * The whole Hakbang 1-4 screen in one submit: build the frozen-price
     * lines, resolve exactly which order they land on (never guessed — see
     * class doc), create the Quotation record already at Added, and append
     * the batch. All inside one transaction, so a failure anywhere leaves
     * neither a stray Quotation row nor a stray order line behind.
     */
    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $space = Space::findOrFail($request->integer('space_id'));
        $requestId = $request->string('request_id')->toString();

        // A retried double-click carries the same request_id — return what
        // the first submit already produced instead of adding it twice.
        if ($existing = Quotation::where('request_id', $requestId)->first()) {
            return redirect()->route('quotations.show', $existing)
                ->with('status', __('Quotation :number added to order.', ['number' => $existing->quotation_number]));
        }

        try {
            $result = DB::transaction(function () use ($request, $space, $requestId) {
                $lines = collect($request->input('items'))->map(function (array $line) {
                    $menuItem = MenuItem::findOrFail($line['menu_item_id']);

                    $variant = null;
                    if ($menuItem->hasVariants()) {
                        $variant = ! empty($line['menu_item_variant_id'])
                            ? $menuItem->variants->firstWhere('id', (int) $line['menu_item_variant_id'])
                            : null;
                        $variant ??= $menuItem->variants->firstWhere('is_default', true) ?? $menuItem->variants->first();
                    }

                    $unitPrice = (string) ($variant->price ?? $menuItem->price);
                    $quantity = (int) $line['quantity'];

                    return [
                        'menu_item_id' => $menuItem->id,
                        'menu_item_variant_id' => $variant?->id,
                        'item_name' => $variant ? "{$menuItem->name} — {$variant->name}" : $menuItem->name,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'subtotal' => bcmul($unitPrice, (string) $quantity, 2),
                        'notes' => $line['notes'] ?? null,
                    ];
                });

                $subtotal = $lines->reduce(fn ($carry, $line) => bcadd($carry, $line['subtotal'], 2), '0.00');

                $scheduledFor = $request->date('scheduled_for');
                $target = $request->string('target')->toString();

                $quotation = Quotation::create([
                    'quotation_number' => 'TMP-'.Str::random(12),
                    'request_id' => $requestId,
                    'area_id' => $space->area_id,
                    'space_category_id' => $space->category_id,
                    'space_id' => $space->id,
                    'customer_name' => $request->string('customer_name')->toString() ?: null,
                    'customer_contact' => $request->string('customer_contact')->toString() ?: null,
                    'scheduled_for' => $scheduledFor,
                    'status' => QuotationStatus::Added,
                    'subtotal' => $subtotal,
                    'notes' => $request->string('notes')->toString() ?: null,
                    'created_by' => auth()->id(),
                    'converted_at' => now(),
                ]);
                $quotation->update(['quotation_number' => sprintf('QT-%05d', $quotation->id)]);
                $quotation->items()->createMany($lines->all());

                // Trust Hakbang 3's explicit choice unconditionally — ONLY
                // "new receipt" ever creates a standalone order. This used
                // to also force a new order whenever scheduled_for was in
                // the future (even a few minutes), which silently overrode
                // an explicit "add to this receipt" pick — that was the bug:
                // staff chose an open receipt, the request still landed on
                // a freshly created one instead.
                $joined = $target !== 'new';

                if ($joined) {
                    // Staff picked one of Hakbang 3's cards — re-validate at
                    // commit time, never trust the id blindly: still this
                    // table's, still the CURRENT active session (or no
                    // session, for a staff walk-in), still open. It may have
                    // been paid/closed since the panel loaded, or point at a
                    // different table's order entirely.
                    $chosen = Order::whereKey($target)->lockForUpdate()->firstOrFail();

                    if (! $this->joinableOrdersForTable($space)->contains('id', $chosen->id)) {
                        throw ValidationException::withMessages([
                            'target' => __('That receipt is no longer open — refresh and pick again.'),
                        ]);
                    }

                    $order = $chosen;
                } else {
                    // "New receipt" chosen explicitly — always its own
                    // standalone order, whether or not the table already
                    // has something else open.
                    $session = TableSessionManager::activeSessionFor($space);

                    $order = OrderAppender::resolveOrder($space, [
                        'order_type' => OrderType::DineIn,
                        'area_id' => $space->area_id,
                        'space_category_id' => $space->category_id,
                        'space_id' => $space->id,
                        'space_session_id' => $session?->id,
                        'created_by' => auth()->id(),
                        'customer_name' => $quotation->customer_name,
                        'notes' => trim(__('ADVANCE ORDER / QUOTATION').' '.$quotation->quotation_number.($quotation->notes ? ' — '.$quotation->notes : '')),
                    ], $session, forceNew: true);
                }

                // scheduled_for and quotation_id live on each appended line,
                // not just the order — a receipt can hold both
                // already-cooking lines and a newly-appended batch meant
                // for later, and each needs to trace back to its own
                // quotation for the receipt's per-line labeling.
                $itemAttributes = $lines->map(fn ($line) => array_merge($line, [
                    'notes' => trim(($line['notes'] ? $line['notes'].' — ' : '').__('Advance order :number', ['number' => $quotation->quotation_number])),
                    'scheduled_for' => $scheduledFor,
                    'quotation_id' => $quotation->id,
                ]))->all();

                OrderAppender::appendBatch($order, $itemAttributes, auth()->user(), $requestId);

                $quotation->update(['converted_order_id' => $order->id]);

                activity('audit')
                    ->causedBy(auth()->user())
                    ->performedOn($order)
                    ->event('advance_order_added')
                    ->withProperties([
                        'quotation_number' => $quotation->quotation_number,
                        'order_id' => $order->id,
                        'order_number' => $order->orderNumber(),
                        'line_count' => count($itemAttributes),
                        'total_amount' => (string) $subtotal,
                        'joined_existing_receipt' => $joined,
                    ])
                    ->log(sprintf(
                        'ADVANCE ORDER ADDED: %s — %d line(s), ₱%s, %s order %s',
                        $quotation->quotation_number,
                        count($itemAttributes),
                        $subtotal,
                        $joined ? 'joined existing' : 'new',
                        $order->orderNumber(),
                    ));

                return ['quotation' => $quotation, 'order' => $order, 'joined' => $joined];
            });
        } catch (ValidationException $e) {
            // Re-thrown as-is: Inertia's useForm().errors picks this up the
            // same way StoreQuotationRequest's own rules do — no hard page
            // reload, nothing typed is lost.
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', __(
                'Something went wrong while adding this advance order — nothing was saved. Please try again.'
            ));
        }

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());

        if ($result['joined']) {
            broadcast(new CustomerOrderStatusUpdated($result['order']));
        }

        return redirect()->route('orders.show', $result['order'])
            ->with('status', $result['joined']
                ? __('Quotation :number added to existing order :order.', [
                    'number' => $result['quotation']->quotation_number,
                    'order' => $result['order']->orderNumber(),
                ])
                : __('Quotation :number converted to order :order.', [
                    'number' => $result['quotation']->quotation_number,
                    'order' => $result['order']->orderNumber(),
                ]));
    }

    public function show(Quotation $quotation): Response
    {
        $quotation->load(['items', 'space', 'area', 'creator', 'convertedOrder']);

        return Inertia::render('Quotations/Show', [
            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'status' => $quotation->status->value,
                'status_label' => $quotation->status->label(),
                'badge_classes' => $quotation->status->badgeClasses(),
                'space_label' => $quotation->area && $quotation->space
                    ? "{$quotation->area->name} - {$quotation->space->name}"
                    : null,
                'customer_name' => $quotation->customer_name,
                'customer_contact' => $quotation->customer_contact,
                'scheduled_for' => $quotation->scheduled_for?->toIso8601String(),
                'notes' => $quotation->notes,
                'subtotal' => (float) $quotation->subtotal,
                'created_by' => $quotation->creator?->name,
                'created_at' => $quotation->created_at->toIso8601String(),
                'items' => $quotation->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'notes' => $item->notes,
                ])->values(),
                'converted_order' => $quotation->convertedOrder ? [
                    'id' => $quotation->convertedOrder->id,
                    'order_number' => $quotation->convertedOrder->orderNumber(),
                ] : null,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function areasWithTables(): array
    {
        $spaceIdsWithOpenOrders = WeighStationController::openOrderSpaceIds();

        return Area::where('is_active', true)
            ->with(['spaces' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
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
     * @return array<int, array<string, mixed>>
     */
    protected function menuCategoriesForPicker(): array
    {
        return MenuCategory::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query->with('variants')
                ->whereIn('availability_status', ['available', 'seasonal'])
                ->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->menuItems->isNotEmpty())
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'items' => $category->menuItems->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    // Kept visible, never filtered out — Section 7 wants the
                    // item seen and explained, not hidden, when it can't be
                    // quoted at a fixed price.
                    'counter_only' => (bool) $item->counter_only,
                    'variants' => $item->variants->map(fn ($variant) => [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => (float) $variant->price,
                        'is_default' => (bool) $variant->is_default,
                    ])->values(),
                ])->values(),
            ])->values()->all();
    }
}
