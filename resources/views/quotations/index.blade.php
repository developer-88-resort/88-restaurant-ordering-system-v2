<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Advance Orders / Quotations') }}
            </h2>
            <a href="{{ route('quotations.create') }}"
               class="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition">
                + {{ __('New Quotation') }}
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($quotations->isEmpty())
        <x-empty-state
            :title="__('No quotations yet')"
            :description="__('Create an advance order for a table — it stays a quotation (never hits the kitchen) until you convert it on the day.')"
        />
    @else
        <div class="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E5DDD0]">
                    <thead class="bg-[#FAF6EE]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Quotation') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Table') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Scheduled For') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Customer') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5DDD0]">
                        @foreach ($quotations as $quotation)
                            <tr class="hover:bg-[#FDFBF7]">
                                <td class="px-6 py-4 text-sm font-medium">
                                    <a href="{{ route('quotations.show', $quotation) }}" class="text-[#8A3330] hover:underline font-mono">{{ $quotation->quotation_number }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $quotation->area->name ?? '—' }}{{ $quotation->space ? ' - '.$quotation->space->name : '' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $quotation->scheduled_for?->format('M d, Y g:i A') ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $quotation->customer_name ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $quotation->status->badgeClasses() }}">
                                        {{ $quotation->status->label() }}
                                    </span>
                                    @if ($quotation->convertedOrder)
                                        <a href="{{ route('orders.show', $quotation->convertedOrder) }}" class="block mt-1 text-xs text-[#8A3330] hover:underline font-mono">{{ $quotation->convertedOrder->orderNumber() }}</a>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">₱{{ number_format($quotation->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
