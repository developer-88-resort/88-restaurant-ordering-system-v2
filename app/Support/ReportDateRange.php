<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Resolves the Today/This Week/This Month/All Time/pick-a-date filter used
 * by any report-style page. Originally lived only inside
 * Superadmin\ReportController; extracted so the Weigh Log page can offer
 * the exact same filter behavior without a second, drifting copy of the
 * same precedence rules.
 *
 * Precedence: an explicit ?date= wins over ?month=, which wins over
 * ?range= (default 'month'). Malformed or future dates/months are silently
 * ignored, falling back to the range pills, rather than erroring.
 */
class ReportDateRange
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly string $rangeLabel,
        public readonly string $range,
        public readonly ?Carbon $selectedMonth,
        public readonly ?Carbon $selectedDate,
        public readonly string $calendarMonth,
    ) {}

    public static function resolve(Request $request): self
    {
        $selectedDate = self::parseSelectedDate($request->string('date')->toString());
        $selectedMonth = $selectedDate ? null : self::parseSelectedMonth($request->string('month')->toString());
        $range = $request->string('range')->toString() ?: 'month';

        if ($selectedDate) {
            $start = $selectedDate->copy()->startOfDay();
            $end = $selectedDate->copy()->endOfDay();
            $rangeLabel = $selectedDate->format('F j, Y');
        } elseif ($selectedMonth) {
            $start = $selectedMonth->copy()->startOfMonth();
            $end = $selectedMonth->copy()->endOfMonth();
            $rangeLabel = $selectedMonth->format('F Y');
        } else {
            [$start, $end, $rangeLabel] = match ($range) {
                'today' => [now()->startOfDay(), now()->endOfDay(), 'Today'],
                'week' => [now()->startOfWeek(), now()->endOfWeek(), 'This Week'],
                'all' => [Carbon::parse(Order::min('created_at') ?? now()), now()->endOfDay(), 'All Time'],
                default => [now()->startOfMonth(), now()->endOfMonth(), 'This Month'],
            };
        }

        return new self(
            start: $start,
            end: $end,
            rangeLabel: $rangeLabel,
            range: $range,
            selectedMonth: $selectedMonth,
            selectedDate: $selectedDate,
            calendarMonth: ($selectedDate ?? $selectedMonth ?? now())->format('Y-m'),
        );
    }

    /**
     * The month picker posts a "Y-m" string (e.g. "2026-06"). Anything
     * malformed, or a future month (nothing to report yet), is silently
     * ignored so the page falls back to the quick-range pills instead of
     * erroring.
     */
    protected static function parseSelectedMonth(string $month): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        } catch (\Exception) {
            return null;
        }

        return $parsed->greaterThan(now()) ? null : $parsed;
    }

    /**
     * The calendar posts a "Y-m-d" string for a specific day. Same
     * fallback rules as the month: malformed or a future date is ignored
     * rather than erroring.
     */
    protected static function parseSelectedDate(string $date): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Exception) {
            return null;
        }

        return $parsed->greaterThan(now()->endOfDay()) ? null : $parsed;
    }
}
