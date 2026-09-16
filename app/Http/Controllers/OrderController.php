<?php

namespace App\Http\Controllers;

use App\Enums\DiscountEligibilityMethod;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Http\Requests\AppendOrderItemRequest;
use App\Http\Requests\FinalizeOrderPaymentRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderItemWeightRequest;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\PrinterJob;
use App\Models\SpaceSession;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceNumberGenerator;
use App\Services\OrderAppender;
use App\Services\OrderCreator;
use App\Services\Printing\KitchenSlipPayloadBuilder;
use App\Services\WeighedLineRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        return view('orders.index', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'totalOrders' => $statusCounts->sum(),
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

        return view('orders.create', [
            'areas' => $areas,
            'categories' => $menuCategories,
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
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

                if (! $space) {
                    $spaceSessionId = SpaceSession::create([
                        'category_id' => $request->integer('space_category_id'),
                    ])->id;
                }
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

        return redirect()->route('orders.show', $order)
            ->with('status', __('Order created successfully.'));
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
        ]);
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

        $order->update(['status' => $request->string('status')->toString()]);

        if ($order->status->isFinal()) {
            $this->releaseOrderLocation($order);
        }

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($order));

        return redirect()->back()->with('status', __('Order :number is now :status.', [
            'number' => $order->orderNumber(),
            'status' => $order->status->label(),
        ]));
    }

    /**
     * Free up the space (or pooled session) an order was using once the
     * order reaches a final state (Completed/Cancelled), so it's ready for
     * the next customer without staff having to release it by hand.
     */
    protected function releaseOrderLocation(Order $order): void
    {
        if ($order->space && $order->space->status !== SpaceStatus::Available) {
            $order->space->setStatusWithSharedTables(SpaceStatus::Available);

            return;
        }

        if ($order->spaceSession && $order->spaceSession->status === 'active') {
            $order->spaceSession->update(['status' => 'completed', 'ended_at' => now()]);
        }
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

            broadcast(new CustomerOrderStatusUpdated($order));
            broadcast(new DashboardStatsChanged());

            return redirect()->back()->with('status', __('Order :number marked as paid.', ['number' => $order->orderNumber()]));
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

        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new DashboardStatsChanged());

        return redirect()->back()->with('status', __('Order :number marked as paid.', ['number' => $order->orderNumber()]));
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

        $data = $request->validated();

        DB::transaction(function () use ($data, $order, $orderItem, $request) {
            $orderItem->load(['adjustments']);

            $activeQuantity = $orderItem->activeQuantity();

            if ($activeQuantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => __(':item is already fully cancelled.', ['item' => $orderItem->item_name]),
                ]);
            }

            $quantity = (int) $data['quantity'];

            if ($quantity > $activeQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :count of this item can still be cancelled.', ['count' => $activeQuantity]),
                ]);
            }

            // Past Pending (already being prepared/served) or already paid:
            // a manager must authorize removing the charge.
            $needsApproval = $order->status !== OrderStatus::Pending
                || $order->payment_status === PaymentStatus::Paid;

            $approver = $needsApproval
                ? \App\Services\CheckoutDiscountResolver::resolveApprover(
                    $request->user(),
                    $data['manager_email'] ?? null,
                    $data['manager_password'] ?? null,
                )
                : null;

            // Reverse the per-unit charge, clamped so a line can never
            // reverse more than it actually charged.
            $reversed = bcmul((string) $orderItem->unit_price, (string) $quantity, 2);

            $alreadyReversed = $orderItem->reversedAmount();
            $lineGross = (string) $orderItem->subtotal;
            $maxReversible = bcsub($lineGross, $alreadyReversed, 2);
            if (bccomp($reversed, $maxReversible, 2) > 0) {
                $reversed = $maxReversible;
            }

            $order->itemAdjustments()->create([
                'order_item_id' => $orderItem->id,
                'quantity' => $quantity,
                'unit_price' => $orderItem->unit_price,
                'reversed_amount' => $reversed,
                'reason_code' => $data['reason_code'],
                'notes' => $data['notes'],
                // Served/contaminated food never restocks by default; an
                // explicit checkbox is the only way this becomes true.
                // (Recorded for the audit trail — no inventory module
                // exists yet to act on it.)
                'inventory_restored' => (bool) ($data['inventory_restored'] ?? false),
                'requested_by' => $request->user()->id,
                'approved_by' => $approver?->id,
            ]);

            // A weighed line's void also lands on its weighing record, so
            // the reading itself carries who voided it and why — the row is
            // never deleted, only marked.
            if ($orderItem->isWeighed()) {
                WeighedLineRecorder::void($orderItem, $data['notes'], $request->user());
            }

            $order->recalculateTotal();
        });

        // The kitchen sees the cancellation immediately on its live board.
        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());
        broadcast(new CustomerOrderStatusUpdated($order));

        return redirect()->back()->with('status', __(':item cancelled (:qty×) on order :number.', [
            'item' => $orderItem->item_name,
            'qty' => $data['quantity'],
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
            return response()->json([
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
                    'total_amount' => (string) $order->fresh()->total_amount,
                ],
            ], 201);
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

        return view('orders.kitchen-slip-print', [
            'order' => $order,
            'paperWidth' => $paperWidth,
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
    public function queueKitchenSlipPrint(Order $order): JsonResponse
    {
        $order->load(['area', 'spaceCategory', 'space', 'creator', 'guestSession', 'sourceQuotation', 'items.adjustments', 'items.cookingStyle']);

        PrinterJob::create([
            'type' => 'kitchen_slip',
            'order_id' => $order->id,
            'payload' => KitchenSlipPayloadBuilder::build($order),
        ]);

        return response()->json(['queued' => true]);
    }
}
