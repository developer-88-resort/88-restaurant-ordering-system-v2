{{--
    Shared across the 2 order-taking cart views (customer/menu.blade.php,
    welcome-takeout.blade.php) and the staff orders/create.blade.php screen.
    Expects an ancestor Alpine scope exposing: variantPickerItem (null or
    {id, name, variants: [{id, name, price, imageUrl}]}), closeVariantPicker(),
    chooseVariant(variant). Styled to match x-order-confirm-modal.
--}}
<div
    x-show="variantPickerItem !== null" x-cloak
    x-on:keydown.escape.window="closeVariantPicker()"
    role="dialog" aria-modal="true" aria-labelledby="variant-picker-title"
    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
>
    <div x-show="variantPickerItem !== null"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-on:click="closeVariantPicker()"
         class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div x-show="variantPickerItem !== null"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
         class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[85vh] flex flex-col overflow-hidden">

        <div class="px-5 pt-5 pb-4 border-b border-[#E5DDD0] flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <h3 id="variant-picker-title" class="font-semibold text-gray-900" x-text="variantPickerItem?.name"></h3>
                <p class="text-xs text-[#8A7B6D] mt-0.5">{{ __('Choose an option') }}</p>
            </div>
            <button type="button" x-on:click="closeVariantPicker()" aria-label="{{ __('Close') }}" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="px-5 py-2 overflow-y-auto divide-y divide-[#F0E6D8]">
            <template x-for="variant in (variantPickerItem?.variants ?? [])" :key="variant.id">
                <button type="button" @click="chooseVariant(variant)"
                        class="w-full flex items-center gap-3 py-3 text-left hover:bg-[#FAF6EE] transition">
                    <div class="h-12 w-12 rounded-lg bg-[#FAF6EE] shrink-0 overflow-hidden flex items-center justify-center">
                        <template x-if="variant.imageUrl">
                            <img :src="variant.imageUrl" class="h-full w-full object-cover">
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900" x-text="variant.name"></p>
                        <template x-if="variant.description">
                            <p class="text-xs text-[#8A7B6D] mt-0.5 line-clamp-2" x-text="variant.description"></p>
                        </template>
                        <p class="text-xs text-[#8A3330] font-semibold mt-0.5" x-text="'₱' + Number(variant.price).toFixed(2)"></p>
                    </div>
                    <span class="shrink-0 h-8 w-8 rounded-full bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                    </span>
                </button>
            </template>
        </div>
    </div>
</div>
