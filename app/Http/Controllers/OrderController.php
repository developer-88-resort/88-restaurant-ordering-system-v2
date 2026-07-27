<?php

namespace App\Http\Controllers;

use App\Enums\DiscountEligibilityMethod;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SpaceStatus;
use App\Events\CustomerOrderStatusUpdated;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Http\Requests\FinalizeOrderPaymentRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Models\SpaceSession;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceNumberGenerator;
use App\Services\OrderCreator;
use Barryvdh\DomPDF\Facade\Pdf;
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
            ->with(['menuItems' => fn ($query) => $query->with('variants')->whereIn('availability_status', ['available', 'seasonal'])->orderBy('sort_order')->orderBy('name')])
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
                'area_id' => $isTakeout ? null : $request->integer('area_id'),
                'space_category_id' => $isTakeout ? null : $request->integer('space_category_id'),
                'space_id' => $spaceId,
                'space_session_id' => $spaceSessionId,
                'created_by' => auth()->id(),
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
        $order->load(['area', 'spaceCategory', 'space', 'creator', 'items', 'currentInvoiceSnapshot']);

        return view('orders.show', ['order' => $order, 'setting' => Setting::current()]);
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
        if ($order->payment_status === PaymentStatus::Paid) {
            return redirect()->back()
                ->with('error', __('Order :number is already paid.', [
                    'number' => $order->orderNumber(),
                ]));
        }

        $data = $request->validated();

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
        });

        broadcast(new CustomerOrderStatusUpdated($order));
        broadcast(new DashboardStatsChanged());

        return redirect()->back()->with('status', __('Payment for order :number has been voided.', ['number' => $order->orderNumber()]));
    }

    public function receipt(Order $order): View
    {
        abort_unless($order->receipt_number, 404);

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'items', 'currentInvoiceSnapshot', 'voidedBy']);

        return view('orders.receipt', ['order' => $order]);
    }

    public function receiptPdf(Order $order): Response
    {
        abort_unless($order->receipt_number, 404);

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'items', 'currentInvoiceSnapshot', 'voidedBy']);

        $pdf = Pdf::loadView('orders.receipt-pdf', ['order' => $order])->setPaper('a5', 'portrait');

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
     * Print-optimized receipt for thermal printers (58mm/80mm), rendered
     * as plain HTML and printed via the browser's native window.print() —
     * no ESC/POS, no print agent, works with any printer already
     * installed as a normal OS printer. Paper width is overridable per
     * request via ?paper=58mm|80mm since the client's exact printer model
     * wasn't known at build time; see config('receipts.default_paper_width').
     */
    public function printView(Request $request, Order $order): View
    {
        abort_unless($order->receipt_number, 404);

        $order->load(['area', 'spaceCategory', 'space', 'creator', 'items', 'currentInvoiceSnapshot', 'voidedBy']);

        $paperWidth = in_array($request->query('paper'), ['58mm', '80mm'], true)
            ? $request->query('paper')
            : config('receipts.default_paper_width', '80mm');

        return view('receipts.print', ['order' => $order, 'paperWidth' => $paperWidth]);
    }
}
