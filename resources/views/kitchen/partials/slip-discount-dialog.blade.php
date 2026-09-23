@php
    $slipDiscountConfig = [
        'rules' => collect($slipDiscountRules ?? [])->map(fn (\App\Models\DiscountRule $rule) => [
            'id' => $rule->id,
            'name' => $rule->name,
            'mode' => $rule->calculation_mode->value,
            'value' => $rule->value !== null ? (float) $rule->value : null,
            'isCustom' => $rule->is_custom_value,
            'scope' => $rule->scope,
            'stackable' => $rule->is_stackable,
            'minBill' => $rule->min_bill_amount !== null ? (float) $rule->min_bill_amount : null,
            'maxAmount' => $rule->max_discount_amount !== null ? (float) $rule->max_discount_amount : null,
            'askName' => $rule->requires_customer_id,
        ])->values(),
        'text' => [
            'failed' => __('Could not save the discount. Check your connection and try again.'),
        ],
    ];
@endphp

{{--
    One dialog for the whole board. Each card's Discount button dispatches
    `kitchen-slip-discount` with that slip's lines; see
    resources/js/lib/kitchen-slip-discount.js. What is picked here is printed
    on the order slip only — checkout still picks the receipt's discounts.
--}}
<div
    x-data="kitchenSlipDiscount(@js($slipDiscountConfig))"
    @kitchen-slip-discount.window="open($event.detail)"
