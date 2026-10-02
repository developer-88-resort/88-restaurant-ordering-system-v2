{{--
    PDF twin of room-charges-table: per room (type order, then number) with a
    subtotal each, then the "legacy" free-text charges.

    Expects: $byRoom, $legacy, $legacyTotal, and optionally $total / $count.
--}}
<table class="data">
    <thead>
        <tr><th>{{ __('Received') }}</th><th>{{ __('Order') }}</th><th>{{ __('Room') }}</th><th>{{ __('Guest') }}</th><th>{{ __('Guest ref') }}</th><th>{{ __('Recorded by') }}</th><th class="right">{{ __('Amount') }}</th></tr>
    </thead>
    <tbody>
        @foreach ($byRoom as $room)
            @foreach ($room->payments as $charge)
                <tr>
                    <td>{{ $charge->received_at?->format('M j, g:i A') }}</td>
                    <td>{{ $charge->order->orderNumber() }}<br><span style="color:#888">{{ $charge->order->slipLocationLabel() }}</span></td>
                    <td><strong>{{ $room->label }}</strong></td>
                    <td>{{ $charge->guest_name ?: '—' }}</td>
                    <td>{{ $charge->guest_ref ?: '—' }}</td>
                    <td>{{ $charge->receivedBy?->name ?? '—' }}</td>
                    <td class="right">&#8369;{{ number_format($charge->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6"><em>{{ __('Room :room subtotal', ['room' => $room->label]) }}@if ($room->type_name) · {{ $room->type_name }}@endif ({{ $room->count }})</em></td>
                <td class="right"><strong>&#8369;{{ number_format($room->total, 2) }}</strong></td>
            </tr>
        @endforeach

        @if ($legacy->isNotEmpty())
            <tr><td colspan="7"><strong>{{ __('Legacy (free text — before rooms were picked from a list)') }}</strong></td></tr>
            @foreach ($legacy as $charge)
                <tr>
                    <td>{{ $charge->received_at?->format('M j, g:i A') }}</td>
                    <td>{{ $charge->order->orderNumber() }}<br><span style="color:#888">{{ $charge->order->slipLocationLabel() }}</span></td>
                    <td colspan="2">{{ $charge->charged_to ?: ($charge->reference ?: '—') }} <span style="color:#888">({{ __('legacy') }})</span></td>
                    <td>—</td>
                    <td>{{ $charge->receivedBy?->name ?? '—' }}</td>
                    <td class="right">&#8369;{{ number_format($charge->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6"><em>{{ __('Legacy subtotal') }} ({{ $legacy->count() }})</em></td>
                <td class="right"><strong>&#8369;{{ number_format($legacyTotal, 2) }}</strong></td>
            </tr>
        @endif

        @isset($total)
            <tr>
                <td colspan="6"><strong>{{ __('Total to bill') }} ({{ $count }})</strong></td>
                <td class="right"><strong>&#8369;{{ number_format($total, 2) }}</strong></td>
            </tr>
        @endisset
    </tbody>
</table>
