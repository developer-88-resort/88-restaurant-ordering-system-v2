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
    $paidSnapshot = $lateDiscount ? $order->currentInvoiceSnapshot : null;

    // The discounts already on the bill, as the checklist's own selections,
    // so correcting a bill adds to what it had instead of replacing it. A
    // rule that is no longer offered can't be re-applied and is left off.
    $initialSelections = $paidSnapshot
        ? $paidSnapshot->discounts
            ->filter(fn ($line) => $discountRules->contains('id', $line->discount_rule_id))
            // One invoice line per person (Senior/PWD/Diplomat), so lines of
            // the same rule come back as that rule's list of persons.
            ->groupBy('discount_rule_id')
            ->map(function ($lines, $ruleId) use ($discountRules) {
                $rule = $discountRules->firstWhere('id', $ruleId);
                $first = $lines->first();

                return [
                    'enteredValue' => $rule->is_custom_value ? (float) $first->entered_value : ($rule->value !== null ? (float) $rule->value : null),
                    'reason' => $first->reason ?? '',
                    'totalPersons' => $first->total_persons ?? '',
                    'persons' => $lines->map(fn ($line) => [
                        'qualifiedName' => $line->qualified_name ?? '',
                        'idNumber' => $line->id_number ?? '',
                        'eligMode' => 'amount',
                        'itemIds' => [],
                        'eligibleAmount' => $line->eligible_amount !== null ? (float) $line->eligible_amount : '',
                    ])->values()->all(),
                ];
            })
        : collect();

    // Everything the orderPayment() Alpine component (resources/js/lib/
    // order-payment.js) needs, as a single JSON-safe payload passed via
    // @js() below — never build this component's state as an inline
    // x-data string again; that's what let a stray " inside a // comment
    // truncate the whole attribute last time.
    // A breakfast order is complimentary as a whole (App\Support\Breakfast):
    // Others is its only payment method, for the full amount.
    $complimentary = ! $lateDiscount && \App\Support\Breakfast::isComplimentary($order);

    $paymentConfig = [
        'complimentary' => $complimentary,
        'orderTotal' => (float) $order->total_amount,
        'orderPax' => $order->pax ? (int) $order->pax : null,
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
        'methods' => collect($complimentary ? \App\Enums\PaymentMethod::complimentaryOnly() : \App\Enums\PaymentMethod::selectable())->map(fn ($method) => [
            'value' => $method->value,
            'label' => $method->label(),
            'requiresReference' => $method->requiresReference(),
        ])->values(),
        // Room Charge's room picker — only needed when payments are taken.
        'rooms' => $lateDiscount ? null : \App\Support\RoomChargePicker::config(),
        // Foreign card surcharge rate (settings.foreign_card_surcharge_percent).
        'foreignCardSurchargePercent' => (float) ($setting->foreign_card_surcharge_percent ?? 12),
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

                                    {{-- Offered on every hand-keyed discount, demanded only where
                                         the rule says so. Custom Amount asks for no reason, but a
                                         cashier writing down why ₱500 came off the bill is worth
                                         keeping — it lands on the invoice line either way. --}}
                                    <template x-if="rule.requiresReason || rule.isCustom">
                                        <input type="text" x-model="selections[rule.id].reason" :required="rule.requiresReason"
                                               :placeholder="rule.requiresReason ? '{{ __('Reason for this discount') }}' : '{{ __('Reason (optional)') }}'"
                                               class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                    </template>

                                    {{-- Headcount share (Diplomat): the bill ÷ persons in the group is
                                         one diplomat's share; every diplomat added below takes one. --}}
                                    <template x-if="rule.scope === 'per_person'">
                                        <div class="space-y-1.5">
                                            <div>
                                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Persons in group') }}</label>
                                                <input type="number" min="1" step="1" x-model="selections[rule.id].totalPersons" required
                                                       class="mt-0.5 min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                            </div>
                                            <p class="text-[11px] text-gray-500 tabular-nums"
                                               x-text="'₱' + orderTotal.toFixed(2) + ' ÷ ' + (selections[rule.id].totalPersons || '?') + ' = ₱' + perPersonShare(rule).toFixed(2) + ' {{ __('per diplomat') }} × ' + selections[rule.id].persons.length + ' = ₱' + (perPersonShare(rule) * selections[rule.id].persons.length).toFixed(2) + ' {{ __('VAT-exempt') }}'"></p>
                                            <p x-show="tooManyPersons(rule)" x-cloak class="text-[11px] font-semibold text-red-600">{{ __('More diplomats than persons in the group.') }}</p>
                                        </div>
                                    </template>

                                    {{-- One block per person: name + ID, and — for Senior/PWD — the
                                         food that is theirs. A rule without a person (none today)
                                         keeps a single block for its eligibility. --}}
                                    <template x-for="(person, pIndex) in (rule.requiresId ? selections[rule.id].persons : selections[rule.id].persons.slice(0, 1))" :key="pIndex">
                                        <div class="space-y-2" :class="rule.requiresId ? 'rounded-lg border border-slate-200 bg-white p-2.5' : ''">
                                            <template x-if="rule.requiresId">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                                       x-text="(rule.statutory === 'diplomat' ? '{{ __('Diplomat') }}' : (rule.statutory === 'pwd' ? '{{ __('PWD') }}' : '{{ __('Senior') }}')) + ' ' + (pIndex + 1)"></p>
                                                    <button type="button" @click="removePerson(rule, pIndex)" x-show="selections[rule.id].persons.length > 1"
                                                            class="text-[11px] font-medium text-red-600 hover:underline">{{ __('Remove') }}</button>
                                                </div>
                                            </template>
                                            <template x-if="rule.requiresId">
                                                <div class="grid grid-cols-1 gap-2">
                                                    <input type="text" x-model="person.qualifiedName" required placeholder="{{ __('Qualified Customer Name') }}"
                                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                                    <input type="text" x-model="person.idNumber" required
                                                           :placeholder="rule.statutory === 'diplomat' ? '{{ __('Diplomatic ID / Passport No.') }}' : '{{ __('ID Number (SC/PWD ID)') }}'"
                                                           class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                                </div>
                                            </template>

                                            <template x-if="rule.scope === 'eligible_items'">
                                                <div class="space-y-1.5">
                                                    <div class="flex gap-4 text-xs text-gray-600">
                                                        <label class="inline-flex items-center gap-1.5">
                                                            <input type="radio" value="items" x-model="person.eligMode" class="text-[#8A3330] focus:ring-[#8A3330]">
                                                            {{ __('Select eligible items') }}
                                                        </label>
                                                        <label class="inline-flex items-center gap-1.5">
                                                            <input type="radio" value="amount" x-model="person.eligMode" class="text-[#8A3330] focus:ring-[#8A3330]">
                                                            {{ __('Enter eligible amount') }}
                                                        </label>
                                                    </div>
                                                    <template x-if="person.eligMode === 'items'">
                                                        <div class="space-y-1 max-h-32 overflow-y-auto">
                                                            <template x-for="orderItem in orderItems" :key="orderItem.id">
                                                                <label class="flex items-center justify-between gap-2 text-xs py-0.5"
                                                                       :class="itemClaimedElsewhere(orderItem.id, rule, pIndex) ? 'text-gray-300' : 'text-gray-600'">
                                                                    <span class="inline-flex items-center gap-1.5 min-w-0">
                                                                        <input type="checkbox" :value="orderItem.id" x-model.number="person.itemIds"
                                                                               :disabled="itemClaimedElsewhere(orderItem.id, rule, pIndex)"
                                                                               class="rounded text-[#8A3330] focus:ring-[#8A3330] disabled:opacity-40">
                                                                        <span class="truncate" x-text="orderItem.name"></span>
                                                                        <span x-show="itemClaimedElsewhere(orderItem.id, rule, pIndex)" class="shrink-0 text-[10px]">{{ __('(another person)') }}</span>
                                                                    </span>
                                                                    <span class="shrink-0" x-text="'₱' + orderItem.amount.toFixed(2)"></span>
                                                                </label>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="person.eligMode === 'amount'">
                                                        <input type="number" step="0.01" min="0" :max="orderTotal" x-model="person.eligibleAmount"
                                                               placeholder="{{ __('Eligible Amount') }}"
                                                               class="min-h-11 w-full text-sm rounded-xl border-slate-200 focus:border-slate-400 focus:ring-slate-200">
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="rule.requiresId">
                                        <button type="button" @click="addPerson(rule)"
                                                class="text-xs font-semibold text-[#8A3330] hover:underline"
                                                x-text="'+ ' + (rule.statutory === 'diplomat' ? '{{ __('Add another diplomat') }}' : (rule.statutory === 'pwd' ? '{{ __('Add another PWD') }}' : '{{ __('Add another senior citizen') }}'))"></button>
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
                                ? ('{{ __('Less: VAT Exemption') }}' + (line.ruleName ? ' — ' + line.ruleName : '') + ' (' + taxRate.toFixed(0) + '%)')
                                : (line.ruleName + (line.basisNet !== null ? ' (' + line.pct.toFixed(0) + '% {{ __('of VAT-exempt') }} ₱' + line.basisNet.toFixed(2) + ')' : ''))"></span>
                            <span x-text="'−₱' + line.amount.toFixed(2)"></span>
                        </div>
                        <template x-if="line.eligibleGross !== undefined && line.eligibleGross !== null">
                            <p class="text-[11px] text-gray-400 mt-0.5"
                               x-text="(line.who ? line.who + ' · ' : '') + (line.personsNote ? '{{ __('Persons') }} ' + line.personsNote : (line.eligibleNames && line.eligibleNames.length ? '{{ __('Eligible items') }}: ' + line.eligibleNames.join(', ') : '{{ __('Eligible amount') }}')) + ' — ₱' + line.eligibleGross.toFixed(2)"></p>
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
            @include('orders.partials.payment-rows')

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

            {{-- Hidden inputs: the discounts[] / payments[] arrays — one discounts[]
                 row per person on a Senior/PWD/Diplomat discount. --}}
            <template x-for="(row, rIndex) in discountRows" :key="'d' + row.rule.id + '-' + row.pIndex">
                <span>
                    <input type="hidden" :name="'discounts[' + rIndex + '][rule_id]'" :value="row.rule.id">
                    <input type="hidden" :name="'discounts[' + rIndex + '][entered_value]'" :value="row.rule.isCustom ? row.sel.enteredValue : ''">
                    <input type="hidden" :name="'discounts[' + rIndex + '][qualified_name]'" :value="row.rule.requiresId ? row.person.qualifiedName : ''">
                    <input type="hidden" :name="'discounts[' + rIndex + '][id_number]'" :value="row.rule.requiresId ? row.person.idNumber : ''">
                    <input type="hidden" :name="'discounts[' + rIndex + '][reason]'" :value="row.sel.reason">
                    <input type="hidden" :name="'discounts[' + rIndex + '][total_persons]'" :value="row.rule.scope === 'per_person' ? row.sel.totalPersons : ''">
                    <input type="hidden" :name="'discounts[' + rIndex + '][eligible_amount]'"
                           :value="row.rule.scope === 'eligible_items' && row.person.eligMode === 'amount' ? row.person.eligibleAmount : ''">
                    <template x-if="row.rule.scope === 'eligible_items' && row.person.eligMode === 'items'">
                        <span>
                            <template x-for="itemId in row.person.itemIds" :key="itemId">
                                <input type="hidden" :name="'discounts[' + rIndex + '][item_ids][]'" :value="itemId">
                            </template>
                        </span>
                    </template>
                </span>
            </template>


            {{-- The server's own limits on Senior / PWD / Diplomat, shown before submitting. --}}
            <p x-show="statutoryProblem" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" x-text="statutoryProblem"></p>

            <button type="submit" :disabled="(lateDiscount && selectedRules.length === 0) || !!statutoryProblem"
                    class="min-h-12 w-full text-sm font-semibold rounded-xl px-4 py-3 bg-slate-800 hover:bg-slate-900 text-white focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                {{ $lateDiscount ? __('Apply Discount') : __('Finalize Payment') }}
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
                <h3 class="font-semibold text-gray-900">{{ __('Confirm Payment') }}</h3>
                <p class="mt-1 text-xs text-gray-400">{{ __('Final amounts are computed by the server on submit.') }}</p>
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
                {{-- A foreign card: the surcharge and what to key into the terminal. --}}
                <template x-for="(card, fIndex) in foreignCardRows" :key="'fc' + fIndex">
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                       x-text="'{{ __('Foreign card') }}: ₱' + (Number(card.amount) || 0).toFixed(2) + ' + ' + foreignCardSurchargePercent + '% {{ __('surcharge') }} ₱' + surchargeFor(card).toFixed(2) + ' — {{ __('charge') }} ₱' + terminalAmount(card).toFixed(2) + ' {{ __('on the terminal') }}'"></p>
                </template>
                {{-- Each room, locker and kubo charge spelled out, so the cashier confirms where it goes. --}}
                <template x-for="(charge, cIndex) in chargeConfirmations" :key="'rc' + cIndex">
                    <p class="mt-3 rounded-lg border border-[#F3E1DC] bg-[#FDF7F5] px-3 py-2 text-sm text-gray-900"
                       x-text="'{{ __('Charge') }} ₱' + charge.amount.toFixed(2) + ' {{ __('to') }} ' + charge.target + (charge.guestName ? ' — {{ __('Guest') }}: ' + charge.guestName : '') + '?'"></p>
                </template>
                <p class="mt-2 text-xs text-red-600" x-show="insufficientAmount" x-cloak
                   x-text="'{{ __('Insufficient payment') }} — ₱' + remainingBalance.toFixed(2) + ' {{ __('still due.') }}'"></p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="open = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="open = false; $root.submit()" :disabled="insufficientAmount || !!statutoryProblem"
                            class="text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ __('Confirm Payment') }}
                    </button>
                </div>
                @endif
            </div>
        </dialog>
    </form>
</div>
