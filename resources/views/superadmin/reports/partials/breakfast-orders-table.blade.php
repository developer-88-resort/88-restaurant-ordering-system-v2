{{--
    The Breakfast section's orders, line by line: breakfast lines and the
    regular menu added to them (tagged "Regular menu" — counted in Breakfast
    too, since the whole order is complimentary), with how each was settled.
    Ten orders a page (report-pagination.js).

    Expects: $orders (ReportController::breakfastOrders), $breakfastTotal,
    $regularTotal.
--}}
<div class="overflow-x-auto">
    <table data-paginate="10" class="tabular-nums min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50">
            <tr>
                <th scope="col" class="whitespace-nowrap px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Order') }}</th>
                <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Items') }}</th>
                <th scope="col" class="whitespace-nowrap px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Breakfast menu') }}</th>
                <th scope="col" class="whitespace-nowrap px-5 py-2.5 text-center text-xs font-medium text-slate-500">{{ __('Regular menu') }}</th>
                <th scope="col" class="px-5 py-2.5 text-left text-xs font-medium text-slate-500">{{ __('Settled with') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($orders as $row)
                <tr class="align-top transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-5 py-3">
                        <a href="{{ route('orders.show', $row->order) }}" class="text-sm font-bold text-slate-700 hover:underline">{{ $row->number }}</a>
                        <p class="text-xs text-slate-500">{{ $row->location }}</p>
                        <p class="text-[11px] text-slate-400">{{ $row->paidAt?->format('M j, g:i A') }}</p>
                    </td>
                    <td class="px-5 py-3">
                        @foreach ($row->lines as $line)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-slate-800">
                                    {{ $line->quantity }}&times; {{ $line->name }}
                                    @unless ($line->breakfast)
                                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ __('Regular menu') }}</span>
                                    @endunless
                                </span>
                                <span class="whitespace-nowrap text-slate-600">&#8369;{{ number_format($line->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </td>
                    <td class="whitespace-nowrap px-5 py-3 text-center text-sm font-bold text-slate-900">&#8369;{{ number_format($row->breakfastTotal, 2) }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-center text-sm {{ $row->regularTotal > 0 ? 'font-bold text-slate-900' : 'text-slate-400' }}">
                        {{ $row->regularTotal > 0 ? '₱'.number_format($row->regularTotal, 2) : '—' }}
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-600">{{ $row->settledWith }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
            <tr>
                <td colspan="2" class="px-5 py-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-900">{{ __('Total') }} ({{ $orders->count() }})</td>
                <td class="whitespace-nowrap px-5 py-3 text-center text-sm font-bold text-slate-700">&#8369;{{ number_format($breakfastTotal, 2) }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-center text-sm font-bold text-slate-700">&#8369;{{ number_format($regularTotal, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
