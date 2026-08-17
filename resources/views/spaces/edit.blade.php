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

            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 sm:gap-5">
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-7 w-7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125" />
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                            {{ __('Edit Space') }}
                        </h2>
                        <p class="mt-1.5 max-w-md text-sm leading-6 text-white/55">
                            {{ __('Editing :name.', ['name' => $space->name]) }}
                        </p>
                    </div>
                </div>

                <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.1em] text-white/80 backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full {{ $space->status === \App\Enums\SpaceStatus::Available ? 'bg-emerald-400' : 'bg-amber-300' }}"></span>
                    {{ $space->status->label() }}
                </span>
            </div>
        </section>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-2xl border border-[#E5DDD0] bg-white p-6 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-8">
            {{-- data-turbo="false": this form redirects to spaces.index, now an Inertia/React page. --}}
            <form method="POST" action="{{ route('spaces.update', $space) }}" data-draft-key="space-edit-{{ $space->id }}" data-turbo="false">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" :value="__('Space Name')" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $space->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <span class="relative mt-1 block">
                            <select id="status" name="status" class="h-[2.6rem] w-full appearance-none bg-none rounded-lg border-gray-300 py-2 pl-3 pr-9 shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]" required>
                                @foreach (\App\Enums\SpaceStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $space->status->value) === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9B8C84]" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 9 5.25 5.25L17.25 9" />
                            </svg>
                        </span>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-5 max-w-xs">
                    <x-input-label for="capacity" :value="__('Seat Count')" />
                    <x-text-input id="capacity" name="capacity" type="number" min="1"
                                  class="block mt-1 w-full" :value="old('capacity', $space->capacity)"
                                  placeholder="{{ __('e.g. 4') }}" />
                    <p class="mt-1 text-xs text-[#9A8B84]">{{ __('Optional.') }}</p>
                    <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                </div>

                <div class="mt-6">
                    <x-input-label :value="__('Shared Table')" />
                    <p class="mt-1 text-xs leading-5 text-[#9A8B84]">{{ __('Combine this table with others for large groups. Whichever one gets picked for an order, the rest are marked Occupied too.') }}</p>
                    @php $sharedIds = old('shared_space_ids', $space->sharedTables->pluck('id')->all()); @endphp
                    @if ($availableSpaces->isEmpty())
                        <p class="mt-3 rounded-xl border border-dashed border-[#DCCFC1] bg-[#FCFAF7] px-4 py-3 text-sm text-[#8B7D75]">{{ __('No other available tables in this area to combine with.') }}</p>
                    @else
                        <div class="mt-3 grid max-h-56 grid-cols-1 gap-1.5 overflow-y-auto rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-2 sm:grid-cols-2">
                            @foreach ($availableSpaces as $other)
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-semibold text-[#463934] transition hover:bg-white">
                                    <input type="checkbox" name="shared_space_ids[]" value="{{ $other->id }}"
                                           @checked(in_array($other->id, $sharedIds))
                                           class="h-4 w-4 rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]">
                                    {{ $other->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('shared_space_ids')" class="mt-2" />
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-[#EEE6DC] pt-6">
                    <a href="{{ route('spaces.index', ['area' => $space->area_id]) }}" data-turbo="false" class="text-sm font-semibold text-[#766860] hover:text-[#302521]">{{ __('Cancel') }}</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-[#E5DDD0] bg-white p-5 text-center shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#9A8B84]">{{ __('QR Code') }}</p>
                <h3 class="mt-1 text-sm font-bold text-[#251C19]">{{ $space->name }}</h3>

                <div class="mx-auto mt-4 grid w-fit place-items-center rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-3">
                    <img src="{{ route('spaces.qr-code', $space) }}" alt="{{ __('QR code for') }} {{ $space->name }}" class="h-40 w-40 rounded-lg">
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a
                        href="{{ route('spaces.print', $space) }}"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2.5 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                        </svg>
                        {{ __('Print') }}
                    </a>

                    <a
                        href="{{ route('spaces.qr-code', ['space' => $space, 'download' => 1]) }}"
                        data-turbo="false"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-[#E5D9CC] bg-[#FCF9F5] px-3 py-2.5 text-xs font-bold text-[#6E5E57] transition hover:border-[#CDAEA4] hover:bg-[#F8EEEA] hover:text-[#8A3330]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        {{ __('SVG') }}
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-[#EEE6DC] bg-[#FCFAF7] p-5">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#9A8B84]">{{ __('Quick tips') }}</p>
                <ul class="mt-2.5 space-y-2 text-xs leading-5 text-[#6C5E57]">
                    <li class="flex gap-2">
                        <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-[#8A3330]"></span>
                        {{ __('Reprint the QR code if the table is moved or replaced.') }}
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-[#8A3330]"></span>
                        {{ __('Setting a table to Disabled hides it from the ordering picker.') }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
