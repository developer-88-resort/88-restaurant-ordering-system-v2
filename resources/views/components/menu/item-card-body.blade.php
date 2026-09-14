@props(['item', 'imageUrl' => null])

<div class="h-16 w-16 rounded-lg bg-[#FAF6EE] shrink-0 overflow-hidden flex items-center justify-center">
    @if ($imageUrl)
        <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
    @else
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" class="h-6 w-6 text-[#D9CCBA]">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
    @endif
</div>
<div class="min-w-0 flex-1">
    <p class="text-sm font-medium text-gray-900">{{ $item->name }}</p>
    @if ($item->description)
        <p class="text-xs text-gray-500 truncate">{{ $item->description }}</p>
    @endif
    @if ($item->isPerKilo())
        @php($rate = $item->effectivePricePerKilo())
        @if ($rate)
            <p class="text-sm font-semibold text-[#8A3330] mt-1">₱{{ number_format($rate, 0) }}/kg</p>
            @if ($item->min_weight_grams > 0)
                <p class="text-[11px] text-gray-400">
                    {{ __('min') }}
                    {{ $item->min_weight_grams >= 1000 ? number_format($item->min_weight_grams / 1000, $item->min_weight_grams % 1000 === 0 ? 0 : 1).' kg' : $item->min_weight_grams.' g' }}
                    (~₱{{ number_format(($item->min_weight_grams / 1000) * $rate, 0) }})
                </p>
            @endif
        @endif
        @if ($item->resolvedCookingStyles()->isNotEmpty())
            <p class="text-[11px] text-gray-500 truncate mt-1">{{ $item->resolvedCookingStyles()->pluck('name')->implode(', ') }}</p>
        @endif
        <p class="inline-flex items-center gap-1 rounded-full bg-[#F3E1DC] px-2 py-0.5 text-[10px] font-semibold text-[#8A3330] mt-1">
            {{ __('Ask our staff — counter service') }}
        </p>
    @else
        <p class="text-sm font-semibold text-[#8A3330] mt-0.5">{{ $item->priceRangeLabel() }}</p>
    @endif
    <template x-if="itemAvailability[{{ $item->id }}] === 'out_of_stock'">
        <span class="inline-flex items-center gap-1 mt-1 text-[11px] font-semibold text-gray-500">
            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>{{ __('Out of Stock') }}
        </span>
    </template>
    <template x-if="itemAvailability[{{ $item->id }}] === 'seasonal'">
        <span class="inline-flex items-center gap-1 mt-1 text-[11px] font-semibold text-amber-600">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>{{ __('Seasonal') }}
        </span>
    </template>
</div>
@if ($item->isPerKilo())
    {{-- No action to take here — no chevron, no add-to-cart icon. --}}
@elseif ($item->hasVariants())
    {{-- Chevron instead of the plus-in-circle used for plain items — hints
         that tapping opens a choice (variant picker) rather than adding
         directly to the cart. --}}
    <template x-if="isOrderable({{ $item->id }})">
        <span class="shrink-0 h-8 w-8 rounded-full bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </span>
    </template>
@else
    <template x-if="isOrderable({{ $item->id }})">
        <span class="shrink-0 h-8 w-8 rounded-full bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
        </span>
    </template>
@endif
