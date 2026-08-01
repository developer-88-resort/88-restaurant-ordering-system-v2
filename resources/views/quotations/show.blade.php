<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">{{ $quotation->quotation_number }}</h2>
            <a href="{{ route('quotations.index') }}" class="text-sm text-[#8A3330] hover:underline font-medium">{{ __('Back to Quotations') }}</a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col lg:flex-row gap-6 items-start">
        <div class="flex-1 w-full">
            <div class="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
                <div class="px-6 py-3 bg-[#FAF6EE] border-b border-[#E5DDD0]">
                    <p class="text-xs font-bold uppercase tracking-wider text-[#8A3330]">{{ __('ADVANCE ORDER / QUOTATION') }}</p>
                    <p class="text-sm text-gray-700 mt-0.5">
                        {{ $quotation->area->name ?? '' }}{{ $quotation->space ? ' - '.$quotation->space->name : '' }}
                        @if ($quotation->scheduled_for)
                            · {{ __('Scheduled') }}: {{ $quotation->scheduled_for->format('M d, Y g:i A') }}
                        @endif
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#E5DDD0]">
                        <thead class="bg-[#FAF6EE]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Qty') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Quoted Price') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5DDD0]">
                            @foreach ($quotation->items as $item)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $item->item_name }}
                                        @if ($item->notes)
                                            <p class="text-xs text-gray-500 font-normal mt-0.5">{{ $item->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-600">{{ $item->quantity }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-600">₱{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900">₱{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-[#FAF6EE]">
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-right text-sm font-semibold text-gray-900">{{ __('Quoted Subtotal') }}</td>
                                <td class="px-6 py-3 text-right text-base font-bold text-[#8A3330]">₱{{ number_format($quotation->subtotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($quotation->notes)
                <div class="mt-6 bg-white border border-[#E5DDD0] rounded-xl p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Notes') }}</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $quotation->notes }}</p>
                </div>
            @endif
        </div>

        <div class="w-full lg:w-80 shrink-0 space-y-6">
            <div class="bg-white border border-[#E5DDD0] rounded-xl p-6 space-y-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E] mb-1">{{ __('Status') }}</p>
                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $quotation->status->badgeClasses() }}">
                        {{ $quotation->status->label() }}
                    </span>
                </div>

                @if ($quotation->customer_name || $quotation->customer_contact)
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Customer') }}</p>
                        <p class="text-sm text-gray-700">{{ $quotation->customer_name }}</p>
                        @if ($quotation->customer_contact)
                            <p class="text-xs text-gray-500">{{ $quotation->customer_contact }}</p>
                        @endif
                    </div>
                @endif

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Created By') }}</p>
                    <p class="text-sm text-gray-700">{{ $quotation->creator->name ?? '—' }} · {{ $quotation->created_at->format('M d, Y g:i A') }}</p>
                </div>

                @if ($quotation->convertedOrder)
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#8A7B9E]">{{ __('Converted To') }}</p>
                        <a href="{{ route('orders.show', $quotation->convertedOrder) }}" class="text-sm font-mono text-[#8A3330] hover:underline">
                            {{ $quotation->convertedOrder->orderNumber() }}
                        </a>
                        <p class="text-xs text-gray-400">{{ $quotation->converted_at?->format('M d, Y g:i A') }}</p>
                    </div>
                @endif

                @if ($quotation->status->isOpen())
                    <div class="pt-4 border-t border-dashed border-[#D9CCBA] space-y-2">
                        @if ($quotation->status === \App\Enums\QuotationStatus::Draft)
                            <form method="POST" action="{{ route('quotations.update-status', $quotation) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="sent">
                                <button type="submit" class="w-full text-sm font-medium rounded-md px-4 py-2 border border-[#8A3330] text-[#8A3330] hover:bg-[#FAF6EE]">
                                    {{ __('Mark as Sent to Customer') }}
                                </button>
                            </form>
                        @endif

                        @if ($quotation->status === \App\Enums\QuotationStatus::Sent)
                            <form method="POST" action="{{ route('quotations.update-status', $quotation) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="accepted">
                                <button type="submit" class="w-full text-sm font-medium rounded-md px-4 py-2 border border-[#8A3330] text-[#8A3330] hover:bg-[#FAF6EE]">
                                    {{ __('Customer Accepted') }}
                                </button>
                            </form>
                        @endif

                        @if ($quotation->status === \App\Enums\QuotationStatus::Accepted)
                            <form method="POST" action="{{ route('quotations.update-status', $quotation) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="w-full text-sm font-medium rounded-md px-4 py-2 border border-[#8A3330] text-[#8A3330] hover:bg-[#FAF6EE]">
                                    {{ __('Confirm Advance Order') }}
                                </button>
                            </form>
                        @endif

                        @if (in_array($quotation->status, [\App\Enums\QuotationStatus::Accepted, \App\Enums\QuotationStatus::Confirmed], true))
                            <x-confirm-form
                                :action="route('quotations.convert', $quotation)"
                                method="POST"
                                :title="__('Convert to an actual order?')"
                                :message="__('The quoted items will be sent to the kitchen as a real order on :table, at the frozen quoted prices. This can only happen once.', ['table' => $quotation->space->name ?? __('the table')])"
                                :confirm-label="__('Convert to Order')"
                            >
                                <button type="submit" class="w-full text-sm font-medium rounded-md px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white">
                                    {{ __('Convert to Order') }}
                                </button>
                            </x-confirm-form>
                        @endif

                        <x-confirm-form
                            :action="route('quotations.update-status', $quotation)"
                            method="PATCH"
                            :title="__('Cancel this quotation?')"
                            :message="__('This cannot be undone.')"
                            :confirm-label="__('Cancel Quotation')"
                        >
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="w-full text-sm font-medium text-red-600 hover:underline">
                                {{ __('Cancel Quotation') }}
                            </button>
                        </x-confirm-form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
