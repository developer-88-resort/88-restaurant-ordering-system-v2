<x-app-layout>
    <x-slot name="header">
        <section class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>

            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative flex items-center gap-4 sm:gap-5">
                <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                        {{ __('Edit Area') }}
                    </h2>
                    <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                        {{ __('Editing :name.', ['name' => $area->name]) }}
                    </p>
                </div>
            </div>
        </section>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-2xl border border-[#E5DDD0] bg-white p-6 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-8">
            <form method="POST" action="{{ route('areas.update', $area) }}" data-draft-key="area-edit-{{ $area->id }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" :value="__('Area Name')" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $area->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="sort_order" :value="__('Sort Order')" />
                        <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="block mt-1 w-full" :value="old('sort_order', $area->sort_order)" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>
                </div>
                <p class="mt-2.5 text-xs text-[#9A8B84]">{{ __('Lower sort order numbers appear first on the Spaces page.') }}</p>

                <label for="is_active" class="mt-6 flex items-center gap-3 rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] px-4 py-3.5">
                    <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $area->is_active)) class="h-4 w-4 rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]">
                    <span>
                        <span class="block text-sm font-bold text-[#251C19]">{{ __('Active') }}</span>
                        <span class="block text-xs text-[#8B7D75]">{{ __('Inactive areas are hidden from the ordering picker.') }}</span>
                    </span>
                </label>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 016 4.5h3A2.25 2.25 0 0111.25 6.75v3A2.25 2.25 0 019 12H6a2.25 2.25 0 01-2.25-2.25v-3zm9 0A2.25 2.25 0 0115 4.5h3a2.25 2.25 0 012.25 2.25v3A2.25 2.25 0 0118 12h-3a2.25 2.25 0 01-2.25-2.25v-3zm-9 9A2.25 2.25 0 016 13.5h3a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 019 19.5H6a2.25 2.25 0 01-2.25-2.25v-1.5zm9 0A2.25 2.25 0 0115 13.5h3a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 19.5h-3a2.25 2.25 0 01-2.25-2.25v-1.5z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-lg font-black text-[#251C19]">{{ $area->categories_count }}</p>
                                <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-[#9A8B84]">{{ trans_choice(':count category|:count categories', $area->categories_count, ['count' => $area->categories_count]) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v4H4V6z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 10v8M17 10v8" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-lg font-black text-[#251C19]">{{ $area->spaces_count }}</p>
                                <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-[#9A8B84]">{{ trans_choice(':count space|:count spaces', $area->spaces_count, ['count' => $area->spaces_count]) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-[#EEE6DC] pt-6">
                    <a href="{{ route('areas.index') }}" class="text-sm font-semibold text-[#766860] hover:text-[#302521]">{{ __('Cancel') }}</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>

        <x-area-reference-list :areas="$existingAreas" />
    </div>
</x-app-layout>
