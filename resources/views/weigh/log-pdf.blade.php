<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Nanum Gothic Coding', 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; margin: 0; padding: 20px; }
        .center { text-align: center; }
        .name { font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 14px; }
        .muted { color: #777; font-size: 10px; }
        .period { font-size: 12px; font-weight: bold; color: #8A3330; margin-top: 4px; }
        .rule { border-top: 1px solid #ddd; margin: 12px 0; }

        .stat-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .stat-table td { width: 25%; padding: 8px 6px; border: 1px solid #E5DDD0; vertical-align: top; }
        .stat-label { color: #8A7B9E; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 14px; font-weight: bold; margin-top: 3px; }
        .stat-value.revenue { color: #8A3330; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 8px; text-transform: uppercase; color: #8A7B9E; padding: 4px 5px; border-bottom: 1px solid #E5DDD0; }
        table.data td { padding: 4px 5px; border-bottom: 1px solid #F3EEE4; font-size: 9px; }
        table.data .right { text-align: right; }
        table.data tr.over-tolerance td { background-color: #FFF7E6; }
        .empty { color: #999; font-size: 10px; padding: 8px 0; }

        .footer { text-align: center; color: #999; font-size: 9px; margin-top: 20px; }
    </style>
</head>
<body>
    @php $setting = \App\Models\Setting::current(); @endphp
    <div class="center">
        <p class="name">{{ $setting->invoiceBusinessName() }}</p>
        <p class="muted">{{ __('Weighed Lines') }}</p>
        <p class="period">{{ $rangeLabel }}</p>
    </div>

    <div class="rule"></div>

    <table class="stat-table">
        <tr>
            <td>
                <div class="stat-label">{{ __('Lines') }}</div>
                <div class="stat-value">{{ $totals['total_lines'] }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Total kg') }}</div>
                <div class="stat-value">{{ number_format($totals['total_kg'], 3) }} {{ __('kg') }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Total Charged') }}</div>
                <div class="stat-value revenue">&#8369;{{ number_format($totals['total_charged'], 2) }}</div>
            </td>
            <td>
                <div class="stat-label">{{ __('Total Variance') }}</div>
                <div class="stat-value">{{ $totals['total_variance'] >= 0 ? '+' : '' }}&#8369;{{ number_format($totals['total_variance'], 2) }}</div>
            </td>
        </tr>
    </table>

    @if ($rows->isEmpty())
        <p class="empty">{{ __('No weighed lines for this period.') }}</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Date & Time') }}</th>
                    <th>{{ __('Item') }}</th>
                    <th class="right">{{ __('Weight (kg)') }}</th>
                    <th class="right">{{ __('Rate/kg') }}</th>
                    <th class="right">{{ __('Expected') }}</th>
                    <th class="right">{{ __('Charged') }}</th>
                    <th class="right">{{ __('Variance') }}</th>
                    <th>{{ __('Order #') }}</th>
                    <th>{{ __('Table') }}</th>
                    <th>{{ __('Weighed By') }}</th>
                    <th>{{ __('Channel') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ $row['over_tolerance'] ? 'over-tolerance' : '' }}">
                        <td>{{ $row['weighed_at'] }}</td>
                        <td>{{ $row['item_name'] }}</td>
                        <td class="right">{{ $row['kg'] }}</td>
                        <td class="right">&#8369;{{ number_format($row['reference_price_per_kilo'], 2) }}</td>
                        <td class="right">&#8369;{{ number_format($row['computed_amount'], 2) }}</td>
                        <td class="right">&#8369;{{ number_format($row['amount_charged'], 2) }}</td>
                        <td class="right">{{ $row['variance_amount'] >= 0 ? '+' : '' }}&#8369;{{ number_format($row['variance_amount'], 2) }}</td>
                        <td>{{ $row['order_number'] }}</td>
                        <td>{{ $row['space_name'] ?? '—' }}</td>
                        <td>{{ $row['weighed_by_name'] ?? '—' }}</td>
                        <td>{{ $row['entry_mode_label'] }}</td>
                        <td>{{ $row['status_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">{{ __('Generated') }} {{ now()->format('M d, Y g:i A') }}</p>
</body>
</html>