>
    <dialog
        x-ref="dialog"
        x-effect="isOpen ? $refs.dialog.showModal() : $refs.dialog.close()"
        @cancel.prevent="close()"
        @click="$event.target === $refs.dialog && ! submitting && close()"
        class="rounded-2xl border border-[#E5DDD0] p-0 backdrop:bg-black/40 max-w-md w-[calc(100%-2rem)] m-auto shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)]"
    >
        <template x-if="order">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-900">{{ __('Slip Discount') }}</h3>
                        <p class="text-xs text-gray-400 truncate" x-text="order.slipLabel"></p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    {{ __('Shown on the printed order slip only. The receipt keeps its own discount, chosen at checkout.') }}
                </p>

                <div class="mt-4 space-y-2 max-h-[55vh] overflow-y-auto">
                    <template x-for="rule in rules" :key="rule.id">
                        <div class="border rounded-lg transition"
                             :class="selections[rule.id] ? 'border-[#8A3330] bg-[#FAF6EE]' : (isDisabled(rule) || belowMinBill(rule) ? 'border-[#E5DDD0] opacity-50' : 'border-[#E5DDD0]')">
                            <label class="flex items-start gap-2.5 px-3 py-2.5"
                                   :class="isDisabled(rule) || belowMinBill(rule) ? 'cursor-not-allowed' : 'cursor-pointer'">
                                <input type="checkbox"
                                       :checked="!! selections[rule.id]"
                                       :disabled="(isDisabled(rule) || belowMinBill(rule)) && ! selections[rule.id]"
                                       @change="toggleRule(rule)"
                                       class="mt-0.5 rounded text-[#8A3330] focus:ring-[#8A3330]">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900" x-text="rule.name"></span>
                                    <span class="block text-xs text-gray-400">
                                        <span x-show="! rule.isCustom && rule.mode === 'percent'" x-text="Number(rule.value).toFixed(0) + '%'"></span>
                                        <span x-show="! rule.isCustom && rule.mode === 'fixed'" x-text="money(rule.value)"></span>
                                        <span x-show="rule.isCustom">{{ __('custom value') }}</span>
                                        <span x-show="! rule.stackable"> · {{ __('exclusive') }}</span>
                                    </span>
                                    <span x-show="belowMinBill(rule)" x-cloak class="block text-[11px] text-amber-700 mt-0.5"
                                          x-text="'{{ __('Requires a minimum bill of') }} ' + money(rule.minBill)"></span>
                                </span>
                            </label>

                            <template x-if="selections[rule.id]">
                                <div class="px-3 pb-3 pl-9 space-y-2">
                                    <template x-if="rule.isCustom">
                                        <div>
                                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]"
                                                   x-text="rule.mode === 'percent' ? '{{ __('Discount Percent') }}' : '{{ __('Discount Amount (₱)') }}'"></label>
                                            <input type="number" step="0.01" min="0.01" :max="rule.mode === 'percent' ? 100 : null"
                                                   x-model="selections[rule.id].value"
                                                   class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                        </div>
                                    </template>

                                    <template x-if="rule.askName">
                                        <input type="text" x-model="selections[rule.id].qualifiedName" maxlength="100"
                                               placeholder="{{ __('Name (optional)') }}"
                                               class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    </template>

                                    <template x-if="rule.scope === 'eligible_items'">
                                        <div class="space-y-1.5">
                                            <div class="flex gap-4 text-xs text-gray-600">
                                                <label class="inline-flex items-center gap-1.5">
                                                    <input type="radio" value="items" x-model="selections[rule.id].eligMode" class="text-[#8A3330] focus:ring-[#8A3330]">
                                                    {{ __('Select eligible items') }}
                                                </label>
                                                <label class="inline-flex items-center gap-1.5">
                                                    <input type="radio" value="amount" x-model="selections[rule.id].eligMode" class="text-[#8A3330] focus:ring-[#8A3330]">
                                                    {{ __('Enter eligible amount') }}
                                                </label>
                                            </div>
                                            <template x-if="selections[rule.id].eligMode === 'items'">
                                                <div class="space-y-1 max-h-40 overflow-y-auto">
                                                    <template x-for="orderItem in order.items" :key="orderItem.id">
                                                        <label class="flex items-center justify-between gap-2 text-xs text-gray-600 py-0.5">
                                                            <span class="inline-flex items-center gap-1.5 min-w-0">
                                                                <input type="checkbox" :value="orderItem.id" x-model.number="selections[rule.id].itemIds" class="rounded text-[#8A3330] focus:ring-[#8A3330]">
                                                                <span class="truncate" x-text="orderItem.name"></span>
                                                            </span>
                                                            <span class="shrink-0" x-text="money(orderItem.amount)"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="selections[rule.id].eligMode === 'amount'">
                                                <input type="number" step="0.01" min="0" :max="order.subtotal" x-model="selections[rule.id].eligibleAmount"
                                                       placeholder="{{ __('Eligible Amount') }}"
                                                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- What the slip will print. No VAT: a discount is a plain share of the price. --}}
                <div class="mt-4 rounded-lg bg-[#FAF6EE] border border-[#E5DDD0] px-4 py-3 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('Subtotal') }}</span>
                        <span class="text-gray-900" x-text="money(order.subtotal)"></span>
                    </div>
                    <template x-for="(line, i) in preview.lines" :key="i">
                        <div class="flex justify-between text-[#8A3330]">
                            <span x-text="line.name + (line.rate !== null ? ' (' + line.rate + '%)' : '')"></span>
                            <span x-text="'−' + money(line.amount)"></span>
                        </div>
                    </template>
                    <div class="flex justify-between border-t border-[#E5DDD0] pt-1 font-bold text-gray-900">
                        <span>{{ __('Slip Total') }}</span>
                        <span x-text="money(preview.total)"></span>
                    </div>
                </div>

                <p x-show="error" x-text="error" x-cloak class="mt-4 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-700"></p>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    <button type="button" x-show="(order.current ?? []).length" @click="removeAll()" :disabled="submitting"
                            class="mr-auto min-h-11 px-2 text-sm font-semibold text-red-600 hover:text-red-700 disabled:opacity-50">
                        {{ __('Remove Discount') }}
                    </button>
                    <button type="button" @click="close()" :disabled="submitting"
                            class="min-h-11 px-4 text-sm font-semibold text-gray-600 hover:text-gray-900 disabled:opacity-50">
                        {{ __('Close') }}
                    </button>
                    <button type="button" @click="save()" :disabled="submitting"
                            class="min-h-11 rounded-xl bg-[#8A3330] px-5 text-sm font-bold text-white hover:bg-[#742927] disabled:opacity-60">
                        <span x-show="! submitting">{{ __('Save') }}</span>
                        <span x-show="submitting">{{ __('Saving…') }}</span>
                    </button>
                </div>
            </div>
        </template>
    </dialog>
</div>
