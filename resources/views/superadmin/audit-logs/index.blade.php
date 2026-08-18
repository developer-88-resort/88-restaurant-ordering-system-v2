<x-app-layout>
    <x-slot name="header">
        <section
            class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7"
            x-data="{ connected: false }"
            x-init="
                // Read the CURRENT connection state on mount — the socket
                // is a long-lived singleton that survives Turbo
                // navigations, so a page you navigate back to would
                // otherwise only find out it's connected on the NEXT state
                // transition (which may never come if it's been connected
                // the whole time), staying stuck on Reconnecting until a
                // real browser refresh.
                connected = Echo.connector.pusher.connection.state === 'connected';

                const onConnected = () => connected = true;
                const onDisconnected = () => connected = false;
                const onUnavailable = () => connected = false;
                Echo.private('audit-logs').listen('.AuditLogCreated', () => window.location.reload());
                Echo.connector.pusher.connection.bind('connected', onConnected);
                Echo.connector.pusher.connection.bind('disconnected', onDisconnected);
                Echo.connector.pusher.connection.bind('unavailable', onUnavailable);
                turboCleanup(() => {
                    Echo.leave('audit-logs');
                    Echo.connector.pusher.connection.unbind('connected', onConnected);
                    Echo.connector.pusher.connection.unbind('disconnected', onDisconnected);
                    Echo.connector.pusher.connection.unbind('unavailable', onUnavailable);
                });
             "
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>
            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                                {{ __('Audit Logs') }}
                            </h2>
                            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[11px] font-bold text-white/80 backdrop-blur-sm">
                                {{ trans_choice(':count entry|:count entries', $logs->total(), ['count' => $logs->total()]) }}
                            </span>
                        </div>
                        <p class="mt-1 flex items-center gap-2 text-sm leading-6 text-white/60">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-40" :class="connected ? 'bg-emerald-300' : 'bg-gray-400'"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full" :class="connected ? 'bg-emerald-300' : 'bg-gray-400'"></span>
                            </span>
                            {{ $activeFilterSummary ?? __('Every change made across the system, newest first.') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </x-slot>

    @php
        // Action label/badge colour logic lives in App\Support\AuditLogPresenter
        // now — one source of truth shared with the filter dropdown below.
        $actionLabel = fn ($log) => \App\Support\AuditLogPresenter::label($log);
        $badgeClasses = fn ($event) => \App\Support\AuditLogPresenter::badgeClasses($event);
        $initial = fn (?string $name) => $name ? mb_strtoupper(mb_substr($name, 0, 1)) : '•';
        $controlClasses = 'h-[38px] rounded-lg border border-[#E5DDD0] bg-white px-3 text-sm text-gray-700 focus:border-[#8A3330] focus:ring-1 focus:ring-[#8A3330]';
        $hasActiveFilters = ($filters['events'] ?? []) || $filters['search'] || $filters['user_id'] || $filters['from'] || $filters['to'] || $range || $selectedMonth || $selectedDate;
    @endphp

    {{-- Filters --}}
    <form
        method="GET"
        x-data="{
            selectedEvents: @js($filters['events'] ?? []),
            optionLabels: @js($eventOptions),
            actionOpen: false,
            get actionButtonLabel() {
                return this.selectedEvents.length === 0
                    ? @js(__('Action: All'))
                    : @js(__('Action: ')) + this.selectedEvents.length + @js(__(' selected'));
            },
        }"
        @submit="Array.from($el.elements).forEach((el) => {
            if (el.type !== 'checkbox' && el.type !== 'radio' && !el.value) el.disabled = true;
        })"
        class="mb-5 rounded-2xl border border-[#E5DDD0] bg-white p-4"
    >
        <div class="flex flex-wrap items-end gap-3">
            {{-- Action multi-select --}}
            <div class="relative" @click.outside="actionOpen = false">
                <label class="mb-1 block text-xs font-medium text-gray-500">{{ __('Action') }}</label>
                <button type="button" @click="actionOpen = !actionOpen"
                        class="{{ $controlClasses }} flex w-56 items-center justify-between gap-2 hover:border-[#8A3330]/40">
                    <span x-text="actionButtonLabel" class="truncate"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <div x-show="actionOpen" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute z-40 mt-1 max-h-80 w-80 overflow-y-auto rounded-xl border border-[#E5DDD0] bg-white p-2 shadow-[0_24px_55px_-24px_rgba(45,27,23,0.6)]">
                    @foreach ($eventOptions as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-gray-700 hover:bg-[#FAF6EE]">
                            <input type="checkbox" name="events[]" value="{{ $value }}" x-model="selectedEvents"
                                   class="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="min-w-[10rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-500">{{ __('Search Details') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search…') }}"
                       class="{{ $controlClasses }} w-full">
            </div>

            <div class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-medium text-gray-500">{{ __('User') }}</label>
                <select name="user_id" class="{{ $controlClasses }} w-full">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex h-[38px] items-center justify-center rounded-xl bg-[#8A3330] px-5 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-[#742927]">
                    {{ __('Filter') }}
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('superadmin.audit-logs.index') }}"
                       class="inline-flex h-[38px] items-center text-xs font-semibold text-[#9A8B84] hover:text-[#463934]">
                        {{ __('Clear') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-3">
            <x-date-range-filter
                :range="$range"
                :selected-month="$selectedMonth"
                :selected-date="$selectedDate"
                :calendar-month="$calendarMonth"
            />
        </div>

        {{-- Selected actions as removable chips --}}
        <div x-show="selectedEvents.length > 0" x-cloak class="mt-3 flex flex-wrap gap-1.5">
            <template x-for="value in selectedEvents" :key="value">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#F3E1DC] py-1 pl-3 pr-1.5 text-xs font-semibold text-[#8A3330]">
                    <span x-text="optionLabels[value] ?? value"></span>
                    <button type="button"
                            @click="selectedEvents = selectedEvents.filter((v) => v !== value); $nextTick(() => $el.closest('form').requestSubmit())"
                            class="grid h-4 w-4 place-items-center rounded-full hover:bg-[#8A3330]/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-2.5 w-2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
            </template>
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state
            :title="__('No activity yet')"
            :description="__('Changes made across the system (menu, tables, users, settings) will appear here.')"
        />
    @else
        {{-- Desktop table --}}
        <div class="hidden overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)] sm:block">
            <div class="max-h-[70vh] overflow-auto">
                <table class="min-w-full divide-y divide-[#E5DDD0]">
                    <thead class="sticky top-0 z-10 bg-[#FAF6EE]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Date & Time') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('User') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Action') }}</th>
                            <th class="w-[28rem] px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5DDD0]">
                        @foreach ($logs as $log)
                            <tr class="transition hover:bg-[#FAF6EE]">
                                <td class="whitespace-nowrap px-6 py-4 align-top text-sm text-gray-500">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 align-top">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#8A3330] text-xs font-bold text-white">
                                            {{ $initial($log->causer->name ?? null) }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-900">{{ $log->causer->name ?? __('System') }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 align-top">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide {{ $badgeClasses($log->event) }}">
                                        {{ $actionLabel($log) }}
                                    </span>
                                </td>
                                <td class="w-[28rem] px-6 py-4 align-top text-sm text-gray-700" x-data="{ expanded: false }">
                                    <p :class="expanded ? '' : 'truncate'" title="{{ $log->description }}">{{ $log->description }}</p>
                                    @if (mb_strlen($log->description) > 60)
                                        <button type="button" @click="expanded = !expanded" class="mt-0.5 text-xs font-semibold text-[#8A3330] hover:underline" x-text="expanded ? @js(__('Show less')) : @js(__('Show more'))"></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-3 sm:hidden">
            @foreach ($logs as $log)
                <div class="rounded-2xl border border-[#E5DDD0] bg-white p-4 shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)]">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y g:i A') }}</p>
                        <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide {{ $badgeClasses($log->event) }}">
                            {{ $actionLabel($log) }}
                        </span>
                    </div>
                    <div class="mt-2.5 flex items-center gap-2.5">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#8A3330] text-[11px] font-bold text-white">
                            {{ $initial($log->causer->name ?? null) }}
                        </span>
                        <p class="text-sm font-medium text-gray-900">{{ $log->causer->name ?? __('System') }}</p>
                    </div>
                    <p class="mt-1.5 text-sm text-gray-700">{{ $log->description }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $logs->links() }}
        </div>
    @endif
</x-app-layout>
