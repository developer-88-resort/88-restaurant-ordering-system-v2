<x-app-layout>
    <x-slot name="header">
        <section class="py-2">
            <div class="relative flex items-center gap-4 sm:gap-5">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                        <rect x="3" y="4.5" width="16" height="6" rx="2" fill="currentColor" fill-opacity="0.08" stroke-width="1.6" />
                        <path d="M6 10.5v8m-2 0h6m6-8v1" stroke-width="1.6" stroke-linecap="round" />
                        <rect x="13" y="13" width="8" height="8" rx="2.5" fill="currentColor" fill-opacity="0.1" stroke-width="1.6" />
                        <path d="M17 15.25v3.5m-1.75-1.75h3.5" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ __('New Space') }} — {{ $category->name }}
                    </h2>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">
                        {{ __('Bulk-create tables, cottages, or rooms in :area.', ['area' => $category->area->name ?? $category->name]) }}
                    </p>
                </div>
            </div>
        </section>
    </x-slot>

    <div
        x-data="{
            prefix: @js(old('prefix', $prefix ?? '')),
            start: {{ $nextNumber }},
            count: 5,
            get total() {
                return Math.max(0, Math.min(this.count || 0, 50));
            },
            get preview() {
                const names = [];
                for (let i = 0; i < Math.min(this.total, 8); i++) {
                    names.push((this.prefix || '').trim() + ' ' + (Number(this.start || 1) + i));
                }
                return { names, remaining: Math.max(0, this.total - names.length) };
            }
        }"
        class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
    >
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            {{-- data-turbo="false": this form redirects to spaces.index, now an Inertia/React page. --}}
            <form method="POST" action="{{ route('spaces.store-bulk') }}" data-draft-key="space-create-{{ $category->id }}" data-turbo="false">
                @csrf
                <input type="hidden" name="category_id" value="{{ $category->id }}">

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    {{-- Locked once the category has spaces: every table in it
                         shares one prefix (App\Support\SpaceNaming), so a KUBO
                         table can't end up called "Korean resto …". Only an
                         empty category names its first spaces here. --}}
                    <div>
                        <x-input-label for="prefix" :value="__('Name')" />
                        @if ($prefix !== null)
                            <div id="prefix" class="mt-2 flex h-12 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-700" x-text="prefix"></div>
                        @else
                            <x-text-input id="prefix" name="prefix" type="text" class="block mt-2 h-12 w-full !rounded-xl !border-slate-200 !shadow-none focus:!border-slate-400 focus:!ring-slate-200" x-model="prefix" required placeholder="{{ __('e.g. Table') }}" />
                        @endif
                        <x-input-error :messages="$errors->get('prefix')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="start" :value="__('Starting Number')" />
                        <x-text-input id="start" name="start" type="number" min="1" max="9999" class="block mt-2 h-12 w-full !rounded-xl !border-slate-200 !shadow-none focus:!border-slate-400 focus:!ring-slate-200" x-model="start" required />
                        <x-input-error :messages="$errors->get('start')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="count" :value="__('How Many')" />
                        <x-text-input id="count" name="count" type="number" min="1" max="50" class="block mt-2 h-12 w-full !rounded-xl !border-slate-200 !shadow-none focus:!border-slate-400 focus:!ring-slate-200" x-model="count" required />
                        <x-input-error :messages="$errors->get('count')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <p class="text-xs leading-5 text-slate-500">
                        @if ($prefix !== null)
                            {{ __('Spaces in :category are always named ":prefix" plus a number. Numbers already in use are skipped automatically. For a different place (e.g. another restaurant), create its own area first.', ['category' => $category->name, 'prefix' => $prefix]) }}
                        @else
                            {{ __('This is the first set of spaces in :category — the name you give them here is what every later space in :category will use.', ['category' => $category->name]) }}
                        @endif
                    </p>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('spaces.index', ['area' => $category->area_id]) }}" data-turbo="false" class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">{{ __('Cancel') }}</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                            <rect x="3" y="4.5" width="16" height="6" rx="2" fill="currentColor" fill-opacity="0.08" stroke-width="1.6" />
                        <path d="M6 10.5v8m-2 0h6m6-8v1" stroke-width="1.6" stroke-linecap="round" />
                        <rect x="13" y="13" width="8" height="8" rx="2.5" fill="currentColor" fill-opacity="0.1" stroke-width="1.6" />
                        <path d="M17 15.25v3.5m-1.75-1.75h3.5" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        {{ __('Create Spaces') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Preview panel --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-slate-900">{{ __('Preview') }}</h3>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500" x-show="total > 0">
                    <span x-text="total"></span> {{ __('total') }}
                </span>
            </div>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('How the new space names will look.') }}</p>

            <ul class="mt-5 space-y-2">
                <template x-for="(name, index) in preview.names" :key="name">
                    <li class="flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-xs font-medium tabular-nums text-slate-500" x-text="index + 1"></span>
                        <span class="truncate text-sm font-semibold text-slate-700" x-text="name"></span>
                    </li>
                </template>
            </ul>

            <p x-show="preview.remaining > 0" class="mt-2 text-xs font-semibold text-slate-500" x-text="'+ ' + preview.remaining + ' {{ __('more') }}'"></p>
            <p x-show="preview.names.length === 0" class="mt-4 text-sm text-slate-500">{{ __('Enter details to preview the space names.') }}</p>
        </div>
    </div>
</x-app-layout>
