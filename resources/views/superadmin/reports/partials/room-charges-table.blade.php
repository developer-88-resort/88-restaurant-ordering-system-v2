{{--
    Room charges to bill, per room: grouped by room type in front desk order,
    then room number, each room with its own subtotal so front desk can check
    it against what they posted to that room. Charges from before rooms were
    picked from a list keep the free text staff typed, under "Legacy".

    Expects: $byRoom (ReportController::roomChargeGroups), $legacy,
    $legacyTotal, and optionally $total / $count for a "Total to bill" footer.
--}}
@php
    $cell = 'whitespace-nowrap px-5 py-3 text-sm';
    $head = 'whitespace-nowrap px-5 py-2.5 text-xs font-medium text-slate-500';
@endphp

<div class="overflow-x-auto">
    <table class="tabular-nums min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50">
            <tr>
                <th scope="col" class="{{ $head }} text-left">{{ __('Received') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Order') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Room') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Guest') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Guest ref') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Recorded by') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($byRoom as $room)
                @foreach ($room->payments as $charge)
                    <tr class="transition hover:bg-slate-50">
                        <td class="{{ $cell }} text-slate-600">{{ $charge->received_at?->format('M j, g:i A') }}</td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('orders.show', $charge->order) }}" class="text-sm font-bold text-slate-700 hover:underline">{{ $charge->order->orderNumber() }}</a>
                            <p class="text-xs text-slate-500">{{ $charge->order->slipLocationLabel() }}</p>
                        </td>
                        <td class="{{ $cell }} text-center font-bold text-slate-900" title="{{ $room->type_name }}">{{ $room->label }}</td>
                        <td class="px-5 py-3 text-center text-sm text-slate-700">{{ $charge->guest_name ?: '—' }}</td>
                        <td class="{{ $cell }} text-center text-slate-600">{{ $charge->guest_ref ?: '—' }}</td>
                        <td class="{{ $cell }} text-center text-slate-600">{{ $charge->receivedBy?->name ?? '—' }}</td>
                        <td class="{{ $cell }} text-center font-bold text-slate-900">&#8369;{{ number_format($charge->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-[#FCF8F1]">
                    <td colspan="6" class="px-5 py-2 text-xs font-semibold text-slate-600">
                        {{ __('Room :room subtotal', ['room' => $room->label]) }}@if ($room->type_name) · {{ $room->type_name }}@endif
                        ({{ trans_choice(':count room charge|:count room charges', $room->count, ['count' => $room->count]) }})
                    </td>
                    <td class="whitespace-nowrap px-5 py-2 text-center text-sm font-semibold text-amber-700">&#8369;{{ number_format($room->total, 2) }}</td>
                </tr>
            @endforeach

            @if ($legacy->isNotEmpty())
                <tr class="bg-slate-50">
                    <td colspan="7" class="px-5 py-2 text-xs font-semibold uppercase tracking-[0.06em] text-slate-500">
                        {{ __('Legacy (free text — before rooms were picked from a list)') }}
                    </td>
                </tr>
                @foreach ($legacy as $charge)
                    <tr class="transition hover:bg-slate-50">
                        <td class="{{ $cell }} text-slate-600">{{ $charge->received_at?->format('M j, g:i A') }}</td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('orders.show', $charge->order) }}" class="text-sm font-bold text-slate-700 hover:underline">{{ $charge->order->orderNumber() }}</a>
                            <p class="text-xs text-slate-500">{{ $charge->order->slipLocationLabel() }}</p>
                        </td>
                        <td colspan="2" class="px-5 py-3 text-center text-sm font-bold text-slate-900">
                            {{ $charge->charged_to ?: ($charge->reference ?: '—') }}
                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ __('legacy') }}</span>
                        </td>
                        <td class="{{ $cell }} text-center text-slate-600">—</td>
                        <td class="{{ $cell }} text-center text-slate-600">{{ $charge->receivedBy?->name ?? '—' }}</td>
                        <td class="{{ $cell }} text-center font-bold text-slate-900">&#8369;{{ number_format($charge->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-[#FCF8F1]">
                    <td colspan="6" class="px-5 py-2 text-xs font-semibold text-slate-600">
                        {{ __('Legacy subtotal') }} ({{ trans_choice(':count room charge|:count room charges', $legacy->count(), ['count' => $legacy->count()]) }})
                    </td>
                    <td class="whitespace-nowrap px-5 py-2 text-center text-sm font-semibold text-amber-700">&#8369;{{ number_format($legacyTotal, 2) }}</td>
                </tr>
            @endif
        </tbody>
        @isset($total)
            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                <tr>
                    <td colspan="6" class="px-5 py-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('Total to bill') }} ({{ $count }})</td>
                    <td class="whitespace-nowrap px-5 py-3 text-center text-base font-semibold text-slate-700">&#8369;{{ number_format($total, 2) }}</td>
                </tr>
            </tfoot>
        @endisset
    </table>
</div>
