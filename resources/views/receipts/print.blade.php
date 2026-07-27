<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $order->receipt_number }}</title>
    <style>
        /*
         * @page controls how the browser's print dialog paginates this
         * document. `size: {width} auto` tells it to treat the page as a
         * continuous receipt strip (thermal printers feed paper of a fixed
         * width but arbitrary/auto length) rather than a fixed A4/Letter
         * sheet. This is set dynamically below from $paperWidth since the
         * exact printer (58mm vs 80mm) wasn't known ahead of the client's
         * on-site test.
         */
        @page {
            size: {{ $paperWidth }} auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            width: {{ $paperWidth }};
            margin: 0 auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $paperWidth === '58mm' ? '10px' : '12px' }};
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 6px 8px 16px;
        }

        p {
            margin: 0;
        }

        .center {
            text-align: center;
        }

        .name {
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .muted {
            color: #444;
        }

        .small {
            font-size: 0.85em;
        }

        .rule {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .section {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #000;
        }

        .section:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
        }

        /*
         * Rows use flexbox instead of a fixed-width <table> so long labels
         * or values wrap onto a new line within the paper width instead of
         * forcing horizontal overflow/cut-off — min-width:0 + overflow-wrap
         * is what allows the label to actually shrink and wrap rather than
         * pushing the value off the edge.
         */
        .row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            padding: 1px 0;
        }

        .row .label {
            color: #444;
            min-width: 0;
            overflow-wrap: break-word;
        }

        .row .value {
            text-align: right;
            min-width: 0;
            overflow-wrap: break-word;
        }

        .item-name {
            overflow-wrap: break-word;
        }

        .item-sub {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            color: #444;
            font-size: 0.85em;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            font-weight: bold;
            padding-top: 4px;
            margin-top: 4px;
            border-top: 1px dashed #000;
        }

        .voided-banner {
            text-align: center;
            font-weight: bold;
            letter-spacing: 2px;
            border: 2px solid #000;
            padding: 4px;
            margin-bottom: 8px;
        }

        .footer {
            text-align: center;
            color: #444;
            font-size: 0.85em;
            margin-top: 8px;
        }

        .disclaimer {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8em;
            margin-top: 6px;
        }

        /* Controls (back link, print/paper-size buttons) are for the
           on-screen preview only and must never show up on the printed
           receipt itself or in the browser print preview. */
        .no-print {
            max-width: {{ $paperWidth }};
            margin: 0 auto 10px;
            font-family: system-ui, sans-serif;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }

        .no-print a, .no-print button {
            font: inherit;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #8A3330;
            background: #fff;
            color: #8A3330;
            text-decoration: none;
            cursor: pointer;
        }

        .no-print button.primary {
            background: #8A3330;
            color: #fff;
        }

        .no-print .paper-toggle a.active {
            font-weight: bold;
            text-decoration: underline;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="{{ route('orders.receipt', $order) }}">&larr; {{ __('Back to Receipt') }}</a>
        <span class="paper-toggle">
            {{ __('Paper') }}:
            <a href="{{ route('orders.print', ['order' => $order, 'paper' => '58mm']) }}" class="{{ $paperWidth === '58mm' ? 'active' : '' }}">58mm</a>
            <a href="{{ route('orders.print', ['order' => $order, 'paper' => '80mm']) }}" class="{{ $paperWidth === '80mm' ? 'active' : '' }}">80mm</a>
        </span>
        <button type="button" class="primary" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    @php $invoice = $order->currentInvoiceSnapshot; @endphp

    @if ($order->payment_status === \App\Enums\PaymentStatus::Voided)
        <div class="voided-banner">{{ __('VOIDED') }}</div>
    @endif

    @if ($invoice)
        {{-- BIR invoice layout — read entirely from the frozen invoice
             snapshot (never live Settings), same source of truth as the
             on-screen and PDF receipts, so all three always agree. --}}
        <div class="center">
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
                    <span class="label item-name">{{ $item->item_name }}{{ $isEligible && $eligibilityTag ? ' ['.$eligibilityTag.']' : '' }}</span>
                    <span class="value">{{ number_format($item->subtotal, 2) }}</span>
                </div>
                <div class="item-sub"><span>{{ $item->quantity }} x {{ number_format($item->unit_price, 2) }}</span></div>
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

            @if ($invoice->discount_type)
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

        @if ($invoice->discount_type)
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

        <div class="section">
            <div class="row small"><span class="label">{{ __('Payment Method') }}</span><span class="value">{{ $invoice->payment_method?->label() }}</span></div>
            @if ($invoice->payment_reference)
                <div class="row small"><span class="label">{{ __('Reference No.') }}</span><span class="value">{{ $invoice->payment_reference }}</span></div>
            @endif
            <div class="row small"><span class="label">{{ __('Amount Received') }}</span><span class="value">{{ number_format($invoice->amount_received, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Change Due') }}</span><span class="value">{{ number_format($invoice->change_amount, 2) }}</span></div>
            <div class="row small"><span class="label">{{ __('Payment Status') }}</span><span class="value">{{ $order->payment_status->label() }}</span></div>
        </div>

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
        {{-- Pre-existing paid order with no invoice snapshot — original
             simple format, unchanged, so old receipts keep printing
             exactly as they always have. --}}
        <div class="center">
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

    <script>
        // Auto-trigger the browser print dialog on load. Some browsers
        // (notably Safari, and Chrome under popup-blocker-like settings)
        // can suppress a print() call fired too early or block it
        // silently — the "Print" button above is the manual fallback for
        // whenever auto-print doesn't fire.
        window.addEventListener('load', function () {
            window.print();
        });
    </script>

    {{--
        Thermal printer testing checklist (client connects printer for the
        first time the day after this was written — verify all of these
        against the real hardware, not just browser print preview):

        [ ] Print preview shows no browser header/footer (date, URL, page
            numbers). CSS @page cannot suppress these — the user must turn
            off "Headers and footers" in the browser's print dialog
            (Chrome: More settings > Headers and footers).
        [ ] Content fits within 58mm (?paper=58mm) and 80mm (?paper=80mm)
            without horizontal cut-off.
        [ ] Long item names wrap onto a new line instead of overflowing
            or getting clipped.
        [ ] Totals and footer are not cut off at the bottom — @page uses
            `size: {width} auto`, i.e. auto height, not a fixed page
            height, so the page should grow to fit all content.
        [ ] Test on the actual thermal printer driver once installed —
            confirm character size/readability; the 10px (58mm) / 12px
            (80mm) font-size in this file's <style> may need adjusting up
            or down based on real print output.
    --}}
</body>
</html>
