<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Order') }}
        </h2>
    </x-slot>

    @if ($areas->isEmpty())
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
            {{ __('Add a space first before creating orders.') }}
        </div>
    @elseif ($categories->isEmpty())
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
            {{ __('Add at least one available menu item first before creating orders.') }}
        </div>
    @else
        <form method="POST" action="{{ route('orders.store') }}"
              data-draft-key="orders-create"
              x-persist="{ key: 'orders-create-cart', paths: ['cart'] }"
              x-data="{
                  cart: [],
                  eachLabel: @js(__('each')),
                  orderType: 'dine_in',
                  areaId: null,
                  categoryId: null,
                  spaceId: null,
                  isFreeCategory: false,
                  showPicker: true,
                  activeAreaTab: {{ $areas->first()->id }},
                  areaNames: { {{ $areas->map(fn ($a) => "'{$a->id}': " . Js::from($a->name))->implode(', ') }} },
                  spaceNames: { {{ $areas->flatMap(fn ($a) => $a->categories->flatMap->spaces)->map(fn ($s) => "'{$s->id}': " . Js::from($s->name))->implode(', ') }} },
                  categoryNames: { {{ $areas->flatMap(fn ($a) => $a->categories)->map(fn ($c) => "'{$c->id}': " . Js::from($c->name))->implode(', ') }} },
                  selectSpace(areaId, categoryId, spaceId) {
                      this.areaId = areaId; this.categoryId = categoryId; this.spaceId = spaceId; this.isFreeCategory = false;
                      this.showPicker = false;
                  },
                  selectFreeCategory(areaId, categoryId) {
                      this.areaId = areaId; this.categoryId = categoryId; this.spaceId = null; this.isFreeCategory = true;
                      this.showPicker = false;
                  },
                  get locationSelected() {
                      return this.orderType === 'takeout' || (this.areaId && this.categoryId && (this.spaceId || this.isFreeCategory));
                  },
                  get locationLabel() {
                      const area = this.areaNames[this.areaId] ?? '';
                      const spot = this.spaceId ? this.spaceNames[this.spaceId] : this.categoryNames[this.categoryId];
                      return area + ' - ' + (spot ?? '');
                  },
                  addItem(item) {
                      const variantId = item.variantId ?? null;
                      const existing = this.cart.find(line => line.id === item.id && line.variantId === variantId);
                      if (existing) {
                          existing.qty++;
                      } else {
                          this.cart.push({ id: item.id, variantId, name: item.name, price: item.price, qty: 1 });
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
                      this.addItem({ id: item.id, variantId: variant.id, name: item.name + ' — ' + variant.name, price: variant.price });
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
                  get total() {
                      return this.cart.reduce((sum, line) => sum + (line.price * line.qty), 0);
                  },
                  get isEmpty() {
                      return this.cart.length === 0;
                  }
              }"
        >
            @csrf

            <input type="hidden" name="order_type" :value="orderType">
            <input type="hidden" name="area_id" :value="areaId">
            <input type="hidden" name="space_category_id" :value="categoryId">
            <input type="hidden" name="space_id" :value="spaceId">

            <div class="flex flex-col lg:flex-row gap-6 items-start">
                {{-- Menu browser --}}
                <div class="flex-1 w-full space-y-6">
                    {{-- Dine In / Take-out toggle --}}
                    <div class="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <x-input-label :value="__('Order Type')" />
                        <x-input-error :messages="$errors->get('order_type')" class="mt-1" />
                        <div class="flex gap-2 mt-3">
                            <button type="button" @click="orderType = 'dine_in'"
                                    :class="orderType === 'dine_in' ? 'bg-[#8A3330] text-white border-[#8A3330]' : 'bg-white text-gray-600 border-[#D9CCBA] hover:border-[#8A3330]'"
                                    class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg border transition">
                                {{ __('Dine In') }}
                            </button>
                            <button type="button" @click="orderType = 'takeout'"
                                    :class="orderType === 'takeout' ? 'bg-[#8A3330] text-white border-[#8A3330]' : 'bg-white text-gray-600 border-[#D9CCBA] hover:border-[#8A3330]'"
                                    class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg border transition">
                                {{ __('Take-out') }}
                            </button>
                        </div>
                    </div>

                    <div class="bg-white border border-[#E5DDD0] rounded-xl p-6" x-show="orderType === 'dine_in'">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <x-input-label :value="__('Location')" class="!mb-0" />
                            <p class="text-xs text-gray-400" x-show="!locationSelected" x-cloak>
                                {{ __('Select a table above to see the food menu.') }}
                            </p>
                        </div>
                        <x-input-error :messages="$errors->get('area_id')" class="mt-1" />
                        <x-input-error :messages="$errors->get('space_category_id')" class="mt-1" />
                        <x-input-error :messages="$errors->get('space_id')" class="mt-1" />

                        {{-- Compact summary once a location is picked --}}
                        <div x-show="!showPicker && locationSelected" x-cloak
                             class="mt-3 flex items-center justify-between gap-3 border border-[#8A3330] bg-[#FAF6EE] rounded-lg px-4 py-3">
                            <span class="text-sm font-semibold text-[#8A3330]" x-text="locationLabel"></span>
                            <button type="button" @click="showPicker = true" class="text-sm font-medium text-[#8A3330] hover:underline shrink-0">
                                {{ __('Change') }}
                            </button>
                        </div>

                        <div x-show="showPicker">
                        {{-- Area tabs --}}
                        <div class="flex gap-2 mt-3 mb-4 border-b border-[#E5DDD0] overflow-x-auto">
                            @foreach ($areas as $area)
                                <button type="button" @click="activeAreaTab = {{ $area->id }}"
                                        :class="activeAreaTab === {{ $area->id }} ? 'border-[#8A3330] text-[#8A3330]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap">
                                    {{ $area->name }}
                                </button>
                            @endforeach
                        </div>

                        @foreach ($areas as $area)
                            <div x-show="activeAreaTab === {{ $area->id }}"
                                 x-data="{ activeCategory: {{ $area->categories->first()->id ?? 'null' }} }">
                                @if ($area->categories->isEmpty())
                                    <p class="text-sm text-gray-400">{{ __('No categories set up for this area yet.') }}</p>
                                @else
                                    {{-- Category sub-tabs (only shown when there's more than one) --}}
                                    @if ($area->categories->count() > 1)
                                        <div class="flex flex-wrap gap-2 mb-4">
                                            @foreach ($area->categories as $category)
                                                <button type="button" @click="activeCategory = {{ $category->id }}"
                                                        :class="activeCategory === {{ $category->id }} ? 'bg-[#8A3330] text-white border-[#8A3330]' : 'bg-white text-gray-600 border-[#D9CCBA] hover:border-[#8A3330]'"
                                                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition">
                                                    {{ $category->name }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif

                                    @foreach ($area->categories as $category)
                                        <div x-show="activeCategory === {{ $category->id }}">
                                            @if ($category->is_free)
                                                @php $isFull = $category->isFull(); @endphp
                                                <button type="button"
                                                        @if (! $isFull) @click="selectFreeCategory({{ $area->id }}, {{ $category->id }})" @endif
                                                        :class="(areaId === {{ $area->id }} && categoryId === {{ $category->id }}) ? 'border-[#8A3330] bg-[#FAF6EE]' : 'border-[#E5DDD0]'"
                                                        class="w-full text-left border rounded-lg px-4 py-3 transition {{ $isFull ? 'opacity-40 cursor-not-allowed' : 'hover:border-[#8A3330] hover:bg-[#FAF6EE]' }}"
                                                        {{ $isFull ? 'disabled' : '' }}>
                                                    <span class="text-sm font-medium text-gray-900">{{ $category->name }}</span>
                                                    <span class="block text-xs text-gray-500 mt-0.5">
                                                        {{ $category->occupied_count }} / {{ $category->capacity_count ?? '—' }} {{ __('occupied') }}
                                                        @if ($isFull) &mdash; {{ __('Full') }} @endif
                                                    </span>
                                                </button>
                                            @elseif ($category->spaces->isEmpty())
                                                <p class="text-sm text-gray-400">{{ __('No spaces added under this category yet.') }}</p>
                                            @else
                                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                                    @foreach ($category->spaces as $space)
                                                        @php
                                                            $available = $space->status === \App\Enums\SpaceStatus::Available;
                                                            $accent = $space->status->pickerAccentClasses();
                                                            [$borderAccent, $textAccent] = explode(' ', $accent);
                                                        @endphp
                                                        <button type="button"
                                                                @if ($available) @click="selectSpace({{ $area->id }}, {{ $category->id }}, {{ $space->id }})" @endif
                                                                :class="spaceId === {{ $space->id }} ? 'border-[#8A3330] bg-[#FAF6EE]' : 'bg-white border-[#E5DDD0] {{ $borderAccent }}'"
                                                                class="border border-l-[6px] rounded-lg px-3 py-2.5 text-sm font-semibold text-center transition {{ $available ? 'cursor-pointer hover:bg-[#FAF6EE]' : 'cursor-not-allowed' }}"
                                                                {{ $available ? '' : 'disabled' }}>
                                                            <span :class="spaceId === {{ $space->id }} ? 'text-[#8A3330]' : 'text-gray-900'">{{ $space->name }}</span>
                                                            <span class="block text-[10px] font-medium {{ $textAccent }}">{{ $space->status->label() }}</span>
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

                    <template x-if="locationSelected">
                        <div class="space-y-6">
                            @foreach ($categories as $category)
                                <div class="bg-white border border-[#E5DDD0] rounded-xl p-6">
                                    <h3 class="font-semibold text-gray-900 mb-4">{{ $category->name }}</h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach ($category->menuItems as $item)
                                            @if ($item->hasVariants())
                                                <button type="button"
                                                        @click="openVariantPicker({ id: {{ $item->id }}, name: {{ Js::from($item->name) }}, variants: {{ Js::from($item->variants->map(fn ($variant) => [
                                                            'id' => $variant->id,
                                                            'name' => $variant->name,
                                                            'description' => $variant->description,
                                                            'price' => (float) $variant->price,
                                                            'imageUrl' => $variant->imageUrl(),
                                                        ])) }} })"
                                                        class="flex items-center justify-between gap-3 text-left border border-[#E5DDD0] rounded-lg px-4 py-3 hover:border-[#8A3330] hover:bg-[#FAF6EE] transition">
                                                    <span class="text-sm font-medium text-gray-900">{{ $item->name }}</span>
                                                    <span class="text-xs font-semibold text-[#8A7B9E] shrink-0">{{ $item->priceRangeLabel() }}</span>
                                                </button>
                                            @else
                                                <button type="button"
                                                        @click="addItem({ id: {{ $item->id }}, name: {{ Js::from($item->name) }}, price: {{ $item->price }} })"
                                                        class="flex items-center justify-between gap-3 text-left border border-[#E5DDD0] rounded-lg px-4 py-3 hover:border-[#8A3330] hover:bg-[#FAF6EE] transition">
                                                    <span class="text-sm font-medium text-gray-900">{{ $item->name }}</span>
                                                    <span class="text-sm font-semibold text-[#8A3330] shrink-0">₱{{ number_format($item->price, 2) }}</span>
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </template>
                </div>

                {{-- Cart --}}
                <div class="w-full lg:w-96 shrink-0">
                    <div class="bg-white border border-[#E5DDD0] rounded-xl p-6 lg:sticky lg:top-20">
                        <h3 class="font-semibold text-gray-900">{{ __('Order Summary') }}</h3>

                        <div class="mt-4 space-y-3" x-show="!isEmpty">
                            <template x-for="(line, index) in cart" :key="index">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="line.name"></p>
                                            <p class="text-xs text-gray-500" x-text="'₱' + line.price.toFixed(2) + ' ' + eachLabel"></p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="button" @click="decrement(index)" class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50">−</button>
                                            <span class="w-5 text-center text-sm font-medium" x-text="line.qty"></span>
                                            <button type="button" @click="increment(index)" class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50">+</button>
                                        </div>
                                    </div>
                                    <input type="hidden" :name="'items[' + index + '][menu_item_id]'" :value="line.id">
                                    <input type="hidden" :name="'items[' + index + '][menu_item_variant_id]'" :value="line.variantId">
                                    <input type="hidden" :name="'items[' + index + '][quantity]'" :value="line.qty">
                                </div>
                            </template>
                        </div>

                        <p class="mt-4 text-sm text-gray-400" x-show="isEmpty">{{ __('Tap menu items to add them to this order.') }}</p>

                        <div class="mt-4 pt-4 border-t border-[#E5DDD0] flex items-center justify-between" x-show="!isEmpty">
                            <span class="font-semibold text-gray-900">{{ __('Total') }}</span>
                            <span class="text-lg font-bold text-[#8A3330]" x-text="'₱' + total.toFixed(2)"></span>
                        </div>

                        <div class="mt-4">
                            <x-input-label for="notes" :value="__('Notes (optional)')" />
                            <textarea id="notes" name="notes" rows="2"
                                      class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('items')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <p class="text-xs text-gray-400 mb-2" x-show="!locationSelected">{{ __('Select a location above before placing the order.') }}</p>
                            <button type="submit" :disabled="isEmpty || !locationSelected"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-semibold text-white bg-[#8A3330] hover:bg-[#742927] disabled:opacity-40 disabled:cursor-not-allowed transition">
                                {{ __('Place Order') }}
                            </button>
                            <a href="{{ route('orders.index') }}" class="block mt-3 text-center text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <x-menu.variant-picker-modal />
        </form>
    @endif
</x-app-layout>
