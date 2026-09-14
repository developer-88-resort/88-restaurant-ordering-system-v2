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
        $controlClasses = 'h-[38px] rounded-lg border border-[#E5DDD0] bg-white px-3 text-sm text-gray-700 focus:border-[#8A3330] focus:ring-1 focus:ring-[#8A3330]';
        $hasActiveFilters = ($filters['events'] ?? []) || $filters['search'] || $filters['user_id'] || $filters['from'] || $filters['to'] || $range || $selectedMonth || $selectedDate;
    @endphp

    {{-- Filters --}}
    {{-- data-turbo="false": every control in here (Filter button, date-range
         pills, calendar day clicks) resubmits this same GET form. Letting
         Turbo intercept and morph the response — instead of a full reload —
         leaves Alpine's x-for-cloned calendar cells (in x-date-range-filter)
         bound to a scope Turbo's morph disconnected, so a later interaction
         throws "wd is not defined" / "day is not defined". A hard reload
         re-initializes Alpine from scratch and sidesteps the whole class of
         bug, matching how logout/Inertia-page links in this app already
         opt out of Turbo for the same reason. --}}
    <form
        method="GET"
        data-turbo="false"
        x-data="{
            selectedEvents: @js($filters['events'] ?? []),
            actionOpen: false,
            get actionButtonLabel() {
                return this.selectedEvents.length === 0
                    ? @js(__('Action: All'))
                    : @js(__('Action: ')) + this.selectedEvents.length + @js(__(' selected'));
            },
        }"
        x-init="
            // Every control below applies itself the moment you change it —
            // no separate Filter click needed, matching the date-range pills,
            // which already auto-submitted. Watching selectedEvents (rather
            // than an @change on each checkbox) means checking or unchecking
            // any box, or removing a chip, all funnel through this one place.
            $watch('selectedEvents', () => $nextTick(() => $el.requestSubmit()));
        "
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
                     class="absolute z-40 mt-2 w-80 rounded-2xl border border-[#E5DDD0] bg-white p-2.5 shadow-[0_24px_55px_-24px_rgba(45,27,23,0.6)]">
                    <div class="flex items-center justify-between px-2 pb-2 pt-0.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#B0A49E]">{{ __('Filter by action') }}</span>
                        <button type="button" x-show="selectedEvents.length > 0" x-cloak
                                @click="selectedEvents = []"
                                class="text-xs font-semibold text-[#8A3330] hover:underline">
                            {{ __('Clear') }}
                        </button>
                    </div>
                    <div class="max-h-72 space-y-1 overflow-y-auto">
                        @foreach ($eventOptions as $value => $label)
                            <label class="group flex cursor-pointer items-center gap-3 rounded-xl px-2.5 py-2.5 text-sm transition"
                                   :class="selectedEvents.includes('{{ $value }}') ? 'bg-[#F3E1DC] text-[#8A3330] font-semibold' : 'text-[#463934] hover:bg-[#FAF6EE]'">
                                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md border-2 transition"
                                      :class="selectedEvents.includes('{{ $value }}') ? 'border-[#8A3330] bg-[#8A3330]' : 'border-[#D8CDC3] bg-white group-hover:border-[#8A3330]/50'">
                                    <svg x-show="selectedEvents.includes('{{ $value }}')" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" class="h-3 w-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                                <input type="checkbox" name="events[]" value="{{ $value }}" x-model="selectedEvents" class="sr-only">
                                <span class="truncate">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="min-w-[10rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-500">{{ __('Search Details') }}</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search…') }}"
                       x-on:input.debounce.600ms="$el.form.requestSubmit()"
                       x-on:keydown.enter.prevent="$el.form.requestSubmit()"
                       class="{{ $controlClasses }} w-full">
            </div>

            <div class="min-w-[10rem]">
                <label class="mb-1 block text-xs font-medium text-gray-500">{{ __('User') }}</label>
                <select name="user_id" x-on:change="$el.form.requestSubmit()" class="{{ $controlClasses }} w-full">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                @if ($hasActiveFilters)
                    <a href="{{ route('superadmin.audit-logs.index') }}" data-turbo="false"
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
    </form>

    @if ($logs->isEmpty())
        <x-empty-state
            :title="__('No activity yet')"
            :description="__('Changes made across the system (menu, tables, users, settings) will appear here.')"
        />
    @else
        {{-- Desktop table --}}
        <div class="hidden sm:block bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E5DDD0]">
                    <thead class="bg-[#FAF6EE]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Date & Time') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('User') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Action') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5DDD0]">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-[#FAF6EE]">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $log->causer->name ?? __('System') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide {{ $badgeClasses($log->event) }}">
                                        {{ $actionLabel($log) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $log->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden space-y-3">
            @foreach ($logs as $log)
                <div class="bg-white border border-[#E5DDD0] rounded-xl p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y g:i A') }}</p>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide shrink-0 {{ $badgeClasses($log->event) }}">
                            {{ $actionLabel($log) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm font-medium text-gray-900">{{ $log->causer->name ?? __('System') }}</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $log->description }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
</x-app-layout>
