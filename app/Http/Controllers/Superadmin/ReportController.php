<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\DiscountType;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
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

        $paidOrders = Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$start, $end]);

        $totalRevenue = (clone $paidOrders)->sum('total_amount');
        $paidOrderCount = (clone $paidOrders)->count();
        $averageOrderValue = $paidOrderCount > 0 ? $totalRevenue / $paidOrderCount : 0;

        $totalOrders = Order::whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->count();

        $cancelledOrders = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'cancelled')
            ->count();

        $comparison = $this->buildComparison($selectedDate, $selectedMonth, $range, [
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'averageOrderValue' => $averageOrderValue,
            'cancelledOrders' => $cancelledOrders,
        ]);

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
            'totalOrders' => $totalOrders,
            'averageOrderValue' => $averageOrderValue,
            'cancelledOrders' => $cancelledOrders,
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

        $discountTotals = (clone $activeSnapshots)
            ->whereNotNull('discount_type')
            ->selectRaw('discount_type, SUM(discount_amount) as total')
            ->groupBy('discount_type')
            ->pluck('total', 'discount_type');

        return [
            'netAmountCollected' => (clone $activeSnapshots)->sum('total_amount_due'),
            'vatableSales' => (clone $activeSnapshots)->sum('vatable_sales'),
            'vatExemptSales' => (clone $activeSnapshots)->sum('vat_exempt_sales'),
            'zeroRatedSales' => (clone $activeSnapshots)->sum('zero_rated_sales'),
            'vatAmount' => (clone $activeSnapshots)->sum('vat_amount'),
            'seniorDiscounts' => (float) ($discountTotals[DiscountType::SeniorCitizen->value] ?? 0),
            'pwdDiscounts' => (float) ($discountTotals[DiscountType::Pwd->value] ?? 0),
            'promoDiscounts' => (float) ($discountTotals[DiscountType::Promo->value] ?? 0),
            'serviceCharges' => (clone $activeSnapshots)->sum('service_charge_amount'),
            'voidedInvoices' => OrderInvoiceSnapshot::where('status', InvoiceSnapshotStatus::Voided)
                ->whereBetween('computed_at', [$start, $end])
                ->count(),
        ];
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

        $prevPaidOrders = Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('created_at', [$prevStart, $prevEnd]);

        $prevRevenue = (clone $prevPaidOrders)->sum('total_amount');
        $prevPaidCount = (clone $prevPaidOrders)->count();

        $previous = [
            'totalRevenue' => $prevRevenue,
            'totalOrders' => Order::whereBetween('created_at', [$prevStart, $prevEnd])->where('status', '!=', 'cancelled')->count(),
            'averageOrderValue' => $prevPaidCount > 0 ? $prevRevenue / $prevPaidCount : 0,
            'cancelledOrders' => Order::whereBetween('created_at', [$prevStart, $prevEnd])->where('status', 'cancelled')->count(),
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
