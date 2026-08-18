<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Nanum Gothic Coding', 'DejaVu Sans Mono', monospace; font-size: 11px; color: #222; margin: 0; padding: 16px; }
        .center { text-align: center; }
        .name { font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; }
        .muted { color: #777; font-size: 10px; }
        .rule { border-top: 1px dashed #999; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; }
        .label { color: #777; }
        .total-row td { font-weight: bold; padding-top: 6px; border-top: 1px dashed #999; }
        .logo { display: block; margin: 0 auto 6px auto; }
        .footer { text-align: center; color: #999; font-size: 10px; margin-top: 16px; }
        .address { color: #777; font-size: 9px; margin-top: 4px; line-height: 1.4; }
        .invoice-title { font-weight: bold; text-transform: uppercase; font-size: 11px; margin-top: 6px; }
        .voided-banner { text-align: center; color: #c0392b; font-weight: bold; font-size: 13px; letter-spacing: 2px; border: 2px solid #c0392b; padding: 4px; margin-bottom: 10px; }
        .void-details td { color: #c0392b; }
        .void-details .label { color: #999; }
        .discount-row td { color: #8A3330; }
        .advance-tag { color: #8A3330; }
        .disclaimer { text-align: center; font-weight: bold; text-transform: uppercase; font-size: 9px; margin-top: 6px; }
    </style>
</head>
<body>
    @php $invoice = $order->currentInvoiceSnapshot; @endphp

    @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
        <div class="voided-banner">{{ __('VOIDED') }}</div>
    @endif

    @if ($invoice)
        <div class="center">
            @if (file_exists(public_path('images/logo.png')))
                <img class="logo" src="{{ public_path('images/logo.png') }}" width="56" height="56">
            @endif
            <p class="name">{{ $invoice->business_name }}</p>
            @if ($invoice->trade_name && $invoice->trade_name !== $invoice->business_name)
                <p class="muted">{{ __('Trade Name') }}: {{ $invoice->trade_name }}</p>
            @endif
            <p class="address">
                {{ $invoice->business_address }}<br>
                {{ collect([$invoice->contact_number, $invoice->email])->filter()->join(' &bull; ') }}
                @if ($invoice->tin)
                    <br>{{ $invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat ? __('VAT REG TIN') : __('NON-VAT REG TIN') }}: {{ $invoice->tin }}{{ $invoice->branch_code ? ' - '.$invoice->branch_code : '' }}
                @endif
            </p>
            <p class="invoice-title">{{ $invoice->invoice_title }}</p>
        </div>

        <div class="rule"></div>

        <table>
            <tr><td class="label">{{ __('Invoice No.') }}</td><td class="right">{{ $invoice->invoice_number }}</td></tr>
            <tr><td class="label">{{ __('Order No.') }}</td><td class="right">{{ $order->orderNumber() }}</td></tr>
            <tr><td class="label">{{ __('Date') }}</td><td class="right">{{ $invoice->computed_at->format('M d, Y g:i A') }}</td></tr>
            <tr><td class="label">{{ __('Location') }}</td><td class="right">{{ $order->locationLabel() }}</td></tr>
            @if ($order->customer_name)
                <tr><td class="label">{{ __('Customer') }}</td><td class="right">{{ $order->customer_name }}</td></tr>
            @endif
            @if ($order->covers_count)
                <tr><td class="label">{{ __('Covers') }}</td><td class="right">{{ $order->covers_count }}</td></tr>
            @endif
            <tr><td class="label">{{ __('Order Type') }}</td><td class="right">{{ $order->order_type->label() }}</td></tr>
            @if ($order->batch_number)
                <tr><td class="label">{{ __('Batch') }}</td><td class="right">#{{ $order->batch_number }}{{ $order->guestSession ? ' - '.$order->guestSession->displayLabel() : '' }}</td></tr>
            @endif
            <tr><td class="label">{{ __('Cashier') }}</td><td class="right">{{ $invoice->computedBy->name ?? $order->creator->name ?? __('Unknown') }}</td></tr>
        </table>

        @if ($invoice->buyer_name)
            <div class="rule"></div>
            <table>
                <tr><td class="label">{{ __('Buyer') }}</td><td class="right">{{ $invoice->buyer_name }}</td></tr>
                @if ($invoice->buyer_tin)
                    <tr><td class="label">{{ __('Buyer TIN') }}</td><td class="right">{{ $invoice->buyer_tin }}</td></tr>
                @endif
                @if ($invoice->buyer_address)
                    <tr><td class="label">{{ __('Buyer Address') }}</td><td class="right">{{ $invoice->buyer_address }}</td></tr>
                @endif
            </table>
        @endif

        <div class="rule"></div>

        <table>
            @php
                $eligibilityTag = match ($invoice->discount_type) {
                    \App\Enums\DiscountType::SeniorCitizen => 'SC',
                    \App\Enums\DiscountType::Pwd => 'PWD',
                    default => null,
                };
                $eligibleItemNames = collect($invoice->discount_eligible_item_names ?? []);
            @endphp
            @foreach ($order->items as $item)
                @php $isEligible = $eligibleItemNames->contains($item->item_name); @endphp
                <tr>
                    <td>
                        @if ($item->quotation)
                            <span class="advance-tag">{{ __('Advance Order') }} - </span>
                        @endif
                        {{ $item->item_name }}{{ $isEligible && $eligibilityTag ? ' ['.$eligibilityTag.']' : '' }}
                    </td>
                    <td class="right">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="muted" colspan="2">
                        @if ($item->weightLabel())
                            {{ $item->weightLabel() }}{{ $item->cookingLabel() ? ' - '.$item->cookingLabel() : '' }}
                        @else
                            {{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}
                        @endif
                        @if ($item->quotation)
                            &bull; {{ $item->quotation->quotation_number }}
                            @if ($item->scheduled_for)
                                &bull; {{ __('Scheduled') }} {{ $item->scheduled_for->format('M d, Y g:i A') }}
                            @endif
                        @endif
                    </td>
                </tr>
                @foreach ($item->adjustments as $adjustment)
                    <tr class="void-details">
                        <td>{{ __('CANCELLED') }} &mdash; {{ $adjustment->reason_code->label() }} ({{ $adjustment->quantity }}&times;)</td>
                        <td class="right">-{{ number_format($adjustment->reversed_amount, 2) }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if ($eligibilityTag && $invoice->discount_eligibility_method === \App\Enums\DiscountEligibilityMethod::ItemBased)
                <tr><td colspan="2" class="muted">[{{ $eligibilityTag }}] = {{ __('personal consumption of qualified customer') }}</td></tr>
            @endif
        </table>

        <div class="rule"></div>

        <table>
            <tr><td class="label">{{ __('Gross Sales') }}</td><td class="right">{{ number_format($invoice->gross_sales, 2) }}</td></tr>

            @if ($invoice->tax_registration_type === \App\Enums\TaxRegistrationType::Vat)
                @if ($invoice->vatable_sales > 0)
                    <tr><td class="label">{{ __('VATable Sales') }}</td><td class="right">{{ number_format($invoice->vatable_sales, 2) }}</td></tr>
                @endif
                @if ($invoice->vat_exempt_sales > 0)
                    <tr><td class="label">{{ __('VAT-Exempt Sales') }}</td><td class="right">{{ number_format($invoice->vat_exempt_sales, 2) }}</td></tr>
                @endif
                @if ($invoice->zero_rated_sales > 0)
                    <tr><td class="label">{{ __('Zero-Rated Sales') }}</td><td class="right">{{ number_format($invoice->zero_rated_sales, 2) }}</td></tr>
                @endif
                <tr><td class="label">{{ __('VAT (:rate%)', ['rate' => number_format($invoice->tax_rate, 0)]) }}</td><td class="right">{{ number_format($invoice->vat_amount, 2) }}</td></tr>
                @if ($invoice->vat_exemption_amount > 0)
                    <tr><td class="label">{{ __('VAT Exemption') }}</td><td class="right">-{{ number_format($invoice->vat_exemption_amount, 2) }}</td></tr>
                @endif
            @else
                <tr><td colspan="2" class="muted">{{ __('Non-VAT Registered') }}</td></tr>
            @endif

            @if ($invoice->discounts->isNotEmpty())
                @foreach ($invoice->discounts as $discountLine)
                    <tr class="discount-row"><td>{{ $discountLine->rule_name }}</td><td class="right">-{{ number_format($discountLine->calculated_amount, 2) }}</td></tr>
                @endforeach
            @elseif ($invoice->discount_type)
                <tr class="discount-row"><td>{{ $invoice->discount_type->label() }} {{ __('Discount') }}</td><td class="right">-{{ number_format($invoice->discount_amount, 2) }}</td></tr>
            @endif

            @if ($invoice->service_charge_enabled && $invoice->service_charge_amount > 0)
                <tr><td class="label">{{ __('Service Charge (:pct%)', ['pct' => number_format($invoice->service_charge_percent, 0)]) }}</td><td class="right">{{ number_format($invoice->service_charge_amount, 2) }}</td></tr>
            @endif

            @if ($invoice->rounding_adjustment != 0)
                <tr><td class="label">{{ __('Rounding Adjustment') }}</td><td class="right">{{ number_format($invoice->rounding_adjustment, 2) }}</td></tr>
            @endif

            <tr class="total-row"><td>{{ __('Total Amount Due') }}</td><td class="right">{{ number_format($invoice->total_amount_due, 2) }}</td></tr>
        </table>

        @if ($invoice->discounts->isNotEmpty())
            <div class="rule"></div>
            <table>
                <tr><td colspan="2"><strong>{{ __('Discount Details') }}</strong></td></tr>
                @foreach ($invoice->discounts as $discountLine)
                    <tr><td class="label">{{ $discountLine->rule_name }}</td><td class="right">-{{ number_format($discountLine->calculated_amount, 2) }}</td></tr>
                    @if ($discountLine->qualified_name)
                        <tr><td class="label" style="padding-left: 10px;">{{ __('Qualified Customer') }}</td><td class="right">{{ $discountLine->qualified_name }}</td></tr>
                    @endif
                    @if ($discountLine->id_number)
                        <tr><td class="label" style="padding-left: 10px;">{{ __('ID Number') }}</td><td class="right">{{ \App\Models\Setting::current()->reveal_full_discount_id_on_pdf ? $discountLine->id_number : \Illuminate\Support\Str::mask($discountLine->id_number, '*', 0, -4) }}</td></tr>
                    @endif
                    @if ($discountLine->reason)
                        <tr><td class="label" style="padding-left: 10px;">{{ __('Reason') }}</td><td class="right">{{ $discountLine->reason }}</td></tr>
                    @endif
                @endforeach
            </table>
            <p class="muted center" style="margin-top: 10px;">{{ __('Signature: _______________________') }}</p>
        @elseif ($invoice->discount_type)
            <div class="rule"></div>
            <table>
                <tr><td colspan="2"><strong>{{ __('Discount Details') }}</strong></td></tr>
                @if ($invoice->discount_eligibility_method === \App\Enums\DiscountEligibilityMethod::ItemBased)
                    @php
                        $nonEligibleItemNames = $order->items->pluck('item_name')->unique()->diff($eligibleItemNames)->values();
                    @endphp
                    <tr>
                        <td colspan="2" class="muted">
                            {{ $invoice->discount_type->label() }} {{ __("discount applied only to the qualified customer's meal.") }}
                            @if ($nonEligibleItemNames->isNotEmpty())
                                {{ $nonEligibleItemNames->join(', ') }} {{ $nonEligibleItemNames->count() > 1 ? __('were') : __('was') }} {{ __('assigned to the non-qualified diner.') }}
                            @endif
                        </td>
                    </tr>
                @endif
                @if ($invoice->discount_qualified_name)
                    <tr><td class="label">{{ __('Qualified Customer') }}</td><td class="right">{{ $invoice->discount_qualified_name }}</td></tr>
                @endif
                @if ($invoice->discount_id_number)
                    <tr><td class="label">{{ __('ID Number') }}</td><td class="right">{{ \App\Models\Setting::current()->reveal_full_discount_id_on_pdf ? $invoice->discount_id_number : \Illuminate\Support\Str::mask($invoice->discount_id_number, '*', 0, -4) }}</td></tr>
                @endif
                @if ($invoice->discount_qualified_diners)
                    <tr><td class="label">{{ __('Qualified Diners') }}</td><td class="right">{{ $invoice->discount_qualified_diners }} / {{ $invoice->discount_total_diners ?? '—' }}</td></tr>
                @endif
                @if ($invoice->discount_notes)
                    <tr><td class="label">{{ __('Additional Notes') }}</td><td class="right">{{ $invoice->discount_notes }}</td></tr>
                @endif
            </table>
            <p class="muted center" style="margin-top: 10px;">{{ __('Signature: _______________________') }}</p>
        @endif

        <div class="rule"></div>

        @php
            $paymentEntries = $order->payments
                ->where('order_invoice_snapshot_id', $invoice->id)
                ->where('status', \App\Enums\OrderPaymentStatus::Recorded);
        @endphp
        <table>
            @if ($paymentEntries->isNotEmpty())
                @foreach ($paymentEntries as $payment)
                    <tr><td class="label">{{ __('Payment') }} &mdash; {{ $payment->displayLabel() }}</td><td class="right">{{ number_format($payment->amount, 2) }}</td></tr>
                    @if ($payment->terminal_reference)
                        <tr><td class="muted" style="padding-left: 10px;">{{ __('Terminal Reference') }}</td><td class="right muted">{{ $payment->terminal_reference }}</td></tr>
                    @endif
                    @if ($payment->approval_code)
                        <tr><td class="muted" style="padding-left: 10px;">{{ __('Approval Code') }}</td><td class="right muted">{{ $payment->approval_code }}</td></tr>
                    @endif
                    @if ($payment->reference)
                        <tr><td class="muted" style="padding-left: 10px;">{{ __('Reference No.') }}</td><td class="right muted">{{ $payment->reference }}</td></tr>
                    @endif
                    @if ($payment->payment_method === \App\Enums\PaymentMethod::Cash && $payment->tendered_amount !== null)
                        <tr><td class="muted" style="padding-left: 10px;">{{ __('Cash Tendered') }}</td><td class="right muted">{{ number_format($payment->tendered_amount, 2) }}</td></tr>
                    @endif
                @endforeach
                <tr><td class="label">{{ __('Change') }}</td><td class="right">{{ number_format($invoice->change_amount, 2) }}</td></tr>
            @else
                <tr><td class="label">{{ __('Payment Method') }}</td><td class="right">{{ strtoupper($order->payment_method?->label() ?? '') }}</td></tr>
                @if ($order->payment_reference)
                    <tr><td class="label">{{ __('Reference No.') }}</td><td class="right">{{ $order->payment_reference }}</td></tr>
                @endif
                <tr><td class="label">{{ __('Amount Received') }}</td><td class="right">{{ number_format($order->amount_received, 2) }}</td></tr>
                <tr><td class="label">{{ __('Change Due') }}</td><td class="right">{{ number_format($order->change_amount, 2) }}</td></tr>
            @endif
        </table>

        @if (isset($totals) && $totals->hasRefundDue())
            {{-- Items cancelled AFTER this invoice was issued. The invoice
                 above is never rewritten; this states what is owed back. --}}
            <div class="rule"></div>
            <table>
                <tr><td colspan="2"><strong>{{ __('Adjustment After Payment') }}</strong></td></tr>
                <tr><td class="label">{{ __('Original Subtotal') }}</td><td class="right">{{ number_format($totals->originalSubtotal, 2) }}</td></tr>
                <tr><td class="label">{{ __('Cancelled Items') }}</td><td class="right">-{{ number_format($totals->cancelledAmount, 2) }}</td></tr>
                <tr><td class="label">{{ __('Active Subtotal') }}</td><td class="right">{{ number_format($totals->activeSubtotal, 2) }}</td></tr>
                <tr><td class="label">{{ __('Corrected Total') }}</td><td class="right">{{ number_format($totals->correctedTotalDue, 2) }}</td></tr>
                <tr><td class="label">{{ __('Amount Paid') }}</td><td class="right">{{ number_format($totals->amountPaid, 2) }}</td></tr>
                <tr><td><strong>{{ __('Refund Due') }}</strong></td><td class="right"><strong>{{ number_format($totals->refundDue, 2) }}</strong></td></tr>
            </table>
        @endif

        @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
            <div class="rule"></div>
            <table class="void-details">
                <tr><td class="label">{{ __('Status') }}</td><td class="right">{{ __('VOIDED') }}</td></tr>
                <tr><td class="label">{{ __('Void By') }}</td><td class="right">{{ $order->voidedBy->name ?? __('Unknown') }}</td></tr>
                <tr><td class="label">{{ __('Void Date') }}</td><td class="right">{{ $order->voided_at?->format('M d, Y g:i A') }}</td></tr>
                <tr><td class="label">{{ __('Reason') }}</td><td class="right">{{ $order->void_reason }}</td></tr>
            </table>
        @endif

        <div class="rule"></div>
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
            <p>{{ $invoice->footer_message }}</p>
        </div>
    @else
        {{-- Pre-existing paid order with no invoice snapshot — original
             simple format, unchanged, so old receipts keep downloading
             exactly as they always have. --}}
        <div class="center">
            @if (file_exists(public_path('images/logo.png')))
                <img class="logo" src="{{ public_path('images/logo.png') }}" width="56" height="56">
            @endif
            <p class="name">88 Hotspring Resort Inc.</p>
            <p class="muted">{{ __('Official Receipt') }}</p>
            <p class="address">
                #9061 National Highway, Bagong Kalsada,<br>
                Calamba City, 4027 Laguna<br>
                0917-874-7888 &bull; info@88hotspring.com
            </p>
        </div>

        <div class="rule"></div>

        <table>
            <tr><td class="label">{{ __('Receipt No.') }}</td><td class="right">{{ $order->receipt_number }}</td></tr>
            <tr><td class="label">{{ __('Order No.') }}</td><td class="right">{{ $order->orderNumber() }}</td></tr>
            <tr><td class="label">{{ __('Date') }}</td><td class="right">{{ $order->paid_at->format('M d, Y g:i A') }}</td></tr>
            <tr><td class="label">{{ __('Location') }}</td><td class="right">{{ $order->locationLabel() }}</td></tr>
            @if ($order->customer_name)
                <tr><td class="label">{{ __('Customer') }}</td><td class="right">{{ $order->customer_name }}</td></tr>
            @endif
            <tr><td class="label">{{ __('Cashier') }}</td><td class="right">{{ $order->creator->name ?? __('Unknown') }}</td></tr>
        </table>

        <div class="rule"></div>

        <table>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->quantity }}x {{ $item->item_name }}</td>
                    <td class="right">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </table>

        <div class="rule"></div>

        <table>
            <tr class="total-row"><td>{{ __('Total') }}</td><td class="right">{{ number_format($order->total_amount, 2) }}</td></tr>
            <tr><td class="label">{{ __('Payment Method') }}</td><td class="right">{{ strtoupper($order->payment_method?->label() ?? '') }}</td></tr>
            <tr><td class="label">{{ __('Amount Received') }}</td><td class="right">{{ number_format($order->amount_received, 2) }}</td></tr>
            <tr><td class="label">{{ __('Change Due') }}</td><td class="right">{{ number_format($order->change_amount, 2) }}</td></tr>
        </table>

        @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
            <div class="rule"></div>
            <table class="void-details">
                <tr><td class="label">{{ __('Status') }}</td><td class="right">{{ __('VOIDED') }}</td></tr>
                <tr><td class="label">{{ __('Void By') }}</td><td class="right">{{ $order->voidedBy->name ?? __('Unknown') }}</td></tr>
                <tr><td class="label">{{ __('Void Date') }}</td><td class="right">{{ $order->voided_at?->format('M d, Y g:i A') }}</td></tr>
                <tr><td class="label">{{ __('Reason') }}</td><td class="right">{{ $order->void_reason }}</td></tr>
            </table>
        @endif

        <p class="footer">{{ __('Thank you for visiting 88 Hotspring Resort!') }}</p>
    @endif
</body>
</html>
