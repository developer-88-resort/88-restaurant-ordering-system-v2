{{--
    Shared across the customer-facing cart views (customer/menu.blade.php,
    welcome-takeout.blade.php) — the single "Add this item to your cart?"
    confirmation step for every tap-to-add item, plain or variant. Expects
    an ancestor Alpine scope exposing:
      - addConfirmItem: null or {id, name, imageUrl, price, hasVariants, variants:[{id,name,price,imageUrl,isDefault}], addOns:[{id,name,price}]}
      - addConfirmVariantId, addConfirmQty, addConfirmNotes, addConfirmError, addConfirmSubmitting, addConfirmSelectedAddOns
      - addConfirmSelectedVariant / addConfirmUnitPrice / addConfirmSubtotal / addConfirmAvailable / addConfirmSelectedAddOnList (getters)
      - closeAddConfirm(), confirmAddItem(), incrementAddConfirmQty(), decrementAddConfirmQty(), toggleAddConfirmAddOn(id), setAddConfirmAddOnQty(id, qty)
    Styled to match x-order-confirm-modal / the retired x-menu.variant-picker-modal.
--}}
<div
    x-show="addConfirmItem !== null" x-cloak
    x-init="$watch('addConfirmItem', (value) => {
        document.body.classList.toggle('overflow-hidden', value !== null);
    })"
    x-on:keydown.escape.window="closeAddConfirm()"
    role="dialog" aria-modal="true" aria-labelledby="add-confirm-title"
    class="fixed inset-0 z-50 flex items-end sm:items-stretch justify-center sm:justify-end"
