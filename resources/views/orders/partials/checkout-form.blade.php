{{--
    The checkout / finalize-payment form: configurable discount checklist
    (discount_rules table) + split payments (multiple payment entries, cash
    change computed off the cash rows only, masked card-terminal details).
    Everything shown here is an advisory estimate — the server (PaymentFinalizer
    + InvoiceCalculator) recomputes all money authoritatively on submit.

    Expects: $order (items.adjustments/addons loaded), $setting, $discountRules.

    With $lateDiscount = true it corrects a bill that is already paid instead
    (LateDiscountApplier): the bill's current discounts come pre-ticked, the
    payments section is left out (the recorded payments are carried over),
    and it posts to orders.late-discount.
--}}
@php
    $lateDiscount = $lateDiscount ?? false;
    $mayaCheckoutEnabled = ! $lateDiscount && \App\Services\Payments\MayaCheckoutClient::enabled();
    $paidSnapshot = $lateDiscount ? $order->currentInvoiceSnapshot : null;

    // The discounts already on the bill, as the checklist's own selections,
    // so correcting a bill adds to what it had instead of replacing it. A
    // rule that is no longer offered can't be re-applied and is left off.
    $initialSelections = $paidSnapshot
        ? $paidSnapshot->discounts
            ->filter(fn ($line) => $discountRules->contains('id', $line->discount_rule_id))
            ->mapWithKeys(function ($line) use ($discountRules) {
                $rule = $discountRules->firstWhere('id', $line->discount_rule_id);

                return [$line->discount_rule_id => [
                    'enteredValue' => $rule->is_custom_value ? (float) $line->entered_value : ($rule->value !== null ? (float) $rule->value : null),
                    'qualifiedName' => $line->qualified_name ?? '',
                    'idNumber' => $line->id_number ?? '',
                    'reason' => $line->reason ?? '',
                    'eligMode' => 'amount',
                    'itemIds' => [],
                    'eligibleAmount' => $line->eligible_amount !== null ? (float) $line->eligible_amount : '',
                ]];
            })
        : collect();

    // Everything the orderPayment() Alpine component (resources/js/lib/
    // order-payment.js) needs, as a single JSON-safe payload passed via
    // @js() below — never build this component's state as an inline
    // x-data string again; that's what let a stray " inside a // comment
    // truncate the whole attribute last time.
    $paymentConfig = [
        'orderTotal' => (float) $order->total_amount,
        'isVat' => $setting->tax_registration_type->value === 'vat',
        'taxRate' => (float) $setting->tax_rate,
        'serviceChargeEnabled' => (bool) $setting->service_charge_enabled,
        'serviceChargePercent' => (float) ($setting->service_charge_percent ?? 0),
        'isStaff' => auth()->user()->role === \App\Enums\UserRole::Staff,
        'rules' => $discountRules->map(fn ($rule) => [
            'id' => $rule->id,
            'name' => $rule->name,
            'code' => $rule->code,
            'mode' => $rule->calculation_mode->value,
            'value' => $rule->value !== null ? (float) $rule->value : null,
            'isCustom' => (bool) $rule->is_custom_value,
            'statutory' => $rule->statutory_type?->value,
            'scope' => $rule->scope,
            'stackable' => (bool) $rule->is_stackable,
            'requiresId' => (bool) $rule->requires_customer_id,
            'requiresReason' => (bool) $rule->requires_reason,
            'requiresApproval' => (bool) $rule->requires_manager_approval,
            'maxDiscount' => $rule->max_discount_amount !== null ? (float) $rule->max_discount_amount : null,
            'minBill' => $rule->min_bill_amount !== null ? (float) $rule->min_bill_amount : null,
            'priority' => (int) $rule->priority,
        ])->values(),
        'orderItems' => $order->items
            ->filter(fn ($item) => ! $item->isFullyCancelled())
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->item_name, 'amount' => (float) $item->lineTotalNet()])
            ->values(),
        'methods' => collect(\App\Enums\PaymentMethod::cases())->map(fn ($method) => [
            'value' => $method->value,
            'label' => $method->label(),
            'requiresReference' => $method->requiresReference(),
        ])
            // Not a stored method: picking it sends the bill to Maya's hosted
            // checkout, and the payment is recorded as Maya once it's paid.
            ->when($mayaCheckoutEnabled, fn ($methods) => $methods->push([
                'value' => 'maya_checkout',
                'label' => __('Maya Checkout (Online)'),
                'requiresReference' => false,
            ]))
            ->values(),
        'mayaCheckoutUrl' => $mayaCheckoutEnabled ? route('orders.maya-checkout.store', $order) : null,
        'settlementMethods' => collect(\App\Enums\PaymentMethod::settlementOptions())->map(fn ($method) => [
            'value' => $method->value,
            'label' => $method->label(),
        ])->values(),
        'cardBrands' => collect(\App\Enums\CardBrand::cases())->map(fn ($brand) => [
            'value' => $brand->value,
            'label' => $brand->label(),
        ])->values(),
        'lateDiscount' => $lateDiscount,
        'paidTotal' => $paidSnapshot ? (float) $paidSnapshot->total_amount_due : null,
        'initialSelections' => (object) $initialSelections->all(),
    ];
