<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Areas') }}
            </h2>
            <div class="flex items-center gap-4">
                <a href="{{ route('spaces.index') }}" data-turbo="false" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to Spaces') }}</a>
                <a href="{{ route('areas.create') }}">
                    <x-primary-button>{{ __('New Area') }}</x-primary-button>
                </a>
            </div>
        </div>
    </x-slot>

    @if ($areas->isEmpty())
        <x-empty-state
            :title="__('No areas yet')"
            :description="__('Areas group your spaces, e.g. Cottages, Dining Area, Rooms.')"
            :actionLabel="__('New Area')"
            :actionHref="route('areas.create')"
        />
    @else
        <div class="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E5DDD0]">
                    <thead class="bg-[#FAF6EE]">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Categories') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Spaces') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5DDD0]">
                        @foreach ($areas as $area)
                            <tr class="hover:bg-[#FAF6EE]">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $area->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $area->categories_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $area->spaces_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $area->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $area->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <a href="{{ route('areas.edit', $area) }}" class="text-[#8A3330] hover:text-[#5f2120]">{{ __('Edit') }}</a>
                                    <x-confirm-form
                                        :action="route('areas.destroy', $area)"
                                        method="DELETE"
                                        class="inline"
                                        :title="__('Delete this area?')"
                                        :message="__('This will permanently remove :name.', ['name' => $area->name])"
                                        :confirm-label="__('Delete')"
                                    >
                                        <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                    </x-confirm-form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
