<x-customer-layout :location-label="__('Table Session')">
    <div class="min-h-[60vh] flex items-center justify-center px-4">
        <div class="max-w-sm w-full bg-white border border-[#E5DDD0] rounded-2xl p-8 text-center">
            <div class="mx-auto h-14 w-14 rounded-full bg-[#FAF6EE] flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-7 w-7 text-[#8A3330]">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="mt-4 text-lg font-bold text-gray-900">{{ __('This table session has ended') }}</h1>
            <p class="mt-2 text-sm text-[#8A7B6D]">
                {{ __('The QR code you scanned belongs to a dining session that is already closed — no new orders can be added to it.') }}
            </p>
            <p class="mt-2 text-sm text-[#8A7B6D]">
                {{ __('Please scan the QR code on your table to start a fresh order, or ask our staff for help.') }}
            </p>
        </div>
    </div>
</x-customer-layout>
