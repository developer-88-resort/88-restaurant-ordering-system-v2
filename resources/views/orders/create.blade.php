<x-app-layout>
    <x-slot name="header">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.88)] sm:px-8 sm:py-7">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="
                    background-image:
                        linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px);
                    background-size: 28px 28px;
                "
            ></div>

            <div
                aria-hidden="true"
                class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/45 blur-3xl"
            ></div>

            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"
            ></div>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-4 sm:items-center sm:gap-5">
                    <a
                        href="{{ route('orders.index') }}"
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white/80 backdrop-blur-sm transition hover:-translate-x-0.5 hover:bg-white/15 hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15 sm:h-12 sm:w-12"
                        aria-label="{{ __('Back to orders') }}"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </a>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white/70 backdrop-blur-sm">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-35"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                                </span>
                                {{ __('Draft order') }}
                            </span>
                        </div>

                        <h2 class="mt-2 text-2xl font-bold tracking-[-0.035em] text-white sm:text-3xl">
                            {{ __('Create a new order') }}
                        </h2>

                        <p class="mt-1 max-w-2xl text-sm leading-6 text-white/60">
                            {{ __('Choose the service type, assign a location, and build the customer order from the available menu.') }}
                        </p>
                    </div>
                </div>

                <div class="hidden shrink-0 items-center xl:flex">
                    <div class="flex items-center rounded-2xl border border-white/10 bg-white/[0.07] p-2 backdrop-blur-sm">
                        <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-bold text-[#241917] shadow-sm">
                            <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#8A3330] text-[10px] text-white">1</span>
                            {{ __('Order type') }}
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mx-1 h-4 w-4 text-white/30" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>

                        <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white/65">
                            <span class="grid h-6 w-6 place-items-center rounded-lg border border-white/15 bg-white/10 text-[10px] text-white">2</span>
                            {{ __('Location') }}
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mx-1 h-4 w-4 text-white/30" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>

                        <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white/65">
                            <span class="grid h-6 w-6 place-items-center rounded-lg border border-white/15 bg-white/10 text-[10px] text-white">3</span>
                            {{ __('Menu & review') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </x-slot>

    @if ($areas->isEmpty())
        <section class="relative overflow-hidden rounded-[2rem] border border-amber-200 bg-white p-6 shadow-[0_24px_60px_-42px_rgba(62,42,29,0.6)] sm:p-8">
            <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-amber-100/80 blur-3xl" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-700">{{ __('Setup required') }}</p>
                        <h3 class="mt-1 text-lg font-bold text-[#2A211E]">{{ __('No order locations are available yet.') }}</h3>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-[#766962]">{{ __('Add a space first before creating orders.') }}</p>
                    </div>
                </div>

                <a href="{{ route('orders.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#E5DDD0] bg-white px-4 py-2.5 text-sm font-semibold text-[#5D504A] transition hover:border-[#8A3330]/30 hover:bg-[#FAF6EE] hover:text-[#8A3330]">
                    {{ __('Back to orders') }}
                </a>
            </div>
        </section>
    @elseif ($categories->isEmpty())
        <section class="relative overflow-hidden rounded-[2rem] border border-amber-200 bg-white p-6 shadow-[0_24px_60px_-42px_rgba(62,42,29,0.6)] sm:p-8">
            <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-amber-100/80 blur-3xl" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-700">{{ __('Setup required') }}</p>
                        <h3 class="mt-1 text-lg font-bold text-[#2A211E]">{{ __('The menu is not ready for ordering.') }}</h3>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-[#766962]">{{ __('Add at least one available menu item first before creating orders.') }}</p>
                    </div>
                </div>

                <a href="{{ route('orders.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#E5DDD0] bg-white px-4 py-2.5 text-sm font-semibold text-[#5D504A] transition hover:border-[#8A3330]/30 hover:bg-[#FAF6EE] hover:text-[#8A3330]">
                    {{ __('Back to orders') }}
                </a>
            </div>
        </section>
    @else
        <form
            method="POST"
            action="{{ route('orders.store') }}"
            data-draft-key="orders-create"
            {{-- The chosen location has to ride along with the cart. Turbo
                 re-renders this page on navigation, and when only 'cart' was
                 restored the order came back with its items but no table —
                 the summary lost its location and Place Order went dead,
                 which read to staff as the location having been forgotten
                 the moment they opened the order. Cleared together with the
                 cart once the order is submitted. --}}
            x-persist="{ key: 'orders-create-cart', paths: ['cart', 'orderType', 'pax', 'areaId', 'categoryId', 'spaceId', 'isFreeCategory', 'showPicker'] }"
            x-data="{
                cart: [],
                eachLabel: @js(__('each')),
                dineInLabel: @js(__('Dine In')),
                takeoutLabel: @js(__('Take-out')),
                takeoutLocationLabel: @js(__('No location required')),
                pendingLocation: null,
                pax: null,
                paxUnitLabel: @js(__('pax')),
                summaryOpen: false,
                viewOrderLabel: @js(__('View order')),
                viewSummaryLabel: @js(__('View order summary')),
                hideOrderLabel: @js(__('Hide order')),
                orderType: 'dine_in',
                areaId: null,
                categoryId: null,
                spaceId: null,
                isFreeCategory: false,
                showPicker: true,
                menuSearch: '',
                activeAreaTab: {{ $areas->first()->id }},
                areaNames: { {{ $areas->map(fn ($a) => "'{$a->id}': " . Js::from($a->name))->implode(', ') }} },
                spaceNames: { {{ $areas->flatMap(fn ($a) => $a->categories->flatMap->spaces)->map(fn ($s) => "'{$s->id}': " . Js::from($s->name))->implode(', ') }} },
                categoryNames: { {{ $areas->flatMap(fn ($a) => $a->categories)->map(fn ($c) => "'{$c->id}': " . Js::from($c->name))->implode(', ') }} },
                setOrderType(type) {
                    this.orderType = type;
                    if (type === 'dine_in' && !this.locationSelected) {
                        this.showPicker = true;
                    }
                },
                selectSpace(areaId, categoryId, spaceId) {
                    this.pendingLocation = {
                        areaId, categoryId, spaceId,
                        isFreeCategory: false,
                        name: this.spaceNames[spaceId] ?? '',
                    };
                },
                selectFreeCategory(areaId, categoryId) {
                    this.pendingLocation = {
                        areaId, categoryId, spaceId: null,
                        isFreeCategory: true,
                        name: this.categoryNames[categoryId] ?? '',
                    };
                },
                confirmPendingLocation() {
                    if (!this.pendingLocation) return;
                    this.areaId = this.pendingLocation.areaId;
                    this.categoryId = this.pendingLocation.categoryId;
                    this.spaceId = this.pendingLocation.spaceId;
                    this.isFreeCategory = this.pendingLocation.isFreeCategory;
                    this.pendingLocation = null;
                    this.showPicker = false;
                },
                cancelPendingLocation() {
                    this.pendingLocation = null;
                },
                /*
                 * Which tile the picker draws as chosen. A pending pick wins
                 * while the confirm dialog is up; otherwise it falls back to
                 * the location already locked in. Without that fallback the
                 * picker looked completely empty whenever it was reopened —
                 * confirming clears pendingLocation — so staff reasonably
                 * read their chosen table as having been lost.
                 */
                isSpacePicked(spaceId) {
                    return this.pendingLocation
                        ? this.pendingLocation.spaceId === spaceId
                        : (! this.isFreeCategory && this.spaceId === spaceId);
                },
                /*
                 * Below lg the summary is a panel the bottom bar opens and
                 * closes, so the bar's label has to say which of the two the
                 * next tap will do. At lg and up the summary is always beside
                 * the menu and this flag is ignored.
                 */
                toggleSummary() {
                    this.summaryOpen = ! this.summaryOpen;

                    if (this.summaryOpen) {
                        this.$nextTick(() => {
                            document.getElementById('order-summary')
                                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    }
                },
                get summaryLabel() {
                    if (this.summaryOpen) return this.hideOrderLabel;

                    return this.isEmpty ? this.viewSummaryLabel : this.viewOrderLabel;
                },
                isCategoryPicked(areaId, categoryId) {
                    const pick = this.pendingLocation ?? {
                        areaId: this.areaId,
                        categoryId: this.categoryId,
                        isFreeCategory: this.isFreeCategory,
                    };

                    return pick.isFreeCategory && pick.areaId === areaId && pick.categoryId === categoryId;
                },
                get locationSelected() {
                    return this.orderType === 'takeout' || (this.areaId && this.categoryId && (this.spaceId || this.isFreeCategory));
                },
                get locationLabel() {
                    if (this.orderType === 'takeout') return this.takeoutLocationLabel;
                    const area = this.areaNames[this.areaId] ?? '';
                    const spot = this.spaceId ? this.spaceNames[this.spaceId] : this.categoryNames[this.categoryId];
                    return area && spot ? area + ' · ' + spot : '';
                },
                /*
                 * Read-only echo of the count entered up in step 1. The
                 * summary is a review surface — showing it here lets the
                 * waiter check the number against the table before placing
                 * the order, without giving them a second field that could
                 * disagree with the first.
                 */
                get paxLabel() {
                    return this.orderType === 'dine_in' && this.pax
                        ? this.pax + ' ' + this.paxUnitLabel
                        : '';
                },
                get orderTypeLabel() {
                    return this.orderType === 'dine_in' ? this.dineInLabel : this.takeoutLabel;
                },
                addItem(item) {
                    const variantId = item.variantId ?? null;
                    const existing = this.cart.find(line => line.id === item.id && line.variantId === variantId);
                    if (existing) {
                        existing.qty++;
                    } else {
                        this.cart.push({ id: item.id, variantId, name: item.name, price: Number(item.price), qty: 1 });
                    }
                },
                variantPickerItem: null,
                openVariantPicker(item) {
                    this.variantPickerItem = item;
                },
                closeVariantPicker() {
                    this.variantPickerItem = null;
                },
                chooseVariant(variant) {
                    const item = this.variantPickerItem;
                    if (!item) return;
                    this.addItem({
                        id: item.id,
                        variantId: variant.id,
                        name: item.name + ' — ' + variant.name,
                        price: variant.price
                    });
                    this.closeVariantPicker();
                },
                increment(index) {
                    if (this.cart[index]) this.cart[index].qty++;
                },
                decrement(index) {
                    if (!this.cart[index]) return;
                    this.cart[index].qty--;
                    if (this.cart[index].qty <= 0) this.cart.splice(index, 1);
                },
                clearCart() {
                    this.cart = [];
                },
                itemQuantity(itemId) {
                    return this.cart
                        .filter(line => Number(line.id) === Number(itemId))
                        .reduce((sum, line) => sum + Number(line.qty), 0);
                },
                matchesSearch(text) {
                    const query = this.menuSearch.trim().toLowerCase();
                    return !query || String(text).toLowerCase().includes(query);
                },
                categoryHasMatches(items) {
                    const query = this.menuSearch.trim().toLowerCase();
                    return !query || items.some(item => String(item).toLowerCase().includes(query));
                },
                get total() {
                    return this.cart.reduce((sum, line) => sum + (Number(line.price) * Number(line.qty)), 0);
                },
                get cartCount() {
                    return this.cart.reduce((sum, line) => sum + Number(line.qty), 0);
                },
                get isEmpty() {
                    return this.cart.length === 0;
                },
                get canSubmit() {
                    return !this.isEmpty && this.locationSelected && this.total > 0;
                },
                formatMoney(value) {
                    return '₱' + Number(value).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }"
        >
            @csrf

            <input type="hidden" name="order_type" :value="orderType">
            <input type="hidden" name="pax" :value="orderType === 'dine_in' ? (pax ?? '') : ''">
            <input type="hidden" name="area_id" :value="areaId">
            <input type="hidden" name="space_category_id" :value="categoryId">
            <input type="hidden" name="space_id" :value="spaceId">

            @if ($errors->any())
                <div class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <div>
                        <p class="font-semibold">{{ __('Some order details need your attention.') }}</p>
                        <p class="mt-0.5 text-xs text-red-600">{{ __('Review the highlighted fields before placing the order.') }}</p>
                    </div>
                </div>
            @endif

            {{-- The side-by-side cart used to only kick in at xl (1280px), so a
                 tablet in landscape — the device staff most often carry to a
                 table — still had the Order Summary buried below the whole
                 menu. Dropping to lg (1024px) covers that case; phones below
                 it get the fixed mobile bar further down instead. --}}
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_410px]">
                {{-- Clears the fixed mobile bar below, which is now two rows
                     tall when Place Order is showing. --}}
                <div class="min-w-0 space-y-6 pb-36 lg:pb-0">
                    {{-- Step 1: Order type --}}
                    <section class="overflow-hidden rounded-[1.75rem] border border-[#E6DCCF] bg-white shadow-[0_22px_55px_-42px_rgba(57,37,32,0.65)]">
                        <div class="flex flex-col gap-4 border-b border-[#EEE6DC] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex items-start gap-3.5">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#241917] text-sm font-bold text-white shadow-[0_10px_22px_-14px_rgba(36,25,23,0.8)]">01</span>
                                <div>
                                    <h3 class="text-base font-bold tracking-[-0.015em] text-[#261D1A]">{{ __('How will the order be served?') }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-[#7A6D66]">{{ __('Choose dine-in for an assigned location or take-out for pickup.') }}</p>
                                </div>
                            </div>

                            <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-[#F4EEE6] px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#766760]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5 text-[#8A3330]" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {{ __('Required') }}
                            </span>
                        </div>

                        <div class="p-5 sm:p-6">
                            <x-input-error :messages="$errors->get('order_type')" class="mb-4" />

                            <div class="grid gap-3 md:grid-cols-2">
                                <button
                                    type="button"
                                    @click="setOrderType('dine_in')"
                                    :aria-pressed="orderType === 'dine_in'"
                                    :class="orderType === 'dine_in'
                                        ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_18px_35px_-20px_rgba(138,51,48,0.95)]'
                                        : 'border-[#E6DCCF] bg-[#FCFAF7] text-[#302521] hover:-translate-y-0.5 hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]'"
                                    class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border p-4 text-left transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15 sm:p-5"
                                >
                                    <span
                                        :class="orderType === 'dine_in' ? 'bg-white/15 text-white' : 'bg-[#F3E1DC] text-[#8A3330]'"
                                        class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl transition"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v4H4V6z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8M17 10v8" />
                                        </svg>
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold">{{ __('Dine In') }}</span>
                                        <span :class="orderType === 'dine_in' ? 'text-white/65' : 'text-[#85766F]'" class="mt-1 block text-xs leading-5">
                                            {{ __('Assign a cottage, dining table, room, or another available space.') }}
                                        </span>
                                    </span>

                                    <span
                                        :class="orderType === 'dine_in' ? 'border-white bg-white text-[#8A3330]' : 'border-[#D7CCC0] bg-white text-transparent'"
                                        class="grid h-6 w-6 shrink-0 place-items-center rounded-full border transition"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    @click="setOrderType('takeout')"
                                    :aria-pressed="orderType === 'takeout'"
                                    :class="orderType === 'takeout'
                                        ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_18px_35px_-20px_rgba(138,51,48,0.95)]'
                                        : 'border-[#E6DCCF] bg-[#FCFAF7] text-[#302521] hover:-translate-y-0.5 hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]'"
                                    class="group relative flex items-center gap-4 overflow-hidden rounded-2xl border p-4 text-left transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15 sm:p-5"
                                >
                                    <span
                                        :class="orderType === 'takeout' ? 'bg-white/15 text-white' : 'bg-[#F3E1DC] text-[#8A3330]'"
                                        class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl transition"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 10.5h-16.5m16.5 0l-1.125 9.75H4.875L3.75 10.5m16.5 0L18.375 3.75H5.625L3.75 10.5M9 14.25h6" />
                                        </svg>
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold">{{ __('Take-out') }}</span>
                                        <span :class="orderType === 'takeout' ? 'text-white/65' : 'text-[#85766F]'" class="mt-1 block text-xs leading-5">
                                            {{ __('Skip location selection and prepare the order for customer pickup.') }}
                                        </span>
                                    </span>

                                    <span
                                        :class="orderType === 'takeout' ? 'border-white bg-white text-[#8A3330]' : 'border-[#D7CCC0] bg-white text-transparent'"
                                        class="grid h-6 w-6 shrink-0 place-items-center rounded-full border transition"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </span>
                                </button>
                            </div>

                            {{-- Sits with the order type rather than in the
                                 summary panel: it belongs to how the order is
                                 served, and the waiter passes through here
                                 before the table and the menu. The summary is
                                 also collapsed by default on a phone, which
                                 would have kept this field out of sight.
                                 Optional on purpose — an order shouldn't stall
                                 on a head count nobody has yet. --}}
                            <div x-show="orderType === 'dine_in'" x-cloak x-transition.opacity.duration.200ms class="mt-4 border-t border-[#EEE6DC] pt-4">
                                <label for="pax" class="block text-sm font-bold text-[#302521]">
                                    {{ __('Number of guests') }}
                                    <span class="ml-1 text-xs font-semibold uppercase tracking-[0.1em] text-[#A2938B]">{{ __('Optional') }}</span>
                                </label>
                                <p class="mt-1 text-xs leading-5 text-[#85766F]">{{ __('Printed on the kitchen slip so the line knows how many to plate for.') }}</p>

                                <input
                                    id="pax"
                                    type="number"
                                    inputmode="numeric"
                                    min="1"
                                    max="999"
                                    x-model.number="pax"
                                    placeholder="{{ __('e.g. 4') }}"
                                    class="mt-3 w-40 rounded-2xl border-[#E6DCCF] bg-[#FCFAF7] text-sm font-bold text-[#302521] shadow-none focus:border-[#8A3330] focus:ring-[#8A3330]/20"
                                />

                                <x-input-error :messages="$errors->get('pax')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    {{-- Step 2: Location --}}
                    <section
                        x-show="orderType === 'dine_in'"
                        x-transition.opacity.duration.200ms
                        class="overflow-hidden rounded-[1.75rem] border border-[#E6DCCF] bg-white shadow-[0_22px_55px_-42px_rgba(57,37,32,0.65)]"
                    >
                        <div class="flex flex-col gap-4 border-b border-[#EEE6DC] px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex items-start gap-3.5">
                                <span
                                    :class="locationSelected ? 'bg-emerald-600 text-white' : 'bg-[#241917] text-white'"
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl text-sm font-bold shadow-[0_10px_22px_-14px_rgba(36,25,23,0.8)] transition"
                                >
                                    <svg x-show="locationSelected" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span x-show="!locationSelected">02</span>
                                </span>

                                <div>
                                    <h3 class="text-base font-bold tracking-[-0.015em] text-[#261D1A]">{{ __('Choose the customer location') }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-[#7A6D66]">{{ __('Only available spaces can be selected for this order.') }}</p>
                                </div>
                            </div>

                            <span
                                x-show="locationSelected"
                                x-cloak
                                class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-emerald-700"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Location selected') }}
                            </span>
                        </div>

                        <div class="p-5 sm:p-6">
                            <x-input-error :messages="$errors->get('area_id')" class="mb-2" />
                            <x-input-error :messages="$errors->get('space_category_id')" class="mb-2" />
                            <x-input-error :messages="$errors->get('space_id')" class="mb-2" />

                            {{-- Selected location summary --}}
                            <div
                                x-show="!showPicker && locationSelected"
                                x-cloak
                                x-transition
                                class="relative overflow-hidden rounded-2xl bg-[#241917] p-4 text-white shadow-[0_18px_40px_-24px_rgba(36,25,23,0.85)] sm:p-5"
                            >
                                <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-[#A84742]/45 blur-2xl" aria-hidden="true"></div>

                                <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-3.5">
                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white/10 text-white">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                            </svg>
                                        </span>

                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">{{ __('Assigned location') }}</p>
                                            <p class="mt-1 truncate text-sm font-bold sm:text-base" x-text="locationLabel"></p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="showPicker = true"
                                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2.5 text-xs font-bold text-white transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.862 4.487" />
                                        </svg>
                                        {{ __('Change location') }}
                                    </button>
                                </div>
                            </div>

                            {{-- Location picker --}}
                            <div x-show="showPicker" x-transition.opacity.duration.200ms>
                                <div class="flex gap-1.5 overflow-x-auto rounded-2xl bg-[#F5EFE7] p-1.5 no-scrollbar">
                                    @foreach ($areas as $area)
                                        <button
                                            type="button"
                                            @click="activeAreaTab = {{ $area->id }}"
                                            :aria-pressed="activeAreaTab === {{ $area->id }}"
                                            :class="activeAreaTab === {{ $area->id }}
                                                ? 'bg-white text-[#241917] shadow-[0_8px_20px_-14px_rgba(42,28,24,0.65)]'
                                                : 'text-[#786A63] hover:bg-white/60 hover:text-[#8A3330]'"
                                            class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/10"
                                        >
                                            <span
                                                :class="activeAreaTab === {{ $area->id }} ? 'bg-[#8A3330]' : 'bg-[#B9ABA2]'"
                                                class="h-1.5 w-1.5 rounded-full transition"
                                            ></span>
                                            {{ $area->name }}
                                        </button>
                                    @endforeach
                                </div>

                                @foreach ($areas as $area)
                                    <div
                                        x-show="activeAreaTab === {{ $area->id }}"
                                        x-cloak
                                        x-data="{ activeCategory: {{ $area->categories->first()->id ?? 'null' }} }"
                                        class="mt-5"
                                    >
                                        @if ($area->categories->isEmpty())
                                            <div class="rounded-2xl border border-dashed border-[#DCCFC1] bg-[#FCFAF7] px-5 py-8 text-center">
                                                <p class="text-sm font-semibold text-[#5F524C]">{{ __('No categories set up for this area yet.') }}</p>
                                            </div>
                                        @else
                                            @if ($area->categories->count() > 1)
                                                <div class="mb-5 flex gap-2 overflow-x-auto pb-1 no-scrollbar sm:flex-wrap sm:overflow-visible sm:pb-0">
                                                    @foreach ($area->categories as $category)
                                                        <button
                                                            type="button"
                                                            @click="activeCategory = {{ $category->id }}"
                                                            :aria-pressed="activeCategory === {{ $category->id }}"
                                                            :class="activeCategory === {{ $category->id }}
                                                                ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_10px_20px_-15px_rgba(138,51,48,0.9)]'
                                                                : 'border-[#E3D8CB] bg-white text-[#6C5E57] hover:border-[#8A3330]/35 hover:bg-[#FAF5F0] hover:text-[#8A3330]'"
                                                            class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/10"
                                                        >
                                                            {{ $category->name }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @foreach ($area->categories as $category)
                                                <div x-show="activeCategory === {{ $category->id }}" x-cloak>
                                                    @if ($category->is_free)
                                                        @php $isFull = $category->isFull(); @endphp

                                                        <button
                                                            type="button"
                                                            @if (! $isFull) @click="selectFreeCategory({{ $area->id }}, {{ $category->id }})" @endif
                                                            :aria-pressed="isCategoryPicked({{ $area->id }}, {{ $category->id }})"
                                                            :class="isCategoryPicked({{ $area->id }}, {{ $category->id }})
                                                                ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_18px_35px_-22px_rgba(138,51,48,0.95)]'
                                                                : 'border-[#E4D9CC] bg-[#FCFAF7] text-[#302521] hover:-translate-y-0.5 hover:border-[#8A3330]/35 hover:bg-[#FAF3EE]'"
                                                            class="group flex w-full items-center justify-between gap-4 rounded-2xl border p-4 text-left transition duration-200 {{ $isFull ? 'cursor-not-allowed opacity-45' : 'cursor-pointer' }}"
                                                            {{ $isFull ? 'disabled' : '' }}
                                                        >
                                                            <span class="flex min-w-0 items-center gap-3.5">
                                                                <span
                                                                    :class="isCategoryPicked({{ $area->id }}, {{ $category->id }}) ? 'bg-white/15 text-white' : 'bg-[#F3E1DC] text-[#8A3330]'"
                                                                    class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl transition"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                </span>

                                                                <span class="min-w-0">
                                                                    <span class="block truncate text-sm font-bold">{{ $category->name }}</span>
                                                                    <span
                                                                        :class="isCategoryPicked({{ $area->id }}, {{ $category->id }}) ? 'text-white/65' : 'text-[#80716A]'"
                                                                        class="mt-1 block text-xs"
                                                                    >
                                                                        {{ $category->occupied_count }} / {{ $category->capacity_count ?? '—' }} {{ __('occupied') }}
                                                                        @if ($isFull) &mdash; {{ __('Full') }} @endif
                                                                    </span>
                                                                </span>
                                                            </span>

                                                            <span
                                                                :class="isCategoryPicked({{ $area->id }}, {{ $category->id }}) ? 'border-white bg-white text-[#8A3330]' : 'border-[#D7CCC0] bg-white text-transparent'"
                                                                class="grid h-6 w-6 shrink-0 place-items-center rounded-full border transition"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                                </svg>
                                                            </span>
                                                        </button>
                                                    @elseif ($category->spaces->isEmpty())
                                                        <div class="rounded-2xl border border-dashed border-[#DCCFC1] bg-[#FCFAF7] px-5 py-8 text-center">
                                                            <p class="text-sm font-semibold text-[#5F524C]">{{ __('No spaces added under this category yet.') }}</p>
                                                        </div>
                                                    @else
                                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                                            @foreach ($category->spaces as $space)
                                                                @php
                                                                    $available = $space->status === \App\Enums\SpaceStatus::Available;
                                                                    $accent = $space->status->pickerAccentClasses();
                                                                    [$borderAccent, $textAccent] = explode(' ', $accent);
                                                                @endphp

                                                                <button
                                                                    type="button"
                                                                    @if ($available) @click="selectSpace({{ $area->id }}, {{ $category->id }}, {{ $space->id }})" @endif
                                                                    :aria-pressed="isSpacePicked({{ $space->id }})"
                                                                    :class="isSpacePicked({{ $space->id }})
                                                                        ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_16px_30px_-20px_rgba(138,51,48,0.95)]'
                                                                        : 'border-[#E5DDD2] bg-white text-[#302521] {{ $available ? 'hover:-translate-y-0.5 hover:border-[#8A3330]/35 hover:bg-[#FCF7F2]' : '' }}'"
                                                                    class="relative min-h-[105px] overflow-hidden rounded-2xl border p-3.5 text-left transition duration-200 {{ $available ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}"
                                                                    {{ $available ? '' : 'disabled' }}
                                                                >
                                                                    <span class="absolute bottom-3 top-3 left-0 border-l-[4px] {{ $borderAccent }}" aria-hidden="true"></span>

                                                                    <span class="flex items-start justify-between gap-2 pl-1.5">
                                                                        <span
                                                                            :class="isSpacePicked({{ $space->id }}) ? 'bg-white/15 text-white' : 'bg-[#F5EFE7] text-[#8A3330]'"
                                                                            class="grid h-8 w-8 shrink-0 place-items-center rounded-xl transition"
                                                                        >
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v4H4V6z" />
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8M17 10v8" />
                                                                            </svg>
                                                                        </span>

                                                                        <span
                                                                            :class="isSpacePicked({{ $space->id }}) ? 'border-white bg-white text-[#8A3330]' : 'border-[#D7CCC0] bg-white text-transparent'"
                                                                            class="grid h-5 w-5 shrink-0 place-items-center rounded-full border transition"
                                                                        >
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                                            </svg>
                                                                        </span>
                                                                    </span>

                                                                    <span class="mt-3 block truncate pl-1.5 text-sm font-bold">{{ $space->name }}</span>
                                                                    <span
                                                                        :class="isSpacePicked({{ $space->id }}) ? 'text-white/65' : '{{ $textAccent }}'"
                                                                        class="mt-1 block pl-1.5 text-[10px] font-bold uppercase tracking-[0.1em]"
                                                                    >
                                                                        {{ $space->status->label() }}
                                                                    </span>
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    {{-- Step 3 placeholder while no location is selected --}}
                    <section
                        x-show="!locationSelected"
                        x-cloak
                        x-transition.opacity.duration.200ms
                        class="relative overflow-hidden rounded-[1.75rem] border border-dashed border-[#DCCFC1] bg-[#FCFAF7] px-6 py-10 text-center shadow-[0_18px_45px_-40px_rgba(57,37,32,0.6)] sm:py-12"
                    >
                        <div class="absolute -right-20 -top-20 h-52 w-52 rounded-full bg-[#F3E1DC]/70 blur-3xl" aria-hidden="true"></div>

                        <div class="relative mx-auto max-w-md">
                            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC] text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5a1.5 1.5 0 011.493 1.356l.75 7.5A1.5 1.5 0 0118 21H6a1.5 1.5 0 01-1.493-1.644l.75-7.5A1.5 1.5 0 016.75 10.5z" />
                                </svg>
                            </div>

                            <p class="mt-5 text-[11px] font-bold uppercase tracking-[0.16em] text-[#8A3330]">{{ __('Menu locked') }}</p>
                            <h3 class="mt-2 text-lg font-bold text-[#2A211E]">{{ __('Select a location to open the menu.') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#7A6D66]">{{ __('The food menu becomes available after choosing an open space for this dine-in order.') }}</p>
                        </div>
                    </section>

                    {{-- Step 3: Menu browser --}}
                    <section
                        x-show="locationSelected"
                        x-cloak
                        x-transition.opacity.duration.200ms
                        class="overflow-hidden rounded-[1.75rem] border border-[#E6DCCF] bg-white shadow-[0_22px_55px_-42px_rgba(57,37,32,0.65)]"
                    >
                        <div class="border-b border-[#EEE6DC] px-5 py-5 sm:px-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-start gap-3.5">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#241917] text-sm font-bold text-white shadow-[0_10px_22px_-14px_rgba(36,25,23,0.8)]">03</span>
                                    <div>
                                        <h3 class="text-base font-bold tracking-[-0.015em] text-[#261D1A]">{{ __('Build the customer order') }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-[#7A6D66]">{{ __('Tap an item to add it, or choose a variant when options are available.') }}</p>
                                    </div>
                                </div>

                                <div class="relative w-full lg:max-w-xs">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-[#9B8D85]" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.197 5.197a7.5 7.5 0 0010.606 10.606z" />
                                    </svg>
                                    <input
                                        type="search"
                                        x-model.debounce.150ms="menuSearch"
                                        placeholder="{{ __('Search menu items...') }}"
                                        class="block w-full rounded-xl border-[#DED3C7] bg-[#FCFAF7] py-2.5 pl-10 pr-10 text-sm text-[#302521] placeholder:text-[#A2958D] focus:border-[#8A3330] focus:ring-[#8A3330]/20"
                                    >
                                    <button
                                        type="button"
                                        x-show="menuSearch"
                                        x-cloak
                                        @click="menuSearch = ''"
                                        class="absolute right-2.5 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-lg text-[#9B8D85] transition hover:bg-[#F1E8DE] hover:text-[#8A3330]"
                                        aria-label="{{ __('Clear search') }}"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8 p-5 sm:p-6">
                            @php
                                // Per-kilo items are priced from an actual recorded weight (Weigh &
                                // Order), never a flat click-to-add price — pulled out of their normal
                                // category here and rendered once in their own section below, driven
                                // purely off pricing_type so any future per-kilo item picks this up
                                // automatically.
                                $perKiloItems = $categories->flatMap->menuItems->filter(fn ($item) => $item->isPerKilo())->values();
                                $perKiloSearchLabel = __('Fresh By the Kilo');
                            @endphp

                            @foreach ($categories as $category)
                                @php
                                    $categorySearchItems = $category->menuItems
                                        ->reject(fn ($item) => $item->isPerKilo())
                                        ->map(fn ($item) => $category->name . ' ' . $item->name)
                                        ->values();
                                @endphp

                                <section
                                    x-show="categoryHasMatches({{ Js::from($categorySearchItems) }})"
                                    x-cloak
                                >
                                    <div class="mb-4 flex items-center justify-between gap-4">
                                        <div>
                                            <div class="flex items-center gap-2.5">
                                                <span class="h-6 w-1 rounded-full bg-[#8A3330]"></span>
                                                <h4 class="text-base font-bold tracking-[-0.015em] text-[#2A211E]">{{ $category->name }}</h4>
                                            </div>
                                            <p class="mt-1 pl-3.5 text-xs text-[#94867E]">
                                                {{ trans_choice(':count item|:count items', $category->menuItems->count(), ['count' => $category->menuItems->count()]) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                        @foreach ($category->menuItems as $item)
                                            @continue($item->isPerKilo())
                                            <button
                                                type="button"
                                                x-show="matchesSearch({{ Js::from($category->name . ' ' . $item->name) }})"
                                                x-cloak
                                                @if ($item->hasVariants())
                                                    @click="openVariantPicker({
                                                        id: {{ $item->id }},
                                                        name: {{ Js::from($item->name) }},
                                                        variants: {{ Js::from($item->variants->map(fn ($variant) => [
                                                            'id' => $variant->id,
                                                            'name' => $variant->name,
                                                            'description' => $variant->description,
                                                            'price' => (float) $variant->price,
                                                            'imageUrl' => $variant->imageUrl(),
                                                        ])) }}
                                                    })"
                                                @else
                                                    @click="addItem({ id: {{ $item->id }}, name: {{ Js::from($item->name) }}, price: {{ $item->price }} })"
                                                @endif
                                                class="group relative flex min-h-[118px] items-center gap-3 overflow-hidden rounded-2xl border border-[#E6DDD2] bg-[#FCFAF7] p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-[#8A3330]/35 hover:bg-white hover:shadow-[0_16px_32px_-24px_rgba(76,47,39,0.55)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/10"
                                            >
                                                <span class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-[#F3E1DC]/50 transition duration-300 group-hover:scale-125" aria-hidden="true"></span>

                                                @if ($item->primaryImageUrl())
                                                    <span class="relative h-12 w-12 shrink-0 overflow-hidden rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC]">
                                                        <img src="{{ $item->primaryImageUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-full w-full object-cover">
                                                    </span>
                                                @else
                                                    <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC] text-[#8A3330] transition group-hover:bg-[#8A3330] group-hover:text-white">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5V6a1.5 1.5 0 011.5-1.5z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 8.25h.008v.008H15V8.25z" />
                                                        </svg>
                                                    </span>
                                                @endif

                                                <span class="relative min-w-0 flex-1">
                                                    <span class="block break-words text-sm font-bold leading-5 text-[#302521]">{{ $item->name }}</span>

                                                    @if ($item->hasVariants())
                                                        <span class="mt-1.5 block text-xs font-semibold text-[#8A7B9E]">{{ $item->priceRangeLabel() }}</span>
                                                        <span class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[#8A3330]">
                                                            {{ __('Choose option') }}
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3 w-3 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                            </svg>
                                                        </span>
                                                    @else
                                                        <span class="mt-1.5 block text-sm font-bold text-[#8A3330]">₱{{ number_format($item->price, 2) }}</span>
                                                        <span class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[#8A3330]">
                                                            {{ __('Add to order') }}
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3 w-3 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                            </svg>
                                                        </span>
                                                    @endif
                                                </span>

                                                <span
                                                    x-show="itemQuantity({{ $item->id }}) > 0"
                                                    x-cloak
                                                    x-text="itemQuantity({{ $item->id }})"
                                                    class="relative grid h-7 min-w-7 shrink-0 place-items-center rounded-full bg-[#8A3330] px-1.5 text-xs font-bold text-white shadow-[0_8px_18px_-10px_rgba(138,51,48,0.9)]"
                                                ></span>
                                            </button>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach

                            @if ($perKiloItems->isNotEmpty())
                                <section
                                    x-show="categoryHasMatches({{ Js::from($perKiloItems->map(fn ($item) => $perKiloSearchLabel . ' ' . $item->name)->values()) }})"
                                    x-cloak
                                >
                                    <div class="mb-4 flex items-center justify-between gap-4">
                                        <div>
                                            <div class="flex items-center gap-2.5">
                                                <span class="h-6 w-1 rounded-full bg-[#8A3330]"></span>
                                                <h4 class="text-base font-bold tracking-[-0.015em] text-[#2A211E]">🐟 {{ __('Fresh / By the Kilo') }}</h4>
                                            </div>
                                            <p class="mt-1 pl-3.5 text-xs text-[#94867E]">{{ __('Priced per kilogram — weighed at order time') }}</p>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                        @foreach ($perKiloItems as $item)
                                            <div
                                                x-show="matchesSearch({{ Js::from($perKiloSearchLabel . ' ' . $item->name) }})"
                                                x-cloak
                                                class="relative flex min-h-[118px] items-center gap-3 overflow-hidden rounded-2xl border border-dashed border-[#DCCFC1] bg-[#FCFAF7] p-4 text-left"
                                            >
                                                @if ($item->primaryImageUrl())
                                                    <span class="relative h-12 w-12 shrink-0 overflow-hidden rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC]">
                                                        <img src="{{ $item->primaryImageUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-full w-full object-cover">
                                                    </span>
                                                @else
                                                    <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC] text-[#8A3330]">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5V6a1.5 1.5 0 011.5-1.5z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 8.25h.008v.008H15V8.25z" />
                                                        </svg>
                                                    </span>
                                                @endif

                                                <span class="relative min-w-0 flex-1">
                                                    <span class="block break-words text-sm font-bold leading-5 text-[#302521]">{{ $item->name }}</span>
                                                    <span class="mt-1.5 block text-xs leading-5 text-[#7A6D66]">{{ __('Priced per kilogram (market price). Please weigh this at Weigh & Order.') }}</span>
                                                    @if ($rate = $item->effectivePricePerKilo())
                                                        <span class="mt-1 block text-[11px] font-semibold text-[#8A7B9E]">~₱{{ number_format($rate, 0) }}/kg {{ __('today') }}</span>
                                                    @endif
                                                    <a
                                                        :href="spaceId ? '{{ route('weigh.wizard') }}?table=' + spaceId : '{{ route('weigh.wizard') }}'"
                                                        class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[#8A3330] hover:underline"
                                                    >
                                                        {{ __('Weigh & Order') }}
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                        </svg>
                                                    </a>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endif

                            <div
                                x-show="!categoryHasMatches({{ Js::from($categories->flatMap(fn ($category) => $category->menuItems->reject(fn ($item) => $item->isPerKilo())->map(fn ($item) => $category->name . ' ' . $item->name))->merge($perKiloItems->map(fn ($item) => $perKiloSearchLabel . ' ' . $item->name))->values()) }})"
                                x-cloak
                                class="rounded-2xl border border-dashed border-[#DCCFC1] bg-[#FCFAF7] px-6 py-10 text-center"
                            >
                                <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#F3E1DC] text-[#8A3330]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.197 5.197a7.5 7.5 0 0010.606 10.606z" />
                                    </svg>
                                </div>
                                <h4 class="mt-4 text-sm font-bold text-[#302521]">{{ __('No menu item matches your search.') }}</h4>
                                <p class="mt-1 text-xs text-[#8C7E76]">{{ __('Try another item name or clear the search field.') }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Order summary --}}
                {{-- Same clearance as the menu column: on mobile this panel is
                     the last thing on the page, so the fixed bar would sit on
                     top of its own Place Order button and Cancel link. --}}
                <aside
                    id="order-summary"
                    x-cloak
                    :class="summaryOpen ? 'block' : 'hidden lg:block'"
                    class="w-full scroll-mt-20 pb-36 lg:sticky lg:top-20 lg:pb-0"
                >
                    <section class="overflow-hidden rounded-[1.75rem] border border-[#DED2C5] bg-white shadow-[0_28px_65px_-42px_rgba(55,36,31,0.75)]">
                        <div class="relative overflow-hidden bg-[#241917] px-5 py-5 text-white sm:px-6">
                            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-[#A84742]/50 blur-3xl" aria-hidden="true"></div>
                            <div class="absolute -bottom-16 -left-12 h-36 w-36 rounded-full bg-white/5 blur-3xl" aria-hidden="true"></div>

                            <div class="relative flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/45">{{ __('Current order') }}</p>
                                    <h3 class="mt-1.5 text-lg font-bold tracking-[-0.02em]">{{ __('Order Summary') }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-white/55">
                                        <span x-text="orderTypeLabel"></span>
                                        <span x-show="locationSelected"> · </span>
                                        <span x-show="locationSelected" x-text="locationLabel"></span>
                                        <span x-show="paxLabel"> · </span>
                                        <span x-show="paxLabel" x-text="paxLabel"></span>
                                    </p>
                                </div>

                                <div class="flex h-11 min-w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/10 px-3 backdrop-blur-sm">
                                    <span class="text-lg font-bold" x-text="cartCount"></span>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6">
                            {{-- Empty cart --}}
                            <div x-show="isEmpty" class="py-5 text-center">
                                <div class="relative mx-auto grid h-16 w-16 place-items-center rounded-[1.35rem] border border-[#8A3330]/10 bg-[#F3E1DC] text-[#8A3330]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                    </svg>
                                </div>
                                <h4 class="mt-4 text-sm font-bold text-[#302521]">{{ __('Your order is empty') }}</h4>
                                <p class="mx-auto mt-1 max-w-xs text-xs leading-5 text-[#8B7D75]">{{ __('Tap menu items to add them to this order.') }}</p>
                            </div>

                            {{-- Cart items --}}
                            <div x-show="!isEmpty" x-cloak>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#8F8179]">{{ __('Order items') }}</p>
                                    <button type="button" @click="clearCart()" class="text-xs font-semibold text-[#8A3330] transition hover:text-[#6F2725] hover:underline">
                                        {{ __('Clear all') }}
                                    </button>
                                </div>

                                <div class="max-h-[360px] space-y-2.5 overflow-y-auto pr-1">
                                    <template x-for="(line, index) in cart" :key="line.id + '-' + (line.variantId ?? 'base')">
                                        <div class="rounded-2xl border border-[#E9E0D6] bg-[#FCFAF7] p-3.5">
                                            <div class="flex items-start gap-3">
                                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-xs font-bold text-[#8A3330]" x-text="index + 1"></span>

                                                <div class="min-w-0 flex-1">
                                                    <p class="break-words text-sm font-bold leading-5 text-[#302521]" x-text="line.name"></p>
                                                    <p class="mt-1 text-xs text-[#8A7C74]" x-text="formatMoney(line.price) + ' ' + eachLabel"></p>
                                                </div>

                                                <p class="shrink-0 text-sm font-bold text-[#8A3330]" x-text="formatMoney(line.price * line.qty)"></p>
                                            </div>

                                            <div class="mt-3 flex items-center justify-end">
                                                <div class="inline-flex items-center rounded-xl border border-[#DDD1C4] bg-white p-1">
                                                    <button
                                                        type="button"
                                                        @click="decrement(index)"
                                                        class="grid h-7 w-7 place-items-center rounded-lg text-[#6F625B] transition hover:bg-[#F4ECE4] hover:text-[#8A3330]"
                                                        :aria-label="'Decrease ' + line.name"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                                        </svg>
                                                    </button>

                                                    <span class="w-8 text-center text-xs font-bold text-[#302521]" x-text="line.qty"></span>

                                                    <button
                                                        type="button"
                                                        @click="increment(index)"
                                                        class="grid h-7 w-7 place-items-center rounded-lg bg-[#241917] text-white transition hover:bg-[#8A3330]"
                                                        :aria-label="'Increase ' + line.name"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <input type="hidden" :name="'items[' + index + '][menu_item_id]'" :value="line.id">
                                            <input type="hidden" :name="'items[' + index + '][menu_item_variant_id]'" :value="line.variantId">
                                            <input type="hidden" :name="'items[' + index + '][quantity]'" :value="line.qty">
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="mt-5 border-t border-dashed border-[#D9CEC3] pt-5">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold text-[#7C6E66]">{{ __('Order total') }}</p>
                                        <p class="mt-0.5 text-[10px] uppercase tracking-[0.12em] text-[#A1948C]">{{ __('Calculated automatically') }}</p>
                                    </div>
                                    <span class="text-2xl font-bold tracking-[-0.03em] text-[#8A3330]" x-text="formatMoney(total)"></span>
                                </div>
                            </div>

                            <div class="mt-5">
                                <label for="notes" class="flex items-center justify-between gap-3 text-sm font-bold text-[#302521]">
                                    <span>{{ __('Order notes') }}</span>
                                    <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#A1948C]">{{ __('Optional') }}</span>
                                </label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="3"
                                    placeholder="{{ __('Add preparation instructions or customer requests...') }}"
                                    class="mt-2 block w-full resize-none rounded-2xl border-[#DED3C7] bg-[#FCFAF7] px-3.5 py-3 text-sm text-[#302521] placeholder:text-[#A2958D] focus:border-[#8A3330] focus:ring-[#8A3330]/20"
                                >{{ old('notes') }}</textarea>
                                <x-input-error :messages="$errors->get('items')" class="mt-2" />
                            </div>

                            <div class="mt-5">
                                <div
                                    x-show="!locationSelected"
                                    x-cloak
                                    class="mb-3 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                    </svg>
                                    {{ __('Select a location above before placing the order.') }}
                                </div>

                                <div
                                    x-show="locationSelected && isEmpty"
                                    x-cloak
                                    class="mb-3 flex items-start gap-2 rounded-xl bg-[#F5EFE7] px-3 py-2.5 text-xs leading-5 text-[#766860]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-[#8A3330]" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Add at least one menu item to continue.') }}
                                </div>

                                <button
                                    type="submit"
                                    :disabled="!canSubmit"
                                    class="group inline-flex w-full items-center justify-center gap-2.5 rounded-2xl bg-[#8A3330] px-4 py-3.5 text-sm font-bold text-white shadow-[0_16px_30px_-16px_rgba(138,51,48,0.9)] transition duration-200 hover:-translate-y-0.5 hover:bg-[#742927] hover:shadow-[0_20px_35px_-16px_rgba(138,51,48,0.95)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/20 disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:translate-y-0 disabled:hover:bg-[#8A3330]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Place Order') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </button>

                                <a
                                    href="{{ route('orders.index') }}"
                                    class="mt-3 inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-[#766860] transition hover:bg-[#F7F1EA] hover:text-[#302521]"
                                >
                                    {{ __('Cancel and return') }}
                                </a>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            {{-- Phones and portrait tablets (below lg) never get the
                 side-by-side cart, so without this the running total and
                 Place Order button are a long scroll away at the bottom of
                 the whole menu. Tapping the pill jumps straight to the real
                 summary panel above rather than duplicating its logic here.
                 Shown even while the cart is empty — on a small screen at
                 the counter, staff still need a visible way to reach the
                 summary (and its "select a location" reminder) without
                 scrolling past the entire menu first.

                 The Place Order button below it submits this same form
                 directly: a waiter taking a big order at the table was
                 otherwise made to scroll the whole menu, then the whole
                 item list, just to reach the button — the longer the order,
                 the worse it got. It only appears once the order can
                 actually be placed; until then the pill leads to the
                 summary, which spells out what is still missing. --}}
            <div
                x-cloak
                x-transition
                class="fixed inset-x-0 bottom-0 z-40 space-y-2 border-t border-[#E6DCCF] bg-white/95 px-4 py-3 shadow-[0_-18px_45px_-30px_rgba(55,35,30,0.55)] backdrop-blur-md lg:hidden"
            >
                <button
                    type="button"
                    @click="toggleSummary()"
                    :aria-expanded="summaryOpen"
                    aria-controls="order-summary"
                    class="flex w-full items-center justify-between gap-3 rounded-2xl bg-[#241917] px-4 py-3 text-white"
                >
                    <span class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-white/10 text-sm font-bold" x-text="cartCount"></span>
                        <span class="text-sm font-semibold" x-text="summaryLabel"></span>
                    </span>
                    <span class="text-base font-bold" x-show="!isEmpty" x-text="formatMoney(total)"></span>
                </button>

                <button
                    type="submit"
                    x-show="canSubmit"
                    x-cloak
                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#8A3330] px-4 py-3.5 text-sm font-bold text-white shadow-[0_16px_30px_-16px_rgba(138,51,48,0.9)] transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/20"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Place Order') }}
                </button>
            </div>

            {{-- Confirms the picked table/category before it locks in and
                 collapses the picker — styled to match x-order-confirm-modal
                 instead of a native window.confirm() popup. --}}
            <div
                x-show="pendingLocation !== null" x-cloak
                x-on:keydown.escape.window="cancelPendingLocation()"
                role="dialog" aria-modal="true" aria-labelledby="confirm-location-title"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
            >
                <div x-show="pendingLocation !== null"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-on:click="cancelPendingLocation()"
                     class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

                <div x-show="pendingLocation !== null"
                     x-transition:enter="transition ease-out duration-250"
                     x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                     class="relative w-full sm:max-w-sm bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden">

                    <div class="px-5 pt-5 pb-4 flex items-start gap-3">
                        <span class="h-10 w-10 rounded-full bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="confirm-location-title" class="font-semibold text-gray-900">{{ __('Confirm this location?') }}</h3>
                            <p class="text-xs text-[#8A7B6D] mt-0.5" x-text="pendingLocation?.name"></p>
                        </div>
                        <button type="button" x-on:click="cancelPendingLocation()" aria-label="{{ __('Close') }}" class="text-gray-400 hover:text-gray-600 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-5 pb-5">
                        <p class="text-sm text-gray-600">{{ __('The order will be assigned to this location. You can change it later before placing the order.') }}</p>
                    </div>

                    <div class="px-5 py-4 border-t border-[#E5DDD0] bg-[#FAF6EE] flex gap-3">
                        <button type="button" x-on:click="cancelPendingLocation()"
                                class="flex-1 px-4 py-3 rounded-lg font-semibold text-gray-700 bg-white border border-[#D9CCBA] hover:bg-gray-50 transition">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" x-on:click="confirmPendingLocation()"
                                class="flex-1 px-4 py-3 rounded-lg font-semibold text-white bg-[#8A3330] hover:bg-[#742927] transition">
                            {{ __('Confirm') }}
                        </button>
                    </div>
                </div>
            </div>

            <x-menu.variant-picker-modal />
        </form>
    @endif
</x-app-layout>