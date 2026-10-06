<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    // Print A4 (?paper=a4): the A4 sheet is two columns. A slip starts on the
    // left, a long one carries on in the right column before the next sheet,
    // and each added slip ($extraOrders) starts in the next column — so two
    // short slips share one sheet, left and right. The thermal printer
    // bridge renders this page too and never sets any of it.
    $onA4 = $onA4 ?? false;
    $extraOrders = $extraOrders ?? collect();
    $notFound = $notFound ?? null;
    $fromOrder = $fromOrder ?? false;
    $backUrl = $backUrl ?? route('kitchen.index');
    $backLabel = $backLabel ?? __('Back to Kitchen');
    $slipUrl = fn (array $with) => route('orders.kitchen-slip.print', array_filter([
        'order' => $order,
        'paper' => 'a4',
        'from' => $fromOrder ? 'order' : null,
        'with' => implode(',', $with) ?: null,
    ]));
    $withNumbers = $extraOrders->pluck('order_number')->all();
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Kitchen Slip') }} {{ $order->orderNumber() }}</title>
    <style>
        {{-- Same thermal page geometry as orders/receipt-print.blade.php —
             a continuous receipt strip of a fixed width, not a fixed
             A4/Letter sheet. --}}
        @page {
            size: {{ $onA4 ? 'A4' : $paperWidth.' auto' }};
            margin: {{ $onA4 ? '8mm' : '0' }};
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
            {{-- 14px is as large as 80mm takes before the longest item names
                 start wrapping onto a second line; 58mm is left alone since
                 it has far less width to give away. --}}
            font-size: {{ $paperWidth === '58mm' ? '10px' : '14px' }};
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

        {{-- A price never breaks across lines; the name beside it wraps instead. --}}
        .row .value.amount {
            flex-shrink: 0;
            white-space: nowrap;
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
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #000;
            font-size: 1.15em;
            font-weight: bold;
        }

        @if ($onA4)
            {{-- Two columns on the A4 sheet, filled left then right, then
                 the next sheet. A dashed line between them marks where to
                 cut. Each added slip starts in a fresh column, and a line
                 is never split across two columns. --}}
            {{-- Larger than the thermal slip's 14px: on A4 paper 14px came
                 out too small to read comfortably. --}}
            body {
                width: auto;
                padding: 0;
                font-size: 16px;
            }

            .a4-sheet {
                column-count: 2;
                column-gap: 8mm;
                column-fill: auto;
                column-rule: 1px dashed #999;
            }

            .a4-slip {
                padding: 0 2mm;
            }

            .a4-slip + .a4-slip {
                break-before: column;
            }

            .a4-slip .row,
            .a4-slip .item-sub,
            .a4-slip .item-cancelled,
            .a4-slip .center,
            .a4-slip .footer,
            .a4-slip p {
                break-inside: avoid;
            }

            @media screen {
                {{-- On screen, a sheet-sized preview: anything that would go
                     on to the next sheet shows to the right of it. --}}
                html {
                    background: #e7e5e4;
                }

                body {
                    padding: 16px;
                }

                .a4-sheet {
                    width: 210mm;
                    height: 297mm;
                    margin: 0 auto;
                    padding: 8mm;
                    background: #fff;
                    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
                    overflow-x: auto;
                }
            }
        @endif

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

        @if ($onA4)
            .no-print {
                max-width: 210mm;
            }

            .a4-tools {
                max-width: 210mm;
                margin: 0 auto 12px;
                padding: 10px 12px;
                border: 1px solid #d6d3d1;
                border-radius: 8px;
                background: #fff;
                font-family: system-ui, sans-serif;
                font-size: 13px;
            }

            .a4-tools form {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
            }

            .a4-tools input {
                font: inherit;
                padding: 5px 8px;
                border: 1px solid #a8a29e;
                border-radius: 4px;
                width: 12rem;
            }

            .a4-tools button {
                font: inherit;
                padding: 5px 10px;
                border-radius: 4px;
                border: 1px solid #8A3330;
                background: #8A3330;
                color: #fff;
                cursor: pointer;
            }

            .a4-tools .chips {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 8px;
            }

            .a4-tools .chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 999px;
                background: #f5f5f4;
                border: 1px solid #e7e5e4;
            }

            .a4-tools .chip a {
                color: #8A3330;
                text-decoration: none;
                font-weight: bold;
            }

            .a4-tools .error {
                margin-top: 6px;
                color: #b91c1c;
            }

            .a4-tools .hint {
                margin-top: 6px;
                color: #57534e;
            }

            @media print {
                .a4-tools {
                    display: none !important;
                }
            }
        @endif

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    {{--
        $forImage renders the same slip for the thermal printer bridge,
        which screenshots this page and prints the result as a bitmap so
        Direct Print comes out identical to this browser print. It needs
        the slip and nothing else — no on-screen controls, and no print
        dialog firing inside a headless browser that has no one to answer
        it. Everything above this line is shared deliberately: the layout
        has to stay one definition, or the two outputs drift apart.
    --}}
    @unless ($forImage ?? false)
        <div class="no-print">
            <a href="{{ $backUrl }}">&larr; {{ $backLabel }}</a>
            <button type="button" class="primary" onclick="window.print()">{{ __('Print') }}</button>
        </div>
    @endunless

    @if ($onA4)
        {{-- Add another slip to this sheet: it starts in the next column
             (the right, after this one). Not printed. --}}
        <div class="a4-tools">
            <form method="GET" action="{{ route('orders.kitchen-slip.print', $order) }}">
                <input type="hidden" name="paper" value="a4">
                @if ($fromOrder)
                    <input type="hidden" name="from" value="order">
                @endif
                @if ($withNumbers)
                    <input type="hidden" name="with" value="{{ implode(',', $withNumbers) }}">
                @endif
                <label for="add-slip"><strong>{{ __('Add order slip no.') }}</strong></label>
                <input id="add-slip" name="add" type="text" placeholder="{{ __('e.g. 88-1003-004') }}" autocomplete="off" autofocus>
                <button type="submit">{{ __('Add on the right') }}</button>
            </form>

            <div class="chips">
                <span class="chip">{{ $order->orderNumber() }} &middot; {{ __('left') }}</span>
                @foreach ($extraOrders as $extra)
                    <span class="chip">
                        {{ $extra->orderNumber() }}
                        <a href="{{ $slipUrl(array_values(array_diff($withNumbers, [$extra->order_number]))) }}" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }} {{ $extra->orderNumber() }}">&times;</a>
                    </span>
                @endforeach
            </div>

            @if ($notFound)
                <p class="error">{{ __('No order slip found with the number :numbers.', ['numbers' => $notFound]) }}</p>
            @endif
            <p class="hint">{{ __('A long slip carries on in the right column before the next sheet. Each added slip starts in the next column.') }}</p>
        </div>

        <div class="a4-sheet">
            <div class="a4-slip">
                @include('orders.partials.kitchen-slip-print-body', ['order' => $order])
            </div>
            @foreach ($extraOrders as $extra)
                <div class="a4-slip">
                    @include('orders.partials.kitchen-slip-print-body', ['order' => $extra])
                </div>
            @endforeach
        </div>
    @else
        @include('orders.partials.kitchen-slip-print-body', ['order' => $order])
    @endif

    @unless ($forImage ?? false)
    <script>
        // Auto-trigger the browser print dialog on load, same fallback
        // rationale as the receipt print page — the manual button above
        // covers browsers that suppress an early/unprompted print() call.
        // Not on A4: there the page first asks whether to add more slips,
        // and printing waits for the Print button.
        @unless ($onA4)
            window.addEventListener('load', function () {
                window.print();
            });
        @endunless

        // Once the print dialog closes (printed or cancelled), go straight
        // back to the Kitchen tab — no intermediate paper-size picker to
        // land on first.
        window.addEventListener('afterprint', function () {
            window.location.href = @json($backUrl);
        });
    </script>
    @endunless
</body>
</html>
