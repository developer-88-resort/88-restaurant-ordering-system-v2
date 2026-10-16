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
                                           :readonly="complimentary" :class="complimentary && 'bg-slate-50 text-slate-500'"
                                           class="mt-0.5 min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                </div>
                                <button type="button" @click="fillRemaining(index)" x-show="! complimentary"
                                        class="mt-4 text-[10px] font-bold uppercase text-[#8A3330] hover:underline shrink-0">{{ __('Fill') }}</button>
                            </div>

                            {{-- Room Charge: one room from the resort's own list (no
                                 free text, no ranges — two rooms are two rows), and
                                 optionally who signed. Nothing is collected here: the
                                 guest settles at front desk checkout. --}}
                            <template x-if="row.method === 'room_charge'">
                                <div class="space-y-2" x-data="{ roomSearch: '' }">
                                    <div class="relative">
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Charge to room') }}</label>

                                        <div x-show="row.roomId && ! row.pickerOpen" class="mt-0.5 flex items-center justify-between gap-2 rounded-xl border border-[#8A3330] bg-[#FDF7F5] px-3 py-2">
                                            <div class="min-w-0">
                                                <p class="text-base font-bold text-[#8A3330] tabular-nums" x-text="'{{ __('ROOM') }} ' + row.roomLabel"></p>
                                                <p class="text-[11px] text-gray-500" x-text="row.roomTypeName"></p>
                                            </div>
                                            <button type="button" @click="row.pickerOpen = true; $nextTick(() => $el.closest('.relative').querySelector('input[type=search]')?.focus())"
                                                    class="shrink-0 text-xs font-semibold text-[#8A3330] hover:underline">{{ __('Change') }}</button>
                                        </div>

                                        <div x-show="! row.roomId || row.pickerOpen" class="mt-0.5 space-y-2">
                                            <input type="search" x-model="roomSearch" inputmode="numeric" autocomplete="off"
                                                   @keydown.enter.prevent="pickTypedRoom(row, roomSearch)"
                                                   placeholder="{{ __('Type room no. (e.g. 511) and press Enter') }}"
                                                   class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                            <div class="max-h-80 overflow-y-auto overscroll-contain space-y-3 pr-0.5">
                                                <template x-for="group in filteredRoomGroups(roomSearch)" :key="group.code">
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500" x-text="group.code + ' · ' + group.name"></p>
                                                        <div class="grid grid-cols-4 gap-1.5 sm:grid-cols-5">
                                                            <template x-for="room in group.rooms" :key="room.id">
                                                                <button type="button" @click="pickRoom(row, room); roomSearch = ''" :title="room.typeName"
                                                                        class="relative min-h-14 rounded-xl border px-1 py-1.5 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]/40"
                                                                        :class="row.roomId === room.id ? 'border-[#8A3330] bg-[#8A3330] text-white' : 'border-slate-200 bg-white text-gray-900 hover:border-[#8A3330]/50 hover:bg-[#FDF7F5]'">
                                                                    <span class="block text-sm font-bold tabular-nums" x-text="room.no"></span>
                                                                    <span class="block text-[10px] opacity-70" x-text="group.code"></span>
                                                                    <span x-show="chargedToday[room.id]" x-cloak
                                                                          class="absolute -right-1 -top-1 rounded-full bg-amber-500 px-1.5 text-[9px] font-bold leading-4 text-white"
                                                                          :title="'{{ __('Room charges today') }}: ' + chargedToday[room.id]"
                                                                          x-text="'×' + chargedToday[room.id]"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                                <p x-show="filteredRoomGroups(roomSearch).length === 0" class="text-xs text-gray-400">{{ __('No room matches.') }}</p>
                                            </div>
                                        </div>

                                        {{-- Keeps the form from submitting with no room picked. --}}
                                        <input type="text" :value="row.roomId" required tabindex="-1" aria-hidden="true"
                                               class="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
                                               oninvalid="this.setCustomValidity('{{ __('Pick the room for the room charge.') }}')" oninput="this.setCustomValidity('')">
                                    </div>

                                    <p x-show="row.roomId && chargedToday[row.roomId]" x-cloak class="rounded-lg bg-amber-50 px-3 py-1.5 text-[11px] text-amber-800"
                                       x-text="'{{ __('Room') }} ' + row.roomLabel + ' {{ __('already has') }} ' + chargedToday[row.roomId] + ' {{ __('room charge(s) today — double-check the room.') }}'"></p>

                                    <div>
                                        <input type="text" x-model="row.guestName" @input="row.guestSuggested = false" maxlength="100"
                                               placeholder="{{ __('Guest name / Room/Locker/Kubo Charge (optional)') }}"
                                               class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                        <p x-show="row.guestSuggested" x-cloak class="mt-0.5 text-[10px] text-gray-400">{{ __('Last name used on this room in the past 24 hours — change it if someone else is signing.') }}</p>
                                    </div>
                                    <input type="text" x-model="row.guestRef" maxlength="50" placeholder="{{ __('OS (optional)') }}"
                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <p class="text-[10px] text-gray-400">{{ __('To be settled at front desk — nothing is collected here.') }}</p>
                                </div>
                            </template>

                            {{-- Locker Charge / Kubo Charge: same flow as a room —
                                 one numbered locker (1–50) or kubo (1–20) per row,
                                 optionally who signed and the OS no. Settled at the
                                 front desk; nothing is collected here. --}}
                            <template x-if="isSpotMethod(row.method)">
                                <div class="space-y-2" x-data="{ spotSearch: '' }">
                                    <div class="relative">
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500"
                                               x-text="'{{ __('Charge to') }} ' + spotInfo(row.method).name.toLowerCase()"></label>

                                        <div x-show="pickedSpot(row) && ! row.pickerOpen" class="mt-0.5 flex items-center justify-between gap-2 rounded-xl border border-[#8A3330] bg-[#FDF7F5] px-3 py-2">
                                            <p class="text-base font-bold uppercase text-[#8A3330] tabular-nums" x-text="spotLabel(row)"></p>
                                            <button type="button" @click="row.pickerOpen = true; $nextTick(() => $el.closest('.relative').querySelector('input[type=search]')?.focus())"
                                                    class="shrink-0 text-xs font-semibold text-[#8A3330] hover:underline">{{ __('Change') }}</button>
                                        </div>

                                        <div x-show="! pickedSpot(row) || row.pickerOpen" class="mt-0.5 space-y-2">
                                            <input type="search" x-model="spotSearch" inputmode="numeric" autocomplete="off"
                                                   @keydown.enter.prevent="pickTypedSpot(row, spotSearch); spotSearch = ''"
                                                   :placeholder="'{{ __('Type the') }} ' + spotInfo(row.method).name.toLowerCase() + ' {{ __('no. and press Enter') }} (1–' + spotInfo(row.method).count + ')'"
                                                   class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                            <div class="max-h-80 overflow-y-auto overscroll-contain space-y-3 pr-0.5">
                                                <template x-for="group in filteredSpotGroups(row.method, spotSearch)" :key="row.method + group.label">
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500" x-text="spotInfo(row.method).name + ' ' + group.label"></p>
                                                        <div class="grid grid-cols-5 gap-1.5">
                                                            <template x-for="no in group.nos" :key="no">
                                                                <button type="button" @click="pickSpot(row, no); spotSearch = ''"
                                                                        class="relative min-h-12 rounded-xl border px-1 py-1.5 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]/40"
                                                                        :class="pickedSpot(row) === no ? 'border-[#8A3330] bg-[#8A3330] text-white' : 'border-slate-200 bg-white text-gray-900 hover:border-[#8A3330]/50 hover:bg-[#FDF7F5]'">
                                                                    <span class="block text-sm font-bold tabular-nums" x-text="no"></span>
                                                                    <span x-show="spotChargedToday(row, no)" x-cloak
                                                                          class="absolute -right-1 -top-1 rounded-full bg-amber-500 px-1.5 text-[9px] font-bold leading-4 text-white"
                                                                          :title="'{{ __('Charges today') }}: ' + spotChargedToday(row, no)"
                                                                          x-text="'×' + spotChargedToday(row, no)"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                                <p x-show="filteredSpotGroups(row.method, spotSearch).length === 0" class="text-xs text-gray-400">{{ __('No number matches.') }}</p>
                                            </div>
                                        </div>

                                        {{-- Keeps the form from submitting with nothing picked. --}}
                                        <input type="text" :value="pickedSpot(row)" required tabindex="-1" aria-hidden="true"
                                               class="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
                                               oninvalid="this.setCustomValidity('{{ __('Pick the locker or kubo number.') }}')" oninput="this.setCustomValidity('')">
                                    </div>

                                    <p x-show="pickedSpot(row) && spotChargedToday(row, pickedSpot(row))" x-cloak class="rounded-lg bg-amber-50 px-3 py-1.5 text-[11px] text-amber-800"
                                       x-text="spotLabel(row) + ' {{ __('already has') }} ' + spotChargedToday(row, pickedSpot(row)) + ' {{ __('charge(s) today — double-check the number.') }}'"></p>

                                    <div>
                                        <input type="text" x-model="row.guestName" @input="row.guestSuggested = false" maxlength="100"
                                               placeholder="{{ __('Guest name/ Room/Locker/Kubo Charge (optional)') }}"
                                               class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                        <p x-show="row.guestSuggested" x-cloak class="mt-0.5 text-[10px] text-gray-400">{{ __('Last name used on this number in the past 24 hours — change it if someone else is signing.') }}</p>
                                    </div>
                                    <input type="text" x-model="row.guestRef" maxlength="50" placeholder="{{ __('OS (optional)') }}"
                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <p class="text-[10px] text-gray-400">{{ __('To be settled at front desk — nothing is collected here.') }}</p>
                                </div>
                            </template>

                            {{-- Others: a breakfast order's complimentary — nothing is
                                 collected; staff may note who / why. --}}
                            <template x-if="row.method === 'other'">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</span>
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">{{ __('Complimentary') }}</span>
                                    </div>
                                    <input type="text" x-model="row.notes" maxlength="500" placeholder="{{ __('Infos to input (optional)') }}"
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
                                    {{-- Foreign card (Korean, …): a surcharge on top of what this card
                                         covers. The bill doesn't change — the terminal is charged more. --}}
                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-gray-800">
                                        <input type="checkbox" x-model="row.foreignCard" class="rounded border-slate-300 text-[#8A3330] focus:ring-[#8A3330]/30">
                                        <span x-text="'{{ __('Foreign card') }} (+' + foreignCardSurchargePercent + '% {{ __('surcharge') }})'"></span>
                                    </label>
                                    <div x-show="row.foreignCard" x-cloak class="space-y-0.5 rounded-lg bg-amber-50 px-3 py-2 text-xs tabular-nums text-amber-900">
                                        <div class="flex justify-between gap-3"><span>{{ __('Bill amount') }}</span><span x-text="'₱' + (Number(row.amount) || 0).toFixed(2)"></span></div>
                                        <div class="flex justify-between gap-3"><span x-text="'{{ __('Surcharge') }} ' + foreignCardSurchargePercent + '%'"></span><span x-text="'₱' + surchargeFor(row).toFixed(2)"></span></div>
                                        <div class="flex justify-between gap-3 border-t border-amber-200 pt-1 font-bold"><span>{{ __('Charge on terminal') }}</span><span x-text="'₱' + terminalAmount(row).toFixed(2)"></span></div>
                                    </div>
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

                {{-- A breakfast order: Others only, so nothing to add. --}}
                <p x-show="complimentary" x-cloak class="mt-2 text-[11px] text-slate-500">{{ __('Breakfast order — complimentary. Only Others can be used, for the whole bill (regular menu added to it included).') }}</p>

                <button type="button" @click="addPaymentRow()" x-show="! complimentary" class="mt-2 text-sm font-medium text-[#8A3330] hover:underline">
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
                    <input type="hidden" :name="'payments[' + pIndex + '][foreign_card]'" :value="paidAs(row) === 'card' && row.foreignCard ? 1 : 0">
                    {{-- Room Charge: the server writes charged_to from the room + guest. --}}
                    <input type="hidden" :name="'payments[' + pIndex + '][room_id]'" :value="row.method === 'room_charge' ? row.roomId : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][spot_no]'" :value="isSpotMethod(row.method) ? pickedSpot(row) : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][guest_name]'" :value="row.method === 'room_charge' || isSpotMethod(row.method) ? row.guestName : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][guest_ref]'" :value="row.method === 'room_charge' || isSpotMethod(row.method) ? row.guestRef : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][card_last_four]'" :value="row.cardLastFour">
                    <input type="hidden" :name="'payments[' + pIndex + '][terminal_reference]'" :value="row.terminalReference">
                    <input type="hidden" :name="'payments[' + pIndex + '][approval_code]'" :value="row.approvalCode">
                    <input type="hidden" :name="'payments[' + pIndex + '][terminal_id]'" :value="row.terminalId">
                    <input type="hidden" :name="'payments[' + pIndex + '][reference]'" :value="row.reference">
                    <input type="hidden" :name="'payments[' + pIndex + '][notes]'" :value="row.notes">
                </span>
            </template>
