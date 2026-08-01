<x-customer-layout :location-label="$space->area->name.' - '.$space->name">
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
            get addConfirmSubtotal() {
                return this.addConfirmUnitPrice * this.addConfirmQty;
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
                });
                this.closeAddConfirm();
            },
            selectedCategory: 'all',
            confirmOpen: false,
            submitting: false,
            submitKey: (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2)),
            sharePanelOpen: false,
            myOrdersOpen: false,
            reorderBatch(items) {
                let added = 0;
                items.forEach(item => {
                    if (!item.available) {
                        this.notifyOutOfStock(item.name);
                        return;
                    }
                    this.cart.push({ id: item.menu_item_id, variantId: item.variant_id ?? null, name: item.name, price: item.price, qty: item.qty, notes: null });
                    added++;
                });
                if (added > 0) {
                    this.pushToast(@js(__('Items added from your previous order')), 'success');
                    this.myOrdersOpen = false;
                    this.cartOpen = true;
                }
            },
            cartStorageKey: 'cart_space_{{ $space->id }}',
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
            watchCategoryScroll() {
                const sections = [...this.$el.querySelectorAll('[data-category-id]')];
                if (!sections.length) return;

                // Active line sits just below the sticky header + chip bar.
                // The active category is the last section whose top has
                // already scrolled past that line — the standard scrollspy
                // rule, and unlike IntersectionObserver it stays correct in
                // both scroll directions and never flickers between two
                // categories when one section is short.
                const activeLine = 140;
                let ticking = false;

                const updateActiveCategory = () => {
                    let current = sections[0];

                    for (const section of sections) {
                        if (section.getBoundingClientRect().top - activeLine <= 0) {
                            current = section;
                        } else {
                            break;
                        }
                    }

                    this.selectedCategory = Number(current.dataset.categoryId);
                    ticking = false;
                };

                updateActiveCategory();

                window.addEventListener('scroll', () => {
                    if (ticking) return;
                    ticking = true;
                    window.requestAnimationFrame(updateActiveCategory);
                }, { passive: true });
            },
            centerChip(value) {
                const bar = this.$refs.chipBar;
                const chip = document.getElementById('chip-' + value);
                if (!bar || !chip) return;

                // Scroll only the chip strip itself (never scrollIntoView here —
                // it drags the whole page's scroll position too when the strip
                // is inside a position:sticky ancestor).
                bar.scrollTo({
                    left: chip.offsetLeft - (bar.clientWidth / 2) + (chip.clientWidth / 2),
                    behavior: 'smooth',
                });
            },
            addItem(item) {
                const variantId = item.variantId ?? null;
                const notes = item.notes ?? null;
                const qty = item.qty ?? 1;
                // Different special instructions must stay separate lines
                // (e.g. one no-onions + one plain) even for the same
                // item/variant — merging them would hide the distinction
                // from the kitchen.
                const existing = this.cart.find(line => line.id === item.id && line.variantId === variantId && line.notes === notes);
                if (existing) {
                    existing.qty += qty;
                } else {
                    this.cart.push({ id: item.id, variantId, name: item.name, price: item.price, qty, notes });
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
            get total() {
                return this.cart.reduce((sum, line) => sum + (line.price * line.qty), 0);
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
            $nextTick(() => watchCategoryScroll());
            $watch('selectedCategory', (value) => centerChip(value));
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

        <form method="POST" action="{{ route('customer.orders.store', $space) }}"
              @submit="if (submitting) { $event.preventDefault(); return; } submitting = true; clearSavedCart();">
            @csrf
            <input type="hidden" name="idempotency_key" :value="submitKey">
            @if ($customerName)
                <input type="hidden" name="customer_name" value="{{ $customerName }}">
            @endif

            @if ($customerName)
                <p class="px-4 pt-3 text-sm text-[#8A7B6D] max-w-5xl mx-auto">{{ __('Hi :name! Here\'s the menu — add whatever you like.', ['name' => $customerName]) }}</p>
            @endif

            @isset($tableSession)
                {{-- Table dining session: guest identity, shareable child QR,
                     and this guest's previous order batches in this session --}}
                <div class="px-4 pt-3 max-w-5xl mx-auto">
                    <div class="bg-white border border-[#E5DDD0] rounded-xl px-4 py-3">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $space->name }} · {{ $guestSession->displayLabel() }}</p>
                                @if ($sessionOrderCount > 0)
                                    <p class="text-xs text-[#8A7B6D]">{{ __('Table total so far') }}: ₱{{ number_format($sessionTotal, 2) }} ({{ $sessionOrderCount }} {{ $sessionOrderCount === 1 ? __('order') : __('orders') }})</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if (count($previousOrders) > 0)
                                    <button type="button" @click="myOrdersOpen = ! myOrdersOpen; sharePanelOpen = false"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-full border border-[#D9CCBA] text-gray-700 hover:border-[#8A3330] transition">
                                        {{ __('My Orders') }} ({{ count($previousOrders) }})
                                    </button>
                                @endif
                                <button type="button" @click="sharePanelOpen = ! sharePanelOpen; myOrdersOpen = false"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-full border border-[#8A3330] text-[#8A3330] hover:bg-[#FAF6EE] transition">
                                    ⊞ {{ __('Share Table QR') }}
                                </button>
                            </div>
                        </div>

                        <div x-show="sharePanelOpen" x-cloak x-transition class="mt-3 pt-3 border-t border-dashed border-[#E5DDD0] flex flex-col sm:flex-row items-center gap-4">
                            <img src="{{ $joinQrUrl }}" alt="{{ __('Join Table Session QR') }}" class="h-40 w-40 rounded-lg border border-[#E5DDD0] bg-white">
                            <div class="text-sm text-[#8A7B6D] space-y-1">
                                <p class="font-semibold text-gray-900">{{ __('Ordering together?') }}</p>
                                <p>{{ __('Let your companions scan this QR with their own phones — everyone gets their own cart, and all orders stay under :table.', ['table' => $space->name]) }}</p>
                            </div>
                        </div>

                        <div x-show="myOrdersOpen" x-cloak x-transition class="mt-3 pt-3 border-t border-dashed border-[#E5DDD0] space-y-3">
                            @foreach ($previousOrders as $previousOrder)
                                <div class="rounded-lg border border-[#E5DDD0] px-3 py-2.5">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ __('Batch') }} #{{ $previousOrder['batch'] }}
                                                <span class="font-normal text-xs text-gray-400">{{ $previousOrder['number'] }} · {{ $previousOrder['placedAt'] }}</span>
                                            </p>
                                            <p class="text-xs text-[#8A7B6D]">{{ $previousOrder['status'] }} · {{ $previousOrder['paymentStatus'] }} · ₱{{ number_format($previousOrder['total'], 2) }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <a href="{{ $previousOrder['statusUrl'] }}" class="text-xs font-medium text-[#8A3330] hover:underline">{{ __('Track') }}</a>
                                            <button type="button" @click="reorderBatch({{ Js::from($previousOrder['items']) }})"
                                                    class="text-xs font-semibold px-2.5 py-1 rounded-full border border-[#8A3330] text-[#8A3330] hover:bg-[#FAF6EE] transition">
                                                {{ __('Order Again') }}
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 truncate">
                                        {{ collect($previousOrder['items'])->map(fn ($i) => $i['qty'].'× '.$i['name'])->implode(', ') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endisset

            @if ($categories->isNotEmpty())
                <div class="sticky top-16 z-30 bg-[#F7F0E3]/95 backdrop-blur-sm border-b border-[#E5DDD0]">
                    <div x-ref="chipBar" class="max-w-5xl mx-auto px-4 py-2.5 flex items-center gap-2 overflow-x-auto no-scrollbar">
                        <button type="button"
                                id="chip-all"
                                @click="selectCategory('all')"
                                :class="selectedCategory === 'all' ? 'bg-[#8A3330] text-white border-[#8A3330]' : 'bg-white text-gray-600 border-[#E5DDD0] hover:border-[#8A3330]'"
                                class="shrink-0 whitespace-nowrap text-sm font-medium px-3.5 py-1.5 rounded-full border transition-colors">
                            {{ __('All') }}
                        </button>
                        @foreach ($categories as $category)
                            <button type="button"
                                    id="chip-{{ $category->id }}"
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
                        @php $optionCount = $category->orderableOptionCount(); @endphp
                        <div id="category-{{ $category->id }}" data-category-id="{{ $category->id }}" class="scroll-mt-32">
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="h-5 w-1 rounded-full bg-[#8A3330]"></span>
                                <h3 class="font-semibold text-gray-900">{{ $category->name }}</h3>
                                <span class="text-xs font-medium text-[#8A7B6D] bg-[#F7F0E3] border border-[#E5DDD0] rounded-full px-2 py-0.5">{{ $optionCount }} {{ $optionCount === 1 ? __('option') : __('options') }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($category->menuItems as $item)
                                    <x-menu.item-card :item="$item" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Sticky bottom cart: full-width bar + bottom sheet on mobile, floating
                 pill + right-side panel on desktop (matches x-menu.add-confirm-modal) --}}
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
                                        <p x-show="line.notes" x-cloak class="text-xs text-gray-400 italic mt-0.5" x-text="line.notes"></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="decrement(index)" class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50">−</button>
                                        <span class="w-5 text-center text-sm font-medium" x-text="line.qty"></span>
                                        <button type="button" @click="increment(index)" class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50">+</button>
                                    </div>
                                </div>
                                <input type="hidden" :name="'items[' + index + '][menu_item_id]'" :value="line.id">
                                <input type="hidden" :name="'items[' + index + '][menu_item_variant_id]'" :value="line.variantId">
                                <input type="hidden" :name="'items[' + index + '][notes]'" :value="line.notes">
                                <input type="hidden" :name="'items[' + index + '][quantity]'" :value="line.qty">
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
                        class="w-full sm:w-auto bg-[#8A3330] hover:bg-[#742927] text-white px-4 py-3.5 sm:px-5 sm:py-3 sm:rounded-full flex items-center justify-between sm:gap-4 shadow-2xl transition">
                    <span class="flex items-center gap-3">
                        <span class="relative shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                            </svg>
                            <span
                                x-show="count > 0" x-cloak x-text="count"
                                class="absolute -top-2 -right-2 h-5 min-w-[1.25rem] px-1 rounded-full bg-white text-[#8A3330] text-[11px] font-bold flex items-center justify-center leading-none"
                            ></span>
                        </span>
                        <span class="text-sm font-semibold" x-text="'₱' + total.toFixed(2)"></span>
                    </span>
                    <span class="text-sm font-semibold sm:ms-1">{{ __('View Order') }}</span>
                </button>
            </div>

            <x-order-confirm-modal :location="$space->area->name.' · '.$space->name" />
        </form>
        <x-menu.add-confirm-modal />
    </div>
</x-customer-layout>
