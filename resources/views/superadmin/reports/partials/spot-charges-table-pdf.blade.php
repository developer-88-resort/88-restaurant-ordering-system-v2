{{--
    PDF twin of spot-charges-table: per locker / kubo with a subtotal each.

    Expects: $bySpot, optionally $spotHeading ("Locker") and $total / $count.
--}}
<table class="data">
    <thead>
        <tr><th>{{ __('Received') }}</th><th>{{ __('Order') }}</th><th>{{ $spotHeading ?? __('Locker / Kubo') }}</th><th>{{ __('Guest') }}</th><th>{{ __('OS') }}</th><th>{{ __('Recorded by') }}</th><th class="right">{{ __('Amount') }}</th></tr>
    </thead>
    <tbody>
        @foreach ($bySpot as $spot)
            @foreach ($spot->payments as $charge)
                <tr>
                    <td>{{ $charge->received_at?->format('M j, g:i A') }}</td>
                    <td>{{ $charge->order->orderNumber() }}<br><span style="color:#888">{{ $charge->order->slipLocationLabel() }}</span></td>
                    <td><strong>{{ $spot->label }}</strong></td>
                    <td>{{ $charge->guest_name ?: '—' }}</td>
                    <td>{{ $charge->guest_ref ?: '—' }}</td>
                    <td>{{ $charge->receivedBy?->name ?? '—' }}</td>
                    <td class="right">&#8369;{{ number_format($charge->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6"><em>{{ __(':spot subtotal', ['spot' => $spot->label]) }} ({{ $spot->count }})</em></td>
                <td class="right"><strong>&#8369;{{ number_format($spot->total, 2) }}</strong></td>
            </tr>
        @endforeach

        @isset($total)
            <tr>
                <td colspan="6"><strong>{{ __('Total to bill') }} ({{ $count }})</strong></td>
                <td class="right"><strong>&#8369;{{ number_format($total, 2) }}</strong></td>
            </tr>
        @endisset
    </tbody>
</table>
