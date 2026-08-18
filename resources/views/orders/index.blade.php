<x-app-layout>
    <x-slot name="header">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7">
            {{-- Subtle grid texture --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>

            {{-- Decorative glows --}}
            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
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
                            <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                                {{ __('Order Management') }}
                            </h2>

                            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[11px] font-bold text-white/80 backdrop-blur-sm">
                                {{ trans_choice(':count order|:count orders', $totalOrders, ['count' => $totalOrders]) }}
                            </span>
                        </div>

                        <p class="mt-1 text-sm leading-6 text-white/60">
                            {{ __('Monitor, process and manage customer orders in one workspace.') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($orders->isEmpty())
                        {{-- Avoid a duplicated CTA because the onboarding panel already has one. --}}
                        <div class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-semibold text-white/80 backdrop-blur-sm">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-40"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                            </span>

                            {{ __('Ready for your first order') }}
                        </div>
                    @else
                        <a
                            href="{{ route('orders.create') }}"
                            class="group inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-bold text-[#7E302D] shadow-[0_12px_28px_-16px_rgba(0,0,0,0.65)] transition duration-200 hover:-translate-y-0.5 hover:bg-[#FFF7F3] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/20"
                        >
                            <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#8A3330]/10">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2.3"
                                    stroke="currentColor"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </span>

                            {{ __('New Order') }}

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
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
        <div
            x-data="{
                selectedStatus: 'all',
                statusCounts: {{ Js::from($statusCounts) }},
                isVisible(status) {
                    return this.selectedStatus === 'all' || this.selectedStatus === status;
                },
                get hasVisibleOrders() {
                    return this.selectedStatus === 'all' || !!this.statusCounts[this.selectedStatus];
                },
            }"
        >
            {{-- Status filter panel --}}
            <section class="mb-6 rounded-[1.75rem] border border-[#E6DCCF] bg-white p-4 shadow-[0_18px_45px_-35px_rgba(57,37,32,0.55)] sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#F3E1DC] text-[#8A3330]">
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
                            <h3 class="text-sm font-bold text-[#261D1A]">
                                {{ __('Order pipeline') }}
                            </h3>

                            <p class="mt-0.5 text-xs leading-5 text-[#80716A]">
                                {{ __('Filter the workspace by the current order status.') }}
                            </p>
                        </div>
                    </div>

                    <div class="inline-flex w-fit items-center gap-2 rounded-full border border-[#E8DED2] bg-[#FBF8F3] px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#75665F]">
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
                            ? 'border-[#241917] bg-[#241917] text-white shadow-[0_10px_22px_-14px_rgba(36,25,23,0.9)]'
                            : 'border-[#E7DED3] bg-[#FBF8F3] text-[#655750] hover:border-[#8A3330]/30 hover:bg-[#F7EFE9] hover:text-[#8A3330]'"
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
                            {{ $totalOrders }}
                        </span>
                    </button>

                    @foreach (\App\Enums\OrderStatus::cases() as $status)
                        <button
                            type="button"
                            @click="selectedStatus = '{{ $status->value }}'"
                            :aria-pressed="selectedStatus === '{{ $status->value }}'"
                            :class="selectedStatus === '{{ $status->value }}'
                                ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_10px_22px_-14px_rgba(138,51,48,0.9)]'
                                : 'border-[#E7DED3] bg-[#FBF8F3] text-[#655750] hover:border-[#8A3330]/30 hover:bg-[#F7EFE9] hover:text-[#8A3330]'"
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
                                {{ $statusCounts[$status->value] ?? 0 }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- No orders in the selected status --}}
            <div x-show="!hasVisibleOrders" x-transition.opacity x-cloak>
                <x-empty-state
                    :title="__('No orders in this status')"
                    :description="__('Select another status to view the available orders.')"
                />
            </div>

            {{-- Desktop table --}}
            <section
                x-show="hasVisibleOrders"
                x-transition.opacity
                x-cloak
                class="hidden overflow-hidden rounded-[1.75rem] border border-[#E6DCCF] bg-white shadow-[0_22px_55px_-40px_rgba(55,35,30,0.6)] sm:block"
            >
                <div class="flex items-center justify-between border-b border-[#EEE6DC] px-6 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-[#261D1A]">
                            {{ __('Current orders') }}
                        </h3>

                        <p class="mt-0.5 text-xs text-[#8A7B74]">
                            {{ __('Open an order to review its items, payment and status.') }}
                        </p>
                    </div>

                    <span class="inline-flex items-center rounded-full bg-[#F5ECE7] px-3 py-1.5 text-xs font-bold text-[#8A3330]">
                        {{ trans_choice(':count record|:count records', $orders->count(), ['count' => $orders->count()]) }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-[#FBF8F3]">
                            <tr class="border-b border-[#EEE6DC]">
                                <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Order') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Location') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Status') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Payment') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Total') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Created') }}
                                </th>

                                <th scope="col" class="px-6 py-3.5 text-right text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">
                                    {{ __('Action') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#F0E9E1]">
                            @foreach ($orders as $order)
                                <tr
                                    x-show="isVisible('{{ $order->status->value }}')"
                                    x-cloak
                                    class="group transition duration-150 hover:bg-[#FFFCF8]"
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
                                                <p class="font-mono text-sm font-bold text-[#251C19]">
                                                    {{ $order->orderNumber() }}
                                                </p>

                                                <p class="mt-0.5 max-w-[180px] truncate text-xs text-[#94857E]">
                                                    {{ $order->customer_name ?? __('Walk-in customer') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-[#554741]">
                                            <span class="grid h-8 w-8 place-items-center rounded-xl bg-[#F7F2EC] text-[#8A7B74]">
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

                                            {{ $order->locationLabel() }}
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
                                                <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#A0938D]">
                                                    {{ $order->payment_method->label() }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="text-sm font-bold text-[#8A3330]">
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
                                                class="mt-0.5 h-4 w-4 shrink-0 text-[#A0938D]"
                                                aria-hidden="true"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                            </svg>

                                            <div>
                                                <p class="text-sm font-medium text-[#655750]">
                                                    {{ $order->created_at->format('M d, g:i A') }}
                                                </p>

                                                <p class="mt-0.5 text-xs text-[#A0938D]">
                                                    {{ $order->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <a
                                            href="{{ route('orders.show', $order) }}"
                                            class="inline-flex items-center gap-2 rounded-xl border border-[#E5DCD1] bg-white px-3.5 py-2 text-xs font-bold text-[#8A3330] transition duration-150 hover:border-[#8A3330]/30 hover:bg-[#F9F1EC] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15"
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
            <div x-show="hasVisibleOrders" x-transition.opacity x-cloak class="space-y-3 sm:hidden">
                @foreach ($orders as $order)
                    <a
                        href="{{ route('orders.show', $order) }}"
                        x-show="isVisible('{{ $order->status->value }}')"
                        x-cloak
                        class="group relative block overflow-hidden rounded-[1.5rem] border border-[#E6DCCF] bg-white p-4 shadow-[0_18px_40px_-32px_rgba(57,37,32,0.65)] transition duration-200 active:scale-[0.99]"
                    >
                        <div aria-hidden="true" class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-[#F7EEE8]"></div>

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
                                        <p class="truncate font-mono text-sm font-bold text-[#251C19]">
                                            {{ $order->orderNumber() }}
                                        </p>

                                        <p class="mt-0.5 truncate text-xs text-[#94857E]">
                                            {{ $order->customer_name ?? __('Walk-in customer') }}
                                        </p>
                                    </div>
                                </div>

                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold leading-5 {{ $order->status->badgeClasses() }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $order->status->dotClasses() }}"></span>
                                    {{ $order->status->label() }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-[#FBF8F3] p-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#A0938D]">
                                        {{ __('Location') }}
                                    </p>

                                    <p class="mt-1 truncate text-xs font-semibold text-[#554741]">
                                        {{ $order->locationLabel() }}
                                    </p>
                                </div>

                                <div class="border-l border-[#E7DED3] pl-3">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#A0938D]">
                                        {{ __('Created') }}
                                    </p>

                                    <p class="mt-1 truncate text-xs font-semibold text-[#554741]">
                                        {{ $order->created_at->format('M d, g:i A') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#A0938D]">
                                        {{ __('Payment') }}
                                    </p>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold leading-5 {{ $order->payment_status->badgeClasses() }}">
                                            {{ $order->payment_status->label() }}
                                        </span>

                                        @if ($order->payment_method)
                                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#A0938D]">
                                                {{ $order->payment_method->label() }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#A0938D]">
                                        {{ __('Total') }}
                                    </p>

                                    <p class="mt-1 text-lg font-bold tracking-tight text-[#8A3330]">
                                        ₱{{ number_format($order->total_amount, 2) }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-[#EEE6DC] pt-3">
                                <p class="text-xs text-[#A0938D]">
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