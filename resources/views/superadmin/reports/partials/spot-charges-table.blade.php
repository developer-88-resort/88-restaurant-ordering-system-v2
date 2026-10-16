{{--
    Locker and Kubo charges to bill, per locker / kubo: lockers first then
    kubos, by number, each with its own subtotal so front desk can check it
    against what they collected for that locker or kubo.

    Expects: $bySpot (ReportController::spotChargeSummary), optionally
    $spotHeading ("Locker") for the column, and
    $total / $count for a "Total to bill" footer.
--}}
@php
    $cell = 'whitespace-nowrap px-5 py-3 text-sm';
    $head = 'whitespace-nowrap px-5 py-2.5 text-xs font-medium text-slate-500';
@endphp

<div class="overflow-x-auto">
    <table data-paginate="10" class="tabular-nums min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50">
            <tr>
                <th scope="col" class="{{ $head }} text-left">{{ __('Received') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Order') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ $spotHeading ?? __('Locker / Kubo') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Guest') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('OS') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Recorded by') }}</th>
                <th scope="col" class="{{ $head }} text-center">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($bySpot as $spot)
                @foreach ($spot->payments as $charge)
                    <tr class="transition hover:bg-slate-50" data-page-group="spot-{{ $spot->method }}-{{ $spot->spot_no }}">
                        <td class="{{ $cell }} text-slate-600">{{ $charge->received_at?->format('M j, g:i A') }}</td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route($charge instanceof \App\Models\MassagePayment ? 'massage.orders.show' : 'orders.show', $charge->order) }}" class="text-sm font-bold text-slate-700 hover:underline">{{ $charge->order->orderNumber() }}</a>
                            <p class="text-xs text-slate-500">{{ $charge->order->slipLocationLabel() }}</p>
                        </td>
                        <td class="{{ $cell }} text-center font-bold text-slate-900">{{ $spot->label }}</td>
                        <td class="px-5 py-3 text-center text-sm text-slate-700">{{ $charge->guest_name ?: '—' }}</td>
                        <td class="{{ $cell }} text-center text-slate-600">{{ $charge->guest_ref ?: '—' }}</td>
                        <td class="{{ $cell }} text-center text-slate-600">{{ $charge->receivedBy?->name ?? '—' }}</td>
                        <td class="{{ $cell }} text-center font-bold text-slate-900">&#8369;{{ number_format($charge->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-[#FCF8F1]" data-page-group="spot-{{ $spot->method }}-{{ $spot->spot_no }}" data-page-subtotal>
                    <td colspan="6" class="px-5 py-2 text-xs font-semibold text-slate-600">
                        {{ __(':spot subtotal', ['spot' => $spot->label]) }}
                        ({{ trans_choice(':count charge|:count charges', $spot->count, ['count' => $spot->count]) }})
                    </td>
                    <td class="whitespace-nowrap px-5 py-2 text-center text-sm font-semibold text-amber-700">&#8369;{{ number_format($spot->total, 2) }}</td>
                </tr>
            @endforeach
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
