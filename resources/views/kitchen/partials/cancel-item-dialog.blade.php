@php
    $cancelDialogConfig = [
        'reasons' => collect(\App\Enums\OrderItemAdjustmentReason::kitchenPresets())
            ->map(fn ($reason) => ['value' => $reason->value, 'label' => $reason->label()])
            ->values(),
        'otherValue' => \App\Enums\OrderItemAdjustmentReason::Other->value,
        'approvers' => $approvers ?? [],
        'text' => [
            'nothingToCancel' => __('Keep fewer than are on the slip — otherwise there is nothing to cancel.'),
            'pickReason' => __('Choose a reason first.'),
            'describeOther' => __('Please describe the reason when choosing "Other".'),
            'approvalMissing' => __('Enter a manager\'s email and password to approve this.'),
            'approvalMissingPin' => __('Choose a manager and enter their PIN to approve this.'),
            'failed' => __('Could not cancel the item. Check your connection and try again.'),
        ],
    ];
@endphp

{{--
    One dialog for the whole board. Each line's Cancel / Adjust button
    dispatches `kitchen-cancel-item` with that line's details; see
    resources/js/lib/kitchen-cancel-dialog.js.
--}}
<div
    x-data="kitchenCancelDialog(@js($cancelDialogConfig))"
    @kitchen-cancel-item.window="open($event.detail)"
