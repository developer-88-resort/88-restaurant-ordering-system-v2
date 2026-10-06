{{-- Massage Orders: the restaurant's orders/index, laid out the
     same, over massage orders. Status, Room/Walk-in and search filtering is
     the same orders-browser.js, keeping its own choices (massage: true). --}}
<x-app-layout>
    <x-slot name="header">
        <section class="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                                {{ __('Massage Orders') }}
                            </h2>

                            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
                                {{ trans_choice(':count order|:count orders', $totalOrders, ['count' => $totalOrders]) }}
                            </span>
                        </div>

                        <p class="mt-1.5 text-sm leading-6 text-slate-500">
                            {{ __('Monitor, process and manage every massage order in one workspace.') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($orders->isEmpty())
                        <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-600">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-40"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                            </span>

                            {{ __('Ready for your first massage order') }}
                        </div>
                    @else
                        <a
                            href="{{ route('massage.orders.create') }}"
                            class="group inline-flex min-h-11 items-center justify-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors duration-150 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-600" aria-hidden="true">
                                <path d="M6.5 3.5h7l4 4v4M6.5 3.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h5" fill="currentColor" fill-opacity="0.08" />
                                <path d="M17.5 10.5v-3l-4-4h-7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h5M13.5 3.5v4h4M8 10h3M8 13.5h2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <rect x="13" y="13" width="8" height="8" rx="2.5" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.6" />
                                <path d="M17 15.25v3.5m-1.75-1.75h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                            {{ __('New Massage Order') }}
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
            'paid' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'cancelled' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        };
        $paymentLabel = fn ($order) => $order->recordedPayments->map(fn ($payment) => $payment->payment_method->label())->unique()->implode(' + ');
    @endphp

    @if ($orders->isEmpty())
        <x-modern-order-empty-state
            variant="onboarding"
            :eyebrow="__('Massage workspace')"
            :title="__('Your massage workspace is ready.')"
            :description="__('Create the first massage order: pick the services, enter the room number, and take payment when the guest is ready.')"
            :actionLabel="__('Create First Massage Order')"
            :actionHref="route('massage.orders.create')"
        />
    @else
        <div x-data="ordersBrowser(@js([
            'orders' => $orderIndex,
            'areaKeys' => ['room', 'walkin'],
            'statuses' => collect(\App\Enums\MassageOrderStatus::cases())->map->value->values(),
            'massage' => true,
        ]))">
            {{-- Status filter panel --}}
            <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-slate-50 text-[#8A3330]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5m-13.5 6h10.5m-7.5 6h4.5" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ __('Massage pipeline') }}</h3>
                            <p class="mt-0.5 text-xs leading-5 text-slate-500">{{ __('Filter the massage orders by their payment status.') }}</p>
                        </div>
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
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">{!! $filterIcon(null) !!}</svg>
                        {{ __('All') }}
                        <span :class="selectedStatus === 'all' ? 'bg-white/15 text-white' : 'bg-white text-[#8A3330]'" class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1 text-[11px] font-bold">
                            <span x-text="statusCount('all')">{{ $totalOrders }}</span>
                        </span>
                    </button>

                    @foreach (\App\Enums\MassageOrderStatus::cases() as $status)
                        <button
                            type="button"
                            @click="selectedStatus = '{{ $status->value }}'"
                            :aria-pressed="selectedStatus === '{{ $status->value }}'"
                            :class="selectedStatus === '{{ $status->value }}'
                                ? 'border-slate-800 bg-slate-800 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                            class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2.5 text-sm font-semibold transition duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">{!! $filterIcon($status->value) !!}</svg>
                            {{ $status->label() }}
                            <span :class="selectedStatus === '{{ $status->value }}' ? 'bg-white/15 text-white' : 'bg-white text-[#8A3330]'" class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1 text-[11px] font-bold">
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
                            x-model.debounce.150ms="query"
                            placeholder="{{ __('Search order no., room, guest, service or price...') }}"
                            aria-label="{{ __('Search orders') }}"
                            class="block h-11 w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#8A3330] focus:bg-white focus:ring-[#8A3330]/20 [&::-webkit-search-cancel-button]:appearance-none"
                        >
                        <button type="button" x-show="query" x-cloak @click="query = ''" class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-[#8A3330]" aria-label="{{ __('Clear search') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar sm:flex-wrap sm:overflow-visible sm:pb-0">
                        <span class="shrink-0 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Room / Guest') }}</span>
                        @foreach ([['key' => 'all', 'label' => __('All')], ['key' => 'room', 'label' => __('Rooms')], ['key' => 'walkin', 'label' => __('Walk-in')]] as $filter)
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
                                <span :class="selectedArea === @js($filter['key']) ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'" class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1 text-[11px] font-bold" x-text="areaCount(@js($filter['key']))"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- No orders in the selected status --}}
            <div x-show="!hasVisibleOrders" x-transition.opacity x-cloak class="mb-6">
                <div x-show="!isFiltering">
                    <x-empty-state :title="__('No massage orders in this status')" :description="__('Select another status to view the available orders.')" />
                </div>
                <div x-show="isFiltering" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p class="text-sm font-bold text-slate-900">{{ __('No massage orders match your search or filters.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Try fewer words, another location, or another status.') }}</p>
                    <button type="button" @click="clearFilters()" class="mt-4 inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        {{ __('Clear search and filters') }}
                    </button>
                </div>
            </div>

            {{-- Desktop table --}}
            <section x-show="hasVisibleOrders" x-transition.opacity x-cloak class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:block">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ __('Current massage orders') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('Open a massage order to review its services and take payment.') }}</p>
                    </div>

                    <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1.5 text-xs font-bold text-[#8A3330]">
                        <span x-text="visibleCount + ' ' + @js(__('shown'))">{{ $orders->count() }} {{ __('shown') }}</span>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-100">
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Massage Order') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Room / Guest') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Payment') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Created') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody data-order-list class="divide-y divide-slate-100">
                            @foreach ($orders as $order)
                                <tr data-order-row="{{ $order->id }}" x-show="isVisible({{ $order->id }})" x-cloak class="group transition-colors duration-150 hover:bg-slate-50 focus-within:bg-slate-50">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-3.5">
                                            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl {{ $order->status->badgeClasses() }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900">{{ $order->order_number }}</p>
                                                <p class="mt-0.5 max-w-[200px] truncate text-xs text-slate-500">{{ $order->items->map->label()->implode(', ') }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600">
                                            <span class="grid h-8 w-8 place-items-center rounded-xl bg-slate-50 text-slate-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0l8.955 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                                </svg>
                                            </span>
                                            {{ $order->whoLabel() }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold leading-5 {{ $order->status->badgeClasses() }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $order->status->dotClasses() }}"></span>
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if ($paymentLabel($order) !== '')
                                            <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{{ $paymentLabel($order) }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <span class="text-sm font-semibold tabular-nums text-slate-900">₱{{ number_format($order->total_amount, 2) }}</span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-slate-600">{{ $order->created_at->format('M d, g:i A') }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ $order->created_at->diffForHumans() }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <a href="{{ route('massage.orders.show', $order) }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors duration-150 hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15">
                                            {{ __('View') }}
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
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
                    <a href="{{ route('massage.orders.show', $order) }}" data-order-row="{{ $order->id }}" x-show="isVisible({{ $order->id }})" x-cloak class="group relative block overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm transition duration-200 active:scale-[0.99]">
                        <div aria-hidden="true" class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-slate-50"></div>

                        <div class="relative">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl {{ $order->status->badgeClasses() }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $order->order_number }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ $order->items->map->label()->implode(', ') }}</p>
                                    </div>
                                </div>

                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold leading-5 {{ $order->status->badgeClasses() }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $order->status->dotClasses() }}"></span>
                                    {{ $order->status->label() }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ __('Room / Guest') }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-600">{{ $order->whoLabel() }}</p>
                                </div>
                                <div class="border-l border-slate-200 pl-3">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ __('Created') }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-slate-600">{{ $order->created_at->format('M d, g:i A') }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ __('Payment') }}</p>
                                    <p class="mt-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">{{ $paymentLabel($order) ?: '—' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ __('Total') }}</p>
                                    <p class="mt-1 text-lg font-semibold tracking-tight tabular-nums text-slate-900">₱{{ number_format($order->total_amount, 2) }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                <p class="text-xs text-slate-500">{{ $order->created_at->diffForHumans() }}</p>
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-[#8A3330]">
                                    {{ __('View massage order') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
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
