<x-customer-layout location-label="{{ __('Menu') }}">
    <div
        x-data="{
            itemAvailability: @js($categories->flatMap->menuItems->mapWithKeys(fn ($item) => [$item->id => $item->availability_status->value])),
            isOrderable(id) {
                return this.itemAvailability[id] === 'available' || this.itemAvailability[id] === 'seasonal';
            },
        }"
        x-init="
            Echo.channel('menu').listen('.MenuItemAvailabilityChanged', (e) => {
                itemAvailability[e.menu_item_id] = e.availability_status;
            });
        "
    >
        <div class="px-4 py-4 max-w-5xl mx-auto">
            <a href="{{ route('customer.welcome.show') }}" class="text-sm text-[#8A3330] hover:underline font-medium">
                &larr; {{ __('Back') }}
            </a>
            @if ($customerName)
                <p class="mt-2 text-sm text-[#8A7B6D]">{{ __('Just browsing, :name? Take your time!', ['name' => $customerName]) }}</p>
            @endif
        </div>

        <div class="px-4 pb-10 max-w-5xl mx-auto space-y-8">
            @if ($categories->isEmpty())
                <x-empty-state
                    :title="__('Nothing on the menu right now')"
                    :description="__('Please check back later.')"
                />
            @else
                @foreach ($categories as $category)
                    @php $optionCount = $category->orderableOptionCount(); @endphp
                    <div>
                        <div class="flex items-center gap-2.5 mb-3">
                            <span class="h-5 w-1 rounded-full bg-[#8A3330]"></span>
                            <h3 class="font-semibold text-gray-900">{{ $category->name }}</h3>
                            <span class="text-xs font-medium text-[#8A7B6D] bg-[#F7F0E3] border border-[#E5DDD0] rounded-full px-2 py-0.5">{{ $optionCount }} {{ $optionCount === 1 ? __('option') : __('options') }}</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($category->menuItems as $item)
                                <x-menu.item-card :item="$item" :clickable="false" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-customer-layout>
