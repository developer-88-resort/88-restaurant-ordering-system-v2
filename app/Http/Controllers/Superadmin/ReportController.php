<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\DiscountType;
use App\Enums\InvoiceSnapshotStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\MassagePayment;
use App\Models\Order;
use App\Models\OrderInvoiceSnapshot;
use App\Models\OrderPayment;
use App\Models\RoomType;
use App\Models\Space;
use App\Models\SpaceCategory;
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
     * Outlets tallied on their own, outside the main payment method and room
     * charge tables that the cashiers count their drawer against. Decided by
     * the table an order sits on — never by who rang it up — so each payment
     * lands in exactly one place: a space in one of the listed categories,
     * or (archived tables from before the outlet had its own category) whose
     * name starts with one of the listed prefixes. Keyed for tests; the label
     * is the category's name as shown in Spaces.
     *
     * Days already tallied keep the rule they were tallied under. Sept 28
     * alone was tallied with every payment the resto's account created or
     * recorded — whatever the table, e.g. KR 1 — kept out of the main tally
     * (₱61,197.73), so within `accounts.from`…`accounts.before` those still
     * land in the resto's section. Days before it were tallied with them in
     * main, and from Sept 29 on only the table counts.
     *
     * @var array<string, array{categories: list<string>, space_prefixes: list<string>, accounts?: array{emails: list<string>, from: string, before: string}}>
     */
    public const SEPARATE_SECTIONS = [
        // The Korean resto's old "Korean resto -R1 n" tables sat inside KUBO
        // until they were archived for the KOLD-R tables.
        'korean_oldtb' => [
            'categories' => ['Korean-OLDTB'],
            'space_prefixes' => ['Korean resto'],
            'accounts' => ['emails' => ['resto@88hotspring.com'], 'from' => '2026-09-28 00:00:00', 'before' => '2026-09-29 00:00:00'],
        ],
        'minibar' => ['categories' => ['MINIBAR-MN'], 'space_prefixes' => []],
    ];

    /**
     * Breakfast is tallied on its own, whatever table it was served at: an
     * order with any item from these menu categories is counted in the
     * Breakfast section and left out of every other section (main tables
     * and the outlets above). Decided per order, since one payment covers
     * the whole order; an order that also has other food is flagged on the
     * report so it can be checked.
     *
     * @var list<string>
     */
    public const BREAKFAST_MENU_CATEGORIES = \App\Support\Breakfast::MENU_CATEGORIES;

    /**
     * Breakfast is tallied apart from this moment on (by when the payment was
     * taken). Days before it were tallied with breakfast inside its table's
     * section and keep that.
     */
    public const BREAKFAST_FROM = \App\Support\Breakfast::FROM;

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

        // Each separately tallied outlet gets its own section; the main tables
        // are everything else. Scopes run on order_payments joined to orders.
        // A takeout order has no space, and a NULL would be silently dropped
        // by "NOT IN", so it's kept in main explicitly.
        $breakfastMatch = self::breakfastMatch();

        $separateSections = collect(self::SEPARATE_SECTIONS)->map(function (array $section, string $key) use ($start, $end, $breakfastMatch) {
            $categories = SpaceCategory::whereIn('name', $section['categories'])->get(['id', 'name']);
            $spaceIds = Space::withTrashed()
                ->where(function ($query) use ($categories, $section) {
                    $query->whereIn('category_id', $categories->pluck('id'));
                    foreach ($section['space_prefixes'] as $prefix) {
                        $query->orWhere('name', 'like', $prefix.'%');
                    }
                })
                ->pluck('id')
                ->all();

            $accountMatch = $this->accountMatch($section['accounts'] ?? null);

            $scope = fn ($query) => $query->where(function ($query) use ($spaceIds, $accountMatch) {
                $query->whereIn('orders.space_id', $spaceIds);
                if ($accountMatch) {
                    $query->orWhere($accountMatch);
                }
            })->whereNot($breakfastMatch);
            $methods = $this->buildPaymentMethodTotals($start, $end, $scope);
            $roomCharges = $this->buildRoomCharges($start, $end, $scope);
            $spotCharges = self::spotChargeSummary($this->buildSpotChargeRows($start, $end, $scope));

            return [
                'key' => $key,
                'label' => $categories->pluck('name')->implode(' / ') ?: $section['categories'][0],
                'spaceIds' => $spaceIds,
                'accountMatch' => $accountMatch,
                'paymentMethods' => $methods['rows'],
                'paymentMethodsTotal' => $methods['total'],
                'paymentMethodsCount' => $methods['count'],
                'roomCharges' => $roomCharges['rows'],
                'roomChargesByRoom' => $roomCharges['byRoom'],
                'legacyRoomCharges' => $roomCharges['legacyRoomCharges'],
                'legacyRoomChargesTotal' => $roomCharges['legacyRoomChargesTotal'],
                'roomChargesTotal' => $roomCharges['total'],
                'roomChargesCount' => $roomCharges['count'],
                'spotCharges' => $spotCharges,
                'grandTotal' => $methods['total'] + $roomCharges['total'] + $spotCharges['total'],
            ];
        })->filter(fn (array $section) => $section['spaceIds'] !== [] || $section['accountMatch'] !== null);

        $separateSpaceIds = $separateSections->flatMap(fn (array $section) => $section['spaceIds'])->all();
        $accountMatches = $separateSections->pluck('accountMatch')->filter();
        $mainScope = function ($query) use ($separateSpaceIds, $accountMatches, $breakfastMatch) {
            $query->where(fn ($query) => $query
                ->whereNull('orders.space_id')
                ->orWhereNotIn('orders.space_id', $separateSpaceIds));

            foreach ($accountMatches as $match) {
                $query->whereNot($match);
            }

            $query->whereNot($breakfastMatch);
        };

        $paymentMethods = $this->buildPaymentMethodTotals($start, $end, $mainScope);
        $roomCharges = $this->buildRoomCharges($start, $end, $mainScope);
        $mainSpotRows = $this->buildSpotChargeRows($start, $end, $mainScope);
        $mainAreaBreakdown = $this->buildAreaBreakdown($start, $end, $mainScope);

        // Breakfast, from whichever table: its own section, after the outlets.
        $separateSections = $separateSections->push($this->buildBreakfastSection($start, $end, $breakfastMatch));

        // Massage has its own orders and payments (massage_payments), shown
        // as one more separately counted section, after the outlets above.
        $separateSections = $separateSections->push($this->buildMassageSection($start, $end));

        // Every room charge of the period, whatever table or department it
        // came from, as the one figure front desk's postings tally against.
        $allRoomCharges = $this->buildAllRoomCharges($roomCharges['rows'], $separateSections);

        // Locker and Kubo charges, likewise: every outlet, per locker / kubo.
        $allSpotCharges = $this->buildAllSpotCharges($mainSpotRows, $separateSections);

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

        // Sales by Area on the same basis as Net Collected: the active
        // invoices issued in the period (computed_at), each at what was
        // actually due after discounts — so the column adds up to Net
        // Collected to the centavo. It used to sum the orders' gross
        // total_amount by when they were opened, and an inner join dropped
        // takeout orders (no area) altogether, so it never tallied.
        // total_revenue is the net; gross and less (discounts and the
        // like) are shown beside it.
        $areaSales = DB::table('order_invoice_snapshots')
            ->join('orders', 'orders.id', '=', 'order_invoice_snapshots.order_id')
            ->leftJoin('areas', 'areas.id', '=', 'orders.area_id')
            ->where('order_invoice_snapshots.status', InvoiceSnapshotStatus::Active->value)
            ->whereBetween('order_invoice_snapshots.computed_at', [$start, $end])
            ->select(
                DB::raw("COALESCE(areas.name, '".__('Takeout (no table)')."') as area_name"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(order_invoice_snapshots.gross_sales) as gross_sales'),
                DB::raw('SUM(order_invoice_snapshots.total_amount_due) as total_revenue'),
            )
            ->groupBy('area_name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($row) {
                $row->gross_sales = (float) $row->gross_sales;
                $row->total_revenue = (float) $row->total_revenue;
                $row->less = round($row->gross_sales - $row->total_revenue, 2);

                return $row;
            });

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
            'roomChargesByRoom' => $roomCharges['byRoom'],
            'legacyRoomCharges' => $roomCharges['legacyRoomCharges'],
            'legacyRoomChargesTotal' => $roomCharges['legacyRoomChargesTotal'],
            'roomChargesTotal' => $roomCharges['total'],
            'roomChargesCount' => $roomCharges['count'],
            'roomChargesByMode' => $roomCharges['byMode'],
            'allRoomCharges' => $allRoomCharges,
            'allSpotCharges' => $allSpotCharges,
            'mainAreaBreakdown' => $mainAreaBreakdown,
            'mainSpotCharges' => self::mainSpotCharges($mainSpotRows),
            'separateSections' => $separateSections,
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
     * "Paid from $from up to (not including) $before, and the order was created or the payment recorded
     * by one of these accounts" — see SEPARATE_SECTIONS. COALESCE keeps it a
     * plain true/false: a guest order has no creator, and a NULL inside the
     * main tally's NOT (...) would silently drop that payment. Null when the
     * section has no account rule or none of its accounts exist.
     *
     * @param  array{emails: list<string>, from: string, before: string}|null  $accounts
     */
    protected function accountMatch(?array $accounts): ?\Closure
    {
        $userIds = $accounts ? User::whereIn('email', $accounts['emails'])->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        if ($userIds === []) {
            return null;
        }

        $list = implode(',', $userIds);

        return fn ($query) => $query
            ->where('order_payments.received_at', '>=', Carbon::parse($accounts['from']))
            ->where('order_payments.received_at', '<', Carbon::parse($accounts['before']))
            ->where(fn ($query) => $query
                ->whereRaw("COALESCE(orders.created_by, 0) IN ({$list})")
                ->orWhereRaw("COALESCE(order_payments.received_by, 0) IN ({$list})"));
    }

    /**
     * "This order has a breakfast item" — for a query on orders (joined with
     * order_payments), see BREAKFAST_MENU_CATEGORIES.
     */
    protected static function breakfastMatch(): \Closure
    {
        return fn ($query) => $query->where('order_payments.received_at', '>=', Carbon::parse(self::BREAKFAST_FROM))->whereExists(fn ($items) => $items
            ->select(DB::raw(1))
            ->from('order_items')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('menu_categories.name', self::BREAKFAST_MENU_CATEGORIES));
    }

    /**
     * Breakfast's money in the same shape as an outlet section — collected
     * per method, room / locker / kubo charges, Grand Total — plus the
     * orders that also carry other food (counted here whole), so they can be
     * checked.
     *
     * @return array<string, mixed>
     */
    protected function buildBreakfastSection(Carbon $start, Carbon $end, \Closure $breakfastMatch): array
    {
        $methods = $this->buildPaymentMethodTotals($start, $end, $breakfastMatch);
        $roomCharges = $this->buildRoomCharges($start, $end, $breakfastMatch);
        $spotCharges = self::spotChargeSummary($this->buildSpotChargeRows($start, $end, $breakfastMatch));

        // Others is a breakfast order's complimentary: nothing was collected,
        // so it gets its own line and stays out of Collected and the Grand
        // Total. Older Others (before breakfast had its own section) never
        // reach this section.
        [$complimentaryRows, $collectedRows] = $methods['rows']->partition(fn ($row) => $row->payment_method === PaymentMethod::Other->value);
        $collectedTotal = (float) $collectedRows->sum('total_amount');
        $collectedRows = $this->withPercentOfTotal($collectedRows->values(), 'total_amount', $collectedTotal);

        $orders = $this->breakfastOrders($start, $end);

        return [
            'key' => 'breakfast',
            'label' => __('Breakfast'),
            'description' => __('Orders with a breakfast item, from any table — counted here and in no other tally. A breakfast order is complimentary as a whole, regular menu added to it included.'),
            'spaceIds' => [],
            'accountMatch' => null,
            'paymentMethods' => $collectedRows,
            'paymentMethodsTotal' => $collectedTotal,
            'paymentMethodsCount' => (int) $collectedRows->sum('entry_count'),
            'complimentaryTotal' => (float) $complimentaryRows->sum('total_amount'),
            'complimentaryCount' => (int) $complimentaryRows->sum('entry_count'),
            'roomCharges' => $roomCharges['rows'],
            'roomChargesByRoom' => $roomCharges['byRoom'],
            'legacyRoomCharges' => $roomCharges['legacyRoomCharges'],
            'legacyRoomChargesTotal' => $roomCharges['legacyRoomChargesTotal'],
            'roomChargesTotal' => $roomCharges['total'],
            'roomChargesCount' => $roomCharges['count'],
            'spotCharges' => $spotCharges,
            'grandTotal' => $collectedTotal + $roomCharges['total'] + $spotCharges['total'],
            'breakfastOrders' => $orders,
            'breakfastMenuTotal' => (float) $orders->sum('breakfastTotal'),
            'regularMenuTotal' => (float) $orders->sum('regularTotal'),
        ];
    }

    /**
     * Every breakfast order settled in the period, line by line: which lines
     * are breakfast and which are regular menu added to it (counted here too,
     * marked "Regular menu"), and how it was settled.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function breakfastOrders(Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        $from = Carbon::parse(self::BREAKFAST_FROM);
        $breakfastIds = \App\Support\Breakfast::menuItemIds();
        if ($breakfastIds->isEmpty()) {
            return collect();
        }

        $inPeriod = fn ($query) => $query
            ->where('status', OrderPaymentStatus::Recorded->value)
            ->whereBetween('received_at', [$start, $end])
            ->where('received_at', '>=', $from);

        return Order::query()
            ->with(['items.adjustments', 'space', 'area', 'spaceCategory', 'payments' => $inPeriod])
            ->whereNull('merged_into_order_id')
            ->whereHas('payments', $inPeriod)
            ->whereHas('items', fn ($query) => $query->whereIn('menu_item_id', $breakfastIds))
            ->orderBy('id')
            ->get()
            ->map(function (Order $order) use ($breakfastIds) {
                $lines = $order->items
                    ->filter(fn ($item) => ! $item->isFullyCancelled())
                    ->map(fn ($item) => (object) [
                        'name' => $item->item_name,
                        'quantity' => $item->activeQuantity(),
                        'amount' => (float) $item->lineTotalNet(),
                        'breakfast' => \App\Support\Breakfast::isBreakfastItem($item, $breakfastIds),
                    ])
                    ->sortByDesc('breakfast')
                    ->values();

                return (object) [
                    'order' => $order,
                    'number' => $order->orderNumber(),
                    'location' => $order->locationLabel(),
                    'paidAt' => $order->payments->max('received_at'),
                    'lines' => $lines,
                    'breakfastTotal' => (float) $lines->where('breakfast', true)->sum('amount'),
                    'regularTotal' => (float) $lines->where('breakfast', false)->sum('amount'),
                    'settledWith' => $order->payments->map(fn ($payment) => $payment->displayLabel().' ₱'.number_format((float) $payment->amount, 2))->implode(' · '),
                ];
            });
    }

    /**
     * The Massage department's money, in the same shape as an outlet section
     * (method lines with every method at ₱0 too, room charges on their own,
     * grand total), so the page and the PDF show it the same way.
     *
     * @return array<string, mixed>
     */
    protected function buildMassageSection(Carbon $start, Carbon $end): array
    {
        $payments = MassagePayment::with(['order', 'receivedBy', 'room.roomType'])
            ->where('status', OrderPaymentStatus::Recorded->value)
            ->whereBetween('received_at', [$start, $end])
            ->orderBy('received_at')
            ->get();

        [$charged, $collected] = $payments->partition(fn (MassagePayment $payment) => $payment->payment_method->isChargeToAccount());
        [$spotRows, $roomCharges] = $charged->partition(fn (MassagePayment $payment) => $payment->payment_method->isSpotCharge());
        $spotCharges = self::spotChargeSummary($spotRows->values());

        $methods = collect(PaymentMethod::cases())
            ->reject(fn (PaymentMethod $method) => $method->isChargeToAccount())
            ->map(function (PaymentMethod $method) use ($collected) {
                $rows = $collected->filter(fn (MassagePayment $payment) => $payment->payment_method === $method);

                return (object) [
                    'payment_method' => $method->value,
                    'method_label' => $method->label(),
                    'entry_count' => $rows->count(),
                    'total_amount' => (float) $rows->sum('amount'),
                ];
            })
            // "Other" only when older massage payments used it.
            ->reject(fn ($row) => $row->payment_method === PaymentMethod::Other->value && $row->entry_count === 0)
            ->sortByDesc('total_amount')
            ->values();

        $surchargeTotal = (float) $collected->sum('surcharge_amount');
        if ($surchargeTotal > 0) {
            $methods = $methods->push(self::surchargeRow($collected->where('surcharge_amount', '>', 0)->count(), $surchargeTotal))->values();
        }
        $methodsTotal = (float) $collected->sum('amount') + $surchargeTotal;
        $roomChargesTotal = (float) $roomCharges->sum('amount');
        $roomGroups = self::roomChargeGroups($roomCharges);

        return [
            'key' => 'massage',
            'label' => __('Massage'),
            'description' => __('Massage orders, counted separately from the restaurant.'),
            'orderRoute' => 'massage.orders.show',
            'spaceIds' => [],
            'accountMatch' => null,
            'paymentMethods' => $methods,
            'paymentMethodsTotal' => $methodsTotal,
            'paymentMethodsCount' => $collected->count(),
            'roomCharges' => $roomCharges->values(),
            'roomChargesByRoom' => $roomGroups['byRoom'],
            'legacyRoomCharges' => $roomGroups['legacyRoomCharges'],
            'legacyRoomChargesTotal' => $roomGroups['legacyRoomChargesTotal'],
            'roomChargesTotal' => $roomChargesTotal,
            'roomChargesCount' => $roomCharges->count(),
            'spotCharges' => $spotCharges,
            'grandTotal' => $methodsTotal + $roomChargesTotal + $spotCharges['total'],
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
            ->whereNotIn('order_payments.payment_method', self::chargeMethodValues())
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
        // "Other" is no longer offered at checkout, so it only shows when
        // older payments in the period were recorded that way.
        $unused = collect(PaymentMethod::selectable())
            ->reject(fn (PaymentMethod $method) => $method->isChargeToAccount())
            ->reject(fn (PaymentMethod $method) => $rows->contains('payment_method', $method->value))
            ->map(fn (PaymentMethod $method) => (object) [
                'payment_method' => $method->value,
                'entry_count' => 0,
                'total_amount' => 0.0,
                'method_label' => $method->label(),
            ]);
        $rows = $rows->concat($unused)->values();
        $count = (int) $rows->sum('entry_count');

        // A foreign card's surcharge: taken by the card terminal on top of
        // the bill, so it's money received (in the Grand Total, so the card
        // total tallies with the terminal) but not sales.
        $surcharge = DB::table('order_payments')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.status', OrderPaymentStatus::Recorded->value)
            ->whereNull('orders.merged_into_order_id')
            ->where($scope)
            ->whereBetween('order_payments.received_at', [$start, $end])
            ->where('order_payments.surcharge_amount', '>', 0)
            ->selectRaw('COUNT(*) as entry_count, SUM(order_payments.surcharge_amount) as total_amount')
            ->first();
        if ($surcharge && (float) $surcharge->total_amount > 0) {
            $rows->push(self::surchargeRow((int) $surcharge->entry_count, (float) $surcharge->total_amount));
        }

        $total = (float) $rows->sum('total_amount');

        return [
            'rows' => $this->withPercentOfTotal($rows, 'total_amount', $total),
            'total' => $total,
            'count' => $count,
        ];
    }

    /**
     * The "Foreign card surcharge" line of a Collected table.
     */
    protected static function surchargeRow(int $count, float $total): object
    {
        return (object) [
            'payment_method' => 'card_surcharge',
            'entry_count' => $count,
            'total_amount' => $total,
            'method_label' => __('Foreign card surcharge'),
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
        // first. Room charges picked from the room list have none — the
        // guest settles at front desk checkout — and neither do the oldest
        // ones, from before the mode was asked for.
        $byMode = $rows
            ->groupBy(fn (OrderPayment $payment) => $payment->settled_via?->value ?? '')
            ->map(fn ($payments, $mode) => (object) [
                'mode' => $mode,
                'label' => PaymentMethod::tryFrom($mode)?->label() ?? __('To be settled at front desk'),
                'entry_count' => $payments->count(),
                'total_amount' => (float) $payments->sum('amount'),
            ])
            ->sortByDesc('total_amount')
            ->values();

        return [
            'rows' => $rows,
            'byMode' => $byMode,
            ...self::roomChargeGroups($rows),
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
        ];
    }

    /**
     * All room charges of the period in one place — the restaurant's main
     * tables, each separately tallied outlet (Korean-OLDTB, Minibar…) and
     * Massage — because the front desk posts them all to the guest's folio
     * and their total is what tallies against the front desk's postings for
     * the day. Per outlet, then per room with a subtotal each.
     *
     * Only regroups what the sections already counted: no new rows, no
     * change to any section's own totals.
     *
     * @param  \Illuminate\Support\Collection<int, OrderPayment>  $mainRows
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    protected function buildAllRoomCharges(\Illuminate\Support\Collection $mainRows, \Illuminate\Support\Collection $sections): array
    {
        $outlets = collect([['label' => __('Main tables (IHAWAN)'), 'rows' => $mainRows]])
            ->concat($sections->map(fn (array $section) => ['label' => $section['label'], 'rows' => $section['roomCharges']]));

        $rows = $outlets->flatMap(fn (array $outlet) => $outlet['rows'])->values();

        return [
            'byOutlet' => $outlets->map(fn (array $outlet) => (object) [
                'label' => $outlet['label'],
                'count' => $outlet['rows']->count(),
                'total' => (float) $outlet['rows']->sum('amount'),
            ])->values(),
            ...self::roomChargeGroups($rows),
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
        ];
    }

    /**
     * Where a tally's money came from, per area (KR, KUBO, TABLE…, and
     * Takeout for orders with no table): what was collected, by method,
     * what was charged to a room / locker / kubo, and the two together —
     * which is that area's line in Sales by Area. Same payments and rules as
     * buildPaymentMethodTotals and buildRoomCharges, only regrouped, so the
     * Collected column adds up to the tally's Grand Total.
     *
     * @param  callable(\Illuminate\Database\Query\Builder): mixed  $scope
     * @return array{rows: \Illuminate\Support\Collection<int, object>, collected: float, charged: float, total: float}
     */
    protected function buildAreaBreakdown(Carbon $start, Carbon $end, callable $scope): array
    {
        $takeout = __('Takeout (no table)');

        $rows = DB::table('order_payments')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->leftJoin('areas', 'areas.id', '=', 'orders.area_id')
            ->where('order_payments.status', OrderPaymentStatus::Recorded->value)
            ->whereNull('orders.merged_into_order_id')
            ->where($scope)
            ->whereBetween('order_payments.received_at', [$start, $end])
            ->select(
                DB::raw("COALESCE(areas.name, '{$takeout}') as area_name"),
                'order_payments.payment_method',
                DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                DB::raw('SUM(order_payments.amount) as total_amount'),
                DB::raw('SUM(order_payments.surcharge_amount) as surcharge_amount'),
            )
            ->groupBy('area_name', 'order_payments.payment_method')
            ->get()
            ->groupBy('area_name')
            ->map(function ($lines, $area) {
                $parts = $lines->map(fn ($line) => (object) [
                    'method' => PaymentMethod::tryFrom($line->payment_method),
                    'label' => PaymentMethod::tryFrom($line->payment_method)?->label() ?? $line->payment_method,
                    'amount' => (float) $line->total_amount,
                ])->sortByDesc('amount')->values();
                [$charged, $collected] = $parts->partition(fn ($part) => $part->method?->isChargeToAccount() ?? false);
                $surcharge = (float) $lines->sum('surcharge_amount');
                if ($surcharge > 0) {
                    // Collected, not sales: Total sales stays the area's Sales by Area.
                    $collected = $collected->push((object) ['method' => null, 'label' => __('Foreign card surcharge'), 'amount' => $surcharge]);
                }

                return (object) [
                    'area_name' => $area,
                    'collected' => (float) $collected->sum('amount'),
                    'collectedParts' => $collected->values(),
                    'charged' => (float) $charged->sum('amount'),
                    'chargedParts' => $charged->values(),
                    'total' => (float) $parts->sum('amount'),
                ];
            })
            // Biggest first; Takeout last.
            ->sortBy(fn ($row) => [$row->area_name === $takeout ? 1 : 0, -$row->total])
            ->values();

        return [
            'rows' => $rows,
            'collected' => (float) $rows->sum('collected'),
            'charged' => (float) $rows->sum('charged'),
            'total' => (float) $rows->sum('total'),
        ];
    }

    /**
     * Room, Locker and Kubo Charge — charged now, settled at the front desk,
     * so never counted as money in the drawer.
     *
     * @return list<string>
     */
    protected static function chargeMethodValues(): array
    {
        return array_values(array_map(
            fn (PaymentMethod $method) => $method->value,
            array_filter(PaymentMethod::cases(), fn (PaymentMethod $method) => $method->isChargeToAccount()),
        ));
    }

    /**
     * The Locker and Kubo Charges of the period for these orders — same rules
     * as buildRoomCharges: recorded, by when taken, merged-away slips left out.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $scope
     * @return \Illuminate\Support\Collection<int, OrderPayment>
     */
    protected function buildSpotChargeRows(Carbon $start, Carbon $end, callable $scope): \Illuminate\Support\Collection
    {
        return OrderPayment::with(['order.area', 'order.space', 'order.spaceCategory', 'receivedBy'])
            ->select('order_payments.*')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->whereIn('order_payments.payment_method', array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::spotMethods()))
            ->where('order_payments.status', OrderPaymentStatus::Recorded->value)
            ->whereBetween('order_payments.received_at', [$start, $end])
            ->whereNull('orders.merged_into_order_id')
            ->where($scope)
            ->orderBy('order_payments.received_at')
            ->get();
    }

    /**
     * Locker and Kubo charges summed: a line per method (₱0 included, so a
     * section always shows both), and per locker / kubo with a subtotal each,
     * lockers first then kubos, by number.
     *
     * @param  \Illuminate\Support\Collection<int, OrderPayment|MassagePayment>  $rows
     * @return array{rows: \Illuminate\Support\Collection, byMethod: \Illuminate\Support\Collection<int, object>, bySpot: \Illuminate\Support\Collection<int, object>, total: float, count: int}
     */
    public static function spotChargeSummary(\Illuminate\Support\Collection $rows): array
    {
        $methodOrder = array_flip(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::spotMethods()));

        $byMethod = collect(PaymentMethod::spotMethods())->map(function (PaymentMethod $method) use ($rows) {
            $mine = $rows->filter(fn ($payment) => $payment->payment_method === $method);

            return (object) [
                'method' => $method->value,
                'label' => $method->label(),
                'count' => $mine->count(),
                'total' => (float) $mine->sum('amount'),
            ];
        })->values();

        $bySpot = $rows
            ->groupBy(fn ($payment) => $payment->payment_method->value.':'.($payment->spot_no ?? ''))
            ->map(function ($payments) {
                $first = $payments->first();

                return (object) [
                    'label' => $first->spotLabel() ?? ($first->charged_to ?: $first->payment_method->label()),
                    'method' => $first->payment_method->value,
                    'spot_no' => $first->spot_no,
                    'payments' => $payments->sortBy('received_at')->values(),
                    'count' => $payments->count(),
                    'total' => (float) $payments->sum('amount'),
                ];
            })
            ->sort(fn ($a, $b) => [$methodOrder[$a->method] ?? PHP_INT_MAX, $a->spot_no ?? PHP_INT_MAX] <=> [$methodOrder[$b->method] ?? PHP_INT_MAX, $b->spot_no ?? PHP_INT_MAX])
            ->values();

        return [
            'rows' => $rows,
            'byMethod' => $byMethod,
            'bySpot' => $bySpot,
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
        ];
    }

    /**
     * Every Locker and Kubo Charge of the period, all outlets together (main
     * tables, each separately tallied outlet, Massage) — laid out exactly
     * like All Room Charges, one block per method: per outlet, then per
     * locker / kubo with a subtotal each. Only regroups what the sections
     * counted.
     *
     * @param  \Illuminate\Support\Collection<int, OrderPayment>  $mainRows
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $sections
     * @return array<string, array<string, mixed>> keyed by method value
     */
    protected function buildAllSpotCharges(\Illuminate\Support\Collection $mainRows, \Illuminate\Support\Collection $sections): array
    {
        $outlets = collect([['label' => __('Main tables (IHAWAN)'), 'rows' => $mainRows]])
            ->concat($sections->map(fn (array $section) => ['label' => $section['label'], 'rows' => $section['spotCharges']['rows']]));

        return collect(PaymentMethod::spotMethods())->mapWithKeys(function (PaymentMethod $method) use ($outlets) {
            $ofMethod = fn ($rows) => $rows->filter(fn ($payment) => $payment->payment_method === $method)->values();
            $all = self::spotChargeSummary($outlets->flatMap(fn (array $outlet) => $ofMethod($outlet['rows']))->values());

            return [$method->value => [
                'method' => $method->value,
                'label' => $method->label(),
                'spotName' => $method->spotName(),
                'byOutlet' => $outlets->map(fn (array $outlet) => (object) [
                    'label' => $outlet['label'],
                    'count' => $ofMethod($outlet['rows'])->count(),
                    'total' => (float) $ofMethod($outlet['rows'])->sum('amount'),
                ])->values(),
                'bySpot' => $all['bySpot'],
                'total' => $all['total'],
                'count' => $all['count'],
            ]];
        })->all();
    }

    /**
     * The main tables' own Locker and Kubo charges, one block per method —
     * the twin of "Room Charges — Main tables (IHAWAN)".
     *
     * @param  \Illuminate\Support\Collection<int, OrderPayment>  $mainRows
     * @return array<string, array<string, mixed>> keyed by method value
     */
    protected static function mainSpotCharges(\Illuminate\Support\Collection $mainRows): array
    {
        return collect(PaymentMethod::spotMethods())->mapWithKeys(function (PaymentMethod $method) use ($mainRows) {
            $summary = self::spotChargeSummary($mainRows->filter(fn ($payment) => $payment->payment_method === $method)->values());

            return [$method->value => [
                'label' => $method->label(),
                'spotName' => $method->spotName(),
                'bySpot' => $summary['bySpot'],
                'total' => $summary['total'],
                'count' => $summary['count'],
            ]];
        })->all();
    }

    /**
     * Room charges per room, so front desk can tick each room off against
     * what they posted to it: grouped by room type in front desk order
     * (VR → … → EXR), then room number, each with its subtotal. Charges from
     * before rooms were picked from a list only have the free text staff
     * typed, so they're listed apart as "legacy".
     *
     * @param  \Illuminate\Support\Collection<int, OrderPayment|MassagePayment>  $rows  restaurant or Massage room charges
     * @return array{byRoom: \Illuminate\Support\Collection<int, object>, legacyRoomCharges: \Illuminate\Support\Collection<int, OrderPayment>, legacyRoomChargesTotal: float}
     */
    public static function roomChargeGroups(\Illuminate\Support\Collection $rows): array
    {
        $typeOrder = RoomType::pluck('sort_order', 'code');
        $typeNames = RoomType::pluck('name', 'code');

        $byRoom = $rows
            ->filter(fn ($payment) => $payment->room_no !== null)
            ->groupBy(fn ($payment) => $payment->roomLabel())
            ->map(function ($payments, $label) use ($typeNames) {
                $first = $payments->first();

                return (object) [
                    'label' => $label,
                    'room_no' => $first->room_no,
                    'type_code' => $first->room_type_code,
                    'type_name' => $typeNames[$first->room_type_code] ?? null,
                    'payments' => $payments->sortBy('received_at')->values(),
                    'count' => $payments->count(),
                    'total' => (float) $payments->sum('amount'),
                ];
            })
            ->sort(fn ($a, $b) => [$typeOrder[$a->type_code] ?? PHP_INT_MAX, $a->room_no] <=> [$typeOrder[$b->type_code] ?? PHP_INT_MAX, $b->room_no])
            ->values();

        $legacy = $rows->filter(fn ($payment) => $payment->room_no === null)->values();

        return [
            'byRoom' => $byRoom,
            'legacyRoomCharges' => $legacy,
            'legacyRoomChargesTotal' => (float) $legacy->sum('amount'),
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
