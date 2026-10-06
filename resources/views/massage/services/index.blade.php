{{-- Massage Services: Menu Management's layout (Pages/MenuItems/Index.jsx)
     for the massage services — dark catalog heading with counts, the search
     card, and the same item cards with the availability pill. --}}
@php
    $canManage = in_array(auth()->user()->role, [\App\Enums\UserRole::Superadmin, \App\Enums\UserRole::Admin], true);
    $activeCount = \App\Models\MassageService::count();
    $availableCount = \App\Models\MassageService::available()->count();
@endphp

<x-app-layout>
    <div class="space-y-7 pb-10" x-data="{ q: '' }">
        <header class="rounded-2xl border border-slate-200 bg-white px-5 py-6 shadow-sm sm:px-7">

            <div class="relative">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex max-w-2xl items-start gap-4 sm:gap-5">
                        <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-600 shadow-sm backdrop-blur-sm sm:h-16 sm:w-16">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.65" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">{{ __('Massage catalog') }}</span>
                                <span class="h-1 w-1 rounded-full bg-slate-50"></span>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $archived ? 'bg-slate-300' : 'bg-emerald-300' }}"></span>
                                    {{ $archived ? __('Archived view') : __('Active services') }}
                                </span>
                            </div>
                            <h1 class="mt-2 text-2xl font-semibold tracking-[-0.035em] text-slate-900 sm:text-3xl">{{ __('Massage Services') }}</h1>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-slate-600 sm:text-[15px]">{{ __('Manage the massage services, their prices and availability from one organized list.') }}</p>
                        </div>
                    </div>

                    @if ($canManage)
                        <div class="flex flex-col gap-2.5 sm:flex-row xl:justify-end">
                            <a href="{{ route('massage.services.create') }}" data-turbo="false" class="group inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-500 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50">
                                <span class="grid h-6 w-6 place-items-center rounded-lg bg-slate-700/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6M13 3l5 5v3M13 3v5h5M8 12h3m-3 4h2M18 14v6m-3-3h6" /></svg>
                                </span>
                                {{ __('New Massage Service') }}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                        </div>
                    @endif
                </div>

                <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => __('Massages shown'), 'value' => $services->count(), 'detail' => __('current view'), 'accent' => 'bg-slate-100 text-slate-600', 'icon' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
                        ['label' => __('All massages'), 'value' => $activeCount, 'detail' => __('on the list'), 'accent' => 'bg-slate-100 text-slate-600/80', 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z'],
                        ['label' => __('Available now'), 'value' => $availableCount, 'detail' => __('ready to sell'), 'accent' => 'bg-emerald-50 text-emerald-600', 'icon' => 'M4.5 12.75l6 6 9-13.5'],
                        ['label' => __('Archived'), 'value' => $archivedCount, 'detail' => __('stored items'), 'accent' => 'bg-amber-50 text-amber-700', 'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
                    ] as $metric)
                        <div class="flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3.5 backdrop-blur-sm">
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $metric['accent'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $metric['icon'] }}" /></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-slate-600">{{ $metric['label'] }}</p>
                                <div class="mt-0.5 flex min-w-0 items-baseline gap-1.5">
                                    <p class="text-lg font-semibold leading-none text-slate-900">{{ $metric['value'] }}</p>
                                    <p class="truncate text-[10px] font-medium text-slate-600">{{ $metric['detail'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </header>

        {{-- Find and organize --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">{{ __('Find and organize massage services') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Search by name, or switch to the archived list.') }}</p>
                    </div>
                </div>

                <div class="inline-flex w-fit items-center gap-1 rounded-2xl border border-slate-200 bg-slate-50 p-1">
                    <a href="{{ route('massage.services.index') }}" class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $archived ? 'text-slate-500 hover:text-slate-900' : 'bg-white text-slate-700 shadow-sm' }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Active') }}
                    </a>
                    <a href="{{ route('massage.services.index', ['archived' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $archived ? 'bg-white text-slate-700 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">
                        {{ __('Archived') }}
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-50 px-1.5 text-[10px]">{{ $archivedCount }}</span>
                    </a>
                </div>
            </div>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <label class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500" for="service-search">{{ __('Search massage services') }}</label>
                <div class="relative mt-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-500" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.197 5.197a7.5 7.5 0 0010.606 10.606z" /></svg>
                    <input id="service-search" type="search" x-model="q" placeholder="{{ __('Search by massage name or description...') }}"
                           class="block h-12 w-full rounded-2xl border-slate-200 bg-slate-50 pl-10 text-sm text-slate-900 placeholder:text-slate-500 focus:border-slate-200 focus:ring-slate-400/20">
                </div>
            </div>
        </section>

        @if ($services->isEmpty())
            <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm sm:px-10">
                <h3 class="text-lg font-semibold text-slate-900">{{ $archived ? __('No archived massage services') : __('No massage services yet') }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    {{ $archived ? __('Services you archive will appear here and can be restored at any time.') : ($canManage ? __('Add the first massage service.') : __('An Admin adds the services.')) }}
                </p>
                @if ($canManage && ! $archived)
                    <a href="{{ route('massage.services.create') }}" data-turbo="false" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">{{ __('New Massage Service') }}</a>
                @endif
            </section>
        @else
            <section>
                <div class="mb-4 flex items-center gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm">
                        <span class="text-xs font-semibold">01</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold tracking-[-0.025em] text-slate-900 sm:text-xl">{{ __('MASSAGE') }}</h2>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">{{ trans_choice(':count service|:count services', $services->count(), ['count' => $services->count()]) }}</p>
                    </div>
                    <div class="h-px flex-1 bg-gradient-to-r from-[#DED1C5] to-transparent"></div>
                    <span class="hidden items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500 sm:inline-flex">{{ __('Category') }}</span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                    @foreach ($services as $service)
                        @php
                            $available = $service->is_available;
                            $pill = $archived
                                ? ['label' => __('Archived'), 'classes' => 'border border-slate-200 bg-slate-100/95 text-slate-600', 'dot' => 'bg-slate-400']
                                : ($available
                                    ? ['label' => __('Available'), 'classes' => 'bg-green-100/95 text-green-800', 'dot' => 'bg-emerald-500']
                                    : ['label' => __('Unavailable'), 'classes' => 'bg-gray-200/95 text-gray-700', 'dot' => 'bg-gray-500']);
                        @endphp
                        <article
                            x-show="q.trim() === '' || @js(mb_strtolower($service->name.' '.$service->descriptionText())).includes(q.trim().toLowerCase())"
                            class="group relative z-0 flex min-h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:z-20 hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-sm focus-within:z-30"
                        >
                            <div class="absolute inset-x-7 top-0 h-1 rounded-b-full {{ $archived ? 'bg-slate-500' : ($available ? 'bg-emerald-500' : 'bg-gray-400') }}"></div>

                            <div class="relative px-3 pt-3">
                                <div class="relative aspect-[16/11] overflow-hidden rounded-[1.25rem] bg-slate-50">
                                    @if ($service->primaryImageUrl())
                                        <img src="{{ $service->primaryImageUrl() }}" alt="{{ $service->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.045] {{ $archived ? 'grayscale-[35%]' : '' }}">
                                    @else
                                    <div class="absolute inset-0 overflow-hidden bg-slate-50">
                                        <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full border border-slate-200/10"></div>
                                        <div class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full border border-slate-200/10"></div>
                                        <div class="absolute inset-0 grid place-items-center">
                                            <div class="grid h-16 w-16 place-items-center rounded-[1.4rem] border border-white/80 bg-white/70 text-slate-500 shadow-sm backdrop-blur-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.35" stroke="currentColor" class="h-7 w-7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                {{-- Availability pill: a dropdown, as on Menu Management --}}
                                <div class="absolute right-5 top-5 z-30" x-data="{ open: false }" @keydown.escape.window="open = false">
                                    <button type="button" @click="open = ! open" @if ($archived) disabled @endif
                                            class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-white/70 px-2.5 py-1 text-[10px] font-bold shadow-sm backdrop-blur-md transition hover:-translate-y-0.5 {{ $pill['classes'] }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $pill['dot'] }}"></span>
                                        <span class="max-w-24 truncate">{{ $pill['label'] }}</span>
                                        @unless ($archived)
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="h-3 w-3 transition-transform" :class="open && 'rotate-180'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                        @endunless
                                    </button>
                                    @unless ($archived)
                                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 top-full z-50 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
                                            <p class="px-2.5 pb-1.5 pt-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">{{ __('Availability') }}</p>
                                            @foreach ([[1, __('Available'), 'bg-emerald-500'], [0, __('Unavailable'), 'bg-gray-500']] as [$value, $label, $dot])
                                                <form method="POST" action="{{ route('massage.services.availability', $service) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="is_available" value="{{ $value }}">
                                                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left text-xs transition {{ (bool) $value === $available ? 'bg-slate-50 font-bold text-slate-500' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                                                        <span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                                                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    @endunless
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                                <div class="min-w-0">
                                    <h3 class="truncate text-[15px] font-semibold tracking-[-0.015em] text-slate-900" title="{{ $service->name }}">{{ $service->name }}</h3>
                                    <p class="mt-1 line-clamp-2 min-h-9 text-xs leading-[1.15rem] {{ $service->descriptionText() !== '' ? 'text-slate-500' : 'text-slate-500' }}">{{ $service->descriptionText() ?: __('No description added') }}</p>
                                </div>

                                <div class="mt-3 flex min-h-6 flex-wrap items-center gap-1.5">
                                    @if ($service->duration_minutes)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3 w-3 text-slate-500" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ $service->durationLabel() }}
                                        </span>
                                    @endif
                                    @if ($service->variants->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-500">
                                            {{ trans_choice(':count variant|:count variants', $service->variants->count(), ['count' => $service->variants->count()]) }}
                                        </span>
                                    @endif
                                    @if ($service->addOns->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-1 text-[10px] font-semibold text-teal-700">
                                            {{ trans_choice(':count add-on|:count add-ons', $service->addOns->count(), ['count' => $service->addOns->count()]) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-auto flex items-end justify-between gap-3 pt-4">
                                    <div>
                                        <p class="text-[9px] font-bold uppercase tracking-[0.16em] text-slate-500">{{ __('Selling price') }}</p>
                                        <p class="mt-0.5 text-lg font-semibold tracking-[-0.025em] text-slate-700">{{ $service->priceLabel() }}</p>
                                    </div>
                                    @unless ($archived)
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border {{ $available ? 'border-emerald-100 bg-emerald-50' : 'border-gray-200 bg-gray-50' }}">
                                            <span class="h-2 w-2 rounded-full {{ $available ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                        </span>
                                    @endunless
                                </div>

                                @if ($canManage)
                                    <div class="mt-4 border-t border-slate-200 pt-3">
                                        @if ($archived)
                                            <form method="POST" action="{{ route('massage.services.restore', $service->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">{{ __('Restore item') }}</button>
                                            </form>
                                        @else
                                            <div class="grid grid-cols-2 gap-2">
                                                <a href="{{ route('massage.services.edit', $service) }}" data-turbo="false" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-500 transition hover:border-slate-200 hover:bg-slate-50 hover:text-slate-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                                    {{ __('Edit') }}
                                                </a>
                                                <x-confirm-form
                                                    :action="route('massage.services.destroy', $service)"
                                                    method="DELETE"
                                                    :title="__('Archive this massage service?')"
                                                    :message="__('It disappears from new orders. Past orders keep it, and it can be restored from Archived.')"
                                                    :confirm-label="__('Archive')"
                                                >
                                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-100 bg-white px-3 py-2 text-xs font-bold text-red-600 transition hover:border-red-200 hover:bg-red-50">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                                                        {{ __('Archive') }}
                                                    </button>
                                                </x-confirm-form>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
