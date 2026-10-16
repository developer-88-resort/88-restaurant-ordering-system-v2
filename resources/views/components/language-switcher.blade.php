@props(['align' => 'right', 'compact' => false])

{{-- compact (the staff top bar): a phone shows the icon only, there's no room for the name. --}}
<x-dropdown :align="$align" width="40">
    <x-slot name="trigger">
        <button type="button" title="{{ \App\Support\AvailableLocales::labels()[app()->getLocale()] ?? 'English' }}" class="flex items-center gap-1.5 {{ $compact ? 'px-2.5 sm:px-3' : 'px-3' }} py-1.5 rounded-lg border border-[#D9CCBA] text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
            </svg>
            <span @class(['sr-only sm:not-sr-only' => $compact])>{{ \App\Support\AvailableLocales::labels()[app()->getLocale()] ?? 'English' }}</span>
        </button>
    </x-slot>

    <x-slot name="content">
        @foreach (\App\Support\AvailableLocales::labels() as $code => $label)
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <button type="submit" class="w-full flex items-center justify-between px-4 py-2 text-sm text-left {{ app()->getLocale() === $code ? 'font-semibold text-[#8A3330] bg-[#FAF6EE]' : 'text-gray-700 hover:bg-gray-50' }}">
                    {{ $label }}
                    @if (app()->getLocale() === $code)
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    @endif
                </button>
            </form>
        @endforeach
    </x-slot>
</x-dropdown>
