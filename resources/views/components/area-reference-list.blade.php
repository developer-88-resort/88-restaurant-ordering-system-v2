@props(['areas'])

<div class="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
    <h3 class="text-sm font-bold text-[#251C19]">{{ __('Existing Areas') }}</h3>
    <p class="mt-1 text-xs leading-5 text-[#8B7D75]">{{ __('Reference for naming and sort order.') }}</p>

    @if ($areas->isEmpty())
        <p class="mt-4 text-sm text-[#B0A49E]">{{ __('No areas yet — this will be your first one.') }}</p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($areas as $area)
                <li class="flex items-center gap-3 rounded-xl border border-[#EEE6DC] bg-[#FCFAF7] px-3 py-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#F3E1DC] text-[#8A3330]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 3.75h13.5V21H5.25V3.75z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-[#251C19] {{ $area->is_active ? '' : 'opacity-50' }}">
                            {{ $area->name }}
                            @unless ($area->is_active)
                                <span class="text-[10px] font-medium text-[#B0A49E]">({{ __('inactive') }})</span>
                            @endunless
                        </p>
                        <p class="mt-0.5 text-[11px] font-medium text-[#9A8B84]">
                            {{ trans_choice(':count space|:count spaces', $area->spaces_count, ['count' => $area->spaces_count]) }}
                        </p>
                    </div>

                    <span class="shrink-0 rounded-lg bg-[#F5EFE7] px-2 py-1 font-mono text-[11px] font-bold text-[#6C5E57]">
                        {{ $area->sort_order }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
