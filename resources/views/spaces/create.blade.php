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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                        {{ __('New Space') }} — {{ $category->name }}
                    </h2>
                    <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                        {{ __('Bulk-create tables, cottages, or rooms in :area.', ['area' => $category->area->name ?? $category->name]) }}
                    </p>
                </div>
            </div>
        </section>
    </x-slot>

    @php $suggestedName = __('Table'); @endphp

    <div
        x-data="{
            prefix: '{{ $suggestedName }}',
            start: 1,
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
        <div class="rounded-2xl border border-[#E5DDD0] bg-white p-6 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-8">
            {{-- data-turbo="false": this form redirects to spaces.index, now an Inertia/React page. --}}
            <form method="POST" action="{{ route('spaces.store-bulk') }}" data-draft-key="space-create-{{ $category->id }}" data-turbo="false">
                @csrf
                <input type="hidden" name="category_id" value="{{ $category->id }}">

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
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

                <div class="mt-5 flex items-center gap-2.5 rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] px-4 py-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0 text-[#8A3330]" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <p class="text-xs leading-5 text-[#6C5E57]">{{ __('Existing space names are automatically skipped, so it\'s safe to add more later.') }}</p>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-[#EEE6DC] pt-6">
                    <a href="{{ route('spaces.index', ['area' => $category->area_id]) }}" data-turbo="false" class="text-sm font-semibold text-[#766860] hover:text-[#302521]">{{ __('Cancel') }}</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('Create Spaces') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Preview panel --}}
        <div class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-[#251C19]">{{ __('Preview') }}</h3>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#F3E1DC] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-[#8A3330]" x-show="total > 0">
                    <span x-text="total"></span> {{ __('total') }}
                </span>
            </div>
            <p class="mt-1 text-xs leading-5 text-[#8B7D75]">{{ __('How the new space names will look.') }}</p>

            <ul class="mt-4 space-y-1.5">
                <template x-for="(name, index) in preview.names" :key="name">
                    <li class="flex items-center gap-2.5 rounded-xl border border-dashed border-[#DCCFC1] bg-[#FCFAF7] px-3 py-2.5">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-[#F3E1DC] text-[10px] font-black text-[#8A3330]" x-text="index + 1"></span>
                        <span class="truncate text-sm font-semibold text-[#463934]" x-text="name"></span>
                    </li>
                </template>
            </ul>

            <p x-show="preview.remaining > 0" class="mt-2 text-xs font-semibold text-[#9A8B84]" x-text="'+ ' + preview.remaining + ' {{ __('more') }}'"></p>
            <p x-show="preview.names.length === 0" class="mt-4 text-sm text-[#B0A49E]">{{ __('Enter details to preview the space names.') }}</p>
        </div>
    </div>
</x-app-layout>
