<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Space') }} — {{ $space->name }}
        </h2>
    </x-slot>

    <div class="flex flex-col lg:flex-row gap-6 items-start">
        <div class="flex-1 max-w-2xl w-full">
            <div class="bg-white border border-[#E5DDD0] rounded-xl p-8">
                {{-- data-turbo="false": this form redirects to spaces.index, now an Inertia/React page. --}}
                <form method="POST" action="{{ route('spaces.update', $space) }}" data-draft-key="space-edit-{{ $space->id }}" data-turbo="false">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div class="sm:col-span-2">
                            <x-input-label for="name" :value="__('Space Name')" />
                            <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $space->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm" required>
                                @foreach (\App\Enums\SpaceStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $space->status->value) === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="capacity" :value="__('Seat Count')" />
                            <x-text-input id="capacity" name="capacity" type="number" min="1"
                                          class="block mt-1 w-full" :value="old('capacity', $space->capacity)"
                                          placeholder="{{ __('e.g. 4') }}" />
                            <p class="text-xs text-gray-500 mt-1">{{ __('Optional.') }}</p>
                            <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                        </div>

                    </div>

                    <div class="mt-5">
                        <x-input-label :value="__('Shared Table')" />
                        <p class="text-xs text-gray-500 mb-2">{{ __('Combine this table with others for large groups. Whichever one gets picked for an order, the rest are marked Occupied too.') }}</p>
                        @php $sharedIds = old('shared_space_ids', $space->sharedTables->pluck('id')->all()); @endphp
                        @if ($availableSpaces->isEmpty())
                            <p class="text-sm text-gray-400">{{ __('No other available tables in this area to combine with.') }}</p>
                        @else
                            <div class="max-h-56 overflow-y-auto border border-[#E5DDD0] rounded-lg divide-y divide-[#E5DDD0]">
                                @foreach ($availableSpaces as $other)
                                    <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-[#FAF6EE] cursor-pointer">
                                        <input type="checkbox" name="shared_space_ids[]" value="{{ $other->id }}"
                                               @checked(in_array($other->id, $sharedIds))
                                               class="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]">
                                        {{ $other->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <x-input-error :messages="$errors->get('shared_space_ids')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-8 space-x-3 border-t border-[#E5DDD0] pt-6">
                        <a href="{{ route('spaces.index', ['area' => $space->area_id]) }}" data-turbo="false" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div class="w-full lg:w-72 shrink-0">
            <div class="bg-white border border-[#E5DDD0] rounded-xl p-6 text-center">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('QR Code') }}</h3>
                <img src="{{ route('spaces.qr-code', $space) }}" alt="{{ __('QR code for') }} {{ $space->name }}" class="mx-auto h-40 w-40 border border-[#E5DDD0] rounded">
                <a href="{{ route('spaces.print', $space) }}" target="_blank" class="block mt-3 text-sm text-[#8A3330] hover:underline">{{ __('Print') }}</a>
                <a href="{{ route('spaces.qr-code', ['space' => $space, 'download' => 1]) }}" data-turbo="false" class="block mt-1 text-sm text-[#8A3330] hover:underline">{{ __('Download SVG') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
