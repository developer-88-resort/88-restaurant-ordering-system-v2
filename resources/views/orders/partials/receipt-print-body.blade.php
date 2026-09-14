{{--
    Narrow-width-safe receipt content — plain flex rows in a fixed-width
    monospace layout, not Tailwind. This is what actually reaches thermal
    paper: Tailwind's flexible/responsive classes (used by receipt-body.blade.php
    for the on-screen card) don't degrade safely when the browser is forced
    to lay them out at a genuinely narrow physical width (58/80mm) — labels
    and values run together with no line break. Shared between
    orders/receipt-print.blade.php (the standalone "Thermal Print" page) and
    orders/receipt.blade.php (the print-media view of the main receipt page)
    so the two can never drift apart — same $order/$totals, same output.
--}}
@if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
    <div class="voided-banner">{{ __('VOIDED') }}</div>
@endif

@php $invoice = $order->currentInvoiceSnapshot; @endphp

@if ($invoice)
    {{-- BIR invoice layout — every field below is read from the frozen
         snapshot, never from live Settings, so a later settings/tax-rate
         change never rewrites an already-issued invoice. Mirrors
         orders/partials/receipt-body.blade.php exactly, just in
         thermal-width markup instead of Tailwind. --}}
    <div class="center">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="{{ $invoice->business_name }}" class="logo">
        @endif
        <p class="name">{{ $invoice->business_name }}</p>
        @if ($invoice->trade_name && $invoice->trade_name !== $invoice->business_name)
            <p class="muted small">{{ __('Trade Name') }}: {{ $invoice->trade_name }}</p>
        @endif
        @if ($invoice->business_address)
            <p class="muted small">{{ $invoice->business_address }}</p>
        @endif
        @if ($invoice->contact_number || $invoice->email)
            <p class="muted small">{{ collect([$invoice->contact_number, $invoice->email])->filter()->join(' • ') }}</p>
        @endif
        @if ($invoice->tin)
            <p class="muted small">
                {{ $invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat ? __('VAT REG TIN') : __('NON-VAT REG TIN') }}:
                {{ $invoice->tin }}{{ $invoice->branch_code ? ' - '.$invoice->branch_code : '' }}
            </p>
        @endif
        <p class="name small" style="margin-top: 4px;">{{ $invoice->invoice_title }}</p>
    </div>

    <div class="section">
        <div class="row"><span class="label">{{ __('Invoice No.') }}</span><span class="value">{{ $invoice->invoice_number }}</span></div>
        <div class="row"><span class="label">{{ __('Order No.') }}</span><span class="value">{{ $order->orderNumber() }}</span></div>
        <div class="row"><span class="label">{{ __('Date') }}</span><span class="value">{{ $invoice->computed_at->format('M d, Y g:i A') }}</span></div>
        <div class="row"><span class="label">{{ __('Location') }}</span><span class="value">{{ $order->locationLabel() }}</span></div>
        @if ($order->customer_name)
            <div class="row"><span class="label">{{ __('Customer') }}</span><span class="value">{{ $order->customer_name }}</span></div>
        @endif
        @if ($order->covers_count)
            <div class="row"><span class="label">{{ __('Covers') }}</span><span class="value">{{ $order->covers_count }}</span></div>
        @endif
        <div class="row"><span class="label">{{ __('Order Type') }}</span><span class="value">{{ $order->order_type->label() }}</span></div>
        @if ($order->batch_number)
            <div class="row"><span class="label">{{ __('Batch') }}</span><span class="value">#{{ $order->batch_number }}{{ $order->guestSession ? ' · '.$order->guestSession->displayLabel() : '' }}</span></div>
        @endif
        <div class="row"><span class="label">{{ __('Cashier') }}</span><span class="value">{{ $invoice->computedBy->name ?? $order->creator->name ?? __('Unknown') }}</span></div>
    </div>

    @if ($invoice->buyer_name)
        <div class="section">
            <div class="row"><span class="label">{{ __('Buyer') }}</span><span class="value">{{ $invoice->buyer_name }}</span></div>
            @if ($invoice->buyer_tin)
                <div class="row"><span class="label">{{ __('Buyer TIN') }}</span><span class="value">{{ $invoice->buyer_tin }}</span></div>
            @endif
            @if ($invoice->buyer_address)
                <div class="row"><span class="label">{{ __('Buyer Address') }}</span><span class="value">{{ $invoice->buyer_address }}</span></div>
            @endif
        </div>
    @endif

    @php
        $eligibilityTag = match ($invoice->discount_type) {
            \App\Enums\DiscountType::SeniorCitizen => 'SC',
            \App\Enums\DiscountType::Pwd => 'PWD',
            default => null,
        };
        // Frozen snapshot list, not the live order_items flag — see
        // orders/partials/receipt-body.blade.php for why.
        $eligibleItemNames = collect($invoice->discount_eligible_item_names ?? []);
    @endphp
    <div class="section">
        @foreach ($order->items as $item)
            @php $isEligible = $eligibleItemNames->contains($item->item_name); @endphp
            <div class="row">
                <span class="label item-name">
                    @if ($item->quotation)
                        {{ __('Advance Order') }} -
                    @endif
                    {{ $item->item_name }}{{ $isEligible && $eligibilityTag ? ' ['.$eligibilityTag.']' : '' }}
                </span>
                <span class="value">{{ number_format($item->subtotal, 2) }}</span>
            </div>
            <div class="item-sub">
                <span>
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
                <div class="item-cancelled">
                    <span>{{ __('CANCELLED') }} — {{ $adjustment->reason_code->label() }} ({{ $adjustment->quantity }}×)</span>
                    <span>-{{ number_format($adjustment->reversed_amount, 2) }}</span>
                </div>
            @endforeach
        @endforeach
        @if ($eligibilityTag && $invoice->discount_eligibility_method === \App\Enums\DiscountEligibilityMethod::ItemBased)
            <p class="muted small" style="margin-top: 4px;">[{{ $eligibilityTag }}] = {{ __('personal consumption of qualified customer') }}</p>
        @endif
    </div>

    <div class="section">
        <div class="row"><span class="label">{{ __('Gross Sales') }}</span><span class="value">{{ number_format($invoice->gross_sales, 2) }}</span></div>

        @if ($invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat)
            @if ($invoice->vatable_sales > 0)
                <div class="row small"><span class="label">{{ __('VATable Sales') }}</span><span class="value">{{ number_format($invoice->vatable_sales, 2) }}</span></div>
            @endif
            @if ($invoice->vat_exempt_sales > 0)
                <div class="row small"><span class="label">{{ __('VAT-Exempt Sales') }}</span><span class="value">{{ number_format($invoice->vat_exempt_sales, 2) }}</span></div>
            @endif
            @if ($invoice->zero_rated_sales > 0)
                <div class="row small"><span class="label">{{ __('Zero-Rated Sales') }}</span><span class="value">{{ number_format($invoice->zero_rated_sales, 2) }}</span></div>
            @endif
            <div class="row small"><span class="label">{{ __('VAT (:rate%)', ['rate' => number_format($invoice->tax_rate, 0)]) }}</span><span class="value">{{ number_format($invoice->vat_amount, 2) }}</span></div>
            @if ($invoice->vat_exemption_amount > 0)
                <div class="row small"><span class="label">{{ __('VAT Exemption') }}</span><span class="value">-{{ number_format($invoice->vat_exemption_amount, 2) }}</span></div>
            @endif
        @else
            <p class="muted small">{{ __('Non-VAT Registered') }}</p>
        @endif

        @if ($invoice->discounts->isNotEmpty())
            {{-- Configurable multi-discount lines — each applied
                 discount shows separately, per the business rule. --}}
            @foreach ($invoice->discounts as $discountLine)
                <div class="row small"><span class="label">{{ $discountLine->rule_name }}</span><span class="value">-{{ number_format($discountLine->calculated_amount, 2) }}</span></div>
            @endforeach
        @elseif ($invoice->discount_type)
            <div class="row small"><span class="label">{{ $invoice->discount_type->label() }} {{ __('Discount') }}</span><span class="value">-{{ number_format($invoice->discount_amount, 2) }}</span></div>
        @endif

        @if ($invoice->service_charge_enabled && $invoice->service_charge_amount > 0)
            <div class="row small"><span class="label">{{ __('Service Charge (:pct%)', ['pct' => number_format($invoice->service_charge_percent, 0)]) }}</span><span class="value">{{ number_format($invoice->service_charge_amount, 2) }}</span></div>
        @endif

        @if ($invoice->rounding_adjustment != 0)
            <div class="row small"><span class="label">{{ __('Rounding Adjustment') }}</span><span class="value">{{ number_format($invoice->rounding_adjustment, 2) }}</span></div>
        @endif

        <div class="total-row"><span>{{ __('Total Amount Due') }}</span><span>{{ number_format($invoice->total_amount_due, 2) }}</span></div>
    </div>

    @if ($invoice->discounts->isNotEmpty())
        <div class="section">
            <p style="font-weight: bold;">{{ __('Discount Details') }}</p>
            @foreach ($invoice->discounts as $discountLine)
                <div class="row small"><span class="label">{{ $discountLine->rule_name }}</span><span class="value">-{{ number_format($discountLine->calculated_amount, 2) }}</span></div>
                @if ($discountLine->qualified_name)
                    <div class="row small"><span class="label">{{ __('Qualified Customer') }}</span><span class="value">{{ $discountLine->qualified_name }}</span></div>
                @endif
                @if ($discountLine->id_number)
                    <div class="row small"><span class="label">{{ __('ID Number') }}</span><span class="value">{{ Str::mask($discountLine->id_number, '*', 0, -4) }}</span></div>
                @endif
                @if ($discountLine->reason)
                    <div class="row small"><span class="label">{{ __('Reason') }}</span><span class="value">{{ $discountLine->reason }}</span></div>
                @endif
            @endforeach
            <p class="muted small center" style="margin-top: 8px;">{{ __('Signature: _______________________') }}</p>
        </div>
    @elseif ($invoice->discount_type)
        <div class="section">
            <p style="font-weight: bold;">{{ __('Discount Details') }}</p>
            @if ($invoice->discount_eligibility_method === \App\Enums\DiscountEligibilityMethod::ItemBased)
                @php
                    $nonEligibleItemNames = $order->items->pluck('item_name')->unique()->diff($eligibleItemNames)->values();
                @endphp
                <p class="muted small">
                    {{ $invoice->discount_type->label() }} {{ __("discount applied only to the qualified customer's meal.") }}
                    @if ($nonEligibleItemNames->isNotEmpty())
                        {{ $nonEligibleItemNames->join(', ') }} {{ $nonEligibleItemNames->count() > 1 ? __('were') : __('was') }} {{ __('assigned to the non-qualified diner.') }}
                    @endif
                </p>
            @endif
            @if ($invoice->discount_qualified_name)
                <div class="row small"><span class="label">{{ __('Qualified Customer') }}</span><span class="value">{{ $invoice->discount_qualified_name }}</span></div>
            @endif
            @if ($invoice->discount_id_number)
                <div class="row small"><span class="label">{{ __('ID Number') }}</span><span class="value">{{ Str::mask($invoice->discount_id_number, '*', 0, -4) }}</span></div>
            @endif
            @if ($invoice->discount_qualified_diners)
                <div class="row small"><span class="label">{{ __('Qualified Diners') }}</span><span class="value">{{ $invoice->discount_qualified_diners }} / {{ $invoice->discount_total_diners ?? '—' }}</span></div>
            @endif
            @if ($invoice->discount_notes)
                <div class="row small"><span class="label">{{ __('Notes') }}</span><span class="value">{{ $invoice->discount_notes }}</span></div>
            @endif
            <p class="muted small center" style="margin-top: 8px;">{{ __('Signature: _______________________') }}</p>
        </div>
    @endif

    @php
        $paymentEntries = $order->payments
            ->where('order_invoice_snapshot_id', $invoice->id)
            ->where('status', \App\Enums\OrderPaymentStatus::Recorded);
    @endphp
    <div class="section">
        @if ($paymentEntries->isNotEmpty())
            {{-- Split-payment breakdown: one block per payment entry. --}}
            @foreach ($paymentEntries as $payment)
                <div class="row small"><span class="label">{{ __('Payment') }} — {{ $payment->displayLabel() }}</span><span class="value">{{ number_format($payment->amount, 2) }}</span></div>
                @if ($payment->terminal_reference)
                    <div class="row small"><span class="label">{{ __('Terminal Reference') }}</span><span class="value">{{ $payment->terminal_reference }}</span></div>
                @endif
                @if ($payment->approval_code)
                    <div class="row small"><span class="label">{{ __('Approval Code') }}</span><span class="value">{{ $payment->approval_code }}</span></div>
                @endif
                @if ($payment->reference)
                    <div class="row small"><span class="label">{{ __('Reference No.') }}</span><span class="value">{{ $payment->reference }}</span></div>
                @endif
                @if ($payment->payment_method === \App\Enums\PaymentMethod::Cash && $payment->tendered_amount !== null)
                    <div class="row small"><span class="label">{{ __('Cash Tendered') }}</span><span class="value">{{ number_format($payment->tendered_amount, 2) }}</span></div>
                @endif
            @endforeach
            <div class="row small" style="border-top: 1px dashed #D9CCBA; padding-top: 4px;"><span class="label">{{ __('Change') }}</span><span class="value">{{ number_format($invoice->change_amount, 2) }}</span></div>
        @else
            <div class="row small"><span class="label">{{ __('Payment Method') }}</span><span class="value">{{ $invoice->payment_method?->label() }}</span></div>
            @if ($invoice->payment_reference)
                <div class="row small"><span class="label">{{ __('Reference No.') }}</span><span class="value">{{ $invoice->payment_reference }}</span></div>
            @endif
            <div class="row small"><span class="label">{{ __('Amount Received') }}</span><span class="value">{{ number_format($invoice->amount_received, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Change Due') }}</span><span class="value">{{ number_format($invoice->change_amount, 2) }}</span></div>
        @endif
    </div>

    @if (isset($totals) && $totals?->hasRefundDue())
        {{-- An item was cancelled AFTER this invoice was issued. The
             invoice above stands exactly as printed (it is already on
             permanent record); this block states what is owed back. --}}
        <div class="section">
            <p class="refund-banner">{{ __('Adjustment After Payment') }}</p>
            <div class="row small"><span class="label">{{ __('Original Subtotal') }}</span><span class="value">{{ number_format($totals->originalSubtotal, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Cancelled Items') }}</span><span class="value">-{{ number_format($totals->cancelledAmount, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Active Subtotal') }}</span><span class="value">{{ number_format($totals->activeSubtotal, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Corrected Total') }}</span><span class="value">{{ number_format($totals->correctedTotalDue, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Amount Paid') }}</span><span class="value">{{ number_format($totals->amountPaid, 2) }}</span></div>
            <div class="total-row"><span>{{ __('Refund Due') }}</span><span>{{ number_format($totals->refundDue, 2) }}</span></div>
        </div>
    @endif

    @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
        <div class="section">
            <div class="row small"><span class="label">{{ __('Void By') }}</span><span class="value">{{ $order->voidedBy->name ?? __('Unknown') }}</span></div>
            <div class="row small"><span class="label">{{ __('Void Date') }}</span><span class="value">{{ $order->voided_at?->format('M d, Y g:i A') }}</span></div>
            <div class="row small"><span class="label">{{ __('Reason') }}</span><span class="value">{{ $order->void_reason }}</span></div>
        </div>
    @endif

    <div class="footer">
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
            <p class="disclaimer">{{ __('This document is not valid for claim of input tax') }}</p>
        @endif
        <p style="margin-top: 4px;">{{ $invoice->footer_message }}</p>
    </div>
@else
    {{-- Pre-existing paid order with no invoice snapshot (paid before
         this feature shipped) — original simple format, unchanged, so
         old receipts keep printing exactly as they always have. --}}
    <div class="center">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="88 Hotspring Resort" class="logo">
        @endif
        <p class="name">88 Hotspring Resort Inc.</p>
        <p class="muted small">{{ __('Official Receipt') }}</p>
        <p class="muted small">#9061 National Highway, Bagong Kalsada, Calamba City, 4027 Laguna</p>
        <p class="muted small">0917-874-7888 &bull; info@88hotspring.com</p>
    </div>

    <div class="section">
        <div class="row"><span class="label">{{ __('Receipt No.') }}</span><span class="value">{{ $order->receipt_number }}</span></div>
        <div class="row"><span class="label">{{ __('Order No.') }}</span><span class="value">{{ $order->orderNumber() }}</span></div>
        <div class="row"><span class="label">{{ __('Date') }}</span><span class="value">{{ $order->paid_at->format('M d, Y g:i A') }}</span></div>
        <div class="row"><span class="label">{{ __('Location') }}</span><span class="value">{{ $order->locationLabel() }}</span></div>
        @if ($order->customer_name)
            <div class="row"><span class="label">{{ __('Customer') }}</span><span class="value">{{ $order->customer_name }}</span></div>
        @endif
        <div class="row"><span class="label">{{ __('Cashier') }}</span><span class="value">{{ $order->creator->name ?? __('Unknown') }}</span></div>
    </div>

    <div class="section">
        @foreach ($order->items as $item)
            <div class="row">
                <span class="label item-name">{{ $item->quantity }}x {{ $item->item_name }}</span>
                <span class="value">{{ number_format($item->subtotal, 2) }}</span>
            </div>
        @endforeach
    </div>

    <div class="section">
        <div class="total-row"><span>{{ __('Total') }}</span><span>{{ number_format($order->total_amount, 2) }}</span></div>
        <div class="row small" style="margin-top: 4px;"><span class="label">{{ __('Payment Method') }}</span><span class="value">{{ $order->payment_method?->label() }}</span></div>
        <div class="row small"><span class="label">{{ __('Amount Received') }}</span><span class="value">{{ number_format($order->amount_received, 2) }}</span></div>
        <div class="row small"><span class="label">{{ __('Change Due') }}</span><span class="value">{{ number_format($order->change_amount, 2) }}</span></div>
    </div>

    @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
        <div class="section">
            <div class="row small"><span class="label">{{ __('Void By') }}</span><span class="value">{{ $order->voidedBy->name ?? __('Unknown') }}</span></div>
            <div class="row small"><span class="label">{{ __('Void Date') }}</span><span class="value">{{ $order->voided_at?->format('M d, Y g:i A') }}</span></div>
            <div class="row small"><span class="label">{{ __('Reason') }}</span><span class="value">{{ $order->void_reason }}</span></div>
        </div>
    @endif

    <p class="footer">{{ __('Thank you for visiting 88 Hotspring Resort!') }}</p>
@endif
