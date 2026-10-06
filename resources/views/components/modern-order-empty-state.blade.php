@props([
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'variant' => 'compact',
    'eyebrow' => null,
])


    <section
        {{ $attributes->merge([
            'class' => 'relative isolate overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm'
        ]) }}
    >
        <div class="relative grid min-h-[450px] lg:grid-cols-[1.08fr_.92fr]">
            {{-- Main content --}}
            <div class="relative z-10 flex flex-col justify-center p-7 sm:p-10 lg:p-12 xl:p-14">
                <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200/15 bg-slate-50/5 px-3 py-1.5">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-slate-50 opacity-30"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-slate-50"></span>
                    </span>

                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                        {{ $eyebrow ?: __('Order workspace') }}
                    </span>
                </div>

                <h3 class="mt-6 max-w-xl text-3xl font-semibold tracking-[-0.035em] text-slate-900 sm:text-4xl lg:text-4xl lg:leading-tight">
                    {{ $title }}
                </h3>

                @if ($description)
                    <p class="mt-4 max-w-xl text-sm leading-7 text-slate-500 sm:text-base">
                        {{ $description }}
                    </p>
                @endif

                @if ($actionLabel && $actionHref)
                    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <a
                            href="{{ $actionHref }}"
                            class="group inline-flex min-h-12 items-center justify-center gap-2.5 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
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
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6M13 3l5 5v3M13 3v5h5M8 12h3m-3 4h2M18 14v6m-3-3h6" />
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

                        <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                                class="h-4 w-4 text-slate-500"
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
                    <div class="rounded-2xl border border-slate-200 bg-white/75 p-4 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-slate-50 text-xs font-semibold text-slate-500">
                                01
                            </span>

                            <p class="text-xs font-semibold leading-5 text-slate-900">
                                {{ __('Add order items') }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white/75 p-4 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-slate-50 text-xs font-semibold text-slate-500">
                                02
                            </span>

                            <p class="text-xs font-semibold leading-5 text-slate-900">
                                {{ __('Assign a location') }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white/75 p-4 backdrop-blur-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-slate-50 text-xs font-semibold text-slate-500">
                                03
                            </span>

                            <p class="text-xs font-semibold leading-5 text-slate-900">
                                {{ __('Review and submit') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Decorative order preview --}}
            <div class="relative flex min-h-[390px] items-center justify-center overflow-hidden bg-slate-50 p-8 sm:p-12">

                <div class="relative w-full max-w-sm">




                    <div class="relative rounded-[1.85rem] border border-slate-200 bg-white p-2 shadow-sm">
                        <div class="rounded-[1.4rem] bg-white p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                        {{ __('Order preview') }}
                                    </p>

                                    <p class="mt-1 font-mono text-lg font-semibold text-slate-900">
                                        #NEW-ORDER
                                    </p>
                                </div>

                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-50"></span>
                                    {{ __('Draft') }}
                                </span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                        {{ __('Location') }}
                                    </p>

                                    <p class="mt-1 text-xs font-semibold text-slate-900">
                                        {{ __('Not selected') }}
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-slate-50 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                        {{ __('Customer') }}
                                    </p>

                                    <p class="mt-1 text-xs font-semibold text-slate-900">
                                        {{ __('Walk-in') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-xs font-semibold text-slate-500">
                                        1
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="h-2.5 w-3/4 rounded-full bg-slate-200"></div>
                                        <div class="mt-2 h-2 w-1/3 rounded-full bg-slate-200"></div>
                                    </div>

                                    <div class="h-3 w-12 rounded-full bg-slate-200"></div>
                                </div>

                                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-xs font-semibold text-slate-500">
                                        2
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="h-2.5 w-2/3 rounded-full bg-slate-200"></div>
                                        <div class="mt-2 h-2 w-2/5 rounded-full bg-slate-200"></div>
                                    </div>

                                    <div class="h-3 w-12 rounded-full bg-slate-200"></div>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-between border-t border-dashed border-slate-200 pt-5">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                        {{ __('Estimated total') }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ __('Calculated automatically') }}
                                    </p>
                                </div>

                                <p class="text-xl font-semibold tracking-tight text-slate-500">
                                    ₱0.00
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="absolute -bottom-6 -left-4 flex items-center gap-3 rounded-2xl border border-white/80 bg-white/90 p-3 pr-5 shadow-sm backdrop-blur-md sm:-left-8">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600">
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
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                {{ __('Next step') }}
                            </p>

                            <p class="mt-0.5 text-xs font-semibold text-slate-900">
                                {{ __('Create your first order') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>