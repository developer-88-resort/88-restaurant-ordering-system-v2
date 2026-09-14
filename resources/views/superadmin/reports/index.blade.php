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
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>

            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                            {{ __('Reports') }}
                        </h2>
                        <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                            {{ __('Revenue, orders, and sales breakdowns for the property.') }}
                        </p>
                    </div>
                </div>

                <a
                    href="{{ route('superadmin.reports.pdf', $dateQuery) }}"
                    data-turbo="false"
                    class="group inline-flex w-fit items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-black text-[#7B2D2A] shadow-[0_16px_32px_-18px_rgba(0,0,0,0.75)] transition hover:-translate-y-0.5 hover:bg-[#FFF7F3] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15"
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
    <div class="mb-6 inline-flex rounded-xl border border-[#E5DDD0] bg-white p-1">
        <span class="rounded-lg bg-[#8A3330] px-4 py-2 text-sm font-bold text-white">{{ __('Overview') }}</span>
        <a
            href="{{ route('superadmin.reports.weighed-lines', $dateQuery) }}"
            data-turbo="false"
            class="rounded-lg px-4 py-2 text-sm font-semibold text-[#6C5E57] transition hover:bg-[#FAF6EE]"
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
    <form method="GET" action="{{ route('superadmin.reports.index') }}" data-turbo="false" class="mb-6">
        <x-date-range-filter
            :range="$range"
            :selected-month="$selectedMonth"
            :selected-date="$selectedDate"
            :calendar-month="$calendarMonth"
        />
    </form>

    {{-- Stat cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="animate-fade-slide-up rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)] transition hover:-translate-y-0.5 hover:border-[#CDB9A8] [animation-delay:0ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Total Revenue') }}</div>
                    <div class="mt-2 text-2xl font-black tracking-[-0.02em] text-[#8A3330]">₱{{ number_format($totalRevenue, 2) }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['totalRevenue'] ?? null" />
            <p class="mt-3 border-t border-[#EEE6DC] pt-3 text-xs font-medium text-[#9A8B84]">{{ $rangeLabel }} &middot; {{ __('Paid orders, gross (before discounts)') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)] transition hover:-translate-y-0.5 hover:border-[#CDB9A8] [animation-delay:80ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Total Orders') }}</div>
                    <div class="mt-2 text-2xl font-black tracking-[-0.02em] text-[#251C19]">{{ $totalOrders }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25h10.176a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H15M9 3.75c0 1.036.84 1.875 1.875 1.875h2.25c1.036 0 1.875-.84 1.875-1.875M9 3.75c0-1.036.84-1.875 1.875-1.875h2.25c1.036 0 1.875.84 1.875 1.875M9 12h6m-6 3.75h6" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['totalOrders'] ?? null" />
            <p class="mt-3 border-t border-[#EEE6DC] pt-3 text-xs font-medium text-[#9A8B84]">{{ __('Excludes cancelled') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)] transition hover:-translate-y-0.5 hover:border-[#CDB9A8] [animation-delay:160ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Average Order Value') }}</div>
                    <div class="mt-2 text-2xl font-black tracking-[-0.02em] text-[#251C19]">₱{{ number_format($averageOrderValue, 2) }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['averageOrderValue'] ?? null" />
            <p class="mt-3 border-t border-[#EEE6DC] pt-3 text-xs font-medium text-[#9A8B84]">{{ __('Per paid order') }}</p>
        </div>

        <div class="animate-fade-slide-up rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)] transition hover:-translate-y-0.5 hover:border-[#CDB9A8] [animation-delay:240ms]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{{ __('Cancelled Orders') }}</div>
                    <div class="mt-2 text-2xl font-black tracking-[-0.02em] text-[#251C19]">{{ $cancelledOrders }}</div>
                </div>
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <x-trend-badge :data="$comparison['cancelledOrders'] ?? null" :invert="true" />
            <p class="mt-3 border-t border-[#EEE6DC] pt-3 text-xs font-medium text-[#9A8B84]">{{ $rangeLabel }}</p>
        </div>
    </div>

    {{-- Daily revenue chart --}}
    <div class="animate-fade-slide-up mb-6 rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] [animation-delay:320ms] sm:p-6">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4.5 w-4.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
            </span>
            <div>
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('Daily Revenue') }}</h3>
                <p class="text-xs text-[#9A8B84]">{{ $rangeLabel }}</p>
            </div>
        </div>

        @if ($dailySales->isEmpty())
            <p class="py-12 text-center text-sm text-[#B0A49E]">{{ __('No sales data for this period.') }}</p>
        @else
            @php
                $maxRevenue = $dailySales->max('revenue') ?: 1;
                $gridLines = [1, 0.75, 0.5, 0.25, 0];
            @endphp
            <div class="mt-5 flex gap-3">
                {{-- Y-axis labels --}}
                <div class="flex h-48 shrink-0 flex-col justify-between pb-1 text-right text-[10px] text-[#B0A49E]">
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
                                <span class="mt-2 whitespace-nowrap text-[10px] font-semibold text-[#9A8B84]">{{ \Illuminate\Support\Carbon::parse($day->sale_date)->format('M d') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Tax & discount summary — from persisted invoice snapshots, so this
         always matches what was actually shown on each invoice. --}}
    <div class="animate-fade-slide-up mb-6 rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] [animation-delay:360ms] sm:p-6">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4.5 w-4.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z" />
                </svg>
            </span>
            <div>
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('Tax & Discount Summary') }}</h3>
                <p class="text-xs text-[#9A8B84]">{{ __('By invoice date') }} &middot; {{ $rangeLabel }}</p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Net Collected') }}</p>
                <p class="mt-1 text-lg font-black text-[#8A3330]">₱{{ number_format($taxSummary['netAmountCollected'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('VATable Sales') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['vatableSales'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('VAT-Exempt Sales') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['vatExemptSales'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('VAT Amount') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['vatAmount'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Service Charges') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['serviceCharges'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Senior Discounts') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['seniorDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('PWD Discounts') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['pwdDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Promo Discounts') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">₱{{ number_format($taxSummary['promoDiscounts'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Voided Invoices') }}</p>
                <p class="mt-1 text-lg font-bold text-[#251C19]">{{ $taxSummary['voidedInvoices'] }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Best sellers --}}
        <div class="animate-fade-slide-up overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] [animation-delay:400ms]">
            <div class="border-b border-[#EEE5DC] px-5 py-4">
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('Best-Selling Items') }}</h3>
            </div>
            @if ($bestSellers->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-[#B0A49E]">{{ __('No item sales for this period.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#EEE5DC]">
                        <thead class="bg-[#FAF6EE]">
                            <tr>
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Item') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Qty') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Revenue') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEE5DC]">
                            @foreach ($bestSellers as $item)
                                <tr class="transition hover:bg-[#FAF6EE]">
                                    <td class="px-5 py-3 text-sm font-bold text-[#251C19]">{{ $item->item_name }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-[#6C5E57]">
                                        @if ($item->line_type === 'weighed')
                                            {{ number_format($item->total_net_grams / 1000, 3) }} {{ __('kg') }}
                                        @else
                                            {{ $item->total_qty }} {{ __('pc') }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-sm font-bold text-[#251C19]">₱{{ number_format($item->total_revenue, 2) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="h-1.5 w-10 overflow-hidden rounded-full bg-[#F3E1DC]">
                                                <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $item->percent) }}%"></div>
                                            </div>
                                            <span class="w-8 text-right text-xs font-bold text-[#9A8B84]">{{ number_format($item->percent, 0) }}%</span>
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
        <div class="animate-fade-slide-up overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] [animation-delay:480ms]">
            <div class="border-b border-[#EEE5DC] px-5 py-4">
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('Sales by Category') }}</h3>
            </div>
            @if ($categorySales->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-[#B0A49E]">{{ __('No category sales for this period.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#EEE5DC]">
                        <thead class="bg-[#FAF6EE]">
                            <tr>
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Category') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Revenue') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEE5DC]">
                            @foreach ($categorySales as $category)
                                <tr class="transition hover:bg-[#FAF6EE]">
                                    <td class="px-5 py-3 text-sm font-bold text-[#251C19]">{{ $category->category_name }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-bold text-[#251C19]">₱{{ number_format($category->total_revenue, 2) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="h-1.5 w-10 overflow-hidden rounded-full bg-[#F3E1DC]">
                                                <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $category->percent) }}%"></div>
                                            </div>
                                            <span class="w-8 text-right text-xs font-bold text-[#9A8B84]">{{ number_format($category->percent, 0) }}%</span>
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
        <div class="animate-fade-slide-up overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] [animation-delay:560ms]">
            <div class="border-b border-[#EEE5DC] px-5 py-4">
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('Sales by Area') }}</h3>
            </div>
            @if ($areaSales->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-[#B0A49E]">{{ __('No area sales for this period.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#EEE5DC]">
                        <thead class="bg-[#FAF6EE]">
                            <tr>
                                <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Area') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Orders') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Revenue') }}</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEE5DC]">
                            @foreach ($areaSales as $area)
                                <tr class="transition hover:bg-[#FAF6EE]">
                                    <td class="px-5 py-3 text-sm font-bold text-[#251C19]">{{ $area->area_name }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-[#6C5E57]">{{ $area->order_count }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-bold text-[#251C19]">₱{{ number_format($area->total_revenue, 2) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="h-1.5 w-10 overflow-hidden rounded-full bg-[#F3E1DC]">
                                                <div class="h-full rounded-full bg-[#8A3330]" style="width: {{ min(100, $area->percent) }}%"></div>
                                            </div>
                                            <span class="w-8 text-right text-xs font-bold text-[#9A8B84]">{{ number_format($area->percent, 0) }}%</span>
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
    <div class="animate-fade-slide-up mt-4 overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] [animation-delay:620ms]">
        <div class="border-b border-[#EEE5DC] px-5 py-4">
            <h3 class="text-sm font-bold text-[#251C19]">{{ __('Weighed Items') }}</h3>
        </div>
        @if ($weighedItems->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-[#B0A49E]">{{ __('No weighed-item sales for this period.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#EEE5DC]">
                    <thead class="bg-[#FAF6EE]">
                        <tr>
                            <th class="px-5 py-2.5 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Item') }}</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Total kg') }}</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Lines') }}</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Total Revenue') }}</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Avg Rate/kg') }}</th>
                            <th class="px-5 py-2.5 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#9A8B84]">{{ __('Variance Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEE5DC]">
                        @foreach ($weighedItems as $item)
                            <tr class="transition hover:bg-[#FAF6EE]">
                                <td class="px-5 py-3 text-sm font-bold text-[#251C19]">
                                    @if ($item->menu_item_id)
                                        <a
                                            href="{{ route('superadmin.reports.weighed-lines', ['item_id' => $item->menu_item_id] + $dateQuery) }}"
                                            data-turbo="false"
                                            class="hover:text-[#8A3330] hover:underline"
                                        >
                                            {{ $item->item_name }}
                                        </a>
                                    @else
                                        {{ $item->item_name }}
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-sm text-[#6C5E57]">{{ number_format($item->total_kg, 3) }} {{ __('kg') }}</td>
                                <td class="px-5 py-3 text-right text-sm text-[#6C5E57]">{{ $item->total_lines }}</td>
                                <td class="px-5 py-3 text-right text-sm font-bold text-[#251C19]">₱{{ number_format($item->total_revenue, 2) }}</td>
                                <td class="px-5 py-3 text-right text-sm text-[#6C5E57]">₱{{ number_format($item->avg_rate_per_kilo, 2) }}</td>
                                <td class="px-5 py-3 text-right text-sm font-bold {{ abs($item->variance_total) > 0.004 ? 'text-amber-700' : 'text-[#6C5E57]' }}">
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
