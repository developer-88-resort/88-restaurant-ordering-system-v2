{{--
    The split-payment rows of a checkout: method, amount, cash tendered and
    change, card type + Reference No. + Approval Code, a Room Charge's mode
    and room, and the hidden payments[] inputs. Shared by the restaurant
    checkout (checkout-form) and Massage (massage/orders/show), so both take
    payment exactly the same way. Must sit inside an orderPayment() form.
--}}
            {{-- ============ PAYMENTS (SPLIT) ============ --}}
            <div class="pt-3 border-t border-slate-100">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-2">{{ __('Payments') }}</label>
                <div class="space-y-3">
                    <template x-for="(row, index) in payments" :key="index">
                        <div class="border border-slate-200 rounded-lg p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <select x-model="row.method"
                                        class="flex-1 text-sm rounded-lg border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <template x-for="option in methods" :key="option.value">
                                        <option :value="option.value" x-text="option.label"></option>
                                    </template>
                                </select>
                                <button type="button" @click="removePaymentRow(index)" x-show="payments.length > 1"
                                        class="text-xs text-red-600 hover:underline shrink-0">{{ __('Remove') }}</button>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount Applied') }}</label>
                                    <input type="number" step="0.01" min="0" x-model="row.amount" @input="onAmountInput(index)" required
                                           class="mt-0.5 min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                </div>
                                <button type="button" @click="fillRemaining(index)"
                                        class="mt-4 text-[10px] font-bold uppercase text-[#8A3330] hover:underline shrink-0">{{ __('Fill') }}</button>
                            </div>

                            {{-- Room Charge: how it will be paid and which room it
                                 went on. The mode's own fields (cash tendered, card
                                 details, reference no.) follow below, the same as
                                 when that mode is picked directly. --}}
                            <template x-if="row.method === 'room_charge'">
                                <div class="space-y-2">
                                    <div class="relative" x-data="{ viaOpen: false }" @click.outside="viaOpen = false" @keydown.escape.stop="viaOpen = false">
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Paid through') }}</label>
                                        <button type="button" @click="viaOpen = !viaOpen" :aria-expanded="viaOpen" aria-haspopup="listbox"
                                                class="mt-0.5 flex w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-left text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]/30"
                                                :class="viaOpen ? 'border-[#8A3330]' : 'border-slate-200'">
                                            <span :class="row.settledVia ? 'font-medium text-gray-900' : 'text-gray-400'"
                                                  x-text="row.settledVia ? settlementLabel(row.settledVia) : '{{ __('Mode of payment') }}'"></span>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="viaOpen && 'rotate-180'" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                        {{-- Keeps the form from submitting with no mode picked. --}}
                                        <input type="text" :value="row.settledVia" required tabindex="-1" aria-hidden="true"
                                               class="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
                                               oninvalid="this.setCustomValidity('{{ __('Pick how the room charge will be paid.') }}')" oninput="this.setCustomValidity('')">
                                        <div x-show="viaOpen" x-cloak x-transition.opacity.duration.100ms role="listbox" aria-label="{{ __('Paid through') }}"
                                             class="absolute left-0 right-0 top-full z-30 mt-1 max-h-60 overflow-y-auto overscroll-contain rounded-xl border border-[#E6DCCF] bg-white p-1 shadow-[0_24px_50px_-20px_rgba(55,35,30,0.45)]">
                                            <template x-for="option in settlementMethods" :key="option.value">
                                                <button type="button" role="option" :aria-selected="row.settledVia === option.value"
                                                        @click="row.settledVia = option.value; viaOpen = false; $el.closest('.relative').querySelector('input').setCustomValidity('')"
                                                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
                                                        :class="row.settledVia === option.value ? 'bg-slate-50 font-bold text-[#8A3330]' : 'text-gray-800 hover:bg-[#F5EFE7]'">
                                                    <span x-text="option.label"></span>
                                                    <svg x-show="row.settledVia === option.value" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <input type="text" x-model="row.chargedTo" required placeholder="{{ __('Room No. / Guest name') }}"
                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                </div>
                            </template>

                            <template x-if="paidAs(row) === 'cash'">
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Cash Tendered') }}</label>
                                    <input type="number" step="0.01" min="0" x-model="row.tendered" @input="onTenderedInput(index)" :placeholder="row.amount"
                                           class="mt-0.5 min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <p class="text-[11px] text-gray-400 mt-0.5"
                                       x-text="'{{ __('Change') }}: ₱' + Math.max(0, (Number(row.tendered) || Number(row.amount) || 0) - (Number(row.amount) || 0)).toFixed(2)"></p>
                                </div>
                            </template>

                            {{-- Card type, Reference No. and Approval Code, all on
                                 every card machine receipt. The same Reference No.
                                 can be keyed on several slips — one guest's card
                                 often pays them all. --}}
                            <template x-if="paidAs(row) === 'card'">
                                <div class="space-y-2">
                                    {{-- The app's own dropdown, not a <select>: on a
                                         phone or tablet a <select> opens the system's
                                         full-screen picker. --}}
                                    <div class="relative" x-data="{ brandOpen: false }" @click.outside="brandOpen = false" @keydown.escape.stop="brandOpen = false">
                                        <button type="button" @click="brandOpen = !brandOpen" :aria-expanded="brandOpen" aria-haspopup="listbox"
                                                class="flex w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-left text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]/30"
                                                :class="brandOpen ? 'border-[#8A3330]' : 'border-slate-200'">
                                            <span :class="row.cardBrand ? 'font-medium text-gray-900' : 'text-gray-400'"
                                                  x-text="row.cardBrand ? cardBrandLabel(row.cardBrand) : '{{ __('Card type') }}'"></span>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="brandOpen && 'rotate-180'" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                        {{-- Keeps the form from submitting with no card type. --}}
                                        <input type="text" :value="row.cardBrand" required tabindex="-1" aria-hidden="true"
                                               class="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
                                               oninvalid="this.setCustomValidity('{{ __('Pick the card type.') }}')" oninput="this.setCustomValidity('')">
                                        <div x-show="brandOpen" x-cloak x-transition.opacity.duration.100ms role="listbox" aria-label="{{ __('Card type') }}"
                                             class="absolute left-0 right-0 top-full z-30 mt-1 max-h-60 overflow-y-auto overscroll-contain rounded-xl border border-[#E6DCCF] bg-white p-1 shadow-[0_24px_50px_-20px_rgba(55,35,30,0.45)]">
                                            <template x-for="brand in cardBrands" :key="brand.value">
                                                <button type="button" role="option" :aria-selected="row.cardBrand === brand.value"
                                                        @click="row.cardBrand = brand.value; brandOpen = false; $el.closest('.relative').querySelector('input').setCustomValidity('')"
                                                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
                                                        :class="row.cardBrand === brand.value ? 'bg-slate-50 font-bold text-[#8A3330]' : 'text-gray-800 hover:bg-[#F5EFE7]'">
                                                    <span x-text="brand.label"></span>
                                                    <svg x-show="row.cardBrand === brand.value" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <input type="text" x-model="row.reference" required placeholder="{{ __('Reference No.') }}"
                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <input type="text" x-model="row.approvalCode" required placeholder="{{ __('Approval Code') }}"
                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <p class="text-[10px] text-gray-400">{{ __('From the card machine receipt. Never enter the card number or CVV.') }}</p>
                                </div>
                            </template>

                            <template x-if="paidAs(row) !== 'cash' && paidAs(row) !== 'card' && paidAs(row) !== 'room_charge' && methodInfo(paidAs(row)).requiresReference">
                                <input type="text" x-model="row.reference" required placeholder="{{ __('Reference Number') }}"
                                       class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                            </template>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addPaymentRow()" class="mt-2 text-sm font-medium text-[#8A3330] hover:underline">
                    + {{ __('Add another payment method') }}
                </button>

                <div class="mt-3 rounded-xl bg-slate-50 border border-slate-100 px-4 py-4 text-sm space-y-1">
                    <div class="flex justify-between gap-3 tabular-nums">
                        <span class="text-gray-500">{{ __('Total Paid') }}</span>
                        <span class="text-gray-900" x-text="'₱' + totalPaid.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums font-semibold" :class="remainingBalance < 0.005 ? 'text-green-700' : 'text-red-600'">
                        <span>{{ __('Remaining Balance') }}</span>
                        <span x-text="'₱' + remainingBalance.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums" x-show="totalChange > 0">
                        <span class="text-gray-500">{{ __('Cash Change') }}</span>
                        <span class="text-gray-900" x-text="'₱' + totalChange.toFixed(2)"></span>
                    </div>
                </div>
            </div>


            <template x-for="(row, pIndex) in payments" :key="'p' + pIndex">
                <span>
                    <input type="hidden" :name="'payments[' + pIndex + '][method]'" :value="row.method">
                    <input type="hidden" :name="'payments[' + pIndex + '][amount]'" :value="row.amount">
                    <input type="hidden" :name="'payments[' + pIndex + '][tendered_amount]'" :value="paidAs(row) === 'cash' ? row.tendered : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][card_brand]'" :value="paidAs(row) === 'card' ? row.cardBrand : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][charged_to]'" :value="row.method === 'room_charge' ? row.chargedTo : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][settled_via]'" :value="row.method === 'room_charge' ? row.settledVia : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][card_last_four]'" :value="row.cardLastFour">
                    <input type="hidden" :name="'payments[' + pIndex + '][terminal_reference]'" :value="row.terminalReference">
                    <input type="hidden" :name="'payments[' + pIndex + '][approval_code]'" :value="row.approvalCode">
                    <input type="hidden" :name="'payments[' + pIndex + '][terminal_id]'" :value="row.terminalId">
                    <input type="hidden" :name="'payments[' + pIndex + '][reference]'" :value="row.reference">
                    <input type="hidden" :name="'payments[' + pIndex + '][notes]'" :value="row.notes">
                </span>
            </template>
