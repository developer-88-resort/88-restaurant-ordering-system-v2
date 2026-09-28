<section class="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.88)] sm:px-8 sm:py-7">
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 opacity-[0.07]"
        style="
            background-image:
                linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px);
            background-size: 28px 28px;
        "
    ></div>

    <div
        aria-hidden="true"
        class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/45 blur-3xl"
    ></div>

    <div
        aria-hidden="true"
        class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl"
    ></div>

    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-start gap-4 sm:items-center sm:gap-5">
            <a
                href="{{ route('orders.index') }}"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white/80 backdrop-blur-sm transition hover:-translate-x-0.5 hover:bg-white/15 hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15 sm:h-12 sm:w-12"
                aria-label="{{ __('Back to orders') }}"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    class="h-5 w-5"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white/70 backdrop-blur-sm">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-35"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                        </span>
                        {{ __('Draft order') }}
                    </span>
                </div>

                <h2 class="mt-2 text-2xl font-bold tracking-[-0.035em] text-white sm:text-3xl">
                    {{ __('Create a new order') }}
                </h2>

                <p class="mt-1 max-w-2xl text-sm leading-6 text-white/60">
                    {{ __('Choose the service type, assign a location, and build the customer order from the available menu.') }}
                </p>
            </div>
        </div>

        <div class="hidden shrink-0 items-center xl:flex">
            <div class="flex items-center rounded-2xl border border-white/10 bg-white/[0.07] p-2 backdrop-blur-sm">
                <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-bold text-[#241917] shadow-sm">
                    <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#8A3330] text-[10px] text-white">1</span>
                    {{ __('Order type') }}
                </div>

                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mx-1 h-4 w-4 text-white/30" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>

                <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white/65">
                    <span class="grid h-6 w-6 place-items-center rounded-lg border border-white/15 bg-white/10 text-[10px] text-white">2</span>
                    {{ __('Location') }}
                </div>

                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mx-1 h-4 w-4 text-white/30" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>

                <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white/65">
                    <span class="grid h-6 w-6 place-items-center rounded-lg border border-white/15 bg-white/10 text-[10px] text-white">3</span>
                    {{ __('Menu & review') }}
                </div>
            </div>
        </div>
    </div>
</section>
