<x-customer-layout location-label="{{ __('Takeout Order') }}">
    <div
        x-data="{
            cart: [],
            notes: @js(old('notes', '')),
            eachLabel: @js(__('each')),
            cartOpen: false,
            itemToasts: [],
            itemAvailability: @js($categories->flatMap->menuItems->mapWithKeys(fn ($item) => [$item->id => $item->availability_status->value])),
            isOrderable(id) {
                return this.itemAvailability[id] === 'available' || this.itemAvailability[id] === 'seasonal';
            },
            addConfirmItem: null,
            addConfirmVariantId: null,
            addConfirmQty: 1,
            addConfirmNotes: '',
            addConfirmError: null,
            addConfirmSubmitting: false,
            // Keyed by add-on id -> { checked, qty }. Only checked entries
            // are sent — mirrors AddConfirmModal.jsx's addOnSelections.
            addConfirmSelectedAddOns: {},
            openAddConfirm(item) {
                this.addConfirmItem = item;
                const defaultVariant = item.hasVariants
                    ? (item.variants.find(v => v.isDefault) ?? item.variants[0] ?? null)
                    : null;
                this.addConfirmVariantId = defaultVariant?.id ?? null;
                this.addConfirmQty = 1;
                this.addConfirmNotes = '';
                this.addConfirmError = null;
                this.addConfirmSubmitting = false;
                this.addConfirmSelectedAddOns = {};
            },
            closeAddConfirm() {
                this.addConfirmItem = null;
            },
            get addConfirmSelectedVariant() {
                if (!this.addConfirmItem?.hasVariants) return null;
                return this.addConfirmItem.variants.find(v => v.id === this.addConfirmVariantId) ?? null;
            },
            get addConfirmUnitPrice() {
                if (!this.addConfirmItem) return 0;
                return this.addConfirmItem.hasVariants ? (this.addConfirmSelectedVariant?.price ?? 0) : this.addConfirmItem.price;
            },
            get addConfirmSelectedAddOnList() {
                if (!this.addConfirmItem?.addOns) return [];
                return this.addConfirmItem.addOns
                    .filter(a => this.addConfirmSelectedAddOns[a.id]?.checked)
                    .map(a => ({ id: a.id, name: a.name, price: a.price, qty: this.addConfirmSelectedAddOns[a.id]?.qty ?? 1 }));
            },
            get addConfirmAddOnsTotal() {
                return this.addConfirmSelectedAddOnList.reduce((sum, a) => sum + a.price * a.qty, 0);
            },
            get addConfirmSubtotal() {
                // Add-on quantity is independent of the base item's quantity
                // — NOT (unitPrice + addOnPrices) * qty. Two of the base
                // item plus one add-on is base*2 + addOn*1, not base*2 +
                // addOn*2.
                return (this.addConfirmUnitPrice * this.addConfirmQty) + this.addConfirmAddOnsTotal;
            },
            get addConfirmAvailable() {
                return this.addConfirmItem ? this.isOrderable(this.addConfirmItem.id) : false;
            },
            incrementAddConfirmQty() {
                if (this.addConfirmQty < 99) this.addConfirmQty++;
            },
            decrementAddConfirmQty() {
                if (this.addConfirmQty > 1) this.addConfirmQty--;
            },
            toggleAddConfirmAddOn(addOnId) {
                const addOn = this.addConfirmItem?.addOns?.find(a => a.id === addOnId);
                if (!addOn || addOn.price <= 0) return;
                const existing = this.addConfirmSelectedAddOns[addOnId];
                this.addConfirmSelectedAddOns = {
                    ...this.addConfirmSelectedAddOns,
                    [addOnId]: { checked: !existing?.checked, qty: existing?.qty ?? 1 },
                };
            },
            setAddConfirmAddOnQty(addOnId, qty) {
                const clamped = Math.min(99, Math.max(1, qty));
                this.addConfirmSelectedAddOns = {
                    ...this.addConfirmSelectedAddOns,
                    [addOnId]: { checked: true, qty: clamped },
                };
            },
            confirmAddItem() {
                if (this.addConfirmSubmitting || !this.addConfirmItem || !this.addConfirmAvailable) {
                    return;
                }
                if (this.addConfirmItem.hasVariants && !this.addConfirmVariantId) {
                    this.addConfirmError = @js(__('Please choose an option before adding to cart.'));
                    return;
                }
                this.addConfirmSubmitting = true;
                const item = this.addConfirmItem;
                const variant = this.addConfirmSelectedVariant;
                this.addItem({
                    id: item.id,
                    variantId: variant?.id ?? null,
                    name: variant ? item.name + ' — ' + variant.name : item.name,
                    price: this.addConfirmUnitPrice,
                    qty: this.addConfirmQty,
                    notes: this.addConfirmNotes.trim() || null,
                    addOns: this.addConfirmSelectedAddOnList,
                });
                this.closeAddConfirm();
            },
            selectedCategory: 'all',
            confirmOpen: false,
            submitting: false,
            cartStorageKey: 'cart_takeout',
            cartExpiryMs: 4 * 60 * 60 * 1000,
            loadSavedCart() {
                try {
                    const raw = localStorage.getItem(this.cartStorageKey);
                    if (!raw) return;
                    const saved = JSON.parse(raw);
                    if (!saved || !Array.isArray(saved.items)) return;
                    if (Date.now() - saved.savedAt > this.cartExpiryMs) {
                        localStorage.removeItem(this.cartStorageKey);
                        return;
                    }
                    this.cart = saved.items;
                    if (!this.notes && saved.notes) {
                        this.notes = saved.notes;
                    }
                } catch (e) {
                    localStorage.removeItem(this.cartStorageKey);
                }
            },
            saveCart() {
                localStorage.setItem(this.cartStorageKey, JSON.stringify({
                    savedAt: Date.now(),
                    items: this.cart,
                    notes: this.notes,
                }));
            },
            clearSavedCart() {
                localStorage.removeItem(this.cartStorageKey);
            },
            selectCategory(id) {
                this.selectedCategory = id;
                const target = id === 'all' ? this.$refs.menuTop : document.getElementById('category-' + id);
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            // Stable identity for a line's add-on selection, so two lines
            // for the same item+variant+notes but different add-ons don't
            // get silently merged — mirrors cartLine.js's addOnsKey() on
            // the React dine-in side (duplicated rather than shared across
            // the Vite-bundled React pages and this globally-loaded Alpine
            // block, matching how price*qty math is already duplicated
            // between the two flows in this app).
            addOnsKey(addOns) {
                return (addOns ?? []).map(a => a.id + ':' + a.qty).sort().join(',');
            },
            addItem(item) {
                const variantId = item.variantId ?? null;
                const notes = item.notes ?? null;
                const qty = item.qty ?? 1;
                const addOns = item.addOns ?? [];
                const addOnsKey = this.addOnsKey(addOns);
                const existing = this.cart.find(line => line.id === item.id && line.variantId === variantId && line.notes === notes && this.addOnsKey(line.addOns) === addOnsKey);
                if (existing) {
                    existing.qty += qty;
                } else {
                    this.cart.push({ id: item.id, variantId, name: item.name, price: item.price, qty, notes, addOns });
                }
                this.notifyItemAdded(item.name);
            },
            notifyItemAdded(name) {
                this.pushToast(name, 'success');
            },
            notifyOutOfStock(name) {
                this.pushToast(name, 'unavailable');
            },
            pushToast(name, type) {
                const id = Date.now() + Math.random();
                this.itemToasts.push({ id, name, type });
                setTimeout(() => {
                    this.itemToasts = this.itemToasts.filter(t => t.id !== id);
                }, 2200);
            },
            increment(index) {
                if (this.cart[index]) this.cart[index].qty++;
            },
            decrement(index) {
                if (!this.cart[index]) return;
                this.cart[index].qty--;
                if (this.cart[index].qty <= 0) this.cart.splice(index, 1);
            },
            lineTotal(line) {
                const base = line.price * line.qty;
                const addOnsTotal = (line.addOns ?? []).reduce((sum, a) => sum + a.price * a.qty, 0);
                return base + addOnsTotal;
            },
            get total() {
                return this.cart.reduce((sum, line) => sum + this.lineTotal(line), 0);
            },
            get count() {
                return this.cart.reduce((sum, line) => sum + line.qty, 0);
            },
            get isEmpty() {
                return this.cart.length === 0;
            }
        }"
        x-init="
            loadSavedCart();
            $watch('cart', () => saveCart());
            $watch('notes', () => saveCart());
            Echo.channel('menu').listen('.MenuItemAvailabilityChanged', (e) => {
                itemAvailability[e.menu_item_id] = e.availability_status;
                if (!isOrderable(e.menu_item_id)) {
                    const inCart = cart.find(line => line.id === e.menu_item_id);
                    if (inCart) {
                        cart = cart.filter(line => line.id !== e.menu_item_id);
                        notifyOutOfStock(inCart.name);
                    }
                }
            });
        "
    >
        {{-- "Item added" toasts --}}
        <div class="fixed top-20 inset-x-0 z-[60] flex flex-col items-center gap-3 px-4 pointer-events-none">
            <template x-for="toast in itemToasts" :key="toast.id">
                <div
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="pointer-events-auto bg-white p-4 rounded-md shadow-xs border border-slate-300 w-full max-w-xs sm:w-80 relative"
                    role="alert"
                >
                    <div class="flex items-start gap-2.5">
                        <template x-if="toast.type === 'success'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-[18px] fill-green-700 overflow-visible shrink-0" viewBox="0 0 330 330" aria-hidden="true">
                                <path d="M165 0C74.019 0 0 74.019 0 165s74.019 165 165 165 165-74.019 165-165S255.981 0 165 0m0 300c-74.44 0-135-60.561-135-135S90.56 30 165 30s135 60.561 135 135-60.561 135-135 135" />
                                <path d="m226.872 106.664-84.854 84.853-38.89-38.891c-5.857-5.857-15.355-5.858-21.213-.001-5.858 5.858-5.858 15.355 0 21.213l49.496 49.498a15 15 0 0 0 10.606 4.394h.001c3.978 0 7.793-1.581 10.606-4.393l95.461-95.459c5.858-5.858 5.858-15.355 0-21.213s-15.355-5.859-21.213-.001" />
                            </svg>
                        </template>
                        <template x-if="toast.type === 'unavailable'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-[18px] fill-yellow-700 overflow-visible shrink-0" viewBox="0 0 486.463 486.463" aria-hidden="true">
                                <path d="M243.225 333.382c-13.6 0-25 11.4-25 25s11.4 25 25 25c13.1 0 25-11.4 24.4-24.4.6-14.3-10.7-25.6-24.4-25.6" />
                                <path d="M474.625 421.982c15.7-27.1 15.8-59.4.2-86.4l-156.6-271.2c-15.5-27.3-43.5-43.5-74.9-43.5s-59.4 16.3-74.9 43.4l-156.8 271.5c-15.6 27.3-15.5 59.8.3 86.9 15.6 26.8 43.5 42.9 74.7 42.9h312.8c31.3 0 59.4-16.3 75.2-43.6m-34-19.6c-8.7 15-24.1 23.9-41.3 23.9h-312.8c-17 0-32.3-8.7-40.8-23.4-8.6-14.9-8.7-32.7-.1-47.7l156.8-271.4c8.5-14.9 23.7-23.7 40.9-23.7 17.1 0 32.4 8.9 40.9 23.8l156.7 271.4c8.4 14.6 8.3 32.2-.3 47.1" />
                                <path d="M237.025 157.882c-11.9 3.4-19.3 14.2-19.3 27.3.6 7.9 1.1 15.9 1.7 23.8 1.7 30.1 3.4 59.6 5.1 89.7.6 10.2 8.5 17.6 18.7 17.6s18.2-7.9 18.7-18.2c0-6.2 0-11.9.6-18.2 1.1-19.3 2.3-38.6 3.4-57.9.6-12.5 1.7-25 2.3-37.5 0-4.5-.6-8.5-2.3-12.5-5.1-11.2-17-16.9-28.9-14.1" />
                            </svg>
                        </template>

                        <div class="min-w-0">
                            <p class="text-sm text-slate-900 font-medium leading-tight truncate" x-text="toast.name"></p>
                            <p class="text-xs mt-1 text-slate-600" x-text="toast.type === 'success' ? '{{ __('was added to your cart') }}' : '{{ __('Out of Stock') }}'"></p>
                        </div>

                        <button type="button" @click="itemToasts = itemToasts.filter(t => t.id !== toast.id)" aria-label="{{ __('Dismiss') }}"
                                class="ml-auto flex items-center opacity-70 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330] rounded shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-2.5 cursor-pointer fill-slate-500" aria-hidden="true" viewBox="0 0 329.269 329">
                                <path d="M194.8 164.77 323.013 36.555c8.343-8.34 8.343-21.825 0-30.164-8.34-8.34-21.825-8.34-30.164 0L164.633 134.605 36.422 6.391c-8.344-8.34-21.824-8.34-30.164 0-8.344 8.34-8.344 21.824 0 30.164l128.21 128.215L6.259 292.984c-8.344 8.34-8.344 21.825 0 30.164a21.27 21.27 0 0 0 15.082 6.25c5.46 0 10.922-2.09 15.082-6.25l128.21-128.214 128.216 128.214a21.27 21.27 0 0 0 15.082 6.25c5.46 0 10.922-2.09 15.082-6.25 8.343-8.34 8.343-21.824 0-30.164zm0 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <form method="POST" action="{{ route('customer.welcome.takeout.store') }}"
              @submit="if (submitting) { $event.preventDefault(); return; } submitting = true; clearSavedCart();">
            @csrf
            <input type="hidden" name="customer_name" value="{{ $customerName }}">

            <div class="px-4 pt-4">
                <a href="{{ route('customer.welcome.show') }}" class="text-sm text-[#8A3330] hover:underline font-medium">
                    &larr; {{ __('Back') }}
                </a>
                <p class="mt-2 text-sm text-[#8A7B6D]">{{ __('Hi :name! Pick your takeout order.', ['name' => $customerName]) }}</p>
            </div>

            @php $perKiloItems = $categories->flatMap->menuItems->filter(fn ($item) => $item->isPerKilo())->sortBy(fn ($item) => $item->weighed_sort_order ?? PHP_INT_MAX)->values(); @endphp

            @if ($categories->isNotEmpty())
                <div class="sticky top-16 z-30 bg-[#F7F0E3]/95 backdrop-blur-sm border-b border-[#E5DDD0] mt-3">
                    <div class="max-w-5xl mx-auto px-4 py-2.5 flex items-center gap-2 overflow-x-auto no-scrollbar">
                        <button type="button"
                                @click="selectCategory('all')"
                                :class="selectedCategory === 'all' ? 'bg-[#8A3330] text-white border-[#8A3330]' : 'bg-white text-gray-600 border-[#E5DDD0] hover:border-[#8A3330]'"
                                class="shrink-0 whitespace-nowrap text-sm font-medium px-3.5 py-1.5 rounded-full border transition-colors">
                            {{ __('All') }}
                        </button>
                        @foreach ($categories as $category)
                            @continue($category->menuItems->every(fn ($item) => $item->isPerKilo()))
                            <button type="button"
                                    @click="selectCategory({{ $category->id }})"
                                    :class="selectedCategory === {{ $category->id }} ? 'bg-[#8A3330] text-white border-[#8A3330]' : 'bg-white text-gray-600 border-[#E5DDD0] hover:border-[#8A3330]'"
                                    class="shrink-0 whitespace-nowrap text-sm font-medium px-3.5 py-1.5 rounded-full border transition-colors">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div x-ref="menuTop" class="px-4 py-6 pb-8 max-w-5xl mx-auto space-y-8 scroll-mt-32" :class="!isEmpty ? 'pb-28' : ''">
                @if ($categories->isEmpty())
                    <x-empty-state
                        :title="__('Nothing on the menu right now')"
                        :description="__('Please ask our staff for assistance with your order.')"
                    />
                @else
                    @foreach ($categories as $category)
                        @php $categoryItems = $category->menuItems->reject(fn ($item) => $item->isPerKilo()); @endphp
                        @continue($categoryItems->isEmpty())
                        @php $optionCount = $categoryItems->sum(fn ($item) => $item->hasVariants() ? $item->variants->count() : 1); @endphp
                        <div id="category-{{ $category->id }}" data-category-id="{{ $category->id }}" class="scroll-mt-32">
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="h-5 w-1 rounded-full bg-[#8A3330]"></span>
                                <h3 class="font-semibold text-gray-900">{{ $category->name }}</h3>
                                <span class="text-xs font-medium text-[#8A7B6D] bg-[#F7F0E3] border border-[#E5DDD0] rounded-full px-2 py-0.5">{{ $optionCount }} {{ $optionCount === 1 ? __('option') : __('options') }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($categoryItems as $item)
                                    <x-menu.item-card :item="$item" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if ($perKiloItems->isNotEmpty())
                        <div class="scroll-mt-32">
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="h-5 w-1 rounded-full bg-[#8A3330]"></span>
                                <h3 class="font-semibold text-gray-900">🐟 {{ __('By the Kilo') }}</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($perKiloItems as $item)
                                    <x-menu.item-card :item="$item" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <div x-show="!isEmpty" x-cloak class="fixed inset-x-0 bottom-0 z-40 sm:inset-x-auto sm:right-4 sm:bottom-4">
                <div x-show="cartOpen" x-cloak @click.away="cartOpen = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-full"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:translate-x-0"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-full"
                     class="fixed inset-x-0 bottom-0 sm:inset-x-auto sm:right-0 sm:top-0 sm:bottom-0 bg-white border-t border-[#E5DDD0] sm:border-t-0 sm:border-l rounded-t-2xl sm:rounded-t-none sm:rounded-l-2xl shadow-2xl sm:w-full sm:max-w-md max-h-[70vh] sm:max-h-full sm:h-full flex flex-col overflow-hidden">
                    <div class="p-4 flex items-center justify-between border-b border-[#E5DDD0] shrink-0">
                        <h3 class="font-semibold text-gray-900">{{ __('Your Order') }}</h3>
                        <button type="button" @click="cartOpen = false" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto overscroll-contain p-4 space-y-3">
                        <template x-for="(line, index) in cart" :key="index">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="line.name"></p>
                                        <p class="text-xs text-gray-500" x-text="'₱' + line.price.toFixed(2) + ' ' + eachLabel"></p>
                                        <template x-for="addOn in (line.addOns ?? [])" :key="addOn.id">
                                            <p class="text-xs text-gray-500" x-text="'+ ' + addOn.qty + '× ' + addOn.name"></p>
                                        </template>
                                        <p x-show="line.notes" x-cloak class="text-xs text-gray-400 italic mt-0.5" x-text="line.notes"></p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="decrement(index)" class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50">−</button>
                                            <span class="w-5 text-center text-sm font-medium" x-text="line.qty"></span>
                                            <button type="button" @click="increment(index)" class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50">+</button>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-900 tabular-nums" x-text="'₱' + lineTotal(line).toFixed(2)"></span>
                                    </div>
                                </div>
                                <input type="hidden" :name="'items[' + index + '][menu_item_id]'" :value="line.id">
                                <input type="hidden" :name="'items[' + index + '][menu_item_variant_id]'" :value="line.variantId">
                                <input type="hidden" :name="'items[' + index + '][notes]'" :value="line.notes">
                                <input type="hidden" :name="'items[' + index + '][quantity]'" :value="line.qty">
                                <template x-for="(addOn, aidx) in (line.addOns ?? [])" :key="addOn.id">
                                    <span>
                                        <input type="hidden" :name="'items[' + index + '][add_ons][' + aidx + '][id]'" :value="addOn.id">
                                        <input type="hidden" :name="'items[' + index + '][add_ons][' + aidx + '][quantity]'" :value="addOn.qty">
                                    </span>
                                </template>
                            </div>
                        </template>

                        <div class="pt-1">
                            <x-input-label for="notes" :value="__('Notes (optional)')" />
                            <textarea id="notes" name="notes" rows="2" x-model="notes"
                                      class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error :messages="$errors->get('items')" class="mt-2" />
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="p-4 border-t border-[#E5DDD0] shrink-0">
                        <div class="flex items-center justify-between mb-4">
                            <span class="font-semibold text-gray-900">{{ __('Total') }}</span>
                            <span class="text-lg font-bold text-[#8A3330]" x-text="'₱' + total.toFixed(2)"></span>
                        </div>
                        <button type="button" :disabled="isEmpty" @click="cartOpen = false; confirmOpen = true"
                                class="w-full inline-flex items-center justify-center px-4 py-3 rounded-lg font-semibold text-white bg-[#8A3330] hover:bg-[#742927] disabled:opacity-40 disabled:cursor-not-allowed transition">
                            {{ __('Place Order') }}
                        </button>
                    </div>
                </div>

                <button type="button" x-show="!cartOpen" x-cloak @click="cartOpen = true"
                        class="w-full sm:w-auto sm:rounded-full bg-[#8A3330] hover:bg-[#742927] text-white px-4 py-3.5 sm:px-5 sm:py-3 flex items-center justify-between sm:gap-4 shadow-2xl transition">
                    <span class="text-sm font-semibold" x-text="count + ' {{ __('items') }} · ₱' + total.toFixed(2)"></span>
                    <span class="text-sm font-semibold sm:ms-1">{{ __('View Order') }}</span>
                </button>
            </div>

            <x-order-confirm-modal />
        </form>
        <x-menu.add-confirm-modal />
    </div>
</x-customer-layout>
