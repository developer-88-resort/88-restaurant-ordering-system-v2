<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Category') }} — {{ $area->name }}
        </h2>
    </x-slot>

    <div class="max-w-lg" x-data="{ isFree: {{ old('is_free') ? 'true' : 'false' }} }">
        <div class="bg-white border border-[#E5DDD0] rounded-xl p-8">
            {{-- data-turbo="false": this form redirects to spaces.index, now an Inertia/React page. --}}
            <form method="POST" action="{{ route('space-categories.store') }}" data-draft-key="space-category-create-{{ $area->id }}" data-turbo="false">
                @csrf
                <input type="hidden" name="area_id" value="{{ $area->id }}">

                <div>
                    <x-input-label for="name" :value="__('Category Name')" />
                    <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name')" placeholder="{{ __('e.g. Standard Kubo') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="mt-5">
                    <x-input-label for="capacity" :value="__('Capacity (optional)')" />
                    <x-text-input id="capacity" name="capacity" type="number" min="1" class="block mt-1 w-full" :value="old('capacity')" />
                    <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                </div>

                <div class="mt-5">
                    <x-input-label for="rental_fee" :value="__('Rental Fee (optional)')" />
                    <x-text-input id="rental_fee" name="rental_fee" type="number" step="0.01" min="0" class="block mt-1 w-full" :value="old('rental_fee')" />
                    <x-input-error :messages="$errors->get('rental_fee')" class="mt-2" />
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <input type="checkbox" id="is_free" name="is_free" value="1" x-model="isFree" @checked(old('is_free')) class="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]">
                    <x-input-label for="is_free" :value="__('Shared Capacity (auto-assign)')" class="!mb-0" />
                </div>
                <p class="mt-1 text-xs text-gray-500 pl-6">{{ __('Staff won\'t pick a specific space when placing an order — the system automatically assigns the next available one under this category. If you add individual spaces here (e.g. Bar 1, Bar 2...), it\'s full once none are Available. If you leave it with no spaces at all (e.g. Free Cottage), it uses the Max Active Occupancy below instead.') }}</p>

                <div class="mt-5" x-show="isFree">
                    <x-input-label for="max_active_occupancy" :value="__('Max Active Occupancy (only used if this category has no individual spaces)')" />
                    <x-text-input id="max_active_occupancy" name="max_active_occupancy" type="number" min="1" class="block mt-1 w-full" :value="old('max_active_occupancy')" />
                    <x-input-error :messages="$errors->get('max_active_occupancy')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-8 space-x-3 border-t border-[#E5DDD0] pt-6">
                    <a href="{{ route('spaces.index', ['area' => $area->id]) }}" data-turbo="false" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Create Category') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
