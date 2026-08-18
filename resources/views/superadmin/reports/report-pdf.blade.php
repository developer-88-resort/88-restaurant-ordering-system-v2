<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Nanum Gothic Coding', 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; margin: 0; padding: 24px; }
        .center { text-align: center; }
        .logo { display: block; margin: 0 auto 6px auto; }
        .name { font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 15px; }
        .muted { color: #777; font-size: 10px; }
        .period { font-size: 13px; font-weight: bold; color: #8A3330; margin-top: 4px; }
        .rule { border-top: 1px solid #ddd; margin: 14px 0; }

        .stat-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .stat-table td { width: 25%; padding: 8px 6px; border: 1px solid #E5DDD0; vertical-align: top; }
        .stat-label { color: #8A7B9E; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .stat-value.revenue { color: #8A3330; }
        .stat-trend { font-size: 9px; margin-top: 3px; }
        .stat-trend.up { color: #16803c; }
        .stat-trend.down { color: #c0392b; }
        .stat-trend.flat, .stat-trend.new { color: #777; }

        h3 { font-size: 12px; margin: 18px 0 6px 0; color: #222; border-bottom: 1px solid #E5DDD0; padding-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 9px; text-transform: uppercase; color: #8A7B9E; padding: 4px 6px; border-bottom: 1px solid #E5DDD0; }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #F3EEE4; font-size: 10px; }
        table.data .right { text-align: right; }
        table.data .percent-col { width: 50px; }
        .empty { color: #999; font-size: 10px; padding: 8px 0; }

        .footer { text-align: center; color: #999; font-size: 9px; margin-top: 24px; }
    </style>
</head>
<body>
    @php
        $renderTrend = function (?array $data, bool $invert = false) {
            if (! $data) {
                return '';
            }
            $percent = $data['percent'];
            if (is_null($percent)) {
                return '<p class="stat-trend new">'.__('New').' &middot; '.$data['label'].'</p>';
            }
            $isFlat = abs($percent) < 0.05;
            $isUp = $percent >= 0.05;
            $class = $isFlat ? 'flat' : (($isUp xor $invert) ? 'up' : 'down');
            $arrow = $isFlat ? '' : ($isUp ? '&#9650; ' : '&#9660; ');

            return '<p class="stat-trend '.$class.'">'.$arrow.number_format(abs($percent), 1).'% '.$data['label'].'</p>';
        };
    @endphp
    @php $setting = \App\Models\Setting::current(); @endphp
    <div class="center">
        @if (file_exists(public_path('images/logo.png')))
            <img class="logo" src="{{ public_path('images/logo.png') }}" width="48" height="48">
        @endif
        <p class="name">{{ $setting->invoiceBusinessName() }}</p>
        <p class="muted">{{ __('Sales Report') }}</p>
        <p class="period">{{ $rangeLabel }}</p>
    </div>

    <div class="rule"></div>

    <table class="stat-table">
        <tr>
            <td>
                <div class="stat-label">{{ __('Total Revenue') }}</div>
                <div class="stat-value revenue">&#8369;{{ number_format($totalRevenue, 2) }}</div>
                {!! $renderTrend($comparison['totalRevenue'] ?? null) !!}
            </td>
            <td>
                <div class="stat-label">{{ __('Total Orders') }}</div>
                <div class="stat-value">{{ $totalOrders }}</div>
                {!! $renderTrend($comparison['totalOrders'] ?? null) !!}
            </td>
            <td>
                <div class="stat-label">{{ __('Average Order Value') }}</div>
                <div class="stat-value">&#8369;{{ number_format($averageOrderValue, 2) }}</div>
                {!! $renderTrend($comparison['averageOrderValue'] ?? null) !!}
            </td>
            <td>
                <div class="stat-label">{{ __('Cancelled Orders') }}</div>
                <div class="stat-value">{{ $cancelledOrders }}</div>
                {!! $renderTrend($comparison['cancelledOrders'] ?? null, true) !!}
            </td>
        </tr>
    </table>

    <h3>{{ __('Tax & Discount Summary') }}</h3>
    <table class="stat-table">
        <tr>
            <td>
                <div class="stat-label">{{ __('Net Collected') }}</div>
                <div class="stat-value revenue">&#8369;{{ number_format($taxSummary['netAmountCollected'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('VATable Sales') }}</div>
                <div class="stat-value">&#8369;{{ number_format($taxSummary['vatableSales'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('VAT-Exempt Sales') }}</div>
                <div class="stat-value">&#8369;{{ number_format($taxSummary['vatExemptSales'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('VAT Amount') }}</div>
                <div class="stat-value">&#8369;{{ number_format($taxSummary['vatAmount'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="stat-label">{{ __('Senior Discounts') }}</div>
                <div class="stat-value">&#8369;{{ number_format($taxSummary['seniorDiscounts'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('PWD Discounts') }}</div>
                <div class="stat-value">&#8369;{{ number_format($taxSummary['pwdDiscounts'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Service Charges') }}</div>
                <div class="stat-value">&#8369;{{ number_format($taxSummary['serviceCharges'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Voided Invoices') }}</div>
                <div class="stat-value">{{ $taxSummary['voidedInvoices'] }}</div>
            </td>
        </tr>
    </table>

    <h3>{{ __('Daily Revenue') }}</h3>
    @if ($dailySales->isEmpty())
        <p class="empty">{{ __('No sales data for this period.') }}</p>
    @else
        <table class="data">
            <thead>
                <tr><th>{{ __('Date') }}</th><th class="right">{{ __('Revenue') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($dailySales as $day)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($day->sale_date)->format('M d, Y') }}</td>
                        <td class="right">&#8369;{{ number_format($day->revenue, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ __('Best-Selling Items') }}</h3>
    @if ($bestSellers->isEmpty())
        <p class="empty">{{ __('No item sales for this period.') }}</p>
    @else
        <table class="data">
            <thead>
                <tr><th>{{ __('Item') }}</th><th class="right">{{ __('Qty Sold') }}</th><th class="right">{{ __('Revenue') }}</th><th class="right percent-col">%</th></tr>
            </thead>
            <tbody>
                @foreach ($bestSellers as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td class="right">
                            @if ($item->line_type === 'weighed')
                                {{ number_format($item->total_net_grams / 1000, 3) }} {{ __('kg') }}
                            @else
                                {{ $item->total_qty }} {{ __('pc') }}
                            @endif
                        </td>
                        <td class="right">&#8369;{{ number_format($item->total_revenue, 2) }}</td>
                        <td class="right">{{ number_format($item->percent, 0) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ __('Sales by Category') }}</h3>
    @if ($categorySales->isEmpty())
        <p class="empty">{{ __('No category sales for this period.') }}</p>
    @else
        <table class="data">
            <thead>
                <tr><th>{{ __('Category') }}</th><th class="right">{{ __('Revenue') }}</th><th class="right percent-col">%</th></tr>
            </thead>
            <tbody>
                @foreach ($categorySales as $category)
                    <tr>
                        <td>{{ $category->category_name }}</td>
                        <td class="right">&#8369;{{ number_format($category->total_revenue, 2) }}</td>
                        <td class="right">{{ number_format($category->percent, 0) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ __('Sales by Area') }}</h3>
    @if ($areaSales->isEmpty())
        <p class="empty">{{ __('No area sales for this period.') }}</p>
    @else
        <table class="data">
            <thead>
                <tr><th>{{ __('Area') }}</th><th class="right">{{ __('Orders') }}</th><th class="right">{{ __('Revenue') }}</th><th class="right percent-col">%</th></tr>
            </thead>
            <tbody>
                @foreach ($areaSales as $area)
                    <tr>
                        <td>{{ $area->area_name }}</td>
                        <td class="right">{{ $area->order_count }}</td>
                        <td class="right">&#8369;{{ number_format($area->total_revenue, 2) }}</td>
                        <td class="right">{{ number_format($area->percent, 0) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ __('Weighed Items') }}</h3>
    @if ($weighedItems->isEmpty())
        <p class="empty">{{ __('No weighed-item sales for this period.') }}</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th class="right">{{ __('Total kg') }}</th>
                    <th class="right">{{ __('Lines') }}</th>
                    <th class="right">{{ __('Total Revenue') }}</th>
                    <th class="right">{{ __('Avg Rate/kg') }}</th>
                    <th class="right">{{ __('Variance Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($weighedItems as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td class="right">{{ number_format($item->total_kg, 3) }} {{ __('kg') }}</td>
                        <td class="right">{{ $item->total_lines }}</td>
                        <td class="right">&#8369;{{ number_format($item->total_revenue, 2) }}</td>
                        <td class="right">&#8369;{{ number_format($item->avg_rate_per_kilo, 2) }}</td>
                        <td class="right">{{ $item->variance_total >= 0 ? '+' : '' }}&#8369;{{ number_format($item->variance_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">{{ __('Generated') }} {{ now()->format('M d, Y g:i A') }}</p>
</body>
</html>
