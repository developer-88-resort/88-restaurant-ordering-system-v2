<x-app-layout>
    <x-slot name="header">
        <section
            class="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-7"
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
            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-xl font-semibold tracking-[-0.025em] text-slate-900 sm:text-2xl">
                                {{ __('Audit Logs') }}
                            </h2>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                {{ trans_choice(':count entry|:count entries', $logs->total(), ['count' => $logs->total()]) }}
                            </span>
                        </div>
                        <p class="mt-1 flex items-center gap-2 text-sm leading-6 text-slate-500">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-40" :class="connected ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full" :class="connected ? 'bg-emerald-500' : 'bg-gray-400'"></span>
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
        $controlClasses = 'h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm text-gray-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-300';
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
        class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
    >
        <div class="flex flex-wrap items-end gap-3">
            {{-- Action multi-select --}}
            <div class="relative" @click.outside="actionOpen = false">
                <label class="mb-1 block text-xs font-medium text-gray-500">{{ __('Action') }}</label>
                <button type="button" @click="actionOpen = !actionOpen"
                        class="{{ $controlClasses }} flex w-56 items-center justify-between gap-2 hover:border-slate-400/40">
                    <span x-text="actionButtonLabel" class="truncate"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <div x-show="actionOpen" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute z-40 mt-2 w-80 max-w-[calc(100vw-4rem)] rounded-2xl border border-slate-200 bg-white p-2.5 shadow-lg shadow-slate-200/60">
                    <div class="flex items-center justify-between px-2 pb-2 pt-0.5">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Filter by action') }}</span>
                        <button type="button" x-show="selectedEvents.length > 0" x-cloak
                                @click="selectedEvents = []"
                                class="text-xs font-semibold text-slate-700 hover:underline">
                            {{ __('Clear') }}
                        </button>
                    </div>
                    <div class="max-h-72 space-y-1 overflow-y-auto">
                        @foreach ($eventOptions as $value => $label)
                            <label class="group flex cursor-pointer items-center gap-3 rounded-xl px-2.5 py-2.5 text-sm transition"
                                   :class="selectedEvents.includes('{{ $value }}') ? 'bg-slate-100 text-slate-700 font-semibold' : 'text-slate-700 hover:bg-slate-50'">
                                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md border-2 transition"
                                      :class="selectedEvents.includes('{{ $value }}') ? 'border-slate-400 bg-slate-700' : 'border-slate-300 bg-white group-hover:border-slate-400/50'">
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
                       class="inline-flex h-11 items-center text-xs font-semibold text-slate-500 hover:text-slate-700">
                        {{ __('Clear') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-3">
            <x-date-range-filter :modern="true"
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
        <div class="hidden sm:block bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('Date & Time') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('User') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('Action') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($logs as $log)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $log->causer->name ?? __('System') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold tracking-wide {{ $badgeClasses($log->event) }}">
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
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y g:i A') }}</p>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold tracking-wide shrink-0 {{ $badgeClasses($log->event) }}">
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
