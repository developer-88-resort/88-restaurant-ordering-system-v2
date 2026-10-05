@php
    // Shared by the header's "Download PDF" link, the tab bar's link into
    // Weighed Lines, and every Weighed Items row's deep link — so the
    // current date selection carries over no matter which of those a user
    // clicks, without three separate copies of the same array_filter.
    $dateQuery = array_filter([
        'range' => (! $selectedMonth && ! $selectedDate) ? $range : null,
        'month' => $selectedMonth,
        'date' => $selectedDate,
    ]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <section class="py-2">
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ __('Reports') }}
                        </h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-500">
                            {{ __('Revenue, orders, and sales breakdowns for the property.') }}
                        </p>
                    </div>
                </div>

                <a
                    href="{{ route('superadmin.reports.pdf', $dateQuery) }}"
                    data-turbo="false"
                    class="group inline-flex min-h-11 w-fit items-center justify-center gap-2.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                >
                    <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#8A3330]/10">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 12m0 0l4.5-4.5M12 12V3" />
                        </svg>
                    </span>
                    {{ __('Download PDF') }}
                </a>
            </div>
        </section>
    </x-slot>

    {{-- Tabs — Weighed Lines is a separate (Inertia) page, not a client-side
         panel, since it's a real filtered/paginated table with its own
         exports; this bar just makes the two read as one page. --}}
    <div class="mb-6 inline-flex rounded-xl border border-slate-200 bg-white p-1">
        <span class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">{{ __('Overview') }}</span>
        <a
            href="{{ route('superadmin.reports.weighed-lines', $dateQuery) }}"
            data-turbo="false"
            class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
        >
            {{ __('Weighed Lines') }}
        </a>
    </div>

    {{-- Date range filter --}}
    {{-- data-turbo="false": see the matching comment in
         superadmin/audit-logs/index.blade.php — repeated Turbo morphs of
         this same shared x-date-range-filter component desync Alpine's
         x-for-cloned calendar cells ("wd is not defined"), so this form
         opts out of Turbo and does a full reload on every submit. --}}
    <form method="GET" action="{{ route('superadmin.reports.index') }}" data-turbo="false" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
        <x-date-range-filter :modern="true"
            :range="$range"
            :selected-month="$selectedMonth"
            :selected-date="$selectedDate"
            :calendar-month="$calendarMonth"
        />
    </form>

    {{-- Stat cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <div class="animate-fade-slide-up rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-300 [animation-delay:0ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-slate-500">{{ __('Total Revenue') }}</div>
                    <div class="mt-3 break-words text-2xl font-semibold tracking-tight tabular-nums text-slate-700">₱{{ number_format($totalRevenue, 2) }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['totalRevenue'] ?? null" />
            <p class="mt-3 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500">{{ $rangeLabel }} &middot; {{ __('Paid orders, gross (before discounts)') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-300 [animation-delay:80ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-slate-500">{{ __('Paid Orders') }}</div>
                    <div class="mt-3 break-words text-2xl font-semibold tracking-tight tabular-nums text-slate-900">{{ $paidOrderCount }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25h10.176a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H15M9 3.75c0 1.036.84 1.875 1.875 1.875h2.25c1.036 0 1.875-.84 1.875-1.875M9 3.75c0-1.036.84-1.875 1.875-1.875h2.25c1.036 0 1.875.84 1.875 1.875M9 12h6m-6 3.75h6" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['paidOrderCount'] ?? null" />
            <p class="mt-3 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500">{{ __('Settled in full — the same orders behind Total Revenue') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-300 [animation-delay:160ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-slate-500">{{ __('Average per Paid Order') }}</div>
                    <div class="mt-3 break-words text-2xl font-semibold tracking-tight tabular-nums text-slate-900">₱{{ number_format($averageOrderValue, 2) }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['averageOrderValue'] ?? null" />
            <p class="mt-3 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500">{{ __('Total Revenue ÷ Paid Orders') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-300 [animation-delay:200ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-slate-500">{{ __('Unpaid / Open Orders') }}</div>
                    <div class="mt-3 break-words text-2xl font-semibold tracking-tight tabular-nums text-slate-900">{{ $openOrderCount }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-orange-50 text-orange-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['openOrderCount'] ?? null" :invert="true" />
            <p class="mt-3 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500">{{ __('Still open — no revenue counted yet') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-300 [animation-delay:240ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-slate-500">{{ __('Cancelled Orders') }}</div>
                    <div class="mt-3 break-words text-2xl font-semibold tracking-tight tabular-nums text-slate-900">{{ $cancelledOrders }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['cancelledOrders'] ?? null" :invert="true" />
            <p class="mt-3 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500">{{ $rangeLabel }}</p>
        </div>
    </div>

    {{-- Collected by payment method. Built from the payment entries, not
         the orders' totals: a split-settled order puts its cash half and
         its GCash half in different rows here, which is the whole point —
         the drawer is counted against the cash line at closing. --}}
    <div class="animate-fade-slide-up mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:300ms]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">{{ __('Collected by Payment Method (IHAWAN)') }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('By the date the money was received. Voided payments excluded.') }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Room charges are not included here — see Room Charges below.') }}</p>
                @if ($separateSections->isNotEmpty())
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Sales from :names are not included here — each has its own section below.', ['names' => $separateSections->pluck('label')->implode(', ')]) }}</p>
                @endif
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">
                {{ trans_choice(':count payment|:count payments', $paymentMethodsCount, ['count' => $paymentMethodsCount]) }}
            </span>
        </div>

        {{-- Always a table: every method has a line, including the ones
             nobody used this period (shown faded at ₱0). --}}
        <div>
            <div class="overflow-x-auto">
                <table class="tabular-nums min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Method') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Entries') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Amount') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">%</th>
                        </tr>
                    </thead>
                    <tbody class="[&_td:nth-child(2)]:text-center">
                        @foreach ($paymentMethods as $row)
                            <tr class="transition hover:bg-slate-50 {{ $row->entry_count === 0 ? 'text-slate-500' : '' }}">
                                <td class="px-5 py-3 text-sm font-bold text-slate-900">{{ $row->method_label }}</td>
                                <td class="text-center px-5 py-3 text-sm text-slate-600">{{ $row->entry_count }}</td>
                                <td class="px-5 py-3 text-center text-sm font-bold text-slate-900">&#8369;{{ number_format($row->total_amount, 2) }}</td>
                                <td class="px-5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="h-1.5 w-10 overflow-hidden rounded-full bg-slate-50">
                                            <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $row->percent) }}%"></div>
                                        </div>
                                        <span class="w-8 text-right text-xs font-bold text-slate-500">{{ number_format($row->percent, 0) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr>
                            <td class="px-5 py-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('Grand Total') }}</td>
                            <td class="px-5 py-3 text-center text-sm font-bold text-slate-600">{{ $paymentMethodsCount }}</td>
                            <td class="px-5 py-3 text-center text-base font-semibold text-slate-700">&#8369;{{ number_format($paymentMethodsTotal, 2) }}</td>
                            <td class="text-center px-5 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Room charges, on their own. Not money in the drawer: each of these
         bills went onto a guest's room and is collected at the front desk,
         so they stay out of the Grand Total above and are counted here,
         one by one, with the room/guest reference typed at checkout. --}}
    <div class="animate-fade-slide-up mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:310ms]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                    </svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">{{ __('Room Charges') }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Bills charged to a guest room, to be settled at the front desk.') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">
                    {{ trans_choice(':count room charge|:count room charges', $roomChargesCount, ['count' => $roomChargesCount]) }}
                </span>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700">
                    &#8369;{{ number_format($roomChargesTotal, 2) }}
                </span>
            </div>
        </div>

        @if ($roomCharges->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No room charges in this period.') }}</p>
        @else
            {{-- One subtotal per mode the room charges are paid through, then
                 the overall total across all of them. --}}
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-xs font-medium text-slate-500">{{ __('Totals by mode of payment') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($roomChargesByMode as $mode)
                        <div class="rounded-xl border border-slate-100 bg-[#FCF8F1] px-3 py-2.5">
                            <p class="truncate text-xs font-bold text-slate-600">{{ $mode->label }}</p>
                            <p class="mt-0.5 text-base font-semibold text-slate-900">&#8369;{{ number_format($mode->total_amount, 2) }}</p>
                            <p class="text-[11px] text-slate-500">{{ trans_choice(':count room charge|:count room charges', $mode->entry_count, ['count' => $mode->entry_count]) }}</p>
                        </div>
                    @endforeach
                    <div class="rounded-xl border border-[#8A3330]/25 bg-slate-50 px-3 py-2.5">
                        <p class="truncate text-xs font-bold uppercase tracking-[0.06em] text-slate-700">{{ __('Overall total') }}</p>
                        <p class="mt-0.5 text-base font-semibold text-slate-700">&#8369;{{ number_format($roomChargesTotal, 2) }}</p>
                        <p class="text-[11px] text-slate-500">{{ trans_choice(':count room charge|:count room charges', $roomChargesCount, ['count' => $roomChargesCount]) }}</p>
                    </div>
                </div>
            </div>

            @include('superadmin.reports.partials.room-charges-table', [
                'byRoom' => $roomChargesByRoom,
                'legacy' => $legacyRoomCharges,
                'legacyTotal' => $legacyRoomChargesTotal,
                'total' => $roomChargesTotal,
                'count' => $roomChargesCount,
            ])
        @endif
    </div>

    {{-- Korean resto tables (spaces named "Korean resto …"), on their own:
         every method plus room charge, none of it in the tables above, so
         the main Grand Total still tallies against the cashiers' count. --}}
    @foreach ($separateSections as $separateSales)
        <div class="animate-fade-slide-up mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:315ms]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">{{ $separateSales['label'] }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Sales from the :name tables, counted separately from the tables above.', ['name' => $separateSales['label']]) }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">
                    &#8369;{{ number_format($separateSales['grandTotal'], 2) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="tabular-nums min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Method') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Entries') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($separateSales['paymentMethods'] as $row)
                            <tr class="transition hover:bg-slate-50 {{ $row->entry_count === 0 ? 'text-slate-500' : '' }}">
                                <td class="px-5 py-3 text-sm font-bold text-slate-900">{{ $row->method_label }}</td>
                                <td class="px-5 py-3 text-center text-sm text-slate-600">{{ $row->entry_count }}</td>
                                <td class="px-5 py-3 text-center text-sm font-bold text-slate-900">&#8369;{{ number_format($row->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-[#FCF8F1]">
                            <td class="px-5 py-3 text-sm font-bold text-slate-600">{{ __('Subtotal — collected') }}</td>
                            <td class="px-5 py-3 text-center text-sm font-bold text-slate-600">{{ $separateSales['paymentMethodsCount'] }}</td>
                            <td class="px-5 py-3 text-center text-sm font-semibold text-slate-900">&#8369;{{ number_format($separateSales['paymentMethodsTotal'], 2) }}</td>
                        </tr>
                        <tr class="transition hover:bg-slate-50 {{ $separateSales['roomChargesCount'] === 0 ? 'text-slate-500' : '' }}">
                            <td class="px-5 py-3 text-sm font-bold text-amber-700">{{ __('Room Charge') }}</td>
                            <td class="px-5 py-3 text-center text-sm text-slate-600">{{ $separateSales['roomChargesCount'] }}</td>
                            <td class="px-5 py-3 text-center text-sm font-bold text-amber-700">&#8369;{{ number_format($separateSales['roomChargesTotal'], 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr>
                            <td class="px-5 py-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('Grand Total') }}</td>
                            <td class="px-5 py-3 text-center text-sm font-bold text-slate-600">{{ $separateSales['paymentMethodsCount'] + $separateSales['roomChargesCount'] }}</td>
                            <td class="px-5 py-3 text-center text-base font-semibold text-slate-700">&#8369;{{ number_format($separateSales['grandTotal'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($separateSales['roomCharges']->isNotEmpty())
                <div class="border-t border-slate-100 px-5 pt-4">
                    <p class="text-xs font-medium text-slate-500">{{ __('Room charges to bill') }}</p>
                </div>
                @include('superadmin.reports.partials.room-charges-table', [
                    'byRoom' => $separateSales['roomChargesByRoom'],
                    'legacy' => $separateSales['legacyRoomCharges'],
                    'legacyTotal' => $separateSales['legacyRoomChargesTotal'],
                ])
            @endif
        </div>
    @endforeach

    {{-- Daily revenue chart --}}
    <div class="animate-fade-slide-up mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm [animation-delay:320ms] sm:p-6">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
            </span>
            <div>
                <h3 class="text-sm font-bold text-slate-900">{{ __('Daily Revenue') }}</h3>
                <p class="text-xs text-slate-500">{{ $rangeLabel }}</p>
            </div>
        </div>

        @if ($dailySales->isEmpty())
            <p class="py-12 text-center text-sm text-slate-500">{{ __('No sales data for this period.') }}</p>
        @else
            @php
                $maxRevenue = $dailySales->max('revenue') ?: 1;
                $gridLines = [1, 0.75, 0.5, 0.25, 0];
            @endphp
            <div class="mt-5 flex gap-3">
                {{-- Y-axis labels --}}
                <div class="flex h-48 shrink-0 flex-col justify-between pb-1 text-right text-[10px] text-slate-500">
                    @foreach ($gridLines as $fraction)
                        <span>₱{{ number_format($maxRevenue * $fraction, 0) }}</span>
                    @endforeach
                </div>

                <div class="relative min-w-0 flex-1">
                    {{-- Gridlines --}}
                    <div class="absolute inset-0 pb-1">
                        @foreach ($gridLines as $fraction)
                            <div class="absolute left-0 right-0 border-t border-[#F0E9DF]" style="bottom: {{ $fraction * 100 }}%"></div>
                        @endforeach
                    </div>

                    <div class="relative flex h-48 items-end gap-2 overflow-x-auto pb-1">
                        @foreach ($dailySales as $day)
                            @php $heightPct = max(4, ($day->revenue / $maxRevenue) * 100); @endphp
                            <div class="group relative flex h-full w-14 shrink-0 flex-col items-center justify-end">
                                <div class="absolute -top-8 z-10 hidden whitespace-nowrap rounded-lg bg-[#241917] px-2 py-1 text-xs font-bold text-white shadow-lg group-hover:block">
                                    ₱{{ number_format($day->revenue, 2) }}
                                </div>
                                <div class="animate-grow-bar mx-auto w-full max-w-[32px] rounded-t-lg bg-[#8A3330] transition-colors group-hover:bg-[#742927]"
                                     style="--bar-height: {{ $heightPct }}%; animation-delay: {{ 120 + $loop->index * 60 }}ms"></div>
                                <span class="mt-2 whitespace-nowrap text-[10px] font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse($day->sale_date)->format('M d') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Tax & discount summary — from persisted invoice snapshots, so this
         always matches what was actually shown on each invoice. --}}
    <div class="animate-fade-slide-up mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm [animation-delay:360ms] sm:p-6">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z" />
                </svg>
            </span>
            <div>
                <h3 class="text-sm font-bold text-slate-900">{{ __('Tax & Discount Summary') }}</h3>
                <p class="text-xs text-slate-500">{{ __('By invoice date') }} &middot; {{ $rangeLabel }}</p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('Net Collected') }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-700">₱{{ number_format($taxSummary['netAmountCollected'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('VATable Sales') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['vatableSales'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('VAT-Exempt Sales') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['vatExemptSales'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('VAT Amount') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['vatAmount'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('Service Charges') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['serviceCharges'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('Senior Discounts') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['seniorDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('PWD Discounts') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['pwdDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('Custom % Discounts') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['customPercentDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('Custom Amount Discounts') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['amountDiscounts'], 2) }}</p>
            </div>
            {{-- The catch-all bucket stays in the arithmetic so the named
                 tiles are guaranteed to add up to Total Discounts, but it
                 only takes up space once something actually lands in it —
                 a retired rule in old data, or a percentage rule added
                 later. With today's four rules it is always zero. --}}
            @if ($taxSummary['otherDiscounts'] > 0)
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                    <p class="text-xs font-medium text-slate-500">{{ __('Other Discounts') }}</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">₱{{ number_format($taxSummary['otherDiscounts'], 2) }}</p>
                </div>
            @endif
            <div class="rounded-xl border border-slate-300 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-700">{{ __('Total Discounts') }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-700">₱{{ number_format($taxSummary['totalDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                <p class="text-xs font-medium text-slate-500">{{ __('Voided Invoices') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">{{ $taxSummary['voidedInvoices'] }}</p>
            </div>
        </div>

        {{-- Every discount that actually reached an invoice, by rule. Read
             from the same frozen lines the receipt prints, so nothing can
             reduce a bill here without being named. --}}
        @if ($taxSummary['discountsByRule']->isNotEmpty())
            <p class="mt-6 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-700">{{ __('Discounts Given') }}</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Every discount that reached an invoice, by rule — the same figures printed on the receipt.') }}</p>
            <div class="mt-2.5 overflow-x-auto rounded-xl border border-slate-100">
                <table class="tabular-nums min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Discount Given') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Type') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Times Used') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($taxSummary['discountsByRule'] as $rule)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-4 py-2.5 text-sm font-bold text-slate-900">{{ $rule->rule_name }}</td>
                                <td class="text-center px-4 py-2.5 text-sm text-slate-600">
                                    {{ $rule->calculation_mode === 'fixed' ? __('Fixed Amount') : __('Percentage') }}
                                </td>
                                <td class="px-4 py-2.5 text-center text-sm text-slate-600">{{ $rule->times_used }}</td>
                                <td class="px-4 py-2.5 text-center text-sm font-bold text-slate-900">&#8369;{{ number_format($rule->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Best sellers --}}
        <div class="animate-fade-slide-up overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:400ms]">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-bold text-slate-900">{{ __('Best-Selling Items') }}</h3>
            </div>
            @if ($bestSellers->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No item sales for this period.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="tabular-nums min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Item') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Qty') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Revenue') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($bestSellers as $item)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-3 text-sm font-bold text-slate-900">{{ $item->item_name }}</td>
                                    <td class="px-5 py-3 text-center text-sm text-slate-600">
                                        @if ($item->line_type === 'weighed')
                                            {{ number_format($item->total_net_grams / 1000, 3) }} {{ __('kg') }}
                                        @else
                                            {{ $item->total_qty }} {{ __('pc') }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-center text-sm font-bold text-slate-900">₱{{ number_format($item->total_revenue, 2) }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="h-1.5 w-10 overflow-hidden rounded-full bg-slate-50">
                                                <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $item->percent) }}%"></div>
                                            </div>
                                            <span class="w-8 text-right text-xs font-bold text-slate-500">{{ number_format($item->percent, 0) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Sales by category --}}
        <div class="animate-fade-slide-up overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:480ms]">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-bold text-slate-900">{{ __('Sales by Category') }}</h3>
            </div>
            @if ($categorySales->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No category sales for this period.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="tabular-nums min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Category') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Revenue') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($categorySales as $category)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-3 text-sm font-bold text-slate-900">{{ $category->category_name }}</td>
                                    <td class="px-5 py-3 text-center text-sm font-bold text-slate-900">₱{{ number_format($category->total_revenue, 2) }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="h-1.5 w-10 overflow-hidden rounded-full bg-slate-50">
                                                <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $category->percent) }}%"></div>
                                            </div>
                                            <span class="w-8 text-right text-xs font-bold text-slate-500">{{ number_format($category->percent, 0) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Sales by area --}}
        <div class="animate-fade-slide-up overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:560ms]">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-bold text-slate-900">{{ __('Sales by Area') }}</h3>
            </div>
            @if ($areaSales->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No area sales for this period.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="tabular-nums min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Area') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Orders') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Revenue') }}</th>
                                <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($areaSales as $area)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-3 text-sm font-bold text-slate-900">{{ $area->area_name }}</td>
                                    <td class="px-5 py-3 text-center text-sm text-slate-600">{{ $area->order_count }}</td>
                                    <td class="px-5 py-3 text-center text-sm font-bold text-slate-900">₱{{ number_format($area->total_revenue, 2) }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="h-1.5 w-10 overflow-hidden rounded-full bg-slate-50">
                                                <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $area->percent) }}%"></div>
                                            </div>
                                            <span class="w-8 text-right text-xs font-bold text-slate-500">{{ number_format($area->percent, 0) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Weighed items — kg sold, revenue, and how far scale readings ran
         from the reference rate. Separate from Best-Selling Items since a
         "top 8 by qty" ranking mixes weighed and piece-counted lines. --}}
    <div class="animate-fade-slide-up mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm [animation-delay:620ms]">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-sm font-bold text-slate-900">{{ __('Weighed Items') }}</h3>
        </div>
        @if ($weighedItems->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No weighed-item sales for this period.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabular-nums min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Item') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Total kg') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Lines') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Total Revenue') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Avg Rate/kg') }}</th>
                            <th scope="col" class="px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Variance Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($weighedItems as $item)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-3 text-sm font-bold text-slate-900">
                                    @if ($item->menu_item_id)
                                        <a
                                            href="{{ route('superadmin.reports.weighed-lines', ['item_id' => $item->menu_item_id] + $dateQuery) }}"
                                            data-turbo="false"
                                            class="hover:text-slate-700 hover:underline"
                                        >
                                            {{ $item->item_name }}
                                        </a>
                                    @else
                                        {{ $item->item_name }}
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-center text-sm text-slate-600">{{ number_format($item->total_kg, 3) }} {{ __('kg') }}</td>
                                <td class="px-5 py-3 text-center text-sm text-slate-600">{{ $item->total_lines }}</td>
                                <td class="px-5 py-3 text-center text-sm font-bold text-slate-900">₱{{ number_format($item->total_revenue, 2) }}</td>
                                <td class="px-5 py-3 text-center text-sm text-slate-600">₱{{ number_format($item->avg_rate_per_kilo, 2) }}</td>
                                <td class="px-5 py-3 text-center text-sm font-bold {{ abs($item->variance_total) > 0.004 ? 'text-amber-700' : 'text-slate-600' }}">
                                    {{ $item->variance_total >= 0 ? '+' : '' }}₱{{ number_format($item->variance_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
