{{--
    The reload icon beside a report section's title: refreshes that section
    alone (resources/js/lib/report-section-refresh.js). Must sit inside an
    element with data-report-section.
--}}
<button type="button" data-report-refresh
        title="{{ __('Refresh this section') }}" aria-label="{{ __('Refresh this section') }}"
        data-error-label="{{ __('Could not refresh — try again') }}"
        class="group/refresh inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-[#8A3330] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]/30 disabled:cursor-wait">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
         class="h-3.5 w-3.5 group-[.is-refreshing]/refresh:animate-spin">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
    </svg>
</button>
