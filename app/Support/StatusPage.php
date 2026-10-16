<?php

namespace App\Support;

use App\Models\StatusIncident;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The status page (/status, and the maintenance page), laid out like
 * status.claude.com: the open maintenance incident with its timeline, and a
 * row of 90 daily bars per part of the system with its uptime %.
 *
 * There is no uptime monitor: a day is down only for the time Maintenance
 * Mode was on for that part, so every bar is green until the first
 * maintenance, and the % counts that time as downtime.
 */
class StatusPage
{
    public const DAYS = 90;

    public static function build(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $from = $today->subDays(self::DAYS - 1);
        $now = CarbonImmutable::now();

        $incidents = StatusIncident::with('updates')
            ->where('started_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from))
            ->orderByDesc('started_at')
            ->get();

        return [
            'activeIncidents' => $incidents->filter(fn (StatusIncident $incident) => $incident->resolved_at === null)->values(),
            'components' => collect(StatusIncident::COMPONENTS)
                ->map(fn (string $label, string $key) => self::componentRow($key, __($label), $incidents, $from, $today, $now))
                ->values(),
        ];
    }

    protected static function componentRow(string $key, string $label, Collection $incidents, CarbonImmutable $from, CarbonImmutable $today, CarbonImmutable $now): array
    {
        $mine = $incidents->filter(fn (StatusIncident $incident) => in_array($key, $incident->components ?? [], true));
        $totalDown = 0;

        $days = collect(range(0, self::DAYS - 1))->map(function (int $offset) use ($from, $mine, $now, &$totalDown) {
            $day = $from->addDays($offset);
            $hits = $mine->map(fn (StatusIncident $incident) => ['incident' => $incident, 'minutes' => self::overlapMinutes($incident, $day, $now)])
                ->filter(fn ($hit) => $hit['minutes'] > 0)
                ->values();
            $minutes = min(1440, (int) $hits->sum('minutes'));
            $totalDown += $minutes;
            $worst = $hits->sortByDesc(fn ($hit) => $hit['incident']->impact->severity())->first();

            return [
                'date' => $day,
                'color' => $worst ? $worst['incident']->impact->color() : '#22C55E',
                'downMinutes' => $minutes,
                'incidents' => $hits->map(fn ($hit) => $hit['incident']->title)->unique()->values()->all(),
            ];
        });

        $elapsed = max(1, (int) $from->diffInMinutes($now));
        $open = $mine->filter(fn (StatusIncident $incident) => $incident->resolved_at === null)
            ->sortByDesc(fn (StatusIncident $incident) => $incident->impact->severity())
            ->first();

        return [
            'key' => $key,
            'label' => $label,
            'days' => $days,
            'uptime' => max(0, 100 - ($totalDown / $elapsed) * 100),
            'state' => $open ? $open->impact : null,
        ];
    }

    /** Minutes of $incident that fall on $day (up to now for an open one). */
    protected static function overlapMinutes(StatusIncident $incident, CarbonImmutable $day, CarbonImmutable $now): int
    {
        $start = CarbonImmutable::instance($incident->started_at)->max($day);
        $end = CarbonImmutable::instance($incident->resolved_at ?? $now)->min($day->endOfDay())->min($now);

        return $end->greaterThan($start) ? (int) ceil($start->diffInSeconds($end) / 60) : 0;
    }

    public static function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? $hours.' '.__('hr').' ' : '').($rest || ! $hours ? $rest.' '.__('min') : ''));
    }

    /** A day in the visitor's language ("Oct 8, 2026" / "2026년 10월 8일"). */
    public static function day(Carbon|CarbonImmutable $date): string
    {
        return $date->copy()->locale(app()->getLocale())->isoFormat('ll');
    }

    public static function stamp(Carbon|CarbonImmutable $time): string
    {
        return $time->copy()->timezone(config('app.timezone'))->locale(app()->getLocale())->isoFormat('ll · LT');
    }
}