>
    <div x-show="addConfirmItem !== null"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-on:click="closeAddConfirm()"
         class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div x-show="addConfirmItem !== null"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:translate-x-full"
         x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:translate-x-0"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:translate-x-full"
         class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-t-none sm:rounded-l-2xl shadow-2xl max-h-[92vh] sm:max-h-full sm:h-full flex flex-col overflow-hidden">

        {{-- Header: image, name, close --}}
        <div class="px-5 pt-5 pb-4 border-b border-[#E5DDD0] flex items-start gap-3 shrink-0">
            <div class="h-14 w-14 rounded-lg bg-[#FAF6EE] shrink-0 overflow-hidden flex items-center justify-center">
                <template x-if="addConfirmItem?.imageUrl">
                    <img :src="addConfirmItem.imageUrl" class="h-full w-full object-cover" :alt="addConfirmItem?.name">
                </template>
            </div>
            <div class="min-w-0 flex-1 pt-0.5">
                <h3 id="add-confirm-title" class="font-semibold text-gray-900" x-text="addConfirmItem?.name"></h3>
                <template x-if="addConfirmItem?.description">
                    <p class="text-sm text-gray-500 mt-0.5" x-text="addConfirmItem.description"></p>
                </template>
                <p class="text-sm text-[#8A7B6D] mt-0.5">{{ __('Add this item to your cart?') }}</p>
            </div>
            <button type="button" x-on:click="closeAddConfirm()" aria-label="{{ __('Close') }}" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 px-5 py-4 overflow-y-auto overscroll-contain space-y-5">
            {{-- Out-of-stock notice replaces the interactive parts entirely --}}
            <template x-if="addConfirmItem && !addConfirmAvailable">
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 font-medium">
                    {{ __('Currently unavailable') }}
                </div>
            </template>

            <template x-if="addConfirmItem && addConfirmAvailable">
                <div class="space-y-5">
                    {{-- Variant / size / flavor selector --}}
                    <template x-if="addConfirmItem.hasVariants">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Choose an option') }}</p>
                            <div class="space-y-2">
                                <template x-for="variant in addConfirmItem.variants" :key="variant.id">
                                    <button type="button"
                                            @click="addConfirmVariantId = variant.id; addConfirmError = null"
                                            :class="addConfirmVariantId === variant.id ? 'border-[#8A3330] bg-[#FAF6EE] ring-1 ring-[#8A3330]' : 'border-[#E5DDD0] hover:border-[#8A3330]'"
                                            class="w-full flex items-center gap-3 border rounded-lg px-3 py-2.5 text-left transition">
                                        <div class="h-10 w-10 rounded-md bg-[#FAF6EE] shrink-0 overflow-hidden flex items-center justify-center">
                                            <template x-if="variant.imageUrl">
                                                <img :src="variant.imageUrl" class="h-full w-full object-cover">
                                            </template>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900" x-text="variant.name"></p>
                                            <template x-if="variant.description">
                                                <p class="text-xs text-[#8A7B6D] mt-0.5 line-clamp-2" x-text="variant.description"></p>
                                            </template>
                                        </div>
                                        <span class="text-sm font-semibold text-[#8A3330] shrink-0" x-text="'₱' + Number(variant.price).toFixed(2)"></span>
                                        <span class="shrink-0 h-5 w-5 rounded-full border-2 flex items-center justify-center"
                                              :class="addConfirmVariantId === variant.id ? 'border-[#8A3330] bg-[#8A3330]' : 'border-[#D9CCBA]'">
                                            <svg x-show="addConfirmVariantId === variant.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="white" class="h-3 w-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="addConfirmError" x-cloak class="mt-2 text-xs font-medium text-red-600" x-text="addConfirmError"></p>
                        </div>
                    </template>

                    {{-- Optional extras — each a checkbox that reveals its own quantity stepper once checked --}}
                    <template x-if="addConfirmItem?.addOns?.length > 0">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Add-ons') }}</p>
                            <div class="space-y-2">
                                <template x-for="addOn in addConfirmItem.addOns" :key="addOn.id">
                                    <div :class="[addConfirmSelectedAddOns[addOn.id]?.checked ? 'border-[#8A3330] bg-[#FAF6EE]' : 'border-[#E5DDD0]', addOn.price <= 0 ? 'opacity-70' : '']"
                                         class="rounded-lg border px-3 py-2.5 transition">
                                        <label :class="addOn.price > 0 ? 'cursor-pointer' : 'cursor-default'" class="flex items-start gap-3">
                                            <input type="checkbox"
                                                   :checked="addConfirmSelectedAddOns[addOn.id]?.checked"
                                                   :disabled="addOn.price <= 0"
                                                   @change="addOn.price > 0 && toggleAddConfirmAddOn(addOn.id)"
                                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-[#D9CCBA] text-[#8A3330] focus:ring-[#8A3330] disabled:opacity-40 disabled:cursor-not-allowed">
                                            <span class="flex-1 min-w-0">
                                                <span class="block text-sm font-medium text-gray-900" x-text="addOn.name"></span>
                                                <span x-show="addOn.description" x-cloak class="block text-xs text-[#8A7B6D]" x-text="addOn.description"></span>
                                            </span>
                                            <template x-if="addOn.price > 0">
                                                <span class="shrink-0 text-sm font-semibold text-[#8A3330]" x-text="'₱' + Number(addOn.price).toFixed(2)"></span>
                                            </template>
                                            <template x-if="addOn.price <= 0">
                                                <span class="shrink-0 text-xs font-medium text-[#8A7B6D]">{{ __('Ask staff') }}</span>
                                            </template>
                                        </label>
                                        <div x-show="addConfirmSelectedAddOns[addOn.id]?.checked" x-cloak class="mt-2 flex items-center justify-end gap-3 pl-7">
                                            <button type="button"
                                                    @click="setAddConfirmAddOnQty(addOn.id, (addConfirmSelectedAddOns[addOn.id]?.qty ?? 1) - 1)"
                                                    :disabled="(addConfirmSelectedAddOns[addOn.id]?.qty ?? 1) <= 1"
                                                    class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">−</button>
                                            <span class="w-6 text-center text-sm font-semibold" x-text="addConfirmSelectedAddOns[addOn.id]?.qty ?? 1"></span>
                                            <button type="button"
                                                    @click="setAddConfirmAddOnQty(addOn.id, (addConfirmSelectedAddOns[addOn.id]?.qty ?? 1) + 1)"
                                                    :disabled="(addConfirmSelectedAddOns[addOn.id]?.qty ?? 1) >= 99"
                                                    class="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Quantity --}}
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">{{ __('Quantity') }}</p>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="decrementAddConfirmQty()" :disabled="addConfirmQty <= 1"
                                    class="h-8 w-8 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">−</button>
                            <span class="w-6 text-center text-sm font-semibold" x-text="addConfirmQty"></span>
                            <button type="button" @click="incrementAddConfirmQty()" :disabled="addConfirmQty >= 99"
                                    class="h-8 w-8 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">+</button>
                        </div>
                    </div>

                    {{-- Special instructions --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1.5">{{ __('Special instructions (optional)') }}</label>
                        <textarea x-model="addConfirmNotes" rows="2" maxlength="255" placeholder="{{ __('e.g. no onions, less spicy') }}"
                                  class="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"></textarea>
                    </div>

                    {{-- Subtotal --}}
                    <div class="flex items-center justify-between pt-3 border-t border-dashed border-[#E5DDD0]">
                        <span class="font-semibold text-gray-900">{{ __('Subtotal') }}</span>
                        <span class="text-lg font-bold text-[#8A3330]" x-text="'₱' + addConfirmSubtotal.toFixed(2)"></span>
                    </div>
                </div>
            </template>
        </div>

        <div class="px-5 py-4 border-t border-[#E5DDD0] flex gap-3 shrink-0">
            <button type="button" x-on:click="closeAddConfirm()"
                    class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 transition">
                {{ __('Cancel') }}
            </button>
            <button type="button" x-on:click="confirmAddItem()" :disabled="!addConfirmAvailable || addConfirmSubmitting"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold rounded-lg bg-[#8A3330] hover:bg-[#742927] text-white disabled:opacity-40 disabled:cursor-not-allowed transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
                {{ __('Yes, Add to Cart') }}
            </button>
        </div>
    </div>
</div>
