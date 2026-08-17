<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\OrderItemConfirmationStatus;
use App\Enums\PricingType;
use App\Enums\WeighEntryMode;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\ReportDateRange;
use App\Support\WeighedLineQuery;
use App\Support\WeighedOrderSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Weighed Lines tab of Reports (formerly the standalone /weigh/log
 * page): a per-line ledger of every weighed order item — what the scale
 * said, what was charged, and how far the two sat apart. One row per
 * weighed line, showing its CURRENT state (the highest revision on
 * order_item_weighings) — corrections replace the row shown here rather
 * than adding a second one. The full "billed X, corrected to Y" history
 * stays in Audit Logs (App\Support\WeighAudit's weigh_in_edited events),
 * not duplicated here.
 *
 * Shares its base join (App\Support\WeighedLineQuery) with
 * ReportController::buildWeighedItemsSummary() — the Overview tab's rollup
 * — so the two can never disagree about what a "current" weighed line is.
 */
class WeighLogController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $dateRange = ReportDateRange::resolve($request);

        $rows = $this->filteredQuery($request, $dateRange)
            ->orderByDesc('w.weighed_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($row) => $this->decorate($row));

        return Inertia::render('Weigh/Log', [
            'filters' => $this->currentFilters($request),
            'range' => $dateRange->range,
            'selectedMonth' => $dateRange->selectedMonth?->format('Y-m'),
            'selectedDate' => $dateRange->selectedDate?->format('Y-m-d'),
            'calendarMonth' => $dateRange->calendarMonth,
            'rangeLabel' => $dateRange->rangeLabel,
            'items' => MenuItem::where('pricing_type', PricingType::PerKilo)
                ->orderBy('name')
                ->get(['id', 'name']),
            'weighedByUsers' => User::whereIn('id', DB::table('order_item_weighings')
                ->whereNotNull('weighed_by_user_id')
                ->distinct()
                ->pluck('weighed_by_user_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'channels' => collect(WeighEntryMode::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'statuses' => $this->statusOptions(),
            'totals' => $this->footerTotals($request, $dateRange),
            'logs' => $rows,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $dateRange = ReportDateRange::resolve($request);
        $rows = $this->filteredQuery($request, $dateRange)->orderByDesc('w.weighed_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                __('Date & Time'), __('Item'), __('Weight (kg)'), __('Rate/kg'),
                __('Expected'), __('Charged'), __('Variance (₱)'), __('Variance (%)'),
                __('Order #'), __('Table/Location'), __('Weighed By'), __('Channel'),
                __('Status'), __('Void Reason'),
            ]);

            foreach ($rows as $row) {
                $decorated = $this->decorate($row);
                fputcsv($out, [
                    $decorated['weighed_at'], $decorated['item_name'], $decorated['kg'],
                    $decorated['reference_price_per_kilo'], $decorated['computed_amount'],
                    $decorated['amount_charged'], $decorated['variance_amount'], $decorated['variance_percent'],
                    $decorated['order_number'], $decorated['space_name'], $decorated['weighed_by_name'],
                    $decorated['entry_mode_label'], $decorated['status_label'], $decorated['void_reason'],
                ]);
            }

            fclose($out);
        }, 'weighed-lines-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request): Response
    {
        $dateRange = ReportDateRange::resolve($request);
        $rows = $this->filteredQuery($request, $dateRange)->orderByDesc('w.weighed_at')->get()
            ->map(fn ($row) => $this->decorate($row));

        $pdf = Pdf::loadView('weigh.log-pdf', [
            'rows' => $rows,
            'rangeLabel' => $dateRange->rangeLabel,
            'totals' => $this->footerTotals($request, $dateRange),
        ])->setPaper('a4', 'landscape');

        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $fontMetrics->registerFont(
            ['family' => 'Nanum Gothic Coding', 'style' => 'normal', 'weight' => 'normal'],
            resource_path('fonts/NanumGothicCoding-Regular.ttf')
        );
        $fontMetrics->registerFont(
            ['family' => 'Nanum Gothic Coding', 'style' => 'normal', 'weight' => 'bold'],
            resource_path('fonts/NanumGothicCoding-Bold.ttf')
        );

        return $pdf->download('Weighed-Lines-'.str_replace(' ', '-', $dateRange->rangeLabel).'.pdf');
    }

    /**
     * Shared by index/exportCsv/exportPdf so the three views of the same
     * filtered set can never disagree.
     */
    protected function filteredQuery(Request $request, ReportDateRange $dateRange): Builder
    {
        $query = WeighedLineQuery::base()
            ->whereBetween('w.weighed_at', [$dateRange->start, $dateRange->end])
            ->select([
                'order_items.id as order_item_id',
                'order_items.item_name',
                'order_items.menu_item_id',
                'order_items.confirmation_status',
                'orders.id as order_id',
                'orders.order_number',
                'spaces.name as space_name',
                'w.id as weighing_id',
                'w.net_grams',
                'w.reference_price_per_kilo',
                'w.amount_charged',
                'w.computed_amount',
                'w.variance_amount',
                'w.variance_percent',
                'w.entry_mode',
                'w.weighed_at',
                'w.weighed_by_user_id',
                'weighed_by.name as weighed_by_name',
                'w.voided_at',
                'w.void_reason',
                'w.revision',
            ]);

        $query->when($request->filled('item_id'), fn ($q) => $q->where('order_items.menu_item_id', $request->integer('item_id')));
        $query->when($request->filled('weighed_by'), fn ($q) => $q->where('w.weighed_by_user_id', $request->integer('weighed_by')));
        $query->when($request->filled('channel'), fn ($q) => $q->where('w.entry_mode', $request->string('channel')->toString()));

        $status = $request->string('status')->toString();
        $query->when($status !== '', function ($q) use ($status) {
            match ($status) {
                'voided' => $q->whereNotNull('w.voided_at'),
                'pending_confirmation' => $q->whereNull('w.voided_at')->where('order_items.confirmation_status', OrderItemConfirmationStatus::PendingCustomer->value),
                'rejected' => $q->whereNull('w.voided_at')->where('order_items.confirmation_status', OrderItemConfirmationStatus::Rejected->value),
                'active' => $q->whereNull('w.voided_at')->where(function ($sub) {
                    $sub->whereNull('order_items.confirmation_status')
                        ->orWhere('order_items.confirmation_status', OrderItemConfirmationStatus::Confirmed->value);
                }),
                default => null,
            };
        });

        // Default: voided lines hidden unless the "Show voided" toggle is on.
        $query->when($status === '' && ! $request->boolean('show_voided'), fn ($q) => $q->whereNull('w.voided_at'));

        return $query;
    }

    protected function footerTotals(Request $request, ReportDateRange $dateRange): array
    {
        // filteredQuery() already carries a ->select() of ~22 plain columns
        // for the row listing; selectRaw() APPENDS to that instead of
        // replacing it, mixing plain columns with aggregates and no GROUP
        // BY — MySQL rejects that (SQLite is lenient, so tests missed it).
        // ->select() replaces the column list; ->reorder() drops the
        // inherited ORDER BY, which is meaningless on an aggregate row.
        $totals = (clone $this->filteredQuery($request, $dateRange))
            ->reorder()
            ->select(
                DB::raw('COUNT(*) as total_lines'),
                DB::raw('SUM(w.net_grams) as total_grams'),
                DB::raw('SUM(w.amount_charged) as total_charged'),
                DB::raw('SUM(w.variance_amount) as total_variance'),
            )
            ->first();

        return [
            'total_lines' => (int) ($totals->total_lines ?? 0),
            'total_kg' => round((float) ($totals->total_grams ?? 0) / 1000, 3),
            'total_charged' => (float) ($totals->total_charged ?? 0),
            'total_variance' => (float) ($totals->total_variance ?? 0),
        ];
    }

    /**
     * Shapes one raw joined row into everything the table/CSV/PDF need to
     * display — status derivation, unit conversion, and the per-row
     * variance-tolerance highlight (read from WeighedOrderSettings, never a
     * hardcoded ₱/% figure).
     *
     * @return array<string, mixed>
     */
    protected function decorate(object $row): array
    {
        $status = $this->statusFor($row);
        $entryMode = $row->entry_mode ? WeighEntryMode::from($row->entry_mode) : null;

        $overTolerance = false;
        if ($row->computed_amount !== null && $row->variance_amount !== null) {
            $tolerance = WeighedOrderSettings::current()->varianceAllowanceFor((string) $row->computed_amount);
            $overTolerance = bccomp(ltrim((string) $row->variance_amount, '-'), $tolerance, 2) > 0;
        }

        return [
            'order_item_id' => $row->order_item_id,
            'item_name' => $row->item_name,
            'weighed_at' => $row->weighed_at ? \Illuminate\Support\Carbon::parse($row->weighed_at)->format('M d, Y g:i A') : null,
            'net_grams' => $row->net_grams,
            'kg' => $row->net_grams !== null ? number_format($row->net_grams / 1000, 3) : null,
            'reference_price_per_kilo' => $row->reference_price_per_kilo,
            'computed_amount' => $row->computed_amount,
            'amount_charged' => $row->amount_charged,
            'variance_amount' => $row->variance_amount,
            'variance_percent' => $row->variance_percent,
            'over_tolerance' => $overTolerance,
            'order_id' => $row->order_id,
            'order_number' => $row->order_number,
            'space_name' => $row->space_name,
            'weighed_by_name' => $row->weighed_by_name,
            'entry_mode' => $row->entry_mode,
            'entry_mode_label' => $entryMode?->label(),
            'status' => $status,
            'status_label' => $this->statusOptions()[$status] ?? Str::headline($status),
            'void_reason' => $row->void_reason,
        ];
    }

    protected function statusFor(object $row): string
    {
        return match (true) {
            $row->voided_at !== null => 'voided',
            $row->confirmation_status === OrderItemConfirmationStatus::PendingCustomer->value => 'pending_confirmation',
            $row->confirmation_status === OrderItemConfirmationStatus::Rejected->value => 'rejected',
            default => 'active',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        return [
            'active' => __('Active'),
            'pending_confirmation' => __('Awaiting confirmation'),
            'rejected' => __('Rejected'),
            'voided' => __('Voided'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentFilters(Request $request): array
    {
        return [
            'item_id' => $request->input('item_id'),
            'weighed_by' => $request->input('weighed_by'),
            'channel' => $request->input('channel'),
            'status' => $request->input('status'),
            'show_voided' => $request->boolean('show_voided'),
        ];
    }
}
