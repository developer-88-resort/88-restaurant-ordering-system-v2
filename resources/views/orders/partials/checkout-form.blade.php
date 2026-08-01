{{--
    The checkout / finalize-payment form: configurable discount checklist
    (discount_rules table) + split payments (multiple payment entries, cash
    change computed off the cash rows only, masked card-terminal details).
    Everything shown here is an advisory estimate — the server (PaymentFinalizer
    + InvoiceCalculator) recomputes all money authoritatively on submit.

    Expects: $order (items.adjustments/addons loaded), $setting, $discountRules.
--}}
@php
    $discountRulesPayload = $discountRules->map(fn ($rule) => [
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
    ])->values();

    $eligibleItemsPayload = $order->items
        ->filter(fn ($item) => ! $item->isFullyCancelled())
        ->map(fn ($item) => ['id' => $item->id, 'name' => $item->item_name, 'amount' => (float) $item->lineTotalNet()])
        ->values();

    $paymentMethodsPayload = collect(\App\Enums\PaymentMethod::cases())->map(fn ($method) => [
        'value' => $method->value,
        'label' => $method->label(),
        'requiresReference' => $method->requiresReference(),
    ])->values();

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
        action="{{ route('orders.mark-as-paid', $order) }}"
        x-data="{
            open: false,
            orderTotal: {{ (float) $order->total_amount }},
            isVat: {{ Js::from($setting->tax_registration_type->value === 'vat') }},
            taxRate: {{ (float) $setting->tax_rate }},
            serviceChargeEnabled: {{ Js::from((bool) $setting->service_charge_enabled) }},
            serviceChargePercent: {{ (float) ($setting->service_charge_percent ?? 0) }},
            isStaff: {{ Js::from(auth()->user()->role === \App\Enums\UserRole::Staff) }},
            rules: {{ Js::from($discountRulesPayload) }},
            orderItems: {{ Js::from($eligibleItemsPayload) }},
            methods: {{ Js::from($paymentMethodsPayload) }},
            selections: {},
            overrideMode: false,
            managerEmail: '',
            managerPassword: '',
            showBuyerInfo: false,
            buyerName: '',
            buyerTin: '',
            buyerAddress: '',
            payments: [{ method: 'cash', amount: '', tendered: '', cardBrand: '', cardLastFour: '', terminalReference: '', approvalCode: '', terminalId: '', reference: '', notes: '' }],
            toggleRule(rule) {
                if (this.selections[rule.id]) {
                    delete this.selections[rule.id];
                    return;
                }
                if (this.isDisabled(rule)) return;
                this.selections[rule.id] = {
                    enteredValue: rule.isCustom ? '' : rule.value,
                    qualifiedName: '',
                    idNumber: '',
                    reason: '',
                    eligMode: 'items',
                    itemIds: [],
                    eligibleAmount: '',
                };
            },
            get selectedRules() {
                return this.rules.filter(r => this.selections[r.id]).sort((a, b) => a.priority - b.priority);
            },
            get anySelectedExclusive() {
                return this.selectedRules.some(r => !r.stackable);
            },
            isDisabled(rule) {
                if (this.selections[rule.id]) return false;
                if (this.selectedRules.length === 0) return false;
                if (this.overrideMode) return false;
                return !rule.stackable || this.anySelectedExclusive;
            },
            belowMinBill(rule) {
                return rule.minBill !== null && this.orderTotal < rule.minBill;
            },
            get needsApproval() {
                return this.selectedRules.some(r => r.requiresApproval)
                    || (this.selectedRules.length > 1 && this.anySelectedExclusive);
            },
            eligibleBase(rule) {
                const sel = this.selections[rule.id];
                if (!sel) return 0;
                if (rule.scope === 'eligible_items') {
                    if (sel.eligMode === 'amount') return Math.min(Number(sel.eligibleAmount) || 0, this.orderTotal);
                    return this.orderItems.filter(i => sel.itemIds.includes(i.id)).reduce((sum, i) => sum + i.amount, 0);
                }
                return null;
            },
            get discountPreview() {
                const lines = [];
                let statutoryEligible = 0;
                let statutoryDue = 0;
                for (const rule of this.selectedRules.filter(r => r.statutory)) {
                    const base = this.eligibleBase(rule) ?? this.orderTotal;
                    statutoryEligible += base;
                    const net = this.isVat ? base / (1 + this.taxRate / 100) : base;
                    const pct = Number(this.selections[rule.id].enteredValue ?? rule.value ?? 20);
                    let disc = net * pct / 100;
                    if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                    statutoryDue += net - disc;
                    lines.push({ name: rule.name, amount: disc });
                }
                let runningDue = Math.max(0, this.orderTotal - statutoryEligible);
                for (const rule of this.selectedRules.filter(r => !r.statutory && r.mode === 'percent')) {
                    const explicitBase = this.eligibleBase(rule);
                    const base = Math.min(explicitBase ?? runningDue, runningDue);
                    const pct = Number(this.selections[rule.id].enteredValue ?? rule.value ?? 0);
                    let disc = base * pct / 100;
                    if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                    disc = Math.min(disc, runningDue);
                    runningDue -= disc;
                    lines.push({ name: rule.name, amount: disc });
                }
                for (const rule of this.selectedRules.filter(r => !r.statutory && r.mode === 'fixed')) {
                    let disc = Number(this.selections[rule.id].enteredValue ?? rule.value ?? 0);
                    if (rule.maxDiscount !== null) disc = Math.min(disc, rule.maxDiscount);
                    disc = Math.min(disc, runningDue);
                    runningDue -= disc;
                    lines.push({ name: rule.name, amount: disc });
                }
                const serviceCharge = (this.serviceChargeEnabled && this.serviceChargePercent > 0)
                    ? this.orderTotal * this.serviceChargePercent / 100
                    : 0;
                return { lines, serviceCharge, totalDue: Math.max(0, statutoryDue + runningDue + serviceCharge) };
            },
            get estimatedTotalDue() {
                return this.discountPreview.totalDue;
            },
            addPaymentRow() {
                this.payments.push({ method: 'cash', amount: '', tendered: '', cardBrand: '', cardLastFour: '', terminalReference: '', approvalCode: '', terminalId: '', reference: '', notes: '' });
            },
            removePaymentRow(index) {
                this.payments.splice(index, 1);
                if (this.payments.length === 0) this.addPaymentRow();
            },
            fillRemaining(index) {
                const others = this.payments.reduce((sum, row, i) => i === index ? sum : sum + (Number(row.amount) || 0), 0);
                this.payments[index].amount = Math.max(0, this.estimatedTotalDue - others).toFixed(2);
            },
            get totalPaid() {
                return this.payments.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
            },
            get remainingBalance() {
                return this.estimatedTotalDue - this.totalPaid;
            },
            get totalChange() {
                return this.payments.reduce((sum, row) => {
                    if (row.method !== 'cash') return sum;
                    const tendered = Number(row.tendered) || Number(row.amount) || 0;
                    return sum + Math.max(0, tendered - (Number(row.amount) || 0));
                }, 0);
            },
            methodInfo(value) {
                return this.methods.find(m => m.value === value) ?? { requiresReference: false };
            },
        }"
        @submit.prevent="open = true"
    >
        @csrf
        @method('PATCH')

        <div class="space-y-4">
            {{-- ============ DISCOUNTS ============ --}}
            <div class="pt-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E] mb-2">{{ __('Apply Discount') }}</label>
                <div class="space-y-2">
                    <template x-for="rule in rules" :key="rule.id">
                        <div class="border rounded-lg transition"
                             :class="selections[rule.id] ? 'border-[#8A3330] bg-[#FAF6EE]' : (isDisabled(rule) || belowMinBill(rule) ? 'border-[#E5DDD0] opacity-50' : 'border-[#E5DDD0]')">
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
                                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]"
                                                   x-text="rule.mode === 'percent' ? '{{ __('Discount Percent') }}' : '{{ __('Discount Amount (₱)') }}'"></label>
                                            <input type="number" step="0.01" min="0.01" :max="rule.mode === 'percent' ? 100 : null"
                                                   x-model="selections[rule.id].enteredValue" required
                                                   class="mt-1 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                        </div>
                                    </template>

                                    <template x-if="rule.requiresId">
                                        <div class="grid grid-cols-1 gap-2">
                                            <input type="text" x-model="selections[rule.id].qualifiedName" required placeholder="{{ __('Qualified Customer Name') }}"
                                                   class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                            <input type="text" x-model="selections[rule.id].idNumber" required placeholder="{{ __('ID Number (SC/PWD ID)') }}"
                                                   class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                        </div>
                                    </template>

                                    <template x-if="rule.requiresReason">
                                        <input type="text" x-model="selections[rule.id].reason" required placeholder="{{ __('Reason for this discount') }}"
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
                                                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
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
                <p class="text-xs font-semibold text-[#8A3330]">{{ __('Manager approval required for the selected discounts.') }}</p>
                <input type="email" name="manager_email" x-model="managerEmail" placeholder="{{ __('Manager Email') }}"
                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                <input type="password" name="manager_password" x-model="managerPassword" placeholder="{{ __('Manager Password') }}"
                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
            </div>

            {{-- ============ CALCULATION PREVIEW ============ --}}
            <div class="rounded-lg bg-[#FAF6EE] border border-[#E5DDD0] px-4 py-3 text-sm space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('Order Subtotal (net of cancellations)') }}</span>
                    <span class="text-gray-900" x-text="'₱' + orderTotal.toFixed(2)"></span>
                </div>
                <template x-for="(line, i) in discountPreview.lines" :key="i">
                    <div class="flex justify-between text-[#8A3330]">
                        <span x-text="line.name"></span>
                        <span x-text="'−₱' + line.amount.toFixed(2)"></span>
                    </div>
                </template>
                <div class="flex justify-between" x-show="discountPreview.serviceCharge > 0">
                    <span class="text-gray-500">{{ __('Service Charge') }}</span>
                    <span class="text-gray-900" x-text="'₱' + discountPreview.serviceCharge.toFixed(2)"></span>
                </div>
                <div class="flex justify-between pt-1 border-t border-dashed border-[#D9CCBA] font-semibold">
                    <span class="text-gray-900">{{ __('Estimated Total Due') }}</span>
                    <span class="text-[#8A3330]" x-text="'₱' + estimatedTotalDue.toFixed(2)"></span>
                </div>
                <p class="text-[10px] text-gray-400">{{ __('Final amounts are computed by the server on submit.') }}</p>
            </div>

            {{-- ============ PAYMENTS (SPLIT) ============ --}}
            <div class="pt-3 border-t border-dashed border-[#D9CCBA]">
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E] mb-2">{{ __('Payments') }}</label>
                <div class="space-y-3">
                    <template x-for="(row, index) in payments" :key="index">
                        <div class="border border-[#E5DDD0] rounded-lg p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <select x-model="row.method"
                                        class="flex-1 text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <template x-for="option in methods" :key="option.value">
                                        <option :value="option.value" x-text="option.label"></option>
                                    </template>
                                </select>
                                <button type="button" @click="removePaymentRow(index)" x-show="payments.length > 1"
                                        class="text-xs text-red-600 hover:underline shrink-0">{{ __('Remove') }}</button>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Amount Applied') }}</label>
                                    <input type="number" step="0.01" min="0" x-model="row.amount" required
                                           class="mt-0.5 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                </div>
                                <button type="button" @click="fillRemaining(index)"
                                        class="mt-4 text-[10px] font-bold uppercase text-[#8A3330] hover:underline shrink-0">{{ __('Fill') }}</button>
                            </div>

                            <template x-if="row.method === 'cash'">
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Cash Tendered') }}</label>
                                    <input type="number" step="0.01" min="0" x-model="row.tendered" :placeholder="row.amount"
                                           class="mt-0.5 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <p class="text-[11px] text-gray-400 mt-0.5"
                                       x-text="'{{ __('Change') }}: ₱' + Math.max(0, (Number(row.tendered) || Number(row.amount) || 0) - (Number(row.amount) || 0)).toFixed(2)"></p>
                                </div>
                            </template>

                            <template x-if="row.method === 'card'">
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="row.terminalReference" required placeholder="{{ __('Terminal Reference No.') }}"
                                           class="col-span-2 w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <input type="text" x-model="row.approvalCode" placeholder="{{ __('Approval Code') }}"
                                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <input type="text" x-model="row.terminalId" placeholder="{{ __('Terminal ID') }}"
                                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <input type="text" x-model="row.cardBrand" placeholder="{{ __('Card Brand (Visa...)') }}"
                                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <input type="text" x-model="row.cardLastFour" maxlength="4" pattern="[0-9]{4}" placeholder="{{ __('Last 4 Digits') }}"
                                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                                    <p class="col-span-2 text-[10px] text-gray-400">{{ __('Copy these from the card machine slip. Never enter the full card number or CVV.') }}</p>
                                </div>
                            </template>

                            <template x-if="row.method !== 'cash' && row.method !== 'card' && methodInfo(row.method).requiresReference">
                                <input type="text" x-model="row.reference" required placeholder="{{ __('Reference Number') }}"
                                       class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                            </template>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addPaymentRow()" class="mt-2 text-sm font-medium text-[#8A3330] hover:underline">
                    + {{ __('Add another payment method') }}
                </button>

                <div class="mt-3 rounded-lg bg-[#FAF6EE] border border-[#E5DDD0] px-4 py-3 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('Total Paid') }}</span>
                        <span class="text-gray-900" x-text="'₱' + totalPaid.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between font-semibold" :class="Math.abs(remainingBalance) < 0.005 ? 'text-green-700' : 'text-red-600'">
                        <span>{{ __('Remaining Balance') }}</span>
                        <span x-text="'₱' + remainingBalance.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between" x-show="totalChange > 0">
                        <span class="text-gray-500">{{ __('Cash Change') }}</span>
                        <span class="text-gray-900" x-text="'₱' + totalChange.toFixed(2)"></span>
                    </div>
                </div>
            </div>

            {{-- ============ BUYER INFO (optional, BIR) ============ --}}
            <div class="pt-3 border-t border-dashed border-[#D9CCBA]">
                <button type="button" @click="showBuyerInfo = ! showBuyerInfo" class="text-xs font-medium text-[#8A3330] hover:underline">
                    <span x-show="! showBuyerInfo">{{ __('+ Add buyer info (optional)') }}</span>
                    <span x-show="showBuyerInfo" x-cloak>{{ __('- Hide buyer info') }}</span>
                </button>
                <div x-show="showBuyerInfo" x-cloak class="mt-2 space-y-2">
                    <input type="text" name="buyer_name" x-model="buyerName" placeholder="{{ __('Buyer Name') }}"
                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                    <input type="text" name="buyer_tin" x-model="buyerTin" placeholder="{{ __('Buyer TIN') }}"
                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                    <input type="text" name="buyer_address" x-model="buyerAddress" placeholder="{{ __('Buyer Address') }}"
                           class="w-full text-sm rounded-lg border-[#E5DDD0] focus:border-[#8A3330] focus:ring-[#8A3330]">
                </div>
            </div>

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

            <template x-for="(row, pIndex) in payments" :key="'p' + pIndex">
                <span>
                    <input type="hidden" :name="'payments[' + pIndex + '][method]'" :value="row.method">
                    <input type="hidden" :name="'payments[' + pIndex + '][amount]'" :value="row.amount">
                    <input type="hidden" :name="'payments[' + pIndex + '][tendered_amount]'" :value="row.method === 'cash' ? row.tendered : ''">
                    <input type="hidden" :name="'payments[' + pIndex + '][card_brand]'" :value="row.cardBrand">
                    <input type="hidden" :name="'payments[' + pIndex + '][card_last_four]'" :value="row.cardLastFour">
                    <input type="hidden" :name="'payments[' + pIndex + '][terminal_reference]'" :value="row.terminalReference">
                    <input type="hidden" :name="'payments[' + pIndex + '][approval_code]'" :value="row.approvalCode">
                    <input type="hidden" :name="'payments[' + pIndex + '][terminal_id]'" :value="row.terminalId">
                    <input type="hidden" :name="'payments[' + pIndex + '][reference]'" :value="row.reference">
                    <input type="hidden" :name="'payments[' + pIndex + '][notes]'" :value="row.notes">
                </span>
            </template>

            <button type="submit" class="w-full text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white">
                {{ __('Finalize Payment') }}
            </button>
        </div>

        <dialog
            x-ref="dialog"
            x-effect="open ? $refs.dialog.showModal() : $refs.dialog.close()"
            @cancel="open = false"
            @click="$event.target === $refs.dialog && (open = false)"
            class="rounded-xl border border-[#E5DDD0] p-0 backdrop:bg-black/40 max-w-sm w-[calc(100%-2rem)] m-auto"
        >
            <div class="p-6">
                <h3 class="font-semibold text-gray-900">{{ __('Confirm Payment') }}</h3>
                <p class="mt-1 text-xs text-gray-400">{{ __('Final amounts are computed by the server on submit.') }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('Estimated Total Due') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + estimatedTotalDue.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('Total Paid') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + totalPaid.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between" x-show="totalChange > 0">
                        <dt class="text-gray-500">{{ __('Cash Change') }}</dt>
                        <dd class="font-medium text-gray-900" x-text="'₱' + totalChange.toFixed(2)"></dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-dashed border-[#D9CCBA]" x-show="Math.abs(remainingBalance) >= 0.005">
                        <dt class="font-semibold text-red-600">{{ __('Remaining Balance') }}</dt>
                        <dd class="font-semibold text-red-600" x-text="'₱' + remainingBalance.toFixed(2)"></dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-red-600" x-show="Math.abs(remainingBalance) >= 0.005" x-cloak>
                    {{ __('Payments must cover the total exactly — the server will refuse to close the bill with a balance.') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="open = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="open = false; $root.submit()"
                            class="text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white">
                        {{ __('Confirm Payment') }}
                    </button>
                </div>
            </div>
        </dialog>
    </form>
</div>
