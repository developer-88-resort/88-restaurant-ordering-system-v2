{{-- The status sections, laid out like status.claude.com: each open
     incident with its timeline, then 90 days of uptime per part. Shared by /status (status.blade.php) and the maintenance page;
     $status comes from App\Support\StatusPage::build(). --}}
@php $stamp = fn ($time) => \App\Support\StatusPage::stamp($time); @endphp
<div class="w-full space-y-10 text-left">
    @foreach ($status['activeIncidents'] as $incident)
        <section class="overflow-hidden rounded-2xl border bg-white shadow-sm" style="border-color: {{ $incident->impact->color() }}">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 text-white" style="background: {{ $incident->impact->color() }}">
                <h2 class="text-lg font-semibold">{{ __($incident->title) }}</h2>
                <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold">{{ $incident->impact->label() }}</span>
            </div>
            <ol class="divide-y divide-slate-100 px-5">
                @foreach ($incident->updates as $update)
                    <li class="py-4">
                        <p class="text-sm leading-6 text-slate-700"><span class="font-bold text-slate-900">{{ $update->status->label() }}</span> - {{ __($update->message) }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $stamp($update->created_at) }}</p>
                    </li>
                @endforeach
            </ol>
            <p class="border-t border-slate-100 bg-slate-50 px-5 py-2.5 text-xs text-slate-500">
                {{ __('Affects:') }} {{ collect($incident->components)->map(fn ($key) => __(\App\Models\StatusIncident::COMPONENTS[$key] ?? $key))->implode(', ') }}
            </p>
        </section>
    @endforeach

    <section>
        <p class="mb-2 text-right text-xs text-slate-500">
            <span class="sm:hidden">{{ __('Uptime over the past 30 days.') }}</span>
            <span class="hidden sm:inline">{{ __('Uptime over the past 90 days.') }}</span>
        </p>
        <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
            @foreach ($status['components'] as $row)
                <div class="px-5 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</h3>
                        @if ($row['state'])
                            <span class="text-xs font-semibold" style="color: {{ $row['state']->color() }}">{{ $row['state']->label() }}</span>
                        @else
                            <span class="text-xs font-semibold text-emerald-600">{{ __('Operational') }}</span>
                        @endif
                    </div>
                    <div class="mt-3 flex h-9 gap-[2px]">
                        @foreach ($row['days'] as $day)
                            @php
                                $align = $loop->index >= 70 ? 'right-0' : ($loop->index < 20 ? 'left-0' : 'left-1/2 -translate-x-1/2');
                            @endphp
                            <button type="button"
                                    class="group relative h-full min-w-0 flex-1 rounded-[2px] focus:outline-none {{ $loop->index < 60 ? 'hidden sm:block' : '' }}"
                                    style="background: {{ $day['color'] }}"
                                    aria-label="{{ \App\Support\StatusPage::day($day['date']) }}">
                                <span class="pointer-events-none absolute top-full z-20 mt-2 hidden w-56 rounded-xl border border-slate-200 bg-white p-3 text-left text-xs shadow-lg group-hover:block group-focus:block {{ $align }}">
                                    <span class="block font-semibold text-slate-900">{{ \App\Support\StatusPage::day($day['date']) }}</span>
                                    @if ($day['downMinutes'] > 0)
                                        @foreach ($day['incidents'] as $title)
                                            <span class="mt-1 block text-slate-600">{{ $title }}</span>
                                        @endforeach
                                        <span class="mt-1 block text-slate-400">{{ __('Down for :time', ['time' => \App\Support\StatusPage::durationLabel($day['downMinutes'])]) }}</span>
                                    @else
                                        <span class="mt-1 block text-slate-500">{{ __('No downtime recorded on this day.') }}</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-2 flex items-center gap-3 text-xs text-slate-400">
                        <span class="sm:hidden">{{ __('30 days ago') }}</span>
                        <span class="hidden sm:inline">{{ __('90 days ago') }}</span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-slate-500">{{ __(':percent % uptime', ['percent' => rtrim(rtrim(number_format($row['uptime'], 2), '0'), '.')]) }}</span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span>{{ __('Today') }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
