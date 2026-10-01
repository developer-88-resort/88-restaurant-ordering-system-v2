<x-app-layout>
    <x-slot name="header">
        <section class="py-2">
            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 3.75h13.5V21H5.25V3.75z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5" />
                        </svg>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                                {{ __('Areas') }}
                            </h2>

                            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-500">
                                {{ trans_choice(':count area|:count areas', $areas->count(), ['count' => $areas->count()]) }}
                            </span>
                        </div>

                        <p class="mt-1.5 max-w-xl text-sm leading-6 text-slate-500">
                            {{ __('Group your spaces into cottages, dining areas, rooms, or anything else the property needs.') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2.5 sm:flex-row">
                    <a
                        href="{{ route('spaces.index') }}"
                        data-turbo="false"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        {{ __('Back to Spaces') }}
                    </a>

                    {{-- x-data (empty) just gives this button access to Alpine's
                         $dispatch magic — the modal itself lives in the body
                         slot, a separate DOM subtree from this header slot, so
                         a window-level custom event is how the two talk to
                         each other instead of a shared x-data scope. --}}
                    <button
                        type="button"
                        x-data
                        @click="$dispatch('open-area-modal', { mode: 'create' })"
                        class="group inline-flex min-h-11 items-center justify-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7" rx="2" fill="currentColor" fill-opacity="0.08" stroke="currentColor" stroke-width="1.6" />
                            <rect x="14" y="3" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.6" />
                            <rect x="3" y="14" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.6" />
                            <path d="M17.5 14v7M14 17.5h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        {{ __('New Area') }}
                    </button>
                </div>
            </div>
        </section>
    </x-slot>

    <div
        x-data="{
            open: false,
            mode: 'create',
            submitting: false,
            form: { id: null, name: '', sort_order: {{ $nextSortOrder }}, is_active: true },
        }"
        x-on:open-area-modal.window="
            mode = $event.detail.mode;
            submitting = false;
            form = mode === 'create'
                ? { id: null, name: '', sort_order: {{ $nextSortOrder }}, is_active: true }
                : { id: $event.detail.id, name: $event.detail.name, sort_order: $event.detail.sort_order, is_active: $event.detail.is_active };
            open = true;
        "
        x-on:keydown.escape.window="if (open && !submitting) open = false"
        x-init="@if ($errors->any())
            mode = {{ Js::from(old('_method') === 'PUT' ? 'edit' : 'create') }};
            form = {{ Js::from(['id' => old('area_id'), 'name' => old('name', ''), 'sort_order' => old('sort_order', $nextSortOrder), 'is_active' => (bool) old('is_active', true)]) }};
            open = true;
        @endif"
    >
        @if ($areas->isEmpty())
            <x-empty-state
                :title="__('No areas yet')"
                :description="__('Areas group your spaces, e.g. Cottages, Dining Area, Rooms.')"
                :actionLabel="__('New Area')"
                :actionHref="route('areas.create')"
            />
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Categories') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Spaces') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($areas as $area)
                                <tr class="transition-colors duration-150 hover:bg-slate-50 focus-within:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3.5">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 3.75h13.5V21H5.25V3.75z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5" />
                                                </svg>
                                            </span>
                                            <span class="text-sm font-semibold text-slate-900">{{ $area->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full bg-slate-100/80 px-2.5 py-1 text-xs font-medium tabular-nums text-slate-600">
                                            {{ trans_choice(':count category|:count categories', $area->categories_count, ['count' => $area->categories_count]) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full bg-slate-100/80 px-2.5 py-1 text-xs font-medium tabular-nums text-slate-600">
                                            {{ trans_choice(':count space|:count spaces', $area->spaces_count, ['count' => $area->spaces_count]) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $area->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $area->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            {{ $area->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                @click="$dispatch('open-area-modal', { mode: 'edit', id: {{ $area->id }}, name: {{ Js::from($area->name) }}, sort_order: {{ $area->sort_order }}, is_active: {{ $area->is_active ? 'true' : 'false' }} })"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                                                </svg>
                                                {{ __('Edit') }}
                                            </button>

                                            <x-confirm-form
                                                :action="route('areas.destroy', $area)"
                                                method="DELETE"
                                                class="inline"
                                                :title="__('Delete this area?')"
                                                :message="__('This will permanently remove :name.', ['name' => $area->name])"
                                                :confirm-label="__('Delete')"
                                            >
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-xl border border-red-100 bg-white px-3 py-2 text-xs font-bold text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5v3H3.75v-3z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25v10.5h13.5V8.25M9.75 12h4.5" />
                                                    </svg>
                                                    {{ __('Delete') }}
                                                </button>
                                            </x-confirm-form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 sm:hidden">
                @foreach ($areas as $area)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-slate-50 text-slate-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 3.75h13.5V21H5.25V3.75z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $area->name }}</p>
                                    <p class="mt-0.5 text-xs font-medium text-slate-500">
                                        {{ trans_choice(':count category|:count categories', $area->categories_count, ['count' => $area->categories_count]) }}
                                        &middot;
                                        {{ trans_choice(':count space|:count spaces', $area->spaces_count, ['count' => $area->spaces_count]) }}
                                    </p>
                                </div>
                            </div>

                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $area->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $area->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                {{ $area->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3.5">
                            <button
                                type="button"
                                @click="$dispatch('open-area-modal', { mode: 'edit', id: {{ $area->id }}, name: {{ Js::from($area->name) }}, sort_order: {{ $area->sort_order }}, is_active: {{ $area->is_active ? 'true' : 'false' }} })"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                                </svg>
                                {{ __('Edit') }}
                            </button>

                            <x-confirm-form
                                :action="route('areas.destroy', $area)"
                                method="DELETE"
                                :title="__('Delete this area?')"
                                :message="__('This will permanently remove :name.', ['name' => $area->name])"
                                :confirm-label="__('Delete')"
                            >
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-100 bg-white px-3 py-2 text-xs font-bold text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5v3H3.75v-3z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25v10.5h13.5V8.25M9.75 12h4.5" />
                                    </svg>
                                    {{ __('Delete') }}
                                </button>
                            </x-confirm-form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- New Area / Edit Area modal — shared by the header's "New Area"
             button and every row's "Edit" button (see the open-area-modal
             window event). A real full-page POST/PUT, not fetch/AJAX: on a
             validation failure Laravel redirects back here with $errors and
             old() input, and the x-init above reopens this same modal with
             that state restored, so it never needs its own route/page. --}}
        <div
            x-show="open" x-cloak
            role="dialog" aria-modal="true" aria-labelledby="area-modal-title"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
        >
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="if (!submitting) open = false"
                class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            ></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                class="relative w-full overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl"
            >
                <form
                    method="POST"
                    x-bind:action="mode === 'create' ? '{{ route('areas.store') }}' : '{{ rtrim(url('areas'), '/') }}/' + form.id"
                    @submit="submitting = true"
                >
                    @csrf
                    <template x-if="mode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <input type="hidden" name="area_id" x-bind:value="form.id">

                    <div class="flex items-start gap-3 border-b border-slate-200 px-5 pb-4 pt-5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-50 text-slate-500">
                            <svg x-show="mode === 'create'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                            <svg x-show="mode === 'edit'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="area-modal-title" class="font-bold text-gray-900">
                                <span x-show="mode === 'create'">{{ __('New Area') }}</span>
                                <span x-show="mode === 'edit'">{{ __('Edit Area') }}</span>
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Group your spaces, e.g. Cottages, Dining Area, Rooms.') }}</p>
                        </div>
                        <button type="button" @click="if (!submitting) open = false" aria-label="{{ __('Close') }}" class="shrink-0 text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                        <div>
                            <x-input-label for="area_name" :value="__('Area Name')" />
                            <x-text-input id="area_name" name="name" type="text" class="mt-1 block w-full" x-model="form.name" placeholder="{{ __('e.g. Cottages') }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="mt-5">
                            <x-input-label for="area_sort_order" :value="__('Sort Order')" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('Lower numbers appear first on the Spaces page.') }}</p>
                            <x-text-input id="area_sort_order" name="sort_order" type="number" min="0" class="mt-1.5 block w-full" x-model="form.sort_order" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                        </div>

                        <template x-if="mode === 'edit'">
                            <label class="mt-5 flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3.5">
                                <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="h-4 w-4 rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">{{ __('Active') }}</span>
                                    <span class="block text-xs text-slate-500">{{ __('Inactive areas are hidden from the ordering picker.') }}</span>
                                </span>
                            </label>
                        </template>
                    </div>

                    <div class="flex gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
                        <button type="button" :disabled="submitting" @click="if (!submitting) open = false"
                                class="flex-1 rounded-lg border border-slate-200 bg-white px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" :disabled="submitting"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-[#8A3330] px-4 py-3 font-semibold text-white transition hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-70">
                            <svg x-show="submitting" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-show="!submitting" x-text="mode === 'create' ? '{{ __('Create Area') }}' : '{{ __('Save Changes') }}'"></span>
                            <span x-show="submitting" x-cloak>{{ __('Saving...') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