>
    <dialog
        x-ref="dialog"
        x-effect="isOpen ? $refs.dialog.showModal() : $refs.dialog.close()"
        @cancel.prevent="close()"
        @click="$event.target === $refs.dialog && ! submitting && close()"
        class="rounded-2xl border border-[#E5DDD0] p-0 backdrop:bg-black/40 max-w-md w-[calc(100%-2rem)] m-auto shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)]"
    >
        <template x-if="item">
            <div class="p-6">
                {{-- Header --}}
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-red-50 text-red-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-900" x-text="item.isWeighed ? '{{ __('Void line') }}' : (item.mode === 'adjust' ? '{{ __('Adjust Quantity') }}' : '{{ __('Cancel Item') }}')"></h3>
                        <p class="text-sm text-gray-700 truncate" x-text="item.name"></p>
                        <p class="text-xs text-gray-400 truncate" x-text="item.slipLabel"></p>
                    </div>
                </div>

                {{-- Step 1: what and why --}}
                <div x-show="step === 'form'" class="mt-5 space-y-4">
                    <template x-if="canAdjust">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{{ __('Keep on the slip') }}</p>
                            <div class="mt-2 flex items-center gap-3">
                                <button type="button" @click="decrementKeep()" :disabled="keepQty === 0"
                                        class="h-12 w-12 rounded-xl border border-[#D9CCBA] bg-white text-2xl font-semibold text-gray-700 active:bg-[#F3E1DC] disabled:opacity-40"
                                        aria-label="{{ __('Keep one fewer') }}">&minus;</button>
                                <div class="min-w-[4.5rem] text-center">
                                    <span class="text-3xl font-bold text-gray-900" x-text="keepQty"></span>
                                    <span class="block text-xs text-gray-400">/ <span x-text="item.activeQty"></span></span>
                                </div>
                                <button type="button" @click="incrementKeep()" :disabled="keepQty >= item.activeQty - 1"
                                        class="h-12 w-12 rounded-xl border border-[#D9CCBA] bg-white text-2xl font-semibold text-gray-700 active:bg-[#F3E1DC] disabled:opacity-40"
                                        aria-label="{{ __('Keep one more') }}">+</button>
                                <p class="text-sm font-semibold text-red-600">
                                    &minus;<span x-text="cancelQty"></span> {{ __('cancelled') }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{{ __('Reason') }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <template x-for="option in reasons" :key="option.value">
                                <button type="button" @click="reason = option.value"
                                        :aria-pressed="reason === option.value"
                                        :class="reason === option.value
                                            ? 'border-[#8A3330] bg-[#8A3330] text-white'
                                            : 'border-[#D9CCBA] bg-[#FCF8F1] text-gray-700 hover:border-[#8A3330]'"
                                        class="min-h-12 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                        x-text="option.label"></button>
                            </template>
                        </div>
                    </div>

                    <div x-show="reason === otherValue" x-cloak>
                        <label class="text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]" for="kitchen-cancel-notes">{{ __('Describe the reason') }}</label>
                        <textarea id="kitchen-cancel-notes" x-model="notes" rows="2" maxlength="1000"
                                  placeholder="{{ __('e.g. Customer changed their mind') }}"
                                  class="mt-1 w-full text-sm rounded-xl border-[#D9CCBA] focus:border-[#8A3330] focus:ring-[#8A3330]"></textarea>
                    </div>

                    <template x-if="item.needsApproval">
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 space-y-2">
                            <p class="text-xs font-semibold text-amber-800">{{ __('Manager approval required — this order is already ready, served, or paid.') }}</p>
                            <template x-if="approvalMode === 'pin'">
                                <div class="space-y-2">
                                    <select x-model="managerId" aria-label="{{ __('Approving manager') }}"
                                            class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                        <option value="">{{ __('Choose manager') }}</option>
                                        <template x-for="approver in approvers" :key="approver.id">
                                            <option :value="String(approver.id)" :selected="String(approver.id) === String(managerId)" x-text="approver.name"></option>
                                        </template>
                                    </select>
                                    <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" x-model="managerPin" autocomplete="off" placeholder="{{ __('Manager PIN') }}"
                                           class="w-full text-sm tracking-[0.3em] placeholder:tracking-normal rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <button type="button" @click="approvalMode = 'email'" class="text-xs font-semibold text-amber-800 underline underline-offset-2">{{ __('Use email and password instead') }}</button>
                                </div>
                            </template>
                            <template x-if="approvalMode === 'email'">
                                <div class="space-y-2">
                                    <input type="email" x-model="managerEmail" autocomplete="off" placeholder="{{ __('Manager Email') }}"
                                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <input type="password" x-model="managerPassword" autocomplete="off" placeholder="{{ __('Manager Password') }}"
                                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <button type="button" x-show="approvers.length" @click="approvalMode = 'pin'" class="text-xs font-semibold text-amber-800 underline underline-offset-2">{{ __('Use a manager\'s PIN instead') }}</button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Step 2: confirm --}}
                <div x-show="step === 'confirm'" x-cloak class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-sm font-bold text-red-800">
                        {{ __('Cancel') }} <span x-text="cancelQty"></span>&times; <span x-text="item.name"></span>?
                    </p>
                    <p class="mt-1 text-sm text-red-700">{{ __('Reason') }}: <span x-text="reasonLabel"></span><span x-show="notes.trim()"> — <span x-text="notes.trim()"></span></span></p>
                    <p class="mt-1 text-xs text-red-600" x-show="keepQty > 0">
                        <span x-text="keepQty"></span> {{ __('will stay on the slip.') }}
                    </p>
                    <p class="mt-1 text-xs text-red-600" x-show="keepQty === 0">
                        {{ __('The line stays on the slip, struck through and marked CANCELLED.') }}
                    </p>
                </div>

                <p x-show="error" x-text="error" x-cloak class="mt-4 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-700"></p>

                {{-- Actions --}}
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" x-show="step === 'form'" @click="close()"
                            class="min-h-11 px-4 text-sm font-semibold text-gray-600 hover:text-gray-900">
                        {{ __('Keep Item') }}
                    </button>
                    <button type="button" x-show="step === 'form'" @click="review()"
                            class="min-h-11 rounded-xl bg-[#8A3330] px-5 text-sm font-bold text-white hover:bg-[#742927]">
                        {{ __('Continue') }}
                    </button>

                    <button type="button" x-show="step === 'confirm'" x-cloak @click="step = 'form'" :disabled="submitting"
                            class="min-h-11 px-4 text-sm font-semibold text-gray-600 hover:text-gray-900 disabled:opacity-50">
                        {{ __('Back') }}
                    </button>
                    <button type="button" x-show="step === 'confirm'" x-cloak @click="submit()" :disabled="submitting"
                            class="min-h-11 rounded-xl bg-red-600 px-5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">
                        <span x-show="! submitting">{{ __('Yes, Cancel It') }}</span>
                        <span x-show="submitting">{{ __('Cancelling…') }}</span>
                    </button>
                </div>
            </div>
        </template>
    </dialog>
</div>
