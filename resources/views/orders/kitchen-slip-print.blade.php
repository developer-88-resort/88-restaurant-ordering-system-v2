<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Kitchen Slip') }} {{ $order->orderNumber() }}</title>
    <style>
        {{-- Same thermal page geometry as orders/receipt-print.blade.php —
             a continuous receipt strip of a fixed width, not a fixed
             A4/Letter sheet. --}}
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
            {{--
                A thermal print head puts down far less ink on normal-weight
                strokes, so non-bold text comes out faint/blurry on real
                paper even though it looks fine in a browser preview — hence
                `font-weight: bold` above as the body default, not just on
                headings. The left/right padding is in mm (matching the
                @page/body width units) and deliberately generous: most
                80mm/58mm thermal printers have a few mm of unprintable
                margin on each physical edge, and text set flush against
                the CSS edge gets its outermost character(s) cut off on the
                actual printout even though it renders complete on screen.
            --}}
            padding: 3mm 5mm 5mm 5mm;
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
            {{-- Was a lighter gray — reads as faint/blurry on thermal
                 paper, which can't render gray as anything but sparse
                 dithered dots. Solid black everywhere; size/spacing
                 carries the visual hierarchy instead of color. --}}
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

        .footer {
            text-align: center;
            color: #000;
            font-size: 0.85em;
            margin-top: 8px;
        }

        {{-- Controls (back link, print button) are for the on-screen
             preview only and must never show up on the printed slip
             itself or in the browser print preview. There is no
             paper-size toggle here — this page is reached with a print
             button click, not a page a staffer stops to configure. --}}
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

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="{{ route('kitchen.index') }}">&larr; {{ __('Back to Kitchen') }}</a>
        <button type="button" class="primary" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    @include('orders.partials.kitchen-slip-print-body', ['order' => $order])

    <script>
        // Auto-trigger the browser print dialog on load, same fallback
        // rationale as the receipt print page — the manual button above
        // covers browsers that suppress an early/unprompted print() call.
        window.addEventListener('load', function () {
            window.print();
        });

        // Once the print dialog closes (printed or cancelled), go straight
        // back to the Kitchen tab — no intermediate paper-size picker to
        // land on first.
        window.addEventListener('afterprint', function () {
            window.location.href = @json(route('kitchen.index'));
        });
    </script>
</body>
</html>
