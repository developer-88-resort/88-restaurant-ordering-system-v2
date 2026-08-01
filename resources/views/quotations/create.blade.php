<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New Quotation / Advance Order') }}</h2>
            <a href="{{ route('quotations.index') }}" class="text-sm text-[#8A3330] hover:underline font-medium">{{ __('Back to Quotations') }}</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('quotations.store') }}"
          data-draft-key="quotations-create"
          x-data="{
              menu: @js($categories->map(fn ($category) => [
                  'name' => $category->name,
                  'items' => $category->menuItems->values()->map(fn ($item) => [
                      'id' => $item->id,
                      'name' => $item->name,
                      'price' => (float) $item->price,
                      'variants' => $item->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => (float) $v->price])->values(),
                  ]),
              ])->values()),
              rows: [{ menuItemId: '', variantId: '', qty: 1, notes: '' }],
              get flatItems() {
                  return this.menu.flatMap(c => c.items);
              },
              itemFor(row) {
                  return this.flatItems.find(i => i.id === Number(row.menuItemId)) ?? null;
              },
              rowPrice(row) {
                  const item = this.itemFor(row);
                  if (!item) return 0;
                  if (item.variants.length > 0) {
                      const variant = item.variants.find(v => v.id === Number(row.variantId)) ?? item.variants[0];
                      return variant ? variant.price : 0;
                  }
                  return item.price;
              },
              addRow() {
                  this.rows.push({ menuItemId: '', variantId: '', qty: 1, notes: '' });
              },
              removeRow(index) {
                  this.rows.splice(index, 1);
                  if (this.rows.length === 0) this.addRow();
              },
              get subtotal() {
                  return this.rows.reduce((sum, row) => sum + this.rowPrice(row) * (Number(row.qty) || 0), 0);
              },
          }">
        @csrf

        <div class="flex flex-col lg:flex-row gap-6 items-start">
            <div class="flex-1 w-full space-y-6">
                <div class="bg-white border border-[#E5DDD0] rounded-xl p-6 space-y-5">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Reservation Details') }}</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="space_id" :value="__('Table')" />
                            <select id="space_id" name="space_id" required
                                    class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                                <option value="">{{ __('Select a table...') }}</option>
                                @foreach ($areas as $area)
                                    @foreach ($area->categories as $category)
                                        @if ($category->spaces->isNotEmpty())
                                            <optgroup label="{{ $area->name }} — {{ $category->name }}">
                                                @foreach ($category->spaces as $space)
                                                    <option value="{{ $space->id }}" @selected(old('space_id') == $space->id)>{{ $space->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('space_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="scheduled_for" :value="__('Scheduled Date & Time')" />
                            <input id="scheduled_for" type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for') }}" required
                                   class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                            <x-input-error :messages="$errors->get('scheduled_for')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="customer_name" :value="__('Customer Name (optional)')" />
                            <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name') }}"
                                   class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                            <x-input-error :messages="$errors->get('customer_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="customer_contact" :value="__('Contact Number (optional)')" />
                            <input id="customer_contact" type="text" name="customer_contact" value="{{ old('customer_contact') }}"
                                   class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                            <x-input-error :messages="$errors->get('customer_contact')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Notes (optional)')" />
                        <textarea id="notes" name="notes" rows="2"
                                  class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>

                <div class="bg-white border border-[#E5DDD0] rounded-xl p-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Quoted Items') }}</h3>
                    <p class="text-sm text-gray-500 mt-1 mb-4">{{ __('Prices are frozen at today\'s menu prices — converting later never reprices them. Weight-priced items cannot be quoted in advance.') }}</p>
                    <x-input-error :messages="$errors->get('items')" class="mb-3" />

                    <div class="space-y-3">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-start border border-dashed border-[#E5DDD0] rounded-lg p-3">
                                <div class="sm:col-span-5">
                                    <select :name="'items[' + index + '][menu_item_id]'" x-model="row.menuItemId" required
                                            @change="row.variantId = ''"
                                            class="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                                        <option value="">{{ __('Select an item...') }}</option>
                                        <template x-for="category in menu" :key="category.name">
                                            <optgroup :label="category.name">
                                                <template x-for="item in category.items" :key="item.id">
                                                    <option :value="item.id" x-text="item.name"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </div>
                                <div class="sm:col-span-3">
                                    <template x-if="itemFor(row) && itemFor(row).variants.length > 0">
                                        <select :name="'items[' + index + '][menu_item_variant_id]'" x-model="row.variantId"
                                                class="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                                            <template x-for="variant in itemFor(row).variants" :key="variant.id">
                                                <option :value="variant.id" x-text="variant.name + ' — ₱' + variant.price.toFixed(2)"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="!itemFor(row) || itemFor(row).variants.length === 0">
                                        <p class="text-sm text-gray-500 pt-2" x-text="itemFor(row) ? '₱' + itemFor(row).price.toFixed(2) : ''"></p>
                                    </template>
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="number" :name="'items[' + index + '][quantity]'" x-model.number="row.qty" min="1" max="99" required
                                           class="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm">
                                </div>
                                <div class="sm:col-span-2 flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-900" x-text="'₱' + (rowPrice(row) * (Number(row.qty) || 0)).toFixed(2)"></span>
                                    <button type="button" @click="removeRow(index)" class="text-xs text-red-600 hover:underline shrink-0">{{ __('Remove') }}</button>
                                </div>
                                <div class="sm:col-span-12">
                                    <input type="text" :name="'items[' + index + '][notes]'" x-model="row.notes" placeholder="{{ __('Item note (optional)') }}"
                                           class="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-xs">
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addRow()" class="mt-3 text-sm font-medium text-[#8A3330] hover:underline">
                        + {{ __('Add Item') }}
                    </button>
                </div>
            </div>

            <div class="w-full lg:w-80 shrink-0">
                <div class="bg-white border border-[#E5DDD0] rounded-xl p-6 lg:sticky lg:top-20">
                    <h3 class="font-semibold text-gray-900">{{ __('Quotation Summary') }}</h3>
                    <div class="mt-4 pt-4 border-t border-[#E5DDD0] flex items-center justify-between">
                        <span class="font-semibold text-gray-900">{{ __('Quoted Subtotal') }}</span>
                        <span class="text-lg font-bold text-[#8A3330]" x-text="'₱' + subtotal.toFixed(2)"></span>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">{{ __('A quotation does not affect any bill and never appears in the kitchen until it is converted into an actual order.') }}</p>
                    <button type="submit"
                            class="mt-4 w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg font-semibold text-white bg-[#8A3330] hover:bg-[#742927] transition">
                        {{ __('Save Quotation') }}
                    </button>
                    <a href="{{ route('quotations.index') }}" class="block mt-3 text-center text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
