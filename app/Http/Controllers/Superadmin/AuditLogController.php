<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogPresenter;
use App\Support\ReportDateRange;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $events = (array) $request->input('events', []);

        // The Today/Week/Month/All-Time/pick-a-date control (shared with
        // Reports) only engages when it's actually been used — unlike
        // Reports, Audit Logs has no "this month by default" restriction,
        // so an untouched page must show everything, not silently default
        // to ReportDateRange's own internal 'month' fallback.
        $dateFilterActive = $request->filled('range') || $request->filled('month') || $request->filled('date');
        $dateRange = $dateFilterActive ? ReportDateRange::resolve($request) : null;

        $logs = Activity::with('causer')
            ->when($events !== [], fn ($q) => $this->applyEventFilter($q, $events))
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'like', '%'.$request->string('search')->trim().'%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('causer_id', $request->integer('user_id'))->where('causer_type', User::class))
            ->when($dateRange, fn ($q) => $q->whereBetween('created_at', [$dateRange->start, $dateRange->end]))
            ->when(! $dateRange && $request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')->toString()))
            ->when(! $dateRange && $request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.audit-logs.index', [
            'logs' => $logs,
            'eventOptions' => AuditLogPresenter::filterOptions(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'events' => $events,
                'search' => $request->string('search')->toString(),
                'user_id' => $request->input('user_id'),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
            'range' => $dateRange->range ?? '',
            'selectedMonth' => $dateRange?->selectedMonth?->format('Y-m'),
            'selectedDate' => $dateRange?->selectedDate?->format('Y-m-d'),
            'calendarMonth' => $dateRange?->calendarMonth ?? now()->format('Y-m'),
            'activeFilterSummary' => $this->activeFilterSummary($request, $events, $dateFilterActive, $dateRange),
        ]);
    }

    /**
     * Generic CRUD events (created/updated/deleted) are ambiguous across
     * every audited model, so a filter option for one of them carries a
     * "event:SubjectFqcn" composite value (see AuditLogPresenter::
     * filterOptions()) — split it back into its two real conditions here.
     * Custom-named events (weigh_in_recorded, ...) are already unique and
     * pass straight through as a plain event match.
     */
    protected function applyEventFilter(Builder $query, array $events): void
    {
        $query->where(function ($outer) use ($events) {
            foreach ($events as $value) {
                if (str_contains($value, ':')) {
                    [$event, $subjectType] = explode(':', $value, 2);
                    $outer->orWhere(fn ($w) => $w->where('event', $event)->where('subject_type', $subjectType));
                } else {
                    $outer->orWhere('event', $value);
                }
            }
        });
    }

    protected function activeFilterSummary(Request $request, array $events, bool $dateFilterActive, ?ReportDateRange $dateRange): ?string
    {
        $parts = [];

        if ($events !== []) {
            $parts[] = trans_choice(':count action type|:count action types', count($events), ['count' => count($events)]);
        }

        if ($request->filled('search')) {
            $parts[] = __('matching ":search"', ['search' => $request->string('search')->toString()]);
        }

        if ($request->filled('user_id') && ($user = User::find($request->integer('user_id')))) {
            $parts[] = __('by :name', ['name' => $user->name]);
        }

        if ($dateFilterActive && $dateRange) {
            $parts[] = $dateRange->rangeLabel;
        } elseif ($request->filled('from') || $request->filled('to')) {
            $parts[] = __('within the selected dates');
        }

        return $parts === [] ? null : __('Filtered by :parts.', ['parts' => implode(', ', $parts)]);
    }
}