@endphp

<div class="mt-3">
    @if ($errors->any())
        <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ $lateDiscount ? route('orders.late-discount', $order) : route('orders.mark-as-paid', $order) }}"
        x-data="orderPayment(@js($paymentConfig))"
        @submit.prevent="open = true"
    >
        @csrf
        @unless ($lateDiscount)
            @method('PATCH')
        @endunless

        <div class="space-y-4">
            {{-- ============ DISCOUNTS ============ --}}
            <div class="pt-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-2">{{ __('Apply Discount') }}</label>
                <div class="space-y-2">
                    <template x-for="rule in rules" :key="rule.id">
                        <div class="border rounded-lg transition"
                             :class="selections[rule.id] ? 'border-[#8A3330] bg-slate-50' : (isDisabled(rule) || belowMinBill(rule) ? 'border-slate-200 opacity-50' : 'border-slate-200')">
                            <label class="flex items-start gap-2.5 px-3 py-2.5"
                                   :class="isDisabled(rule) || belowMinBill(rule) ? 'cursor-not-allowed' : 'cursor-pointer'">
                                <input type="checkbox"
                                       :checked="!!selections[rule.id]"
                                       :disabled="(isDisabled(rule) || belowMinBill(rule)) && !selections[rule.id]"
                                       @change="toggleRule(rule)"
                                       class="mt-0.5 rounded text-[#8A3330] focus:ring-[#8A3330]">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900" x-text="rule.name"></span>
                                    <span class="block text-xs text-gray-400">
                                        <span x-show="!rule.isCustom && rule.mode === 'percent'" x-text="Number(rule.value).toFixed(0) + '%'"></span>
                                        <span x-show="rule.isCustom">{{ __('custom value') }}</span>
                                        <span x-show="!rule.stackable"> · {{ __('exclusive') }}</span>
                                        <span x-show="rule.requiresApproval"> · {{ __('manager approval') }}</span>
                                    </span>
                                    <span x-show="isDisabled(rule) && !belowMinBill(rule)" x-cloak class="block text-[11px] text-amber-700 mt-0.5">
                                        {{ __('Cannot combine with an exclusive discount — a manager override is required.') }}
                                    </span>
                                    <span x-show="belowMinBill(rule)" x-cloak class="block text-[11px] text-amber-700 mt-0.5"
                                          x-text="'{{ __('Requires a minimum bill of') }} ₱' + Number(rule.minBill).toFixed(2)"></span>
                                </span>
                            </label>

                            <template x-if="selections[rule.id]">
                                <div class="px-3 pb-3 pl-9 space-y-2">
                                    <template x-if="rule.isCustom">
                                        <div>
                                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                                   x-text="rule.mode === 'percent' ? '{{ __('Discount Percent') }}' : '{{ __('Discount Amount (₱)') }}'"></label>
                                            <input type="number" step="0.01" min="0.01" :max="rule.mode === 'percent' ? 100 : null"
                                                   x-model="selections[rule.id].enteredValue" required
                                                   class="mt-1 min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                        </div>
                                    </template>

                                    <template x-if="rule.requiresId">
                                        <div class="grid grid-cols-1 gap-2">
                                            <input type="text" x-model="selections[rule.id].qualifiedName" required placeholder="{{ __('Qualified Customer Name') }}"
                                                   class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                            <input type="text" x-model="selections[rule.id].idNumber" required placeholder="{{ __('ID Number (SC/PWD ID)') }}"
                                                   class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                        </div>
                                    </template>

                                    {{-- Offered on every hand-keyed discount, demanded only where
                                         the rule says so. Custom Amount asks for no reason, but a
                                         cashier writing down why ₱500 came off the bill is worth
                                         keeping — it lands on the invoice line either way. --}}
                                    <template x-if="rule.requiresReason || rule.isCustom">
                                        <input type="text" x-model="selections[rule.id].reason" :required="rule.requiresReason"
                                               :placeholder="rule.requiresReason ? '{{ __('Reason for this discount') }}' : '{{ __('Reason (optional)') }}'"
                                               class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
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
                                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                                    <template x-for="orderItem in orderItems" :key="orderItem.id">
                                                        <label class="flex items-center justify-between gap-2 text-xs text-gray-600 py-0.5">
                                                            <span class="inline-flex items-center gap-1.5 min-w-0">
                                                                <input type="checkbox" :value="orderItem.id" x-model.number="selections[rule.id].itemIds" class="rounded text-[#8A3330] focus:ring-[#8A3330]">
                                                                <span class="truncate" x-text="orderItem.name"></span>
                                                            </span>
                                                            <span class="shrink-0" x-text="'₱' + orderItem.amount.toFixed(2)"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="selections[rule.id].eligMode === 'amount'">
                                                <input type="number" step="0.01" min="0" :max="orderTotal" x-model="selections[rule.id].eligibleAmount"
                                                       placeholder="{{ __('Eligible Amount') }}"
                                                       class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer" x-show="selectedRules.length > 0" x-cloak>
                    <input type="checkbox" x-model="overrideMode" class="rounded text-[#8A3330] focus:ring-[#8A3330]">
                    {{ __('Manager override — allow combining with an exclusive discount') }}
                </label>
            </div>

            {{-- Manager re-authentication (staff only; admins approve their own action) --}}
            <div x-show="needsApproval && isStaff" x-cloak class="rounded-lg border border-[#F3E1DC] bg-[#FDF7F5] p-3 space-y-2">
                <p class="text-xs font-semibold text-[#8A3330]">
                    @if ($lateDiscount)
                        {{ __('Changing a paid bill needs a manager\'s approval.') }}
                    @else
                        {{ __('Manager approval required for the selected discounts.') }}
                    @endif
                </p>
                <input type="email" name="manager_email" x-model="managerEmail" placeholder="{{ __('Manager Email') }}"
                       class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                <input type="password" name="manager_password" x-model="managerPassword" placeholder="{{ __('Manager Password') }}"
                       class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
            </div>

            {{-- ============ CALCULATION PREVIEW ============ --}}
            <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-4 text-sm space-y-1">
                <div class="flex justify-between gap-3 tabular-nums">
                    <span class="text-gray-500">{{ __('Order Subtotal (net of cancellations)') }}</span>
                    <span class="text-gray-900" x-text="'₱' + orderTotal.toFixed(2)"></span>
                </div>
                <template x-for="(line, i) in discountPreview.lines" :key="i">
                    <div>
                        <div class="flex justify-between gap-3 tabular-nums" :class="line.kind === 'vatExemption' ? 'text-gray-500' : 'text-[#8A3330]'">
                            <span x-text="line.kind === 'vatExemption'
                                ? ('{{ __('Less: VAT Exemption') }} (' + taxRate.toFixed(0) + '%)')
                                : (line.ruleName + (line.basisNet !== null ? ' (' + line.pct.toFixed(0) + '% {{ __('of VAT-exempt') }} ₱' + line.basisNet.toFixed(2) + ')' : ''))"></span>
                            <span x-text="'−₱' + line.amount.toFixed(2)"></span>
                        </div>
                        <template x-if="line.kind === 'discount' && line.eligibleGross !== null">
                            <p class="text-[11px] text-gray-400 mt-0.5"
                               x-text="(line.eligibleNames && line.eligibleNames.length ? '{{ __('Eligible items') }}: ' + line.eligibleNames.join(', ') : '{{ __('Eligible amount') }}') + ' — ₱' + line.eligibleGross.toFixed(2)"></p>
                        </template>
                    </div>
                </template>
                <div class="flex justify-between gap-3 tabular-nums" x-show="discountPreview.serviceCharge > 0">
                    <span class="text-gray-500">{{ __('Service Charge') }}</span>
                    <span class="text-gray-900" x-text="'₱' + discountPreview.serviceCharge.toFixed(2)"></span>
                </div>
                <div class="flex justify-between gap-3 tabular-nums pt-1 border-t border-slate-100 font-semibold">
                    <span class="text-gray-900">{{ __('Estimated Total Due') }}</span>
                    <span class="text-[#8A3330]" x-text="'₱' + estimatedTotalDue.toFixed(2)"></span>
                </div>
                
            </div>

            @if ($lateDiscount)
                {{-- What changes. The payments already recorded are kept;
                     the difference comes off the cash first. --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm space-y-1">
                    <div class="flex justify-between gap-3 tabular-nums">
                        <span class="text-gray-600">{{ __('Paid before') }}</span>
                        <span class="text-gray-900" x-text="'₱' + paidTotal.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums">
                        <span class="text-gray-600">{{ __('New total') }}</span>
                        <span class="text-gray-900" x-text="'₱' + estimatedTotalDue.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums font-semibold text-amber-800 pt-1 border-t border-dashed border-amber-300">
                        <span>{{ __('Comes off the payments') }}</span>
                        <span x-text="'₱' + lateDifference.toFixed(2)"></span>
                    </div>
                    <p class="text-[11px] text-amber-800">{{ __('Taken off the cash first. The sale stays on the day it was paid, so that day\'s report shows the discounted amount. A new receipt number is issued.') }}</p>
                </div>

                <input type="text" name="note" maxlength="255" placeholder="{{ __('What happened? (optional)') }}"
                       class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
            @else
            {{-- ============ PAYMENTS (SPLIT) ============ --}}
            <div class="pt-3 border-t border-slate-100">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-2">{{ __('Payments') }}</label>
                <div class="space-y-3">
                    <template x-for="(row, index) in payments" :key="index">
                        <div class="border border-slate-200 rounded-lg p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <select x-model="row.method" @change="onMethodChange(index)"
                                        class="flex-1 text-sm rounded-lg border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    <template x-for="option in methods" :key="option.value">
                                        <option :value="option.value" x-text="option.label"></option>
                                    </template>
                                </select>
                                <button type="button" @click="removePaymentRow(index)" x-show="payments.length > 1"
                                        class="text-xs text-red-600 hover:underline shrink-0">{{ __('Remove') }}</button>
                            </div>

                            {{-- Maya Checkout: the whole bill, paid on Maya's own page. --}}
                            <template x-if="row.method === 'maya_checkout'">
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs text-emerald-900 space-y-1">
                                    <p class="font-semibold tabular-nums" x-text="'{{ __('Guest pays') }} ₱' + estimatedTotalDue.toFixed(2) + ' {{ __('on Maya') }}'"></p>
                                    <p>{{ __('You\'ll be taken to Maya\'s secure checkout page (card, QRPh, Maya Wallet). The bill is marked paid only after Maya confirms the payment.') }}</p>
                                </div>
                            </template>

                            <div class="flex items-center gap-2" x-show="row.method !== 'maya_checkout'">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount Applied') }}</label>
                                    <input type="number" step="0.01" min="0" x-model="row.amount" @input="onAmountInput(index)" :required="row.method !== 'maya_checkout'"
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

                <button type="button" @click="addPaymentRow()" x-show="! isMayaCheckout" class="mt-2 text-sm font-medium text-[#8A3330] hover:underline">
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

            {{-- ============ BUYER INFO (optional, BIR) ============ --}}
            <div class="pt-3 border-t border-slate-100">
                <button type="button" @click="showBuyerInfo = ! showBuyerInfo" class="text-xs font-medium text-[#8A3330] hover:underline">
                    <span x-show="! showBuyerInfo">{{ __('+ Add buyer info (optional)') }}</span>
                    <span x-show="showBuyerInfo" x-cloak>{{ __('- Hide buyer info') }}</span>
                </button>
                <div x-show="showBuyerInfo" x-cloak class="mt-2 space-y-2">
                    <input type="text" name="buyer_name" x-model="buyerName" placeholder="{{ __('Buyer Name') }}"
                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                    <input type="text" name="buyer_tin" x-model="buyerTin" placeholder="{{ __('Buyer TIN') }}"
                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                    <input type="text" name="buyer_address" x-model="buyerAddress" placeholder="{{ __('Buyer Address') }}"
                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                </div>
            </div>
            @endif

            {{-- Hidden inputs: the discounts[] / payments[] arrays --}}
            <template x-for="(rule, rIndex) in selectedRules" :key="'d' + rule.id">
                <span>
                    <input type="hidden" :name="'discounts[' + rIndex + '][rule_id]'" :value="rule.id">
                    <input type="hidden" :name="'discounts[' + rIndex + '][entered_value]'" :value="rule.isCustom ? selections[rule.id].enteredValue : ''">
                    <input type="hidden" :name="'discounts[' + rIndex + '][qualified_name]'" :value="selections[rule.id].qualifiedName">
                    <input type="hidden" :name="'discounts[' + rIndex + '][id_number]'" :value="selections[rule.id].idNumber">
                    <input type="hidden" :name="'discounts[' + rIndex + '][reason]'" :value="selections[rule.id].reason">
                    <input type="hidden" :name="'discounts[' + rIndex + '][eligible_amount]'"
                           :value="rule.scope === 'eligible_items' && selections[rule.id].eligMode === 'amount' ? selections[rule.id].eligibleAmount : ''">
                    <template x-if="rule.scope === 'eligible_items' && selections[rule.id].eligMode === 'items'">
                        <span>
                            <template x-for="itemId in selections[rule.id].itemIds" :key="itemId">
                                <input type="hidden" :name="'discounts[' + rIndex + '][item_ids][]'" :value="itemId">
                            </template>
                        </span>
                    </template>
                </span>
            </template>

            @unless ($lateDiscount)
            <template x-for="(row, pIndex) in (isMayaCheckout ? [] : payments)" :key="'p' + pIndex">
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
            @endunless

            <button type="submit" :disabled="lateDiscount && selectedRules.length === 0"
                    class="min-h-12 w-full text-sm font-semibold rounded-xl px-4 py-3 bg-slate-800 hover:bg-slate-900 text-white focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                @if ($lateDiscount)
                    {{ __('Apply Discount') }}
                @else
                    <span x-text="isMayaCheckout ? '{{ __('Proceed to Maya Checkout') }}' : '{{ __('Finalize Payment') }}'">{{ __('Finalize Payment') }}</span>
                @endif
            </button>
        </div>

        <dialog
            x-ref="dialog"
            x-show="open"
            x-cloak
            x-effect="open ? $refs.dialog.showModal() : $refs.dialog.close()"
            @cancel="open = false"
            @click="$event.target === $refs.dialog && (open = false)"
            class="rounded-xl border border-slate-200 p-0 backdrop:bg-black/40 max-w-sm w-[calc(100%-2rem)] m-auto"
        >
            <div class="p-6">
                @if ($lateDiscount)
                <h3 class="font-semibold text-gray-900">{{ __('Add this discount to the paid bill?') }}</h3>
                <p class="mt-1 text-xs text-gray-400">{{ __('Final amounts are computed by the server on submit.') }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3 tabular-nums">
                        <dt class="text-gray-500">{{ __('Paid before') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + paidTotal.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums">
                        <dt class="text-gray-500">{{ __('New total') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + estimatedTotalDue.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums pt-2 border-t border-slate-100">
                        <dt class="font-semibold text-amber-800">{{ __('Comes off the payments') }}</dt>
                        <dd class="font-semibold text-amber-800" x-text="'₱' + lateDifference.toFixed(2)"></dd>
                    </div>
                </dl>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="open = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="open = false; $root.submit()"
                            class="text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white">
                        {{ __('Apply Discount') }}
                    </button>
                </div>
                @else
                <h3 class="font-semibold text-gray-900" x-text="isMayaCheckout ? '{{ __('Pay with Maya Checkout?') }}' : '{{ __('Confirm Payment') }}'"></h3>
                <p class="mt-1 text-xs text-gray-400" x-show="! isMayaCheckout">{{ __('Final amounts are computed by the server on submit.') }}</p>
                <p class="mt-1 text-xs text-gray-500" x-show="isMayaCheckout" x-cloak>{{ __('This opens Maya\'s checkout page for the amount below. The bill stays unpaid until Maya confirms the payment.') }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3 tabular-nums">
                        <dt class="text-gray-500">{{ __('Estimated Total Due') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + estimatedTotalDue.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums">
                        <dt class="text-gray-500">{{ __('Total Paid') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + totalPaid.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums" x-show="totalChange > 0">
                        <dt class="text-gray-500">{{ __('Cash Change') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + totalChange.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between gap-3 tabular-nums pt-2 border-t border-slate-100" x-show="insufficientAmount">
                        <dt class="font-semibold text-red-600">{{ __('Remaining Balance') }}</dt>
                        <dd class="font-semibold text-red-600" x-text="'₱' + remainingBalance.toFixed(2)"></dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-red-600" x-show="insufficientAmount" x-cloak
                   x-text="'{{ __('Insufficient payment') }} — ₱' + remainingBalance.toFixed(2) + ' {{ __('still due.') }}'"></p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="open = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="open = false; submitForm($root)" :disabled="insufficientAmount"
                            class="text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white disabled:opacity-50 disabled:cursor-not-allowed"
                            x-text="isMayaCheckout ? '{{ __('Go to Maya') }}' : '{{ __('Confirm Payment') }}'">
                        {{ __('Confirm Payment') }}
                    </button>
                </div>
                @endif
            </div>
        </dialog>
    </form>
</div>
