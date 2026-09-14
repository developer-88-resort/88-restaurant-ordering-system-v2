<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $order->receipt_number }}</title>
    <style>
        /*
         * @page controls how the browser's print dialog paginates this
         * document. `size: {width} auto` treats the page as a continuous
         * receipt strip (thermal printers feed paper of a fixed width but
         * arbitrary/auto length) rather than a fixed A4/Letter sheet.
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
            font-weight: bold;
            line-height: 1.4;
            color: #000;
            background: #fff;
            /*
             * A thermal print head puts down far less ink on normal-weight
             * strokes, so non-bold text comes out faint/blurry on real
             * paper even though it looks fine in a browser preview — hence
             * `font-weight: bold` above as the body default, not just on
             * headings. The left/right padding is in mm (matching the
             * @page/body width units) and deliberately generous: most
             * 80mm/58mm thermal printers have a few mm of unprintable
             * margin on each physical edge, and text set flush against the
             * CSS edge gets its outermost character(s) cut off on the
             * actual printout even though it renders complete on screen.
             */
            padding: 3mm 5mm 5mm 5mm;
        }

        p {
            margin: 0;
        }

        .center {
            text-align: center;
        }

        .logo {
            height: 42px;
            width: 42px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 6px;
            display: block;
        }

        .name {
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .muted {
            /* Was a lighter gray — reads as faint/blurry on thermal paper,
               which can't render gray as anything but sparse dithered
               dots. Solid black everywhere; size/spacing carries the
               visual hierarchy instead of color. */
            color: #000;
        }

        .small {
            font-size: 0.85em;
        }

        .section {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #D9CCBA;
        }

        .section:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
        }

        /*
         * Rows use flexbox instead of a fixed-width <table> so long labels
         * or values wrap onto a new line within the paper width instead of
         * forcing horizontal overflow/cut-off.
         */
        .row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            padding: 1px 0;
        }

        .row .label {
            color: #000;
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
            color: #000;
            font-size: 0.85em;
        }

        .item-cancelled {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            font-weight: bold;
            padding-top: 4px;
            margin-top: 4px;
            border-top: 1px dashed #D9CCBA;
        }

        .voided-banner {
            text-align: center;
            font-weight: bold;
            letter-spacing: 2px;
            border: 2px solid #000;
            padding: 4px;
            margin-bottom: 8px;
        }

        .refund-banner {
            text-align: center;
            font-weight: bold;
            letter-spacing: 1px;
            border-top: 2px dashed #000;
            padding-top: 4px;
        }

        .footer {
            text-align: center;
            color: #000;
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

    @include('orders.partials.receipt-print-body', ['order' => $order, 'totals' => $totals ?? null])

    <script>
        // Auto-trigger the browser print dialog on load. Some browsers
        // (notably Safari, and Chrome under popup-blocker-like settings)
        // can suppress a print() call fired too early or block it
        // silently — the "Print" button above is the manual fallback for
        // whenever auto-print doesn't fire.
        window.addEventListener('load', function () {
            window.print();
        });

        // Once the print dialog closes (printed or cancelled), return to
        // the Receipt page this was opened from instead of leaving staff
        // sitting on the paper-size picker.
        window.addEventListener('afterprint', function () {
            window.location.href = @json(route('orders.receipt', $order));
        });
    </script>
</body>
</html>
