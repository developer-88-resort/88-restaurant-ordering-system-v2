<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\DiscountType;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderPayment;
use App\Models\Space;
use App\Models\User;
use App\Support\ReportDateRange;
use App\Support\WeighedLineQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Korean resto sales are tallied on their own, outside the main payment
     * method and room charge tables. Its tables (R1 1 … R1 13) were set up
     * as spaces inside the KUBO area, and its account also rings up orders
     * on other tables (KR 1), so mixing them into the main Grand Total made
     * it stop matching what the cashiers tally against. A payment belongs to
     * the resto if its order sits on a "Korean resto …" table (matched by
     * name, so new tables are picked up too), or the resto's account created
     * the order, or the resto's account recorded the payment — every peso
     * that account touches is kept out of the main tally.
     */
    public const SEPARATE_SPACE_PREFIX = 'Korean resto';

    /** @var list<string> */
    public const SEPARATE_USER_EMAILS = ['resto@88hotspring.com'];

    public function index(Request $request): View
    {
        return view('superadmin.reports.index', $this->buildReportData($request));
    }

    public function pdf(Request $request): Response
    {
        $data = $this->buildReportData($request);

        $pdf = Pdf::loadView('superadmin.reports.report-pdf', $data)->setPaper('a4', 'portrait');

        // Same Korean-capable font registration used for order receipts —
        // dompdf's bundled fonts have no Hangul glyphs.
        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $fontMetrics->registerFont(
            ['family' => 'Nanum Gothic Coding', 'style' => 'normal', 'weight' => 'normal'],
            resource_path('fonts/NanumGothicCoding-Regular.ttf')
        );
        $fontMetrics->registerFont(
            ['family' => 'Nanum Gothic Coding', 'style' => 'normal', 'weight' => 'bold'],
            resource_path('fonts/NanumGothicCoding-Bold.ttf')
        );

        $filename = 'Sales-Report-'.str_replace(' ', '-', $data['rangeLabel']).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReportData(Request $request): array
    {
        $dateRange = ReportDateRange::resolve($request);
        $start = $dateRange->start;
        $end = $dateRange->end;
        $rangeLabel = $dateRange->rangeLabel;
        $range = $dateRange->range;
        $selectedMonth = $dateRange->selectedMonth;
        $selectedDate = $dateRange->selectedDate;

        $paidOrders = self::countableOrders()
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$start, $end]);

        $totalRevenue = (clone $paidOrders)->sum('total_amount');

        // Every order metric below names the exact population it counts.
        // "Total Orders" used to mean every non-cancelled order while the
        // average beside it divided by the paid ones only, so the two
        // numbers silently disagreed (3 orders, average over 2).
        $paidOrderCount = (clone $paidOrders)->count();
        $averageOrderValue = $paidOrderCount > 0 ? $totalRevenue / $paidOrderCount : 0;

        $openOrderCount = self::countableOrders()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->count();

        $cancelledOrders = self::countableOrders()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'cancelled')
            ->count();

        $comparison = $this->buildComparison($selectedDate, $selectedMonth, $range, [
            'totalRevenue' => $totalRevenue,
            'paidOrderCount' => $paidOrderCount,
            'averageOrderValue' => $averageOrderValue,
            'openOrderCount' => $openOrderCount,
            'cancelledOrders' => $cancelledOrders,
        ]);

        // Korean resto payments come out of the main tables and get their
        // own section. Both scopes run on order_payments joined to orders.
        // A takeout order has no space, a guest order no creator, and a NULL
        // there would be silently dropped by "NOT IN", so NULLs are kept
        // explicitly — the main scope is exactly the separate scope negated.
        $separateSpaceIds = Space::where('name', 'like', self::SEPARATE_SPACE_PREFIX.'%')->pluck('id')->all();
        $separateUserIds = User::whereIn('email', self::SEPARATE_USER_EMAILS)->pluck('id')->all();
        $mainScope = fn ($query) => $query
            ->where(fn ($query) => $query->whereNull('orders.space_id')->orWhereNotIn('orders.space_id', $separateSpaceIds))
            ->where(fn ($query) => $query->whereNull('orders.created_by')->orWhereNotIn('orders.created_by', $separateUserIds))
            ->where(fn ($query) => $query->whereNull('order_payments.received_by')->orWhereNotIn('order_payments.received_by', $separateUserIds));
        $separateScope = fn ($query) => $query->where(fn ($query) => $query
            ->whereIn('orders.space_id', $separateSpaceIds)
            ->orWhereIn('orders.created_by', $separateUserIds)
            ->orWhereIn('order_payments.received_by', $separateUserIds));

        $paymentMethods = $this->buildPaymentMethodTotals($start, $end, $mainScope);
        $roomCharges = $this->buildRoomCharges($start, $end, $mainScope);

        $separateMethods = $this->buildPaymentMethodTotals($start, $end, $separateScope);
        $separateRoomCharges = $this->buildRoomCharges($start, $end, $separateScope);
        $separateSales = [
            'label' => self::SEPARATE_SPACE_PREFIX,
            'enabled' => $separateSpaceIds !== [] || $separateUserIds !== [],
            'paymentMethods' => $separateMethods['rows'],
            'paymentMethodsTotal' => $separateMethods['total'],
            'paymentMethodsCount' => $separateMethods['count'],
            'roomCharges' => $separateRoomCharges['rows'],
            'roomChargesTotal' => $separateRoomCharges['total'],
            'roomChargesCount' => $separateRoomCharges['count'],
            'grandTotal' => $separateMethods['total'] + $separateRoomCharges['total'],
        ];

        $bestSellers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                'order_items.item_name',
                'order_items.line_type',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                // GREATEST() is MySQL-only and the test suite runs on
                // SQLite — a portable CASE WHEN clamp works on both.
                DB::raw('SUM(CASE WHEN (COALESCE(order_items.weight_grams, 0) - COALESCE(order_items.tare_grams, 0)) > 0 THEN (COALESCE(order_items.weight_grams, 0) - COALESCE(order_items.tare_grams, 0)) ELSE 0 END) as total_net_grams'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
            )
            // Grouped by (item_name, line_type) rather than item_name alone
            // so a menu item that changed pricing type over its history
            // never merges a kg-based row with a piece-based one into one
            // ambiguous number — see Reports quantity/unit fix.
            ->groupBy('order_items.item_name', 'order_items.line_type')
            ->orderByDesc('total_qty')
            ->limit(8)
            ->get();

        $categorySales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                DB::raw("COALESCE(menu_categories.name, 'Uncategorized') as category_name"),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
            )
            ->groupBy('category_name')
            ->orderByDesc('total_revenue')
            ->get();

        $dailySales = DB::table('orders')
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereNull('merged_into_order_id')
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as sale_date'),
                DB::raw('SUM(total_amount) as revenue'),
            )
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $areaSales = DB::table('orders')
            ->join('areas', 'areas.id', '=', 'orders.area_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereNull('orders.merged_into_order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                'areas.name as area_name',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(orders.total_amount) as total_revenue'),
            )
            ->groupBy('areas.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Each table's percent column must sum to 100% on its own — dividing
        // by the shared order-level $totalRevenue (which includes tax/
        // service charge and doesn't match a SUM(order_items.subtotal)
        // basis) previously made these overshoot 100% (confirmed: 116%).
        $bestSellers = $this->withPercentOfTotal($bestSellers, 'total_revenue', (float) $bestSellers->sum('total_revenue'));
        $categorySales = $this->withPercentOfTotal($categorySales, 'total_revenue', (float) $categorySales->sum('total_revenue'));
        $areaSales = $this->withPercentOfTotal($areaSales, 'total_revenue', (float) $areaSales->sum('total_revenue'));

        $taxSummary = $this->buildTaxSummary($start, $end);
        $weighedItems = $this->buildWeighedItemsSummary($start, $end);

        return [
            'range' => $range,
            'selectedMonth' => $selectedMonth?->format('Y-m'),
            'selectedDate' => $selectedDate?->format('Y-m-d'),
            'calendarMonth' => ($selectedDate ?? $selectedMonth ?? now())->format('Y-m'),
            'rangeLabel' => $rangeLabel,
            'totalRevenue' => $totalRevenue,
            'paidOrderCount' => $paidOrderCount,
            'averageOrderValue' => $averageOrderValue,
            'openOrderCount' => $openOrderCount,
            'cancelledOrders' => $cancelledOrders,
            'paymentMethods' => $paymentMethods['rows'],
            'paymentMethodsTotal' => $paymentMethods['total'],
            'paymentMethodsCount' => $paymentMethods['count'],
            'roomCharges' => $roomCharges['rows'],
            'roomChargesTotal' => $roomCharges['total'],
            'roomChargesCount' => $roomCharges['count'],
            'roomChargesByMode' => $roomCharges['byMode'],
            'separateSales' => $separateSales,
            'comparison' => $comparison,
            'bestSellers' => $bestSellers,
            'weighedItems' => $weighedItems,
            'categorySales' => $categorySales,
            'dailySales' => $dailySales,
            'areaSales' => $areaSales,
            'taxSummary' => $taxSummary,
        ];
    }

    /**
     * Every order metric on this page counts through here. A slip that was
     * merged onto another table's slip (OrderSlipTransferrer) keeps its row
     * for the audit trail but gave its lines away, so counting it again
     * next to the slip that absorbed it would report one sale as two.
     */
    public static function countableOrders(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()->whereNull('merged_into_order_id');
    }

    /**
     * Cash vs GCash vs card, so the drawer can actually be counted at
     * closing. Room Charge is left out: that money isn't collected here, it
     * goes onto the guest's room and the front desk collects it, so it has
     * its own section (buildRoomCharges) and stays out of this Grand Total,
     * which the cashiers tally their drawer against. Built from the payment rows rather than the orders' totals:
     * one order can be settled with several methods at once (a ₱795 cash +
     * ₱820 GCash split used to show up as a single ₱1,615 lump), and only
     * the payment row knows which money came in through which channel.
     *
     * Voided entries drop out, and the basis is `received_at` — when the
     * money was actually taken — rather than when the order was opened.
     *
     * @param  callable(\Illuminate\Database\Query\Builder): mixed  $scope  narrows which orders count (main vs separately-reported spaces)
     * @return array{rows: \Illuminate\Support\Collection<int, object>, total: float, count: int}
     */
    protected function buildPaymentMethodTotals(Carbon $start, Carbon $end, callable $scope): array
    {
        $rows = DB::table('order_payments')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.status', OrderPaymentStatus::Recorded->value)
            ->where('order_payments.payment_method', '!=', PaymentMethod::RoomCharge->value)
            ->whereNull('orders.merged_into_order_id')
            ->where($scope)
            ->whereBetween('order_payments.received_at', [$start, $end])
            ->select(
                'order_payments.payment_method',
                DB::raw('COUNT(*) as entry_count'),
                DB::raw('SUM(order_payments.amount) as total_amount'),
            )
            ->groupBy('order_payments.payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) {
                $method = PaymentMethod::tryFrom($row->payment_method);

                $row->method_label = $method?->label() ?? $row->payment_method;
                $row->total_amount = (float) $row->total_amount;
                $row->entry_count = (int) $row->entry_count;

                return $row;
            });

        // Every collected method gets a line, even at ₱0, so a method nobody
        // used this period reads as zero rather than missing. Methods with
        // money come first, biggest first; the rest follow in checkout order.
        $unused = collect(PaymentMethod::cases())
            ->reject(fn (PaymentMethod $method) => $method === PaymentMethod::RoomCharge)
            ->reject(fn (PaymentMethod $method) => $rows->contains('payment_method', $method->value))
            ->map(fn (PaymentMethod $method) => (object) [
                'payment_method' => $method->value,
                'entry_count' => 0,
                'total_amount' => 0.0,
                'method_label' => $method->label(),
            ]);
        $rows = $rows->concat($unused)->values();

        $total = (float) $rows->sum('total_amount');

        return [
            'rows' => $this->withPercentOfTotal($rows, 'total_amount', $total),
            'total' => $total,
            'count' => (int) $rows->sum('entry_count'),
        ];
    }

    /**
     * Room Charge on its own: not money in the drawer, so it's kept out of
     * the payment-method table and its Grand Total. Only payments recorded
     * as "Room Charge" at checkout count here, each listed with the
     * room/guest reference staff typed, for the front desk to bill against.
     *
     * Same rules as the method totals: recorded (not voided) payments,
     * by the date they were taken, merged-away slips left out.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $scope  narrows which orders count (main vs separately-reported spaces)
     * @return array{rows: \Illuminate\Support\Collection<int, OrderPayment>, total: float}
     */
    protected function buildRoomCharges(Carbon $start, Carbon $end, callable $scope): array
    {
        // Joined (not whereHas) so the scope can read both the order's and
        // the payment's columns, same as buildPaymentMethodTotals.
        $rows = OrderPayment::with(['order.area', 'order.space', 'order.spaceCategory', 'receivedBy'])
            ->select('order_payments.*')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.payment_method', PaymentMethod::RoomCharge->value)
            ->where('order_payments.status', OrderPaymentStatus::Recorded->value)
            ->whereBetween('order_payments.received_at', [$start, $end])
            ->whereNull('orders.merged_into_order_id')
            ->where($scope)
            ->orderBy('order_payments.received_at')
            ->get();

        // A subtotal per mode the room charges are paid through, biggest
        // first. Room charges from before the mode was asked for have none
        // and are grouped as "Not specified".
        $byMode = $rows
            ->groupBy(fn (OrderPayment $payment) => $payment->settled_via?->value ?? '')
            ->map(fn ($payments, $mode) => (object) [
                'mode' => $mode,
                'label' => PaymentMethod::tryFrom($mode)?->label() ?? __('Not specified'),
                'entry_count' => $payments->count(),
                'total_amount' => (float) $payments->sum('amount'),
            ])
            ->sortByDesc('total_amount')
            ->values();

        return [
            'rows' => $rows,
            'byMode' => $byMode,
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
        ];
    }

    /**
     * BIR tax/discount aggregates — summed directly from the already-
     * persisted OrderInvoiceSnapshot rows (the InvoiceCalculator's output,
     * frozen at payment time) rather than recomputed here, so this stays
     * consistent with whatever was actually shown on each invoice. Filtered
     * by `computed_at` (when the invoice was issued/paid), not `created_at`
     * (when the order was placed) — a different date basis than the
     * existing cards above, which is a known, pre-existing quirk left
     * as-is rather than changed by this feature.
     *
     * @return array<string, mixed>
     */
    protected function buildTaxSummary(Carbon $start, Carbon $end): array
    {
        $activeSnapshots = OrderInvoiceSnapshot::where('status', InvoiceSnapshotStatus::Active)
            ->whereBetween('computed_at', [$start, $end]);

        $byRule = $this->discountLineTotals($start, $end);

        $sumWhere = fn (callable $matches) => (float) $byRule
            ->filter($matches)
            ->sum('total_amount');

        return [
            'netAmountCollected' => (clone $activeSnapshots)->sum('total_amount_due'),
            'vatableSales' => (clone $activeSnapshots)->sum('vatable_sales'),
            'vatExemptSales' => (clone $activeSnapshots)->sum('vat_exempt_sales'),
            'zeroRatedSales' => (clone $activeSnapshots)->sum('zero_rated_sales'),
            'vatAmount' => (clone $activeSnapshots)->sum('vat_amount'),
            // One bucket per kind of discount the resort actually gives, and
            // they are mutually exclusive — every peso taken off a bill lands
            // in exactly one of them, and `totalDiscounts` is their sum.
            'seniorDiscounts' => $sumWhere(fn ($row) => $row->statutory_type === DiscountType::SeniorCitizen->value),
            'pwdDiscounts' => $sumWhere(fn ($row) => $row->statutory_type === DiscountType::Pwd->value),
            'customPercentDiscounts' => $sumWhere(fn ($row) => $row->statutory_type === null && $row->rule_code === 'custom_percent'),
            // Straight peso discounts ("less ₱500"). These were invisible
            // here until 2026-09-25: the snapshot's single discount_type
            // column had no value to hold them, so they were summed into
            // nothing while still reducing the bill.
            'amountDiscounts' => $sumWhere(fn ($row) => $row->statutory_type === null && $row->calculation_mode === 'fixed'),
            // Retired rules and anything seeded later — kept as a catch-all
            // so the buckets always add up to the total.
            'otherDiscounts' => $sumWhere(fn ($row) => $row->statutory_type === null
                && $row->calculation_mode === 'percent'
                && $row->rule_code !== 'custom_percent'),
            'totalDiscounts' => (float) $byRule->sum('total_amount'),
            'discountsByRule' => $byRule->values(),
            'serviceCharges' => (clone $activeSnapshots)->sum('service_charge_amount'),
            'voidedInvoices' => OrderInvoiceSnapshot::where('status', InvoiceSnapshotStatus::Voided)
                ->whereBetween('computed_at', [$start, $end])
                ->count(),
        ];
    }

    /**
     * Every discount that actually landed on an invoice in the period, one
     * row per rule — read from the same frozen order_invoice_discounts
     * lines the receipt prints, so the report and the customer's copy can
     * never disagree.
     *
     * Older invoices issued before the multi-discount shape carry no lines
     * of their own, only the snapshot's legacy discount_type/discount_amount
     * pair; those are folded in afterwards so their history does not vanish
     * from the report.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function discountLineTotals(Carbon $start, Carbon $end)
    {
        $rows = DB::table('order_invoice_discounts')
            ->join('order_invoice_snapshots', 'order_invoice_snapshots.id', '=', 'order_invoice_discounts.order_invoice_snapshot_id')
            ->where('order_invoice_snapshots.status', InvoiceSnapshotStatus::Active->value)
            ->whereBetween('order_invoice_snapshots.computed_at', [$start, $end])
            ->select(
                'order_invoice_discounts.rule_name',
                'order_invoice_discounts.rule_code',
                'order_invoice_discounts.statutory_type',
                'order_invoice_discounts.calculation_mode',
                DB::raw('COUNT(*) as times_used'),
                DB::raw('SUM(order_invoice_discounts.calculated_amount) as total_amount'),
            )
            ->groupBy(
                'order_invoice_discounts.rule_name',
                'order_invoice_discounts.rule_code',
                'order_invoice_discounts.statutory_type',
                'order_invoice_discounts.calculation_mode',
            )
            ->get();

        $legacy = DB::table('order_invoice_snapshots')
            ->where('status', InvoiceSnapshotStatus::Active->value)
            ->whereBetween('computed_at', [$start, $end])
            ->whereNotNull('discount_type')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('order_invoice_discounts')
                ->whereColumn('order_invoice_discounts.order_invoice_snapshot_id', 'order_invoice_snapshots.id'))
            ->select(
                'discount_type',
                DB::raw('COUNT(*) as times_used'),
                DB::raw('SUM(discount_amount) as total_amount'),
            )
            ->groupBy('discount_type')
            ->get()
            ->map(fn ($row) => (object) [
                'rule_name' => (DiscountType::tryFrom($row->discount_type)?->label() ?? $row->discount_type).' '.__('(legacy)'),
                'rule_code' => $row->discount_type,
                'statutory_type' => $row->discount_type === DiscountType::Promo->value ? null : $row->discount_type,
                'calculation_mode' => 'percent',
                'times_used' => (int) $row->times_used,
                'total_amount' => (float) $row->total_amount,
            ]);

        return $rows
            ->map(function ($row) {
                $row->times_used = (int) $row->times_used;
                $row->total_amount = (float) $row->total_amount;

                return $row;
            })
            ->concat($legacy)
            ->sortByDesc('total_amount');
    }

    /**
     * Per-item weighed-goods summary — kg sold, revenue, average rate, and
     * how far off the scale readings ran from what the reference rate
     * implied. Built on the same App\Support\WeighedLineQuery base join
     * (each weighed order_item to its own latest order_item_weighings
     * revision) that the Weighed Lines tab uses, so the two can never
     * disagree about what a "current" weighed line looks like. This is a
     * revenue rollup, so — same as every other card on this page — it's
     * scoped to paid orders and excludes voided lines; the Weighed Lines
     * tab itself is an operational ledger with no such restriction, so its
     * unfiltered totals will include unpaid/still-open orders this rollup
     * does not.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function buildWeighedItemsSummary(Carbon $start, Carbon $end)
    {
        return WeighedLineQuery::base()
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNull('w.voided_at')
            ->select(
                'order_items.item_name',
                // Carried through so the Blade view can deep-link each row
                // into the Weighed Lines tab pre-filtered to this item —
                // grouped alongside item_name (not instead of it) so a
                // renamed item's older, differently-named history doesn't
                // silently merge into whatever the item is called today.
                'order_items.menu_item_id',
                // "lines" is a reserved word in MySQL (LOAD DATA ... LINES
                // TERMINATED BY) and DB::raw() isn't quoted by Laravel, so
                // an unquoted `as lines` alias is a syntax error on MySQL
                // even though SQLite (the test DB) accepts it fine.
                DB::raw('COUNT(*) as total_lines'),
                // "/ 1000.0" (not "/ 1000"): SQLite truncates integer/integer
                // division toward zero — 1800/1000 silently came out as 1,
                // not 1.8 — while MySQL's "/" never truncates either operand
                // is an integer. A float-literal divisor forces real division
                // on both, the same class of MySQL-vs-SQLite gap as the
                // GREATEST()/reserved-word fixes above.
                DB::raw('SUM(COALESCE(w.net_grams, CASE WHEN (order_items.weight_grams - order_items.tare_grams) > 0 THEN (order_items.weight_grams - order_items.tare_grams) ELSE 0 END)) / 1000.0 as total_kg'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('AVG(COALESCE(w.reference_price_per_kilo, order_items.price_per_kilo_snapshot)) as avg_rate_per_kilo'),
                DB::raw('SUM(w.variance_amount) as variance_total'),
            )
            ->groupBy('order_items.item_name', 'order_items.menu_item_id')
            ->orderByDesc('total_kg')
            ->get();
    }

    /**
     * Attaches a `percent` (share of $total, 0–100) to each row of a
     * DB::table() result set — used so the breakdown tables can show each
     * line's contribution at a glance, the way a "real" business report
     * would, instead of just a bare revenue figure.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function withPercentOfTotal($rows, string $field, float $total)
    {
        return $rows->map(function ($row) use ($field, $total) {
            $row->percent = $total > 0 ? ($row->{$field} / $total) * 100 : 0;

            return $row;
        });
    }

    /**
     * Compares the current period's key stats against the immediately
     * preceding period of the same kind (yesterday, last week, last
     * month, ...) so the dashboard can show a "+12% vs last month"-style
     * trend the way a normal business report would. "All Time" has no
     * prior period to compare against, so it's skipped entirely.
     *
     * @param  array<string, float|int>  $current
     * @return array<string, array{percent: ?float, label: string}>|null
     */
    protected function buildComparison(?Carbon $selectedDate, ?Carbon $selectedMonth, string $range, array $current): ?array
    {
        [$prevStart, $prevEnd, $label] = match (true) {
            (bool) $selectedDate => [
                $selectedDate->copy()->subDay()->startOfDay(),
                $selectedDate->copy()->subDay()->endOfDay(),
                __('vs previous day'),
            ],
            (bool) $selectedMonth => [
                $selectedMonth->copy()->subMonthNoOverflow()->startOfMonth(),
                $selectedMonth->copy()->subMonthNoOverflow()->endOfMonth(),
                __('vs previous month'),
            ],
            $range === 'today' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), __('vs yesterday')],
            $range === 'week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek(), __('vs last week')],
            $range === 'month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), __('vs last month')],
            default => [null, null, null],
        };

        if (! $prevStart) {
            return null;
        }

        $prevPaidOrders = self::countableOrders()
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$prevStart, $prevEnd]);

        $prevRevenue = (clone $prevPaidOrders)->sum('total_amount');
        $prevPaidCount = (clone $prevPaidOrders)->count();

        $previous = [
            'totalRevenue' => $prevRevenue,
            'paidOrderCount' => $prevPaidCount,
            'averageOrderValue' => $prevPaidCount > 0 ? $prevRevenue / $prevPaidCount : 0,
            'openOrderCount' => self::countableOrders()
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->where('status', '!=', 'cancelled')
                ->where('payment_status', '!=', PaymentStatus::Paid)
                ->count(),
            'cancelledOrders' => self::countableOrders()
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->where('status', 'cancelled')
                ->count(),
        ];

        $comparison = [];
        foreach ($current as $key => $value) {
            $comparison[$key] = [
                'percent' => $this->percentChange($value, $previous[$key]),
                'label' => $label,
            ];
        }

        return $comparison;
    }

    /**
     * Null means "no prior data to compare against" (shown as "New" in
     * the UI) rather than a misleading +/-infinity percentage.
     */
    protected function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? null : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
