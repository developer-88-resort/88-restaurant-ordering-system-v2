<x-app-layout>
    <x-slot name="header">
        <section class="py-2">
            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.6"
                            stroke="currentColor"
                            class="h-7 w-7"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                                {{ __('Order Management') }}
                            </h2>

                            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
                                {{ trans_choice(':count order|:count orders', $totalOrders, ['count' => $totalOrders]) }}
                            </span>
                        </div>

                        <p class="mt-1.5 text-sm leading-6 text-slate-500">
                            {{ __('Monitor, process and manage customer orders in one workspace.') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($orders->isEmpty())
                        {{-- Avoid a duplicated CTA because the onboarding panel already has one. --}}
                        <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-600">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-40"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                            </span>

                            {{ __('Ready for your first order') }}
                        </div>
                    @else
                        <a
                            href="{{ route('orders.create') }}"
                            class="group inline-flex min-h-11 items-center justify-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors duration-150 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-600" aria-hidden="true">
                                <path d="M6.5 3.5h7l4 4v4M6.5 3.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h5" fill="currentColor" fill-opacity="0.08" />
                                <path d="M17.5 10.5v-3l-4-4h-7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h5M13.5 3.5v4h4M8 10h3M8 13.5h2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <rect x="13" y="13" width="8" height="8" rx="2.5" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.6" />
                                <path d="M17 15.25v3.5m-1.75-1.75h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                            {{ __('New Order') }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
    </x-slot>

    @php
        $filterIcon = fn (?string $status) => match ($status) {
            null => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122" />',
            'pending' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'preparing' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />',
            'ready' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />',
            'served' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />',
            'completed' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'cancelled' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        };
    @endphp

    {{-- Live list: a new order, a kitchen cancellation, or a status change
         anywhere refreshes this page (bursts coalesce into one reload). --}}
    <div
        x-data
        x-init="
            let timer = null;
            const reload = () => {
                if (document.activeElement && document.activeElement.matches('[data-orders-search]')) {
                    timer = setTimeout(reload, 3000);
                    return;
                }
                window.location.reload();
            };
            Echo.private('orders').listen('.OrderUpdated', () => {
                clearTimeout(timer);
                timer = setTimeout(reload, 800);
            });
            turboCleanup(() => { clearTimeout(timer); Echo.leave('orders'); });
        "
        class="hidden"
    ></div>

    @if ($orders->isEmpty())
        <x-empty-state
            variant="onboarding"
            :eyebrow="__('Order workspace')"
            :title="__('Your order workspace is ready.')"
            :description="__('Create your first walk-in order, add the customer items, assign a table or location, and review everything before submitting.')"
            :actionLabel="__('Create First Order')"
            :actionHref="route('orders.create')"
        />
    @else
        {{-- Status buttons, location filter and search: resources/js/lib/
             orders-browser.js, over the orders already on the page. The
             choices are kept per tab so the live reload doesn't drop them. --}}
        <div x-data="ordersBrowser(@js([
            'orders' => $orderIndex,
            'areaKeys' => $areas->map(fn ($area) => (string) $area->id)->push('takeout')->values(),
        ]))">
            {{-- Status filter panel --}}
            <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-slate-50 text-[#8A3330]">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5m-13.5 6h10.5m-7.5 6h4.5" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-slate-900">
                                {{ __('Order pipeline') }}
                            </h3>

                            <p class="mt-0.5 text-xs leading-5 text-slate-500">
                                {{ __('Filter the workspace by the current order status.') }}
                            </p>
                        </div>
                    </div>

                    <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-30"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>

                        {{ __('Live dashboard') }}
                    </div>
                </div>

                <div class="mt-4 flex gap-2 overflow-x-auto pb-1 no-scrollbar sm:flex-wrap sm:overflow-visible sm:pb-0">
                    <button
                        type="button"
                        @click="selectedStatus = 'all'"
                        :aria-pressed="selectedStatus === 'all'"
                        :class="selectedStatus === 'all'
                            ? 'border-slate-800 bg-slate-800 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2.5 text-sm font-semibold transition duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            class="h-4 w-4"
                            aria-hidden="true"
                        >
                            {!! $filterIcon(null) !!}
                        </svg>

                        {{ __('All') }}

                        <span
                            :class="selectedStatus === 'all' ? 'bg-white/15 text-white' : 'bg-white text-[#8A3330]'"
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1 text-[11px] font-bold"
                        >
                            <span x-text="statusCount('all')">{{ $totalOrders }}</span>
                        </span>
                    </button>

                    @foreach (\App\Enums\OrderStatus::cases() as $status)
                        <button
                            type="button"
                            @click="selectedStatus = '{{ $status->value }}'"
                            :aria-pressed="selectedStatus === '{{ $status->value }}'"
                            :class="selectedStatus === '{{ $status->value }}'
                                ? 'border-slate-800 bg-slate-800 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                            class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2.5 text-sm font-semibold transition duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                                class="h-4 w-4"
                                aria-hidden="true"
                            >
                                {!! $filterIcon($status->value) !!}
                            </svg>

                            {{ $status->label() }}

                            <span
                                :class="selectedStatus === '{{ $status->value }}' ? 'bg-white/15 text-white' : 'bg-white text-[#8A3330]'"
                                class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1 text-[11px] font-bold"
                            >
                                <span x-text="statusCount('{{ $status->value }}')">{{ $statusCounts[$status->value] ?? 0 }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- Search and location --}}
                <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.197 5.197a7.5 7.5 0 0010.606 10.606z" />
                        </svg>
                        <input
                            type="search"
                            data-orders-search
                            x-model.debounce.150ms="query"
                            placeholder="{{ __('Search order no., table, price, or who created it...') }}"
                            aria-label="{{ __('Search orders') }}"
                            class="block h-11 w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#8A3330] focus:bg-white focus:ring-[#8A3330]/20 [&::-webkit-search-cancel-button]:appearance-none"
                        >
                        <button
                            type="button"
                            x-show="query"
                            x-cloak
                            @click="query = ''"
                            class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#8A3330]"
                            aria-label="{{ __('Clear search') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar sm:flex-wrap sm:overflow-visible sm:pb-0">
                        <span class="shrink-0 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Location') }}</span>
                        @php
                            $locationFilters = collect([['key' => 'all', 'label' => __('All locations')]])
                                ->merge($areas->map(fn ($area) => ['key' => (string) $area->id, 'label' => $area->name]))
                                ->when($hasTakeout, fn ($filters) => $filters->push(['key' => 'takeout', 'label' => __('Take-out')]));
                        @endphp
                        @foreach ($locationFilters as $filter)
                            <button
                                type="button"
                                @click="selectedArea = @js($filter['key'])"
                                :aria-pressed="selectedArea === @js($filter['key'])"
                                :class="selectedArea === @js($filter['key'])
                                    ? 'border-[#8A3330] bg-[#8A3330] text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                                class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold transition duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15"
                            >
                                {{ $filter['label'] }}
                                <span
                                    :class="selectedArea === @js($filter['key']) ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'"
                                    class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1 text-[11px] font-bold"
                                    x-text="areaCount(@js($filter['key']))"
                                ></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- No orders in the selected status --}}
            <div x-show="!hasVisibleOrders" x-transition.opacity x-cloak class="mb-6">
                <div x-show="!isFiltering">
                    <x-empty-state
                        :title="__('No orders in this status')"
                        :description="__('Select another status to view the available orders.')"
                    />
                </div>
                <div x-show="isFiltering" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p class="text-sm font-bold text-slate-900">{{ __('No orders match your search or filters.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Try fewer words, another location, or another status.') }}</p>
                    <button type="button" @click="clearFilters()" class="mt-4 inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        {{ __('Clear search and filters') }}
                    </button>
                </div>
            </div>

            {{-- Open slips grouped by table --}}
            @if ($openTables->isNotEmpty())
                <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-slate-50 text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v4H4V6z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8M17 10v8" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">{{ __('Open slips by table') }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500">{{ __('Each table with orders still in play, and its slips.') }}</p>
                            </div>
                        </div>

                        <span class="inline-flex shrink-0 items-center rounded-full bg-slate-50 px-3 py-1.5 text-xs font-bold text-[#8A3330]">
                            {{ trans_choice(':count table|:count tables', $openTables->count(), ['count' => $openTables->count()]) }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($openTables as $spaceId => $slips)
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 transition-colors hover:border-slate-300">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="min-w-0 truncate text-sm font-bold text-slate-900">{{ $slips->first()->locationLabel() }}</p>
                                    <a
                                        href="{{ route('orders.create', ['space' => $spaceId]) }}"
                                        class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8A3330]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        {{ __('New slip') }}
                                    </a>
                                </div>

                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    @foreach ($slips as $slip)
                                        <a
                                            href="{{ route('orders.show', $slip) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-900 transition hover:border-[#8A3330]/40 hover:text-[#8A3330]"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full {{ $slip->status->dotClasses() }}"></span>
                                            {{ $slip->slipLabel() ?? $slip->orderNumber() }}
                                            <span class="font-normal text-slate-500">{{ $slip->status->label() }} · {{ $slip->created_at->format('g:i A') }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Desktop table --}}
            <section
                x-show="hasVisibleOrders"
                x-transition.opacity
                x-cloak
                class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:block"
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ __('Current orders') }}
                        </h3>

                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ __('Open an order to review its items, payment and status.') }}
                        </p>
                    </div>

                    <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1.5 text-xs font-bold text-[#8A3330]">
                        <span x-text="visibleCount + ' ' + @js(__('shown'))">{{ $orders->count() }} {{ __('shown') }}</span>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-100">
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Order') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Location') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Status') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Payment') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Total') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Created') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ __('Action') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody data-order-list class="divide-y divide-slate-100">
                            @foreach ($orders as $order)
                                <tr
                                    data-order-row="{{ $order->id }}"
                                    x-show="isVisible({{ $order->id }})"
                                    x-cloak
                                    class="group transition-colors duration-150 hover:bg-slate-50 focus-within:bg-slate-50"
                                >
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-3.5">
                                            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl {{ $order->status->badgeClasses() }}">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="1.8"
                                                    stroke="currentColor"
                                                    class="h-5 w-5"
                                                    aria-hidden="true"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                </svg>
                                            </div>

                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ $order->orderNumber() }}
                                                </p>

                                                <p class="mt-0.5 max-w-[180px] truncate text-xs text-slate-500">
                                                    {{ $order->customer_name ?? __('Walk-in customer') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600">
                                            <span class="grid h-8 w-8 place-items-center rounded-xl bg-slate-50 text-slate-500">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="1.8"
                                                    stroke="currentColor"
                                                    class="h-4 w-4"
                                                    aria-hidden="true"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v4H4V6z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8M17 10v8" />
                                                </svg>
                                            </span>

                                            {{ $order->slipLocationLabel() }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold leading-5 {{ $order->status->badgeClasses() }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $order->status->dotClasses() }}"></span>
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold leading-5 {{ $order->payment_status->badgeClasses() }}">
                                                {{ $order->payment_status->label() }}
                                            </span>

                                            @if ($order->payment_method)
                                                <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">
                                                    {{ $order->payment_method->label() }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <span class="text-sm font-semibold tabular-nums text-slate-900">
                                            ₱{{ number_format($order->total_amount, 2) }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.8"
                                                stroke="currentColor"
                                                class="mt-0.5 h-4 w-4 shrink-0 text-slate-500"
                                                aria-hidden="true"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                            </svg>

                                            <div>
                                                <p class="text-sm font-medium text-slate-600">
                                                    {{ $order->created_at->format('M d, g:i A') }}
                                                </p>

                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    {{ $order->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <a
                                            href="{{ route('orders.show', $order) }}"
                                            class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors duration-150 hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15"
                                        >
                                            {{ __('View') }}

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="2"
                                                stroke="currentColor"
                                                class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                                                aria-hidden="true"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Mobile cards --}}
            <div data-order-list x-show="hasVisibleOrders" x-transition.opacity x-cloak class="space-y-3 sm:hidden">
                @foreach ($orders as $order)
                    <a
                        href="{{ route('orders.show', $order) }}"
                        data-order-row="{{ $order->id }}"
                        x-show="isVisible({{ $order->id }})"
                        x-cloak
                        class="group relative block overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm transition duration-200 active:scale-[0.99]"
                    >
                        <div aria-hidden="true" class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-slate-50"></div>

                        <div class="relative">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl {{ $order->status->badgeClasses() }}">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.8"
                                            stroke="currentColor"
                                            class="h-5 w-5"
                                            aria-hidden="true"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">
                                            {{ $order->orderNumber() }}
                                        </p>

                                        <p class="mt-0.5 truncate text-xs text-slate-500">
                                            {{ $order->customer_name ?? __('Walk-in customer') }}
                                        </p>
                                    </div>
                                </div>

                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold leading-5 {{ $order->status->badgeClasses() }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $order->status->dotClasses() }}"></span>
                                    {{ $order->status->label() }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                        {{ __('Location') }}
                                    </p>

                                    <p class="mt-1 truncate text-xs font-semibold text-slate-600">
                                        {{ $order->slipLocationLabel() }}
                                    </p>
                                </div>

                                <div class="border-l border-slate-200 pl-3">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                        {{ __('Created') }}
                                    </p>

                                    <p class="mt-1 truncate text-xs font-semibold text-slate-600">
                                        {{ $order->created_at->format('M d, g:i A') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                        {{ __('Payment') }}
                                    </p>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold leading-5 {{ $order->payment_status->badgeClasses() }}">
                                            {{ $order->payment_status->label() }}
                                        </span>

                                        @if ($order->payment_method)
                                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">
                                                {{ $order->payment_method->label() }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                        {{ __('Total') }}
                                    </p>

                                    <p class="mt-1 text-lg font-semibold tracking-tight tabular-nums text-slate-900">
                                        ₱{{ number_format($order->total_amount, 2) }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                <p class="text-xs text-slate-500">
                                    {{ $order->created_at->diffForHumans() }}
                                </p>

                                <span class="inline-flex items-center gap-1 text-xs font-bold text-[#8A3330]">
                                    {{ __('View order') }}

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2"
                                        stroke="currentColor"
                                        class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                                        aria-hidden="true"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-app-layout>
