{{-- New massage order: guest details, service selection, and responsive order summary. --}}
<x-app-layout>
    @php
        $payload = [
            'services' => $services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => (float) $service->price,
                'variants' => $service->orderableVariants()->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'description' => $variant->description,
                    'price' => (float) $variant->price,
                    'duration' => $variant->durationLabel(),
                    'is_default' => $variant->is_default,
                ])->values(),
                'add_ons' => $service->addOns->map(fn ($addOn) => [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'description' => $addOn->description,
                    'price' => (float) $addOn->price,
                ])->values(),
            ])->values(),
            'initial' => collect(old('items', []))->map(fn ($row) => [
                'service_id' => (int) ($row['service_id'] ?? 0),
                'variant_id' => isset($row['variant_id']) && $row['variant_id'] !== '' ? (int) $row['variant_id'] : null,
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'add_ons' => collect($row['add_ons'] ?? [])->map(fn ($a) => ['id' => (int) ($a['id'] ?? 0), 'quantity' => (int) ($a['quantity'] ?? 1)])->values(),
            ])->values(),
            'roomNumber' => (string) old('room_number', ''),
            'guestName' => (string) old('guest_name', ''),
            'roomLabel' => __('Room :number'),
        ];
    @endphp

    <form method="POST" action="{{ route('massage.orders.store') }}" x-data="massageOrderForm(@js($payload))" @submit.prevent="submit()">
        @csrf

        {{-- Pinned search, as on the restaurant's New Order --}}
        <div class="sticky top-[65px] z-20 -mx-4 -mt-4 mb-4 border-b border-[#E2E8F0] bg-white/95 px-4 py-2.5 backdrop-blur-md sm:-mx-6 sm:-mt-6 sm:mb-6 sm:px-6">
            <div class="relative min-w-0 flex-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-[#94A3B8]" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.197 5.197a7.5 7.5 0 0010.606 10.606z" />
                </svg>
                <input
                    type="search"
                    x-model.debounce.150ms="search"
                    placeholder="{{ __('Search services...') }}"
                    aria-label="{{ __('Search services...') }}"
                    class="block h-10 w-full truncate rounded-xl border-[#E2E8F0] bg-[#F8FAFC] py-0 pl-10 pr-10 text-sm text-[#1E293B] placeholder:text-[#94A3B8] focus:border-[#1E293B] focus:ring-[#1E293B]/20 [&::-webkit-search-cancel-button]:appearance-none"
                >
                <button type="button" x-show="search" x-cloak @click="search = ''" class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-lg text-[#94A3B8] transition hover:bg-[#F1F5F9] hover:text-[#1E293B]" aria-label="{{ __('Clear search') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Order heading --}}
        <section class="relative isolate mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8 sm:py-7">

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-4 sm:items-center sm:gap-5">
                    <a href="{{ route('massage.orders.index') }}" class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-600 backdrop-blur-sm transition hover:-translate-x-0.5 hover:bg-slate-200 hover:text-slate-900 sm:h-12 sm:w-12" aria-label="{{ __('Back to massage orders') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </a>

                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 backdrop-blur-sm">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full  rounded-full bg-emerald-500 opacity-35"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                            </span>
                            {{ __('Draft massage order') }}
                        </span>
                        <h2 class="mt-2 text-2xl font-bold tracking-[-0.035em] text-slate-900 sm:text-3xl">{{ __('Create a new massage order') }}</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">{{ __('Enter the room number, then build the order from the massage services.') }}</p>
                    </div>
                </div>

                <div class="hidden shrink-0 items-center xl:flex">
                    <div class="flex items-center rounded-2xl border border-slate-200 bg-slate-50 p-2 backdrop-blur-sm">
                        <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-bold text-[#1E293B] shadow-sm">
                            <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#1E293B] text-[10px] text-white">1</span>
                            {{ __('Room / Guest') }}
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mx-1 h-4 w-4 text-slate-300" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                        <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-slate-500">
                            <span class="grid h-6 w-6 place-items-center rounded-lg border border-slate-200 bg-white text-[10px] text-slate-500">2</span>
                            {{ __('Services & review') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="font-semibold">{{ __('Some order details need your attention.') }}</p>
                    @foreach ($errors->all() as $error)
                        <p class="mt-0.5 text-xs text-red-600">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_410px]">
            <div class="min-w-0 space-y-6 pb-36 lg:pb-0">
                {{-- Step 1: Room / guest --}}
                <section class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-[#E2E8F0] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex items-start gap-3.5">
                            <span :class="hasGuest ? 'bg-emerald-600 text-white' : 'bg-[#1E293B] text-white'" class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl text-sm font-bold shadow-sm transition">
                                <svg x-show="hasGuest" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                <span x-show="!hasGuest">01</span>
                            </span>
                            <div>
                                <h3 class="text-base font-bold tracking-[-0.015em] text-[#1E293B]">{{ __('Who is the massage for?') }}</h3>
                                <p class="mt-1 text-sm leading-6 text-[#64748B]">{{ __('Enter the room number. No room? Enter the guest name instead.') }}</p>
                            </div>
                        </div>

                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-[#F8FAFC] px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#64748B]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5 text-[#1E293B]" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ __('Required') }}
                        </span>
                    </div>

                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="room_number" class="block text-sm font-bold text-[#1E293B]">{{ __('Room No.') }}</label>
                            <input id="room_number" name="room_number" type="text" maxlength="50" x-model="roomNumber" placeholder="{{ __('e.g. 204') }}"
                                   class="mt-2 block min-h-11 w-full rounded-xl border-[#E2E8F0] bg-white text-sm font-medium text-[#1E293B] shadow-none focus:border-[#1E293B] focus:ring-[#1E293B]/20">
                            <x-input-error :messages="$errors->get('room_number')" class="mt-2" />
                        </div>
                        <div>
                            <label for="guest_name" class="block text-sm font-bold text-[#1E293B]">
                                {{ __('Guest name') }}
                                <span class="ml-1 text-xs font-semibold uppercase tracking-[0.1em] text-[#94A3B8]">{{ __('Optional') }}</span>
                            </label>
                            <input id="guest_name" name="guest_name" type="text" maxlength="100" x-model="guestName"
                                   class="mt-2 block min-h-11 w-full rounded-xl border-[#E2E8F0] bg-white text-sm font-medium text-[#1E293B] shadow-none focus:border-[#1E293B] focus:ring-[#1E293B]/20">
                        </div>
                    </div>
                </section>

                {{-- Step 2: Services --}}
                <section class="scroll-mt-36 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-5 sm:px-6">
                        <div class="flex items-start gap-3.5">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#1E293B] text-sm font-bold text-white shadow-sm">02</span>
                            <div>
                                <h3 class="text-base font-bold tracking-[-0.015em] text-[#1E293B]">{{ __('Choose the massage services') }}</h3>
                                <p class="mt-1 text-sm leading-6 text-[#64748B]">{{ __('Tap a massage service to add it.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        @if ($services->isEmpty())
                            <p class="py-8 text-center text-sm text-[#64748B]">{{ __('Add the massage services in Massage Services first.') }}</p>
                        @else
                            <div class="mb-4 flex items-center gap-2.5">
                                <span class="h-6 w-1 rounded-full bg-[#1E293B]"></span>
                                <h4 class="text-base font-bold tracking-[-0.015em] text-[#1E293B]">{{ __('Massage') }}</h4>
                                <span class="text-xs text-[#94A3B8]">{{ trans_choice(':count service|:count services', $services->count(), ['count' => $services->count()]) }}</span>
                            </div>

                            <p x-show="!hasMatches" x-cloak class="py-8 text-center text-sm text-[#64748B]">{{ __('No services match your search.') }}</p>

                            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                @foreach ($services as $service)
                                    <button
                                        type="button"
                                        x-show="matchesSearch(@js($service->name))"
                                        @click="tap({{ $service->id }})"
                                        class="group relative flex min-h-[118px] items-center gap-3 overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-[#1E293B]/35 hover:bg-slate-50 hover:shadow-sm focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#1E293B]/10"
                                    >

                                        @if ($service->primaryImageUrl())
                                            <span class="relative h-12 w-12 shrink-0 overflow-hidden rounded-2xl border border-[#1E293B]/10 bg-[#F1F5F9]">
                                                <img src="{{ $service->primaryImageUrl() }}" alt="{{ $service->name }}" loading="lazy" class="h-full w-full object-cover">
                                            </span>
                                        @else
                                            <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-[#1E293B]/10 bg-[#F1F5F9] text-[#1E293B] transition group-hover:bg-[#1E293B] group-hover:text-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15.5c-1.5.5-2 1.2-2 2 0 1.7 4 3 9 3s9-1.3 9-3c0-.8-.5-1.5-2-2M7 10c-2 .5-3 1.3-3 2.3 0 1.6 3.6 2.7 8 2.7s8-1.1 8-2.7c0-1-1-1.8-3-2.3M17 6.5C17 8 14.8 9 12 9S7 8 7 6.5 9.2 4 12 4s5 1 5 2.5Z" />
                                                </svg>
                                            </span>
                                        @endif

                                        <span class="relative min-w-0 flex-1">
                                            <span class="block break-words text-sm font-bold leading-5 text-[#1E293B]">{{ $service->name }}</span>
                                            @if ($service->duration_minutes)
                                                <span class="mt-0.5 block text-[11px] text-[#94A3B8]">{{ $service->durationLabel() }}</span>
                                            @endif
                                            @php $hasOptions = $service->orderableVariants()->isNotEmpty() || $service->addOns->isNotEmpty(); @endphp
                                            <span class="mt-1.5 block text-sm font-bold text-[#1E293B]">{{ $service->priceLabel() }}</span>
                                            <span class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[#1E293B]">
                                                {{ $hasOptions ? __('Choose option') : __('Add to order') }}
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3 w-3 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                </svg>
                                            </span>
                                        </span>

                                        <span x-show="quantity({{ $service->id }}) > 0" x-cloak x-text="quantity({{ $service->id }})"
                                              class="relative grid h-7 min-w-7 shrink-0 place-items-center rounded-full bg-[#1E293B] px-1.5 text-xs font-bold text-white shadow-sm"></span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            </div>

            {{-- Order summary --}}
            <aside id="order-summary" x-cloak :class="summaryOpen ? 'block' : 'hidden lg:block'" class="w-full scroll-mt-36 pb-36 lg:sticky lg:top-[8.5rem] lg:pb-0">
                <section class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="relative overflow-hidden border-b border-slate-200 bg-slate-50/70 px-5 py-5 text-slate-900 sm:px-6">

                        <div class="relative flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ __('Current massage order') }}</p>
                                <h3 class="mt-1.5 text-lg font-bold tracking-[-0.02em]">{{ __('Massage Summary') }}</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    {{ __('Massage') }}<span x-show="hasGuest"> · </span><span x-show="hasGuest" x-text="guestLabel"></span>
                                </p>
                            </div>
                            <div class="flex h-11 min-w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-3 backdrop-blur-sm">
                                <span class="text-lg font-bold" x-text="cartCount"></span>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <div x-show="isEmpty" class="py-5 text-center">
                            <div class="relative mx-auto grid h-16 w-16 place-items-center rounded-[1.35rem] border border-[#1E293B]/10 bg-[#F1F5F9] text-[#1E293B]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                </svg>
                            </div>
                            <h4 class="mt-4 text-sm font-bold text-[#1E293B]">{{ __('No massage added yet') }}</h4>
                            <p class="mx-auto mt-1 max-w-xs text-xs leading-5 text-[#64748B]">{{ __('Tap a massage service to add it here.') }}</p>
                        </div>

                        <div x-show="!isEmpty" x-cloak>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">{{ __('Massage services') }}</p>
                                <button type="button" @click="clearCart()" class="text-xs font-semibold text-[#1E293B] transition hover:text-[#334155] hover:underline">{{ __('Clear all') }}</button>
                            </div>

                            <div class="max-h-[360px] space-y-2.5 overflow-y-auto pr-1">
                                <template x-for="(line, index) in cart" :key="line.key">
                                    <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-3.5">
                                        <div class="flex items-start gap-3">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F1F5F9] text-xs font-bold text-[#1E293B]" x-text="index + 1"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="break-words text-sm font-bold leading-5 text-[#1E293B]" x-text="line.name"></p>
                                                <template x-for="addOn in line.addOns" :key="addOn.id">
                                                    <p class="mt-0.5 text-xs text-[#64748B]" x-text="'+ ' + addOn.name + (addOn.qty > 1 ? ' × ' + addOn.qty : '')"></p>
                                                </template>
                                                <p class="mt-1 text-xs text-[#64748B]" x-text="formatMoney(unitTotal(line)) + ' ' + @js(__('each'))"></p>
                                            </div>
                                            <p class="shrink-0 text-sm font-bold text-[#1E293B]" x-text="formatMoney(lineTotal(line))"></p>
                                        </div>

                                        <div class="mt-3 flex items-center justify-end">
                                            <div class="inline-flex items-center rounded-xl border border-[#E2E8F0] bg-white p-1">
                                                <button type="button" @click="decrement(index)" class="grid h-7 w-7 place-items-center rounded-lg text-[#64748B] transition hover:bg-[#F1F5F9] hover:text-[#1E293B]" :aria-label="'- ' + line.name">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                                </button>
                                                <span class="w-8 text-center text-xs font-bold text-[#1E293B]" x-text="line.qty"></span>
                                                <button type="button" @click="increment(index)" class="grid h-7 w-7 place-items-center rounded-lg bg-[#1E293B] text-white transition hover:bg-[#1E293B]" :aria-label="'+ ' + line.name">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>
                                                </button>
                                            </div>
                                        </div>

                                        <input type="hidden" :name="'items[' + index + '][service_id]'" :value="line.id">
                                        <input type="hidden" :name="'items[' + index + '][variant_id]'" :value="line.variantId ?? ''">
                                        <input type="hidden" :name="'items[' + index + '][quantity]'" :value="line.qty">
                                        <template x-for="(addOn, a) in line.addOns" :key="addOn.id">
                                            <span>
                                                <input type="hidden" :name="'items[' + index + '][add_ons][' + a + '][id]'" :value="addOn.id">
                                                <input type="hidden" :name="'items[' + index + '][add_ons][' + a + '][quantity]'" :value="addOn.qty">
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="mt-5 border-t border-dashed border-[#E2E8F0] pt-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold text-[#64748B]">{{ __('Massage total') }}</p>
                                    <p class="mt-0.5 text-[10px] uppercase tracking-[0.12em] text-[#94A3B8]">{{ __('Calculated automatically') }}</p>
                                </div>
                                <span class="text-2xl font-bold tracking-[-0.03em] text-[#1E293B]" x-text="formatMoney(total)"></span>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label for="notes" class="flex items-center justify-between gap-3 text-sm font-bold text-[#1E293B]">
                                <span>{{ __('Massage notes') }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">{{ __('Optional') }}</span>
                            </label>
                            <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="{{ __('Therapist, time, or guest requests...') }}"
                                      class="mt-2 block w-full resize-none rounded-2xl border-[#E2E8F0] bg-[#F8FAFC] px-3.5 py-3 text-sm text-[#1E293B] placeholder:text-[#94A3B8] focus:border-[#1E293B] focus:ring-[#1E293B]/20">{{ old('notes') }}</textarea>
                        </div>

                        <div class="mt-5">
                            <div x-show="!hasGuest" x-cloak class="mb-3 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                                {{ __('Enter the room number or guest name above before placing the massage order.') }}
                            </div>
                            <div x-show="hasGuest && isEmpty" x-cloak class="mb-3 flex items-start gap-2 rounded-xl bg-[#F8FAFC] px-3 py-2.5 text-xs leading-5 text-[#64748B]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-[#1E293B]" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('Add at least one massage service to continue.') }}
                            </div>

                            <button type="submit" :disabled="!canSubmit || submitting"
                                    class="group inline-flex w-full items-center justify-center gap-2.5 rounded-2xl bg-[#1E293B] px-4 py-3.5 text-sm font-bold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[#334155] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#1E293B]/20 disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:translate-y-0 disabled:hover:bg-[#1E293B]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('Place Massage Order') }}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </button>

                            <a href="{{ route('massage.orders.index') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-[#64748B] transition hover:bg-[#F8FAFC] hover:text-[#1E293B]">
                                {{ __('Cancel and return') }}
                            </a>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        {{-- Phones and portrait tablets: running total and Place Order at the bottom --}}
        <div class="fixed inset-x-0 bottom-0 z-40 space-y-2 border-t border-[#E2E8F0] bg-white/95 px-4 py-3 shadow-sm backdrop-blur-md lg:hidden">
            <button type="button" @click="toggleSummary()" :aria-expanded="summaryOpen" aria-controls="order-summary" class="flex w-full items-center justify-between gap-3 rounded-2xl bg-[#1E293B] px-4 py-3 text-white">
                <span class="flex items-center gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-white/10 text-sm font-bold" x-text="cartCount"></span>
                    <span class="text-sm font-semibold">{{ __('Massage Summary') }}</span>
                </span>
                <span class="text-base font-bold" x-show="!isEmpty" x-text="formatMoney(total)"></span>
            </button>
            <button type="submit" :disabled="submitting" x-show="canSubmit" x-cloak class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#1E293B] px-4 py-3.5 text-sm font-bold text-white shadow-sm">
                {{ __('Place Massage Order') }}
            </button>
        </div>

        {{-- Variant and add-on picker: opens when the tapped service has
             variants (e.g. 30 mins / 1 hr) or add-ons (e.g. Hot Stone). --}}
        <div x-show="picker" x-cloak x-transition.opacity @keydown.escape.window="closePicker()" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true">
            <div @click.outside="closePicker()" class="flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-[1.75rem] bg-white shadow-sm sm:rounded-2xl">
                <template x-if="picker">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="relative overflow-hidden border-b border-slate-200 bg-slate-50/70 px-5 py-5 text-slate-900 sm:px-6">
                            <div class="relative flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ __('Choose option') }}</p>
                                    <h3 class="mt-1.5 break-words text-lg font-bold tracking-[-0.02em]" x-text="picker.service.name"></h3>
                                </div>
                                <button type="button" @click="closePicker()" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200" aria-label="{{ __('Close') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">
                            <template x-if="picker.service.variants.length">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">{{ __('Variant') }}</p>
                                    <div class="mt-2 space-y-2">
                                        <template x-for="variant in picker.service.variants" :key="variant.id">
                                            <button type="button" @click="picker.variantId = variant.id"
                                                    :class="picker.variantId === variant.id ? 'border-[#1E293B] bg-[#1E293B] text-white' : 'border-[#E2E8F0] bg-[#F8FAFC] text-[#1E293B] hover:border-[#1E293B]/40'"
                                                    class="flex w-full items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition">
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-bold" x-text="variant.name"></span>
                                                    <span class="block text-xs" :class="picker.variantId === variant.id ? 'text-slate-500' : 'text-[#85766F]'" x-text="[variant.duration, variant.description].filter(Boolean).join(' · ')"></span>
                                                </span>
                                                <span class="shrink-0 text-sm font-bold" x-text="formatMoney(variant.price)"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="picker.service.add_ons.length">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">{{ __('Add-ons') }}</p>
                                    <div class="mt-2 space-y-2">
                                        <template x-for="addOn in picker.service.add_ons" :key="addOn.id">
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3"
                                                 :class="picker.addOnQty[addOn.id] > 0 && 'border-[#1E293B]/40 bg-[#FAF3EE]'">
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-bold text-[#1E293B]" x-text="addOn.name"></span>
                                                    <span class="block text-xs text-[#85766F]" x-text="['+ ' + formatMoney(addOn.price), addOn.description].filter(Boolean).join(' · ')"></span>
                                                </span>
                                                <div class="inline-flex shrink-0 items-center rounded-xl border border-[#E2E8F0] bg-white p-1">
                                                    <button type="button" @click="changeAddOn(addOn.id, -1)" :disabled="picker.addOnQty[addOn.id] === 0" class="grid h-8 w-8 place-items-center rounded-lg text-[#64748B] hover:bg-[#F1F5F9] disabled:opacity-30" :aria-label="'- ' + addOn.name">&minus;</button>
                                                    <span class="w-7 text-center text-sm font-bold text-[#1E293B]" x-text="picker.addOnQty[addOn.id]"></span>
                                                    <button type="button" @click="changeAddOn(addOn.id, 1)" class="grid h-8 w-8 place-items-center rounded-lg bg-[#1E293B] text-white hover:bg-[#1E293B]" :aria-label="'+ ' + addOn.name">+</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <p class="mt-2 text-[11px] text-[#94A3B8]">{{ __('Add-ons are per massage.') }}</p>
                                </div>
                            </template>

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">{{ __('How many') }}</p>
                                <div class="inline-flex items-center rounded-xl border border-[#E2E8F0] bg-white p-1">
                                    <button type="button" @click="changePickerQty(-1)" :disabled="picker.qty === 1" class="grid h-8 w-8 place-items-center rounded-lg text-[#64748B] hover:bg-[#F1F5F9] disabled:opacity-30" aria-label="{{ __('One fewer') }}">&minus;</button>
                                    <span class="w-7 text-center text-sm font-bold text-[#1E293B]" x-text="picker.qty"></span>
                                    <button type="button" @click="changePickerQty(1)" class="grid h-8 w-8 place-items-center rounded-lg bg-[#1E293B] text-white hover:bg-[#1E293B]" aria-label="{{ __('One more') }}">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-[#E2E8F0] p-5 sm:p-6">
                            <button type="button" @click="confirmPicker()" :disabled="!pickerReady"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#1E293B] px-4 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#334155] disabled:opacity-40">
                                {{ __('Add to order') }}
                                <span x-text="'· ' + formatMoney(pickerTotal)"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </form>
</x-app-layout>
