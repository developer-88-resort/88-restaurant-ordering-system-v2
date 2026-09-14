@props(['location' => null])

{{-- Expects an ancestor Alpine scope exposing: confirmOpen, submitting, cart, notes, total, count, eachLabel --}}
<div
    x-data="{
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, [tabindex]:not([tabindex=\'-1\'])';
            return [...$el.querySelectorAll(selector)].filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);
        },
        firstFocusable() { return this.focusables()[0]; },
        lastFocusable() { return this.focusables().slice(-1)[0]; },
        nextFocusable() {
            const list = this.focusables();
            return list[(list.indexOf(document.activeElement) + 1) % list.length] || this.firstFocusable();
        },
        prevFocusable() {
            const list = this.focusables();
            return list[Math.max(0, list.indexOf(document.activeElement) - 1)] || this.lastFocusable();
        },
    }"
    x-init="$watch('confirmOpen', value => {
        if (value) {
            document.body.classList.add('overflow-hidden');
            $nextTick(() => setTimeout(() => firstFocusable()?.focus(), 50));
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    })"
    x-show="confirmOpen" x-cloak
    x-on:keydown.escape.window="if (confirmOpen && !submitting) confirmOpen = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    role="dialog" aria-modal="true" aria-labelledby="confirm-order-title"
    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
>
    <div x-show="confirmOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-on:click="if (!submitting) confirmOpen = false"
         class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div x-show="confirmOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
         class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[85vh] flex flex-col overflow-hidden">

        <div class="px-5 pt-5 pb-4 border-b border-[#E5DDD0] flex items-start gap-3">
            <span class="h-10 w-10 rounded-full bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <h3 id="confirm-order-title" class="font-semibold text-gray-900">{{ __('Confirm Your Order') }}</h3>
                <p class="text-xs text-[#8A7B6D] mt-0.5">{{ __('Please review before sending to the kitchen.') }}</p>
                @if ($location)
                    <span class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-[#8A3330] bg-[#F3E1DC] rounded-full px-2 py-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3 w-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        {{ $location }}
                    </span>
                @endif
            </div>
            <button type="button" x-on:click="if (!submitting) confirmOpen = false" :disabled="submitting" aria-label="{{ __('Close') }}"
                    class="text-gray-400 hover:text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="px-5 py-3 overflow-y-auto divide-y divide-[#F0E6D8]">
            <template x-for="(line, index) in cart" :key="index">
                <div class="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                    <div class="min-w-0 flex items-start gap-2">
                        <span class="mt-0.5 shrink-0 text-xs font-semibold text-[#8A3330] bg-[#F3E1DC] rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center tabular-nums" x-text="line.qty"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate" x-text="line.name"></p>
                            <p class="text-xs text-[#8A7B6D] tabular-nums" x-text="'₱' + line.price.toFixed(2) + ' ' + eachLabel"></p>
                            {{-- Not every scope that includes this shared modal defines add-ons
                                 on its cart lines (the staff order form's own cart doesn't) — the
                                 (line.addOns ?? []) guard keeps this a no-op there rather than a
                                 hard dependency on a field that page never sets. --}}
                            <template x-for="addOn in (line.addOns ?? [])" :key="addOn.id">
                                <p class="text-xs text-[#8A7B6D] tabular-nums" x-text="'+ ' + addOn.qty + '× ' + addOn.name + ' (₱' + (addOn.price * addOn.qty).toFixed(2) + ')'"></p>
                            </template>
                            <p x-show="line.notes" x-cloak class="text-xs text-gray-400 italic mt-0.5" x-text="line.notes"></p>
                        </div>
                    </div>
                    {{-- Same reasoning: computed inline rather than calling an ancestor
                         lineTotal(line) helper, since the staff order form's scope (which
                         also includes this component) never defines one. --}}
                    <p class="text-sm font-semibold text-gray-900 shrink-0 tabular-nums"
                       x-text="'₱' + (line.price * line.qty + (line.addOns ?? []).reduce((sum, a) => sum + a.price * a.qty, 0)).toFixed(2)"></p>
                </div>
            </template>

            <template x-if="notes.trim().length">
                <div class="py-3">
                    <p class="text-xs font-medium text-[#8A7B6D] mb-1">{{ __('Notes') }}</p>
                    <p class="text-sm text-gray-700" x-text="notes"></p>
                </div>
            </template>
        </div>

        <div class="px-5 py-4 border-t border-[#E5DDD0] bg-[#FAF6EE]">
            <div class="flex items-end justify-between mb-4">
                <div>
                    <span class="font-semibold text-gray-900">{{ __('Total') }}</span>
                    <p class="text-xs text-[#8A7B6D]" x-text="count + ' {{ __('items') }}'"></p>
                </div>
                <span class="text-lg font-bold text-[#8A3330] tabular-nums" x-text="'₱' + total.toFixed(2)"></span>
            </div>
            <div class="flex gap-3">
                <button type="button" :disabled="submitting" x-on:click="if (!submitting) confirmOpen = false"
                        class="flex-1 px-4 py-3 rounded-lg font-semibold text-gray-700 bg-white border border-[#D9CCBA] hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition">
                    {{ __('Edit Order') }}
                </button>
                <button type="submit" :disabled="submitting"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-semibold text-white bg-[#8A3330] hover:bg-[#742927] disabled:opacity-70 disabled:cursor-not-allowed transition">
                    <svg x-show="submitting" x-cloak xmlns="http://www.w3.org/2000/svg" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="submitting ? '{{ __('Placing Order...') }}' : '{{ __('Confirm & Place') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
