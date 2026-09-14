<div class="bg-white border border-[#E5DDD0] rounded-xl p-6 font-mono text-sm relative">
    @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <span class="text-red-600/30 text-5xl font-black uppercase tracking-widest -rotate-12 border-4 border-red-600/30 px-4 py-1">
                {{ __('Voided') }}
            </span>
        </div>
    @endif

    @php $invoice = $order->currentInvoiceSnapshot; @endphp

    @if ($invoice)
        {{-- BIR invoice layout — every field below is read from the frozen
             snapshot, never from live Settings, so a later settings/tax-rate
             change never rewrites an already-issued invoice. --}}
        <div class="text-center">
            @if (file_exists(public_path('images/logo.png')))
                <img src="{{ asset('images/logo.png') }}" alt="{{ $invoice->business_name }}" class="h-14 w-14 rounded-full object-cover mx-auto mb-2">
            @endif
            <p class="font-semibold uppercase tracking-wide">{{ $invoice->business_name }}</p>
            @if ($invoice->trade_name && $invoice->trade_name !== $invoice->business_name)
                <p class="text-xs text-gray-500">{{ __('Trade Name') }}: {{ $invoice->trade_name }}</p>
            @endif
            @if ($invoice->business_address)
                <p class="mt-2 text-[11px] text-gray-500 leading-snug">{{ $invoice->business_address }}</p>
            @endif
            @if ($invoice->contact_number || $invoice->email)
                <p class="text-[11px] text-gray-500">{{ collect([$invoice->contact_number, $invoice->email])->filter()->join(' • ') }}</p>
            @endif
            @if ($invoice->tin)
                <p class="text-[11px] text-gray-500">
                    {{ $invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat ? __('VAT REG TIN') : __('NON-VAT REG TIN') }}:
                    {{ $invoice->tin }}{{ $invoice->branch_code ? ' - '.$invoice->branch_code : '' }}
                </p>
            @endif
            <p class="mt-2 text-xs font-bold uppercase tracking-wide">{{ $invoice->invoice_title }}</p>
        </div>

        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1 text-xs">
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Invoice No.') }}</span><span>{{ $invoice->invoice_number }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Order No.') }}</span><span>{{ $order->orderNumber() }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Date') }}</span><span>{{ $invoice->computed_at->format('M d, Y g:i A') }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Location') }}</span><span>{{ $order->locationLabel() }}</span></div>
            @if ($order->customer_name)
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Customer') }}</span><span>{{ $order->customer_name }}</span></div>
            @endif
            @if ($order->covers_count)
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Covers') }}</span><span>{{ $order->covers_count }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Order Type') }}</span><span>{{ $order->order_type->label() }}</span></div>
            @if ($order->batch_number)
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Batch') }}</span><span>#{{ $order->batch_number }}{{ $order->guestSession ? ' · '.$order->guestSession->displayLabel() : '' }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Cashier') }}</span><span>{{ $invoice->computedBy->name ?? $order->creator->name ?? __('Unknown') }}</span></div>
        </div>

        @if ($invoice->buyer_name)
            <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1 text-xs">
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Buyer') }}</span><span>{{ $invoice->buyer_name }}</span></div>
                @if ($invoice->buyer_tin)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('Buyer TIN') }}</span><span>{{ $invoice->buyer_tin }}</span></div>
                @endif
                @if ($invoice->buyer_address)
                    <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">{{ __('Buyer Address') }}</span><span class="text-right">{{ $invoice->buyer_address }}</span></div>
                @endif
            </div>
        @endif

        @php
            $eligibilityTag = match ($invoice->discount_type) {
                \App\Enums\DiscountType::SeniorCitizen => 'SC',
                \App\Enums\DiscountType::Pwd => 'PWD',
                default => null,
            };
            // Read from the frozen snapshot list, never the live
            // order_items.is_discount_eligible column — that column only
            // reflects the CURRENT/latest payment attempt, which would be
            // wrong for an older, voided invoice after a repay with a
            // different item selection.
            $eligibleItemNames = collect($invoice->discount_eligible_item_names ?? []);
        @endphp
        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1.5">
            @foreach ($order->items as $item)
                @php $isEligible = $eligibleItemNames->contains($item->item_name); @endphp
                <div class="flex justify-between gap-2">
                    <span class="min-w-0 break-words">
                        @if ($item->quotation)
                            <span class="text-amber-700">{{ __('Advance Order') }} - </span>
                        @endif
                        {{ $item->item_name }}{{ $isEligible && $eligibilityTag ? ' ['.$eligibilityTag.']' : '' }}
                    </span>
                    <span class="shrink-0">₱{{ number_format($item->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between gap-2 text-[10px] text-gray-400">
                    <span class="min-w-0 flex-1 truncate">
                        @if ($item->weightLabel())
                            {{ $item->weightLabel() }}{{ $item->cookingLabel() ? ' · '.$item->cookingLabel() : '' }}
                        @else
                            {{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}
                        @endif
                        @if ($item->quotation)
                            · {{ $item->quotation->quotation_number }}
                            @if ($item->scheduled_for)
                                · {{ __('Scheduled') }} {{ $item->scheduled_for->format('M d, Y g:i A') }}
                            @endif
                        @endif
                    </span>
                </div>
                @foreach ($item->adjustments as $adjustment)
                    <div class="flex justify-between gap-2 text-xs text-red-600 font-semibold">
                        <span>{{ __('CANCELLED') }} — {{ $adjustment->reason_code->label() }} ({{ $adjustment->quantity }}×)</span>
                        <span>-₱{{ number_format($adjustment->reversed_amount, 2) }}</span>
                    </div>
                @endforeach
            @endforeach
            @if ($eligibilityTag && $invoice->discount_eligibility_method === \App\Enums\DiscountEligibilityMethod::ItemBased)
                <p class="text-[10px] text-gray-400 mt-1">[{{ $eligibilityTag }}] = {{ __('personal consumption of qualified customer') }}</p>
            @endif
        </div>

        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1">
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Gross Sales') }}</span><span>{{ number_format($invoice->gross_sales, 2) }}</span></div>

            @if ($invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat)
                @if ($invoice->vatable_sales > 0)
                    <div class="flex justify-between text-xs text-gray-500"><span>{{ __('VATable Sales') }}</span><span>{{ number_format($invoice->vatable_sales, 2) }}</span></div>
                @endif
                @if ($invoice->vat_exempt_sales > 0)
                    <div class="flex justify-between text-xs text-gray-500"><span>{{ __('VAT-Exempt Sales') }}</span><span>{{ number_format($invoice->vat_exempt_sales, 2) }}</span></div>
                @endif
                @if ($invoice->zero_rated_sales > 0)
                    <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Zero-Rated Sales') }}</span><span>{{ number_format($invoice->zero_rated_sales, 2) }}</span></div>
                @endif
                <div class="flex justify-between text-xs text-gray-500"><span>{{ __('VAT (:rate%)', ['rate' => number_format($invoice->tax_rate, 0)]) }}</span><span>{{ number_format($invoice->vat_amount, 2) }}</span></div>
                @if ($invoice->vat_exemption_amount > 0)
                    <div class="flex justify-between text-xs text-gray-500"><span>{{ __('VAT Exemption') }}</span><span>-{{ number_format($invoice->vat_exemption_amount, 2) }}</span></div>
                @endif
            @else
                <div class="text-xs text-gray-500">{{ __('Non-VAT Registered') }}</div>
            @endif

            @if ($invoice->discounts->isNotEmpty())
                {{-- Configurable multi-discount lines — each applied
                     discount shows separately, per the business rule. --}}
                @foreach ($invoice->discounts as $discountLine)
                    <div class="flex justify-between text-xs text-[#8A3330]">
                        <span>{{ $discountLine->rule_name }}</span>
                        <span>-{{ number_format($discountLine->calculated_amount, 2) }}</span>
                    </div>
                @endforeach
            @elseif ($invoice->discount_type)
                <div class="flex justify-between text-xs text-[#8A3330]">
                    <span>{{ $invoice->discount_type->label() }} {{ __('Discount') }}</span>
                    <span>-{{ number_format($invoice->discount_amount, 2) }}</span>
                </div>
            @endif

            @if ($invoice->service_charge_enabled && $invoice->service_charge_amount > 0)
                <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Service Charge (:pct%)', ['pct' => number_format($invoice->service_charge_percent, 0)]) }}</span><span>{{ number_format($invoice->service_charge_amount, 2) }}</span></div>
            @endif

            @if ($invoice->rounding_adjustment != 0)
                <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Rounding Adjustment') }}</span><span>{{ number_format($invoice->rounding_adjustment, 2) }}</span></div>
            @endif

            <div class="flex justify-between font-semibold pt-1 border-t border-dashed border-[#D9CCBA]"><span>{{ __('Total Amount Due') }}</span><span>{{ number_format($invoice->total_amount_due, 2) }}</span></div>
        </div>

        @if ($invoice->discounts->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1 text-xs">
                <p class="font-semibold text-gray-700">{{ __('Discount Details') }}</p>
                @foreach ($invoice->discounts as $discountLine)
                    <div class="flex justify-between"><span class="text-gray-500">{{ $discountLine->rule_name }}</span><span>-{{ number_format($discountLine->calculated_amount, 2) }}</span></div>
                    @if ($discountLine->qualified_name)
                        <div class="flex justify-between"><span class="text-gray-500 pl-3">{{ __('Qualified Customer') }}</span><span>{{ $discountLine->qualified_name }}</span></div>
                    @endif
                    @if ($discountLine->id_number)
                        <div class="flex justify-between"><span class="text-gray-500 pl-3">{{ __('ID Number') }}</span><span>{{ Str::mask($discountLine->id_number, '*', 0, -4) }}</span></div>
                    @endif
                    @if ($discountLine->reason)
                        <div class="flex justify-between gap-2"><span class="text-gray-500 pl-3 shrink-0">{{ __('Reason') }}</span><span class="text-right">{{ $discountLine->reason }}</span></div>
                    @endif
                @endforeach
                <p class="mt-3 pt-3 border-t border-dashed border-[#D9CCBA] text-center text-[10px] text-gray-400">{{ __('Signature: _______________________') }}</p>
            </div>
        @elseif ($invoice->discount_type)
            <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1 text-xs">
                <p class="font-semibold text-gray-700">{{ __('Discount Details') }}</p>
                @if ($invoice->discount_eligibility_method === \App\Enums\DiscountEligibilityMethod::ItemBased)
                    @php
                        $nonEligibleItemNames = $order->items->pluck('item_name')->unique()->diff($eligibleItemNames)->values();
                    @endphp
                    <p class="text-gray-600">
                        {{ $invoice->discount_type->label() }} {{ __("discount applied only to the qualified customer's meal.") }}
                        @if ($nonEligibleItemNames->isNotEmpty())
                            {{ $nonEligibleItemNames->join(', ') }} {{ $nonEligibleItemNames->count() > 1 ? __('were') : __('was') }} {{ __('assigned to the non-qualified diner.') }}
                        @endif
                    </p>
                @endif
                @if ($invoice->discount_qualified_name)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('Qualified Customer') }}</span><span>{{ $invoice->discount_qualified_name }}</span></div>
                @endif
                @if ($invoice->discount_id_number)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('ID Number') }}</span><span>{{ Str::mask($invoice->discount_id_number, '*', 0, -4) }}</span></div>
                @endif
                @if ($invoice->discount_qualified_diners)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('Qualified Diners') }}</span><span>{{ $invoice->discount_qualified_diners }} / {{ $invoice->discount_total_diners ?? '—' }}</span></div>
                @endif
                @if ($invoice->discount_notes)
                    <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">{{ __('Additional Notes') }}</span><span class="text-right">{{ $invoice->discount_notes }}</span></div>
                @endif
                <p class="mt-3 pt-3 border-t border-dashed border-[#D9CCBA] text-center text-[10px] text-gray-400">{{ __('Signature: _______________________') }}</p>
            </div>
        @endif

        @php
            $paymentEntries = $order->payments
                ->where('order_invoice_snapshot_id', $invoice->id)
                ->where('status', \App\Enums\OrderPaymentStatus::Recorded);
        @endphp
        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1">
            @if ($paymentEntries->isNotEmpty())
                {{-- Split-payment breakdown: one block per payment entry. --}}
                @foreach ($paymentEntries as $payment)
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ __('Payment') }} — {{ $payment->displayLabel() }}</span>
                        <span>{{ number_format($payment->amount, 2) }}</span>
                    </div>
                    @if ($payment->terminal_reference)
                        <div class="flex justify-between text-[10px] text-gray-400"><span class="pl-3">{{ __('Terminal Reference') }}</span><span>{{ $payment->terminal_reference }}</span></div>
                    @endif
                    @if ($payment->approval_code)
                        <div class="flex justify-between text-[10px] text-gray-400"><span class="pl-3">{{ __('Approval Code') }}</span><span>{{ $payment->approval_code }}</span></div>
                    @endif
                    @if ($payment->reference)
                        <div class="flex justify-between text-[10px] text-gray-400"><span class="pl-3">{{ __('Reference No.') }}</span><span>{{ $payment->reference }}</span></div>
                    @endif
                    @if ($payment->payment_method === \App\Enums\PaymentMethod::Cash && $payment->tendered_amount !== null)
                        <div class="flex justify-between text-[10px] text-gray-400"><span class="pl-3">{{ __('Cash Tendered') }}</span><span>{{ number_format($payment->tendered_amount, 2) }}</span></div>
                    @endif
                @endforeach
                <div class="flex justify-between text-xs text-gray-500 pt-1 border-t border-dashed border-[#E5DDD0]"><span>{{ __('Change') }}</span><span>{{ number_format($invoice->change_amount, 2) }}</span></div>
            @else
                <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Payment Method') }}</span><span>{{ $invoice->payment_method?->label() }}</span></div>
                @if ($invoice->payment_reference)
                    <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Reference No.') }}</span><span>{{ $invoice->payment_reference }}</span></div>
                @endif
                <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Amount Received') }}</span><span>{{ number_format($invoice->amount_received, 2) }}</span></div>
                <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Change Due') }}</span><span>{{ number_format($invoice->change_amount, 2) }}</span></div>
            @endif
        </div>

        @if (isset($totals) && $totals->hasRefundDue())
            {{-- An item was cancelled AFTER this invoice was issued. The
                 invoice above stands exactly as printed (it is already on
                 permanent record); this block states what is owed back. --}}
            <div class="mt-4 pt-4 border-t-2 border-dashed border-amber-400 space-y-1">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">{{ __('Adjustment After Payment') }}</p>
                <div class="flex justify-between text-xs"><span class="text-gray-500">{{ __('Original Subtotal') }}</span><span>{{ number_format($totals->originalSubtotal, 2) }}</span></div>
                <div class="flex justify-between text-xs text-red-600"><span>{{ __('Cancelled Items') }}</span><span>-{{ number_format($totals->cancelledAmount, 2) }}</span></div>
                <div class="flex justify-between text-xs"><span class="text-gray-500">{{ __('Active Subtotal') }}</span><span>{{ number_format($totals->activeSubtotal, 2) }}</span></div>
                <div class="flex justify-between text-xs"><span class="text-gray-500">{{ __('Corrected Total') }}</span><span>{{ number_format($totals->correctedTotalDue, 2) }}</span></div>
                <div class="flex justify-between text-xs"><span class="text-gray-500">{{ __('Amount Paid') }}</span><span>{{ number_format($totals->amountPaid, 2) }}</span></div>
                <div class="flex justify-between font-bold text-amber-800 pt-1 border-t border-dashed border-amber-300">
                    <span>{{ __('Refund Due') }}</span><span>{{ number_format($totals->refundDue, 2) }}</span>
                </div>
            </div>
        @endif

        @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
            <div class="mt-4 pt-4 border-t border-dashed border-red-300 space-y-1 text-xs">
                <div class="flex justify-between font-semibold text-red-600"><span>{{ __('Status') }}</span><span>{{ __('VOIDED') }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Void By') }}</span><span>{{ $order->voidedBy->name ?? __('Unknown') }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Void Date') }}</span><span>{{ $order->voided_at?->format('M d, Y g:i A') }}</span></div>
                <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">{{ __('Reason') }}</span><span class="text-right">{{ $order->void_reason }}</span></div>
            </div>
        @endif

        <div class="mt-6 pt-4 border-t border-dashed border-[#D9CCBA] text-center text-[10px] text-gray-400 leading-relaxed">
            @if ($invoice->bir_permit_number)
                <p>{{ __('BIR Permit No.') }}: {{ $invoice->bir_permit_number }}</p>
            @endif
            @if ($invoice->atp_ocn_number)
                <p>{{ __('ATP/OCN') }}: {{ $invoice->atp_ocn_number }}{{ $invoice->atp_ocn_date_issued ? ' ('.$invoice->atp_ocn_date_issued->format('M d, Y').')' : '' }}</p>
            @endif
            @if ($invoice->invoice_serial_from && $invoice->invoice_serial_to)
                <p>{{ __('Approved Serial') }}: {{ $invoice->invoice_serial_from }} - {{ $invoice->invoice_serial_to }}</p>
            @endif
            @if ($invoice->tax_registration_type === \App\Enums\TaxRegistrationType::NonVat)
                <p class="mt-2 font-semibold uppercase">{{ __('This document is not valid for claim of input tax') }}</p>
            @endif
        </div>

        <p class="mt-4 text-center text-xs text-gray-400">{{ $invoice->footer_message }}</p>
    @else
        {{-- Pre-existing paid order with no invoice snapshot (paid before
             this feature shipped) — original simple format, unchanged, so
             old receipts keep opening exactly as they always have. --}}
        <div class="text-center">
            @if (file_exists(public_path('images/logo.png')))
                <img src="{{ asset('images/logo.png') }}" alt="88 Hotspring Resort" class="h-14 w-14 rounded-full object-cover mx-auto mb-2">
            @endif
            <p class="font-semibold uppercase tracking-wide">88 Hotspring Resort Inc.</p>
            <p class="text-xs text-gray-500">{{ __('Official Receipt') }}</p>
            <p class="mt-2 text-[11px] text-gray-500 leading-snug">
                #9061 National Highway, Bagong Kalsada,<br>
                Calamba City, 4027 Laguna
            </p>
            <p class="text-[11px] text-gray-500">0917-874-7888 &bull; info@88hotspring.com</p>
        </div>

        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1 text-xs">
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Receipt No.') }}</span><span>{{ $order->receipt_number }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Order No.') }}</span><span>{{ $order->orderNumber() }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Date') }}</span><span>{{ $order->paid_at->format('M d, Y g:i A') }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Location') }}</span><span>{{ $order->locationLabel() }}</span></div>
            @if ($order->customer_name)
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Customer') }}</span><span>{{ $order->customer_name }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-gray-500">{{ __('Cashier') }}</span><span>{{ $order->creator->name ?? __('Unknown') }}</span></div>
        </div>

        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA]">
            @foreach ($order->items as $item)
                <div class="flex justify-between gap-2">
                    <span>{{ $item->quantity }}x {{ $item->item_name }}</span>
                    <span>{{ number_format($item->subtotal, 2) }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 pt-4 border-t border-dashed border-[#D9CCBA] space-y-1">
            <div class="flex justify-between font-semibold"><span>{{ __('Total') }}</span><span>{{ number_format($order->total_amount, 2) }}</span></div>
            <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Payment Method') }}</span><span class="uppercase">{{ $order->payment_method?->label() }}</span></div>
            <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Amount Received') }}</span><span>{{ number_format($order->amount_received, 2) }}</span></div>
            <div class="flex justify-between text-xs text-gray-500"><span>{{ __('Change Due') }}</span><span>{{ number_format($order->change_amount, 2) }}</span></div>
        </div>

        @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
            <div class="mt-4 pt-4 border-t border-dashed border-red-300 space-y-1 text-xs">
                <div class="flex justify-between font-semibold text-red-600"><span>{{ __('Status') }}</span><span>{{ __('VOIDED') }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Void By') }}</span><span>{{ $order->voidedBy->name ?? __('Unknown') }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ __('Void Date') }}</span><span>{{ $order->voided_at?->format('M d, Y g:i A') }}</span></div>
                <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">{{ __('Reason') }}</span><span class="text-right">{{ $order->void_reason }}</span></div>
            </div>
        @endif

        <p class="mt-6 text-center text-xs text-gray-400">{{ __('Thank you for visiting 88 Hotspring Resort!') }}</p>
    @endif
</div>
