@props([
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'variant' => 'compact',
    'eyebrow' => null,
])

@if ($variant === 'onboarding')
    <section
        {{ $attributes->merge([
            'class' => 'relative isolate overflow-hidden rounded-[2rem] border border-[#E6DCCF] bg-[#FFFDFC] shadow-[0_30px_80px_-45px_rgba(59,36,31,0.45)]'
        ]) }}
    >
        {{-- Background decorations --}}
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0"
            style="background: radial-gradient(circle at top right, rgba(138, 51, 48, 0.14), transparent 36%), linear-gradient(135deg, #FFFCF7 0%, #FFFFFF 66%);"
        ></div>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full border border-[#8A3330]/10"
        ></div>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute -left-12 -top-12 h-48 w-48 rounded-full border border-[#8A3330]/10"
        ></div>

        <div class="relative grid min-h-[450px] lg:grid-cols-[1.08fr_.92fr]">
            {{-- Main content --}}
            <div class="relative z-10 flex flex-col justify-center p-7 sm:p-10 lg:p-12 xl:p-14">
                <div class="inline-flex w-fit items-center gap-2 rounded-full border border-[#8A3330]/15 bg-[#8A3330]/5 px-3 py-1.5">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#8A3330] opacity-30"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-[#8A3330]"></span>
                    </span>

                    <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#8A3330]">
                        {{ $eyebrow ?: __('Order workspace') }}
                    </span>
                </div>

                <h3 class="mt-6 max-w-xl text-3xl font-bold tracking-[-0.035em] text-[#211816] sm:text-4xl lg:text-[2.75rem] lg:leading-[1.08]">
                    {{ $title }}
                </h3>

                @if ($description)
                    <p class="mt-4 max-w-xl text-sm leading-7 text-[#6F625C] sm:text-base">
                        {{ $description }}
                    </p>
                @endif

                @if ($actionLabel && $actionHref)
                    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <a
                            href="{{ $actionHref }}"
                            class="group inline-flex items-center justify-center gap-2.5 rounded-2xl bg-[#8A3330] px-5 py-3.5 text-sm font-semibold text-white shadow-[0_14px_30px_-14px_rgba(138,51,48,0.85)] transition duration-200 hover:-translate-y-0.5 hover:bg-[#742927] hover:shadow-[0_18px_35px_-14px_rgba(138,51,48,0.9)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/20"
                        >
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-white/15">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </span>

                            {{ $actionLabel }}

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>

                        <div class="flex items-center gap-2 text-xs font-medium text-[#81736C]">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                                class="h-4 w-4 text-[#8A3330]"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>

                            {{ __('Fast entry for walk-in customers') }}
                        </div>
                    </div>
                @endif

                {{-- Quick workflow --}}
                <div class="mt-10 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-[#E8DED2] bg-white/75 p-4 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">
                                01
                            </span>

                            <p class="text-xs font-semibold leading-5 text-[#443833]">
                                {{ __('Add order items') }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E8DED2] bg-white/75 p-4 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">
                                02
                            </span>

                            <p class="text-xs font-semibold leading-5 text-[#443833]">
                                {{ __('Assign a location') }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E8DED2] bg-white/75 p-4 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">
                                03
                            </span>

                            <p class="text-xs font-semibold leading-5 text-[#443833]">
                                {{ __('Review and submit') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Decorative order preview --}}
            <div class="relative flex min-h-[390px] items-center justify-center overflow-hidden bg-[#F3ECE1] p-8 sm:p-12">
                <div
                    aria-hidden="true"
                    class="absolute inset-0 opacity-40"
                    style="background-image: radial-gradient(rgba(138, 51, 48, 0.22) 1px, transparent 1px); background-size: 20px 20px;"
                ></div>

                <div aria-hidden="true" class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-[#8A3330]/10 blur-3xl"></div>
                <div aria-hidden="true" class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-white/70 blur-3xl"></div>

                <div class="relative w-full max-w-sm">
                    <div
                        aria-hidden="true"
                        class="absolute inset-x-8 top-3 h-full rotate-6 rounded-[1.75rem] border border-white/70 bg-white/45 shadow-sm"
                    ></div>

                    <div
                        aria-hidden="true"
                        class="absolute inset-x-8 top-3 h-full -rotate-6 rounded-[1.75rem] border border-[#8A3330]/10 bg-[#EAD9CF]/80 shadow-sm"
                    ></div>

                    <div class="relative rounded-[1.85rem] bg-[#241917] p-3 shadow-[0_32px_60px_-24px_rgba(54,31,27,0.65)]">
                        <div class="rounded-[1.4rem] bg-white p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#9A8B84]">
                                        {{ __('Order preview') }}
                                    </p>

                                    <p class="mt-1 font-mono text-lg font-bold text-[#211816]">
                                        #NEW-ORDER
                                    </p>
                                </div>

                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#F3E1DC] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#8A3330]">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#8A3330]"></span>
                                    {{ __('Draft') }}
                                </span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-[#F8F4EE] p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#A1938C]">
                                        {{ __('Location') }}
                                    </p>

                                    <p class="mt-1 text-xs font-bold text-[#3C302B]">
                                        {{ __('Not selected') }}
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-[#F8F4EE] p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#A1938C]">
                                        {{ __('Customer') }}
                                    </p>

                                    <p class="mt-1 text-xs font-bold text-[#3C302B]">
                                        {{ __('Walk-in') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <div class="flex items-center gap-3 rounded-2xl border border-[#EEE7DD] p-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">
                                        1
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="h-2.5 w-3/4 rounded-full bg-[#DDD3C9]"></div>
                                        <div class="mt-2 h-2 w-1/3 rounded-full bg-[#EEE7DF]"></div>
                                    </div>

                                    <div class="h-3 w-12 rounded-full bg-[#E5DDD4]"></div>
                                </div>

                                <div class="flex items-center gap-3 rounded-2xl border border-[#EEE7DD] p-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-xs font-bold text-[#8A3330]">
                                        2
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="h-2.5 w-2/3 rounded-full bg-[#DDD3C9]"></div>
                                        <div class="mt-2 h-2 w-2/5 rounded-full bg-[#EEE7DF]"></div>
                                    </div>

                                    <div class="h-3 w-12 rounded-full bg-[#E5DDD4]"></div>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-between border-t border-dashed border-[#D9CEC3] pt-5">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#A1938C]">
                                        {{ __('Estimated total') }}
                                    </p>

                                    <p class="mt-1 text-xs text-[#83756F]">
                                        {{ __('Calculated automatically') }}
                                    </p>
                                </div>

                                <p class="text-xl font-bold tracking-tight text-[#8A3330]">
                                    ₱0.00
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="absolute -bottom-6 -left-4 flex items-center gap-3 rounded-2xl border border-white/80 bg-white/90 p-3 pr-5 shadow-xl backdrop-blur-md sm:-left-8">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#8A3330] text-white">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#A1938C]">
                                {{ __('Next step') }}
                            </p>

                            <p class="mt-0.5 text-xs font-bold text-[#332824]">
                                {{ __('Create your first order') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@else
    {{-- Compact version for an empty selected filter --}}
    <section
        {{ $attributes->merge([
            'class' => 'relative overflow-hidden rounded-[1.5rem] border border-[#E5DDD0] bg-white px-5 py-5 shadow-[0_16px_35px_-28px_rgba(58,38,33,0.45)] sm:px-6'
        ]) }}
    >
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-[#F3E1DC]/70 blur-3xl"></div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-[#8A3330]/10 bg-[#F7E9E4] text-[#8A3330]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.7"
                        stroke="currentColor"
                        class="h-6 w-6"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25h10.176a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H15M9 3.75c0 1.036.84 1.875 1.875 1.875h2.25c1.036 0 1.875-.84 1.875-1.875M9 3.75c0-1.036.84-1.875 1.875-1.875h2.25c1.036 0 1.875.84 1.875 1.875M9 12h6m-6 3.75h6" />
                    </svg>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[#251C19]">
                        {{ $title }}
                    </h3>

                    @if ($description)
                        <p class="mt-1 max-w-xl text-sm leading-6 text-[#786A64]">
                            {{ $description }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($actionLabel && $actionHref)
                <a
                    href="{{ $actionHref }}"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#8A3330] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#742927] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/20"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        class="h-4 w-4"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>

                    {{ $actionLabel }}
                </a>
            @endif
        </div>
    </section>
@endif