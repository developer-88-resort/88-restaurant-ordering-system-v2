<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Space') }} — {{ $category->name }}
        </h2>
    </x-slot>

    @php $suggestedName = __('Table'); @endphp

    <div
        x-data="{
            prefix: '{{ $suggestedName }}',
            start: 1,
            count: 5,
            get preview() {
                const names = [];
                const total = Math.max(0, Math.min(this.count || 0, 9999));
                for (let i = 0; i < Math.min(total, 8); i++) {
                    names.push((this.prefix || '').trim() + ' ' + (Number(this.start || 1) + i));
                }
                return { names, remaining: Math.max(0, total - names.length) };
            }
        }"
        class="flex flex-col lg:flex-row gap-6 items-start"
    >
        <div class="flex-1 w-full">
            <div class="bg-white border border-[#E5DDD0] rounded-xl p-8">
                {{-- data-turbo="false": this form redirects to spaces.index, now an Inertia/React page. --}}
                <form method="POST" action="{{ route('spaces.store-bulk') }}" data-draft-key="space-create-{{ $category->id }}" data-turbo="false">
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $category->id }}">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div class="sm:col-span-1">
                            <x-input-label for="prefix" :value="__('Prefix')" />
                            <x-text-input id="prefix" name="prefix" type="text" class="block mt-1 w-full" x-model="prefix" required />
                            <x-input-error :messages="$errors->get('prefix')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="start" :value="__('Starting Number')" />
                            <x-text-input id="start" name="start" type="number" min="1" max="9999" class="block mt-1 w-full" x-model="start" required />
                            <x-input-error :messages="$errors->get('start')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="count" :value="__('How Many')" />
                            <x-text-input id="count" name="count" type="number" min="1" max="50" class="block mt-1 w-full" x-model="count" required />
                            <x-input-error :messages="$errors->get('count')" class="mt-2" />
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-gray-500">{{ __('Existing space names are automatically skipped, so it\'s safe to add more later.') }}</p>

                    <div class="flex items-center justify-end mt-8 space-x-3 border-t border-[#E5DDD0] pt-6">
                        <a href="{{ route('spaces.index', ['area' => $category->area_id]) }}" data-turbo="false" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        <x-primary-button>{{ __('Create Spaces') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Side panel --}}
        <div class="w-full lg:w-80 shrink-0">
            <div class="bg-white border border-[#E5DDD0] rounded-xl p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[#8A7B9E] mb-4">{{ __('Preview') }}</h3>
                <ul class="space-y-2">
                    <template x-for="name in preview.names" :key="name">
                        <li class="text-sm text-gray-700 border border-dashed border-[#D9CCBA] rounded-lg px-3 py-2 bg-[#FAF6EE]" x-text="name"></li>
                    </template>
                </ul>
                <p x-show="preview.remaining > 0" class="mt-2 text-xs text-gray-400" x-text="'+ ' + preview.remaining + ' {{ __('more') }}'"></p>
                <p x-show="preview.names.length === 0" class="text-sm text-gray-400">{{ __('Enter details to preview the space names.') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
