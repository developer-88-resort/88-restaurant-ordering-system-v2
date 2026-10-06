<?php

namespace App\Http\Controllers;

use App\Enums\DiscountEligibilityMethod;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderItemAdjustmentSource;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Events\OrderUpdated;
use App\Http\Requests\AppendOrderItemRequest;
use App\Http\Requests\FinalizeOrderPaymentRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\TransferOrderSlipRequest;
use App\Http\Requests\UpdateOrderItemWeightRequest;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderItem;
use App\Models\PrinterJob;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceNumberGenerator;
use App\Services\OrderAppender;
use App\Services\OrderCreator;
use App\Services\OrderItemCanceller;
use App\Services\OrderLocationReleaser;
use App\Services\OrderSlipTransferrer;
use App\Services\Printing\KitchenSlipQueue;
use App\Services\TableSessionManager;
use App\Services\WeighedLineRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        // Status filtering happens client-side (instant, no reload) — every
        // order ships to the page once and the filtering happens in the
        // browser, same pattern as Menu Management's category pills.
        $orders = Order::with(['area', 'spaceCategory', 'space', 'creator'])
            ->latest()
            ->get();

        $statusCounts = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Tables with slips still in play, each with its slips in order —
        // several can be open for one table at once.
        $openTables = $orders
            ->filter(fn (Order $order) => $order->space_id && ! $order->status->isFinal())
            ->sortBy(fn (Order $order) => [$order->slip_number ?? PHP_INT_MAX, $order->id])
            ->groupBy('space_id')
            ->sortBy(fn ($slips) => $slips->first()->locationLabel());

        // What the search box and the location filter work from, one entry
        // per order. `fields` are the whole values an exact match ranks on;
        // `text` is everything searchable in one lowercase string. `done`
        // is when a finished order last changed — normally the moment it
        // was completed, since orders record no separate completion time —
        // so a Completed list can show the most recently completed first.
        $orderIndex = $orders->mapWithKeys(function (Order $order) {
            $total = (float) $order->total_amount;
            $fields = array_values(array_filter([
                $order->order_number,
                $order->orderNumber(),
                $order->locationLabel(),
                $order->slipLocationLabel(),
                $order->space?->name,
                $order->area?->name,
                $order->spaceCategory?->name,
                $order->creator?->name,
                $order->customer_name,
                number_format($total, 2, '.', ''),
                number_format($total, 2),
                (string) (int) round($total),
                number_format($total, 0),
            ], fn ($value) => $value !== null && $value !== ''));

            return [$order->id => [
                'status' => $order->status->value,
                'area' => $order->order_type === \App\Enums\OrderType::Takeout ? 'takeout' : (string) ($order->area_id ?? ''),
                'fields' => array_values(array_unique(array_map('mb_strtolower', $fields))),
                'text' => mb_strtolower(implode(' ', array_merge($fields, [$order->status->label(), $order->payment_status->label()]))),
                'created' => $order->created_at->getTimestamp(),
                'done' => $order->updated_at->getTimestamp(),
            ]];
        });

        $areas = \App\Models\Area::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('orders.index', [
            'orders' => $orders,
            'openTables' => $openTables,
            'statusCounts' => $statusCounts,
            'totalOrders' => $statusCounts->sum(),
            'orderIndex' => $orderIndex,
            'areas' => $areas,
            'hasTakeout' => $orders->contains(fn (Order $order) => $order->order_type === \App\Enums\OrderType::Takeout),
        ]);
    }

    public function create(): View
    {
        $menuCategories = MenuCategory::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query->with(['variants', 'images'])->whereIn('availability_status', ['available', 'seasonal'])->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->menuItems->isNotEmpty())
            ->values();

        $areas = Area::where('is_active', true)
            ->with(['categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $areas->flatMap(fn (Area $area) => $area->categories)->each(function ($category) {
            if ($category->is_free) {
                $category->setAttribute('occupied_count', $category->activeOccupancyCount());
                $category->setAttribute('capacity_count', $category->capacityCount());
            } else {
                $category->setRelation(
                    'spaces',
                    $category->spaces()->orderBy('sort_order')->orderBy('name')->get()
                );
            }
        });

        // ?space= lands here from "New slip for this table" on Order
        // Management — only honoured for a table that can take an order.
        $preselect = null;
        if ($preselectSpace = Space::find(request()->integer('space'))) {
            if (in_array($preselectSpace->status, [SpaceStatus::Available, SpaceStatus::Occupied], true)) {
                $preselect = [
                    'areaId' => $preselectSpace->area_id,
                    'categoryId' => $preselectSpace->category_id,
                    'spaceId' => $preselectSpace->id,
                ];
            }
        }

        return view('orders.create', [
            'areas' => $areas,
            'categories' => $menuCategories,
            'openSlipsBySpace' => $this->openSlipsBySpace(),
            'nextSlipBySpace' => $this->nextSlipBySpace(),
            'preselect' => $preselect,
        ]);
    }

    /**
     * Every table's slips still in play, for the New Order screen's "this
     * table already has Slip #1 and #2 — start Slip #3, or add to one?"
     * step.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    protected function openSlipsBySpace(): Collection
    {
        return Order::query()
            ->whereNotNull('space_id')
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->withoutFutureReservations()
            ->with('spaceSession')
            ->withCount('items')
            ->orderBy('slip_number')
            ->orderBy('id')
            ->get()
            ->groupBy('space_id')
            ->map(fn ($orders) => $orders->map(fn (Order $slip) => [
                'id' => $slip->id,
                'label' => $slip->slipLabel() ?? $slip->orderNumber(),
                'number' => $slip->orderNumber(),
                'status' => $slip->status->label(),
                'placed_at' => $slip->created_at->format('g:i A'),
                'item_count' => $slip->items_count,
                'can_add' => OrderAppender::canAppend($slip),
            ])->values());
    }

    /**
     * The number the next slip on each table's open tab will get; a table
     * with no open tab starts at Slip #1.
     *
     * @return Collection<int, int>
     */
    protected function nextSlipBySpace(): Collection
    {
        // Oldest first, so when a table somehow has two active tabs the
        // newest one — the one TableSessionManager would use — wins.
        return SpaceSession::query()
            ->where('status', 'active')
            ->whereNotNull('space_id')
            ->whereNotNull('public_token')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->withMax('orders', 'slip_number')
            ->orderBy('started_at')
            ->get()
            ->mapWithKeys(fn (SpaceSession $session) => [$session->space_id => ((int) $session->orders_max_slip_number) + 1]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        // Staff explicitly chose "Add to Slip #N" for an occupied table.
        // Anything else is a new slip.
        if ($target = $request->targetOrder()) {
            return $this->addToSlip($request, $target);
        }

        $order = DB::transaction(function () use ($request) {
            $isTakeout = $request->string('order_type')->toString() === OrderType::Takeout->value;
            $space = null;
            $spaceId = null;
            $spaceSessionId = null;

            if (! $isTakeout) {
                $category = SpaceCategory::findOrFail($request->integer('space_category_id'));
                $spaceId = $request->input('space_id') ?: null;
                $space = $spaceId ? Space::findOrFail($spaceId) : null;

                if (! $space && $category->is_free && $category->usesSpacePool()) {
                    $space = $category->spaces()
                        ->where('status', SpaceStatus::Available)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->first();
                    $spaceId = $space?->id;
                }

                // A table's order joins the table's tab — the same one its
                // QR code opens — and so becomes that tab's next slip. A
                // free-seating spot with no table is its own one-slip tab.
                $spaceSessionId = $space
                    ? TableSessionManager::findOrOpenFor($space)->id
                    : SpaceSession::create([
                        'category_id' => $request->integer('space_category_id'),
                    ])->id;
            }

            return OrderCreator::create($request->input('items'), [
                'order_type' => $isTakeout ? OrderType::Takeout : OrderType::DineIn,
                // Only meaningful for a seated party, and optional even then.
                'pax' => $isTakeout ? null : ($request->integer('pax') ?: null),
                'area_id' => $isTakeout ? null : $request->integer('area_id'),
                'space_category_id' => $isTakeout ? null : $request->integer('space_category_id'),
                'space_id' => $spaceId,
                'space_session_id' => $spaceSessionId,
                'created_by' => auth()->id(),
                'order_source' => OrderSource::Staff,
                'notes' => $request->string('notes')->toString() ?: null,
            ], $space);
        });

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new OrderUpdated($order, 'created'));

        return redirect()->route('orders.show', $order)
            ->with('status', $order->slip_number > 1
                ? __(':slip created for :location.', ['slip' => $order->slipLabel(), 'location' => $order->locationLabel()])
                : __('Order created successfully.'));
    }

    /**
     * The "Add to Slip #N" branch of New Order: the cart lands on that slip
     * as one more round, re-priced from the live menu exactly like a new
     * slip's lines. The kitchen sees the new round on the slip's card.
     */
    protected function addToSlip(StoreOrderRequest $request, Order $slip): RedirectResponse
    {
        $bundles = collect($request->input('items'))
            ->map(fn (array $line) => OrderCreator::fixedLine(MenuItem::findOrFail($line['menu_item_id']), $line))
            ->all();

        DB::transaction(function () use ($request, $slip, $bundles) {
            OrderAppender::appendBatch($slip, $bundles, $request->user());

            if ($note = $request->string('notes')->trim()->toString()) {
                $slip->update(['notes' => $slip->notes ? $slip->notes.' / '.$note : $note]);
            }
        });

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($slip));
        broadcast(new OrderUpdated($slip, 'items_added'));

        return redirect()->route('orders.show', $slip)
            ->with('status', __('Items added to :slip (order :number).', [
                'slip' => $slip->slipLabel() ?? __('this order'),
                'number' => $slip->orderNumber(),
            ]));
    }

    public function show(Order $order): View
    {
        $order->load([
            'area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation',
            'items.adjustments.requestedBy', 'items.adjustments.approvedBy', 'items.cookingStyle',
            'currentInvoiceSnapshot.discounts', 'payments',
            'spaceSession.orders.guestSession',
        ]);

        return view('orders.show', [
            'order' => $order,
            'setting' => Setting::current(),
            'totals' => \App\Services\OrderTotals::for($order),
            'discountRules' => \App\Models\DiscountRule::currentlyAvailable()
                ->orderBy('sort_order')
                ->get(),
            // Who a staff member can pick to approve a line cancel with a PIN.
            'approvers' => auth()->user()->isManager() ? [] : \App\Support\ManagerApproval::pinApprovers(),
            // "They moved to another kubo" — the tables this slip can go to,
            // and what is already open on each so it can be folded in.
            'transferTargets' => $this->transferTargets($order),
            'openSlipsBySpace' => $this->openSlipsBySpace(),
            'slipWasPrinted' => $order->printerJobs()->exists(),
            // Keeps Direct Print locked here too while a slip is still on
            // its way to the printer, same as on the Kitchen Display.
            'activePrintJobId' => KitchenSlipQueue::activeJobsFor([$order->id])[$order->id] ?? null,
        ]);
    }

    /**
     * Tables this slip could move to: everything a party can actually be
     * seated at, minus the one it is already on. Under maintenance or
     * disabled is left out — moving a guest onto a broken table is never
     * the intent — but an occupied one stays, because folding into the slip
     * already open there is half the point of the feature.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function transferTargets(Order $order): Collection
    {
        return Space::query()
            ->whereNotIn('status', [SpaceStatus::Maintenance, SpaceStatus::Disabled])
            ->when($order->space_id, fn ($query) => $query->whereKeyNot($order->space_id))
            ->with(['area', 'category'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Space $space) => [
                'id' => $space->id,
                'name' => $space->name,
                'area_name' => $space->area?->name ?? __('Unassigned'),
                'category_name' => $space->category?->name,
                'status' => $space->status->value,
                'status_label' => $space->status->label(),
            ])
            ->values();
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        if ($order->status->isFinal()) {
            return redirect()->back()
                ->with('error', __('Order :number is already :status and can no longer be changed.', [
                    'number' => $order->orderNumber(),
                    'status' => $order->status->label(),
                ]));
        }

        $request->validate([
            'status' => ['required', new Enum(OrderStatus::class)],
        ]);

        $status = OrderStatus::from($request->string('status')->toString());

        // Completed means settled: an unpaid slip can't be closed by hand,
        // or it drops off every open list with the bill never collected.
        if ($status === OrderStatus::Completed && $order->payment_status !== PaymentStatus::Paid) {
            return redirect()->back()
                ->with('error', __('Order :number is not paid yet. Collect payment first; it completes on its own once it is paid.', [
                    'number' => $order->orderNumber(),
                ]));
        }

        $order->update(['status' => $status]);
        $order->completeIfSettled();

        if ($order->status->isFinal()) {
            OrderLocationReleaser::release($order);
        }

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new OrderUpdated($order, 'status_changed'));

        return redirect()->back()->with('status', __('Order :number is now :status.', [
            'number' => $order->orderNumber(),
            'status' => $order->status->label(),
        ]));
    }

    /**
     * Move this slip to another table — the party changed their mind at the
     * last minute. The same order row moves, so nothing is recorded twice
     * against the table they never sat at; picking a slip already open on
     * the destination folds this one's lines into it instead.
     *
     * Deliberately allowed on completed, cancelled and paid slips too: at
     * that point it's a correction of the record, and refusing it only
     * sends staff back to opening a second slip by hand. The merge path is
     * the one exception — see OrderSlipTransferrer::guardMerge().
     */
    public function transferLocation(TransferOrderSlipRequest $request, Order $order): RedirectResponse
    {
        $target = Space::findOrFail($request->integer('space_id'));

        $mergeInto = $request->filled('merge_into_order_id')
            ? Order::findOrFail($request->integer('merge_into_order_id'))
            : null;

        try {
            $result = OrderSlipTransferrer::transfer($order, $target, $mergeInto, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first());
        }

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new OrderUpdated($result['order'], 'status_changed'));
        broadcast(new CustomerOrderStatusUpdated($result['order']));

        $message = $result['mode'] === 'merged'
            ? __('Order :number moved from :from and merged into :into on :to. Reprint the kitchen slip if one was already printed.', [
                'number' => $order->orderNumber(),
                'from' => $result['from'],
                'into' => $result['order']->orderNumber(),
                'to' => $result['to'],
            ])
            : __('Order :number moved from :from to :to. Reprint the kitchen slip if one was already printed.', [
                'number' => $order->orderNumber(),
                'from' => $result['from'],
                'to' => $result['to'],
            ]);

        return redirect()
            ->route('orders.show', $result['order'])
            ->with('status', $message);
    }

    /**
     * Finalizes payment for an order: resolves any statutory/promo
     * discount, computes the full BIR tax breakdown via InvoiceCalculator,
     * issues a new sequential invoice number, and freezes everything into
     * an immutable OrderInvoiceSnapshot. Allowed from Unpaid OR Voided (a
     * voided order can be paid again — see the class-level note on the
     * void→repay fix), only blocked while already Paid.
     */
    public function markAsPaid(FinalizeOrderPaymentRequest $request, Order $order): RedirectResponse
    {
        if ($order->status === OrderStatus::Cancelled) {
            return redirect()->back()
                ->with('error', __('Order :number is cancelled and cannot be paid.', [
                    'number' => $order->orderNumber(),
                ]));
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return redirect()->back()
                ->with('error', __('Order :number is already paid.', [
                    'number' => $order->orderNumber(),
                ]));
        }

        $data = $request->validated();

        // New checkout shape (configurable multi-discounts and/or split
        // payments) goes through PaymentFinalizer; the legacy single-
        // discount/single-payment shape below stays byte-for-byte intact.
        if (! empty($data['payments']) || ! empty($data['discounts'])) {
            \App\Services\PaymentFinalizer::finalize($order, $data, $request->user());

            return $this->afterPayment($order->refresh());
        }

        DB::transaction(function () use ($data, $order) {
            $eligibleAmount = null;
            $eligibleItemNames = null;

            // Reset first — a repay after a void must never inherit stale
            // eligibility flags from an earlier, different payment attempt
            // on the same order. The *historical* record of what was
            // eligible on a given invoice lives on that invoice's own
            // snapshot (discount_eligible_item_names), never on this
            // mutable current-state column.
            OrderItem::where('order_id', $order->id)->update(['is_discount_eligible' => false]);

            if (! empty($data['discount_type'])) {
                if ($data['discount_eligibility_method'] === DiscountEligibilityMethod::ItemBased->value) {
                    $itemIds = collect($data['discount_item_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
                    $eligibleItems = $order->items()->whereIn('id', $itemIds)->get();

                    if ($eligibleItems->count() !== $itemIds->count()) {
                        throw ValidationException::withMessages([
                            'discount_item_ids' => __('One or more selected items do not belong to this order.'),
                        ]);
                    }

                    OrderItem::where('order_id', $order->id)->whereIn('id', $itemIds)->update(['is_discount_eligible' => true]);
                    $eligibleAmount = (string) $eligibleItems->sum('subtotal');
                    $eligibleItemNames = $eligibleItems->pluck('item_name')->values()->all();
                } else {
                    $eligibleAmount = (string) $data['discount_eligible_amount'];

                    if (bccomp($eligibleAmount, (string) $order->total_amount, 2) > 0) {
                        throw ValidationException::withMessages([
                            'discount_eligible_amount' => __('The eligible amount cannot exceed the order subtotal.'),
                        ]);
                    }
                }
            }

            $setting = Setting::current();

            $breakdown = InvoiceCalculator::compute([
                'gross_sales' => (string) $order->total_amount,
                'tax_registration_type' => $setting->tax_registration_type,
                'tax_rate' => (string) $setting->tax_rate,
                'prices_include_vat' => $setting->prices_include_vat,
                'discount_type' => $data['discount_type'] ?? null,
                'eligible_amount' => $eligibleAmount,
                'promo_percent' => $data['discount_promo_percent'] ?? null,
                'service_charge_enabled' => $setting->service_charge_enabled,
                'service_charge_percent' => $setting->service_charge_percent,
                'service_charge_taxable' => $setting->service_charge_taxable,
            ]);

            $amountReceived = (string) $data['amount_received'];

            if (bccomp($amountReceived, $breakdown['total_amount_due'], 2) < 0) {
                throw ValidationException::withMessages([
                    'amount_received' => __('Amount received must be at least the total amount due (:total).', [
                        'total' => number_format((float) $breakdown['total_amount_due'], 2),
                    ]),
                ]);
            }

            $invoiceNumber = InvoiceNumberGenerator::generate($setting->invoice_number_prefix);

            $snapshot = OrderInvoiceSnapshot::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceSnapshotStatus::Active,
                'business_name' => $setting->invoiceBusinessName(),
                'trade_name' => $setting->resort_name,
                'business_address' => $setting->address,
                'contact_number' => $setting->contact_number,
                'email' => $setting->email,
                'website' => $setting->website,
                'tin' => $setting->tin,
                'branch_code' => $setting->branch_code,
                'tax_registration_type' => $setting->tax_registration_type,
                'tax_rate' => $setting->tax_rate,
                'prices_include_vat' => $setting->prices_include_vat,
                'invoice_title' => $setting->resolvedInvoiceTitle(),
                'bir_permit_number' => $setting->bir_permit_number,
                'atp_ocn_number' => $setting->atp_ocn_number,
                'atp_ocn_date_issued' => $setting->atp_ocn_date_issued,
                'invoice_serial_from' => $setting->invoice_serial_from,
                'invoice_serial_to' => $setting->invoice_serial_to,
                'footer_message' => $setting->resolvedFooterMessage(),
                'gross_sales' => $breakdown['gross_sales'],
                'vatable_sales' => $breakdown['vatable_sales'],
                'vat_exempt_sales' => $breakdown['vat_exempt_sales'],
                'zero_rated_sales' => $breakdown['zero_rated_sales'],
                'vat_amount' => $breakdown['vat_amount'],
                'vat_exemption_amount' => $breakdown['vat_exemption_amount'],
                'service_charge_enabled' => $setting->service_charge_enabled,
                'service_charge_percent' => $setting->service_charge_percent,
                'service_charge_amount' => $breakdown['service_charge_amount'],
                'service_charge_taxable' => $setting->service_charge_taxable,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_qualified_name' => $data['discount_qualified_name'] ?? null,
                'discount_id_number' => $data['discount_id_number'] ?? null,
                'discount_eligibility_method' => $data['discount_eligibility_method'] ?? null,
                'discount_eligible_item_names' => $eligibleItemNames,
                'discount_eligible_amount' => $eligibleAmount,
                'discount_amount' => $breakdown['discount_amount'],
                'discount_promo_percent' => $data['discount_promo_percent'] ?? null,
                'discount_qualified_diners' => $data['discount_qualified_diners'] ?? null,
                'discount_total_diners' => $data['discount_total_diners'] ?? null,
                'discount_notes' => $data['discount_notes'] ?? null,
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_tin' => $data['buyer_tin'] ?? null,
                'buyer_address' => $data['buyer_address'] ?? null,
                'rounding_adjustment' => $breakdown['rounding_adjustment'],
                'total_amount_due' => $breakdown['total_amount_due'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'amount_received' => $amountReceived,
                'change_amount' => bcsub($amountReceived, $breakdown['total_amount_due'], 2),
                'computed_by' => auth()->id(),
                'computed_at' => now(),
            ]);

            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'amount_received' => $amountReceived,
                'change_amount' => bcsub($amountReceived, $breakdown['total_amount_due'], 2),
                'receipt_number' => $invoiceNumber,
                'current_invoice_snapshot_id' => $snapshot->id,
                'paid_at' => now(),
            ]);
        });

        return $this->afterPayment($order->refresh());
    }

    /**
     * A slip closes the moment it is paid (Order::completeIfSettled).
     */
    private function afterPayment(Order $order): RedirectResponse
    {
        $completed = $order->completeIfSettled();

        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new DashboardStatsChanged());

        if ($completed) {
            broadcast(new KitchenUpdated());
            broadcast(new OrderUpdated($order, 'status_changed'));
        }

        return redirect()->back()->with('status', $completed
            ? __('Order :number marked as paid and completed.', ['number' => $order->orderNumber()])
            : __('Order :number marked as paid.', ['number' => $order->orderNumber()]));
    }

    /**
     * Cancel/void a specific quantity of one order item — even one already
     * prepared or served (e.g. contaminated food). The original line is
     * never deleted; an OrderItemAdjustment reversal row is added and the
     * order total is recomputed server-side. Manager approval is required
     * once the order has progressed past Pending or has been paid; staff
     * provide a manager's credentials, admins approve their own action.
     *
     * If the order was already paid, the completed payment/invoice is NOT
     * silently edited — the adjustment row (linked to the item and, via
     * the order, its payments) is the auditable basis for the refund the
     * cashier settles, and the invoice can be voided and re-finalized
     * through the existing void→repay flow when a corrected receipt is
     * needed.
     */
    public function cancelItem(\App\Http\Requests\CancelOrderItemRequest $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->order_id === $order->id, 404);

        if ($order->status === OrderStatus::Cancelled) {
            return redirect()->back()->with('error', __('Order :number is already cancelled.', ['number' => $order->orderNumber()]));
        }

        Gate::authorize('cancel', $orderItem);

        // Shared with the Kitchen Display — same approval rule, same
        // reversal row, same broadcasts. See OrderItemCanceller.
        $adjustment = OrderItemCanceller::cancel(
            $order,
            $orderItem,
            $request->validated(),
            $request->user(),
            OrderItemAdjustmentSource::OrderManagement,
        );

        return redirect()->back()->with('status', __(':item cancelled (:qty×) on order :number.', [
            'item' => $orderItem->item_name,
            'qty' => $adjustment->quantity,
            'number' => $order->orderNumber(),
        ]));
    }

    /**
     * Append one line to an order that is already open.
     *
     * This is the foundation of the "one receipt per table" rule: a party
     * that orders again later — or has fish weighed at the counter mid-meal
     * — keeps accumulating onto the same bill instead of collecting a
     * second order number. No price/total is accepted from the client; the
     * charge is re-derived server-side by OrderAppender.
     */
    public function appendItem(AppendOrderItemRequest $request, Order $order): RedirectResponse|JsonResponse
    {
        if ($reason = OrderAppender::appendBlockedReason($order)) {
            return $this->appendFailure($request, $reason);
        }

        try {
            $item = OrderAppender::append(
                $order,
                $request->lineData(),
                $request->user(),
                $request->idempotencyKey(),
            );
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return $this->appendFailure($request, collect($e->errors())->flatten()->first());
            }

            throw $e;
        }

        // The new line has to reach the kitchen board and the customer's
        // status screen straight away, exactly like a brand-new order does.
        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($order));

        if ($request->wantsJson()) {
            return response()->json(self::appendedLinePayload($item, $order), 201);
        }

        return redirect()->route('orders.show', $order)->with('status', __(':item added to order :number.', [
            'item' => $item->item_name,
            'number' => $order->orderNumber(),
        ]));
    }

    /**
     * Correct a weighed line's scale reading. A weighed line has no
     * quantity to step up or down — the only thing that can change is what
     * the scale said — and every correction carries a reason and an owner,
     * because it moves money on an order the customer may already be
     * looking at.
     */
    public function updateItemWeight(UpdateOrderItemWeightRequest $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->order_id === $order->id, 404);

        if (! $orderItem->isWeighed()) {
            return redirect()->back()->with('error', __('Only weighed lines have a weight to correct.'));
        }

        if ($reason = OrderAppender::appendBlockedReason($order)) {
            return redirect()->back()->with('error', $reason);
        }

        $data = $request->correction();

        DB::transaction(function () use ($data, $order, $orderItem, $request) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            OrderAppender::assertAppendable($locked);

            // The correction becomes revision N+1 in order_item_weighings;
            // the reading it replaces stays exactly as it was recorded.
            WeighedLineRecorder::revise($orderItem, $data, $request->user());

            $locked->recalculateTotal();
        });

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($order));

        return redirect()->back()->with('status', __('Weight updated for :item — line is now ₱:amount.', [
            'item' => $orderItem->item_name,
            'amount' => number_format((float) $orderItem->fresh()->subtotal, 2),
        ]));
    }

    /**
     * The JSON answer to "a line was added" — shared with Weigh & Order's
     * new-slip endpoint, which the same wizard calls.
     *
     * @return array<string, mixed>
     */
    public static function appendedLinePayload(OrderItem $item, Order $order): array
    {
        return [
            'item' => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'line_type' => $item->line_type->value,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
                'net_grams' => $item->netWeightGrams(),
                'amount_charged' => $item->amountCharged(),
                'cooking_surcharge' => $item->isWeighed() ? $item->cookingSurcharge() : null,
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->orderNumber(),
                'slip_label' => $order->slipLabel(),
                'total_amount' => (string) $order->fresh()->total_amount,
            ],
        ];
    }

    protected function appendFailure(AppendOrderItemRequest $request, string $reason): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $reason], 422);
        }

        return redirect()->back()->with('error', $reason);
    }

    public function voidPayment(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            return redirect()->back()
                ->with('error', __('Order :number has no paid payment to void.', ['number' => $order->orderNumber()]));
        }

        $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $order) {
            $order->update([
                'payment_status' => PaymentStatus::Voided,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
                'void_reason' => $request->string('void_reason')->toString(),
            ]);

            // Keeps the snapshot table independently queryable for the
            // "voided invoices" report metric without joining back through
            // Order — the snapshot itself stays otherwise untouched
            // (immutable), only its status is stamped.
            $order->currentInvoiceSnapshot?->update([
                'status' => InvoiceSnapshotStatus::Voided,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
            ]);

            // Void the split-payment entries riding on this invoice too —
            // the rows stay on record, but freeing their terminal
            // references lets the same card slip be re-entered when the
            // order is re-finalized after the void.
            $order->payments()
                ->where('order_invoice_snapshot_id', $order->current_invoice_snapshot_id)
                ->where('status', \App\Enums\OrderPaymentStatus::Recorded)
                ->get()
                ->each(fn ($payment) => $payment->update([
                    'status' => \App\Enums\OrderPaymentStatus::Voided,
                    'voided_by' => auth()->id(),
                    'voided_at' => now(),
                    'void_reason' => __('Invoice :number voided', ['number' => $order->receipt_number]),
                ]));
        });

        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new DashboardStatsChanged());

        return redirect()->back()->with('status', __('Payment for order :number has been voided.', ['number' => $order->orderNumber()]));
    }

    /**
     * Adds a discount that was forgotten at checkout to a bill that is
     * already paid. The corrected bill keeps the original payment date, so
     * that day's Reports come out right — see LateDiscountApplier.
     */
    public function applyLateDiscount(\App\Http\Requests\ApplyLateDiscountRequest $request, Order $order): RedirectResponse
    {
        $snapshot = \App\Services\LateDiscountApplier::apply($order, $request->validated(), $request->user());

        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new DashboardStatsChanged());

        return redirect()->back()->with('status', __('Discount added. Order :number is now ₱:total (new receipt :receipt).', [
            'number' => $order->orderNumber(),
            'total' => number_format((float) $snapshot->total_amount_due, 2),
            'receipt' => $snapshot->invoice_number,
        ]));
    }

    /**
     * Void ONE payment component (e.g. just the cash half of a card+cash
     * split) without deleting the other entries. Requires manager
     * approval. If the entry sits on the currently active invoice, that
     * invoice is no longer fully settled, so the whole payment flips to
     * Voided (existing void→repay semantics) — the cashier then
     * re-finalizes with the corrected payment set; the untouched entries'
     * rows remain on permanent record against the voided invoice.
     */
    public function voidPaymentEntry(Request $request, Order $order, \App\Models\OrderPayment $payment): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id, 404);

        if ($payment->status === \App\Enums\OrderPaymentStatus::Voided) {
            return redirect()->back()->with('error', __('This payment entry is already voided.'));
        }

        $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
            'manager_email' => ['nullable', 'email'],
            'manager_password' => ['nullable', 'string'],
        ]);

        \App\Services\CheckoutDiscountResolver::resolveApprover(
            $request->user(),
            $request->input('manager_email'),
            $request->input('manager_password'),
        );

        DB::transaction(function () use ($request, $order, $payment) {
            $payment->update([
                'status' => \App\Enums\OrderPaymentStatus::Voided,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
                'void_reason' => $request->string('void_reason')->toString(),
            ]);

            $onActiveInvoice = $order->payment_status === PaymentStatus::Paid
                && $payment->order_invoice_snapshot_id === $order->current_invoice_snapshot_id;

            if ($onActiveInvoice) {
                $order->update([
                    'payment_status' => PaymentStatus::Voided,
                    'voided_by' => auth()->id(),
                    'voided_at' => now(),
                    'void_reason' => __('Payment entry voided: :reason', ['reason' => $request->string('void_reason')->toString()]),
                ]);

                $order->currentInvoiceSnapshot?->update([
                    'status' => InvoiceSnapshotStatus::Voided,
                    'voided_at' => now(),
                    'voided_by' => auth()->id(),
                ]);
            }
        });

        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new DashboardStatsChanged());

        return redirect()->back()->with('status', __('Payment entry voided. The order can be re-finalized with the corrected payments.'));
    }

    public function receipt(Order $order): View
    {
        abort_unless($order->receipt_number, 404);

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle', 'items.quotation', 'currentInvoiceSnapshot.discounts', 'payments', 'voidedBy']);

        return view('orders.receipt', ['order' => $order, 'totals' => \App\Services\OrderTotals::for($order)]);
    }

    public function receiptPdf(Order $order): Response
    {
        abort_unless($order->receipt_number, 404);

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle', 'items.quotation', 'currentInvoiceSnapshot.discounts', 'payments', 'voidedBy']);

        $pdf = Pdf::loadView('orders.receipt-pdf', [
            'order' => $order,
            'totals' => \App\Services\OrderTotals::for($order),
        ])->setPaper('a5', 'portrait');

        // Dompdf's bundled fonts (DejaVu Sans, Helvetica, Courier, ...) have no
        // Hangul glyphs, so Korean text would render as missing-glyph boxes.
        // Register a Korean-capable font so it gets embedded in the output.
        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $fontMetrics->registerFont(
            ['family' => 'Nanum Gothic Coding', 'style' => 'normal', 'weight' => 'normal'],
            resource_path('fonts/NanumGothicCoding-Regular.ttf')
        );
        $fontMetrics->registerFont(
            ['family' => 'Nanum Gothic Coding', 'style' => 'normal', 'weight' => 'bold'],
            resource_path('fonts/NanumGothicCoding-Bold.ttf')
        );

        return $pdf->download("{$order->receipt_number}.pdf");
    }

    /**
     * The thermal-printer-formatted receipt — narrow (58mm/80mm), courier
     * monospace, auto-triggers the browser print dialog on load. Separate
     * view from `receipt()` (which targets a normal A4/Letter printer via
     * @media print) because the two need genuinely different page geometry,
     * not just different styling of the same content.
     */
    public function printReceipt(Request $request, Order $order): View
    {
        abort_unless($order->receipt_number, 404);

        $paperWidth = $request->query('paper') === '58mm' ? '58mm' : '80mm';

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle', 'items.quotation', 'currentInvoiceSnapshot.discounts', 'payments', 'voidedBy']);

        return view('orders.receipt-print', [
            'order' => $order,
            'totals' => \App\Services\OrderTotals::for($order),
            'paperWidth' => $paperWidth,
        ]);
    }

    /**
     * Kitchen order slip — a prep ticket, not a billing document: items,
     * quantities, weights, cooking styles, and special instructions, plus
     * who took the order. Deliberately carries no prices/totals, and
     * (unlike printReceipt) isn't gated on $order->receipt_number — a
     * kitchen slip needs to be reprintable the moment an order exists,
     * long before anyone's paid.
     */
    public function printKitchenSlip(Request $request, Order $order): View
    {
        $paperWidth = $request->query('paper') === '58mm' ? '58mm' : '80mm';

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle']);

        // ?paper=a4: the same 80mm slip, centred on an A4 sheet, for whatever
        // printer the device's print dialog has (Print A4, beside Direct
        // Print). ?from=order sends the user back to the order, not the Kitchen.
        $fromOrder = $request->query('from') === 'order';
        $onA4 = $request->query('paper') === 'a4';

        // On A4, more slips can share the sheet: ?with=88-1003-004,88-1003-005
        // (typed in on the page, one at a time with ?add=). Each starts in the
        // next column — the first on the left, the next on the right.
        $extraOrders = collect();
        $notFound = null;
        if ($onA4) {
            $normalize = fn ($number) => ltrim(trim((string) $number), '#');
            $numbers = collect(explode(',', (string) $request->query('with')))
                ->push($request->query('add'))
                ->map($normalize)
                ->filter()
                ->reject(fn ($number) => $number === $order->order_number)
                ->unique()
                ->values();

            $found = Order::whereIn('order_number', $numbers)
                ->with(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle'])
                ->get()
                ->keyBy('order_number');

            $missing = $numbers->reject(fn ($number) => $found->has($number));
            $notFound = $missing->isNotEmpty() ? $missing->map(fn ($number) => '#'.$number)->implode(', ') : null;
            $extraOrders = $numbers->filter(fn ($number) => $found->has($number))->map(fn ($number) => $found[$number])->values();
        }

        return view('orders.kitchen-slip-print', [
            'order' => $order,
            'paperWidth' => $paperWidth,
            'onA4' => $onA4,
            'extraOrders' => $extraOrders,
            'notFound' => $notFound,
            'fromOrder' => $fromOrder,
            'backUrl' => $fromOrder ? route('orders.show', $order) : route('kitchen.index'),
            'backLabel' => $fromOrder ? __('Back to Order') : __('Back to Kitchen'),
        ]);
    }

    /**
     * Queues a kitchen slip for the network thermal printer — same content
     * as printKitchenSlip() above, but fired at the printer directly
     * instead of opening the browser print dialog. Production has no
     * network route to the printer (it's on the resort's own LAN), so this
     * only queues the job; the `printer:bridge` process running on that
     * LAN is what actually prints it. See config/printing.php.
     */
    /**
     * Direct Print. Answers with the job to watch, so the button can stay
     * locked until that slip is actually on paper — and pressing it again
     * while one is still printing gets the same job back, not a second copy.
     */
    public function queueKitchenSlipPrint(Order $order): JsonResponse
    {
        $job = KitchenSlipQueue::queue($order);

        return response()->json([
            'queued' => true,
            'job_id' => $job->id,
            'status' => $job->status->value,
            'already_queued' => ! $job->wasRecentlyCreated,
        ]);
    }

    /**
     * Polled by the Kitchen Display while a slip is printing.
     */
    public function kitchenSlipPrintStatus(Order $order, PrinterJob $printerJob): JsonResponse
    {
        abort_unless($printerJob->order_id === $order->id, 404);

        return response()->json([
            'job_id' => $printerJob->id,
            'status' => $printerJob->status->value,
            'error_message' => $printerJob->error_message,
        ]);
    }
}
