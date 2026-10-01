import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const PromotionsIcon = ({ className = 'h-7 w-7' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" />
    </svg>
);

const CalendarIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
    </svg>
);

const PencilIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM16.862 4.487 19.5 7.125" />
    </svg>
);

const EyeIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
    </svg>
);

const CursorIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="m15.042 21.672-1.358-5.072m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 6.257 6.166 4.666M4.5 10.5H2.25" />
    </svg>
);

export default function Index({ filters, promotions, currentDateTime, statusOptions }) {
    const t = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const searchTimer = useRef(null);

    const submitFilters = (overrides = {}) => {
        clearTimeout(searchTimer.current);

        router.get(
            route('superadmin.promotions.index'),
            {
                search,
                status: filters.status ?? '',
                date_from: filters.date_from ?? '',
                date_to: filters.date_to ?? '',
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const onSearchChange = (value) => {
        setSearch(value);
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => submitFilters({ search: value }), 400);
    };

    const clearFilters = () => {
        setSearch('');
        router.get(route('superadmin.promotions.index'));
    };

    const hasActiveFilters = !!(filters.search || filters.status || filters.date_from || filters.date_to);

    return (
        <AuthenticatedLayout>
            <Head title={t('Promotions & Banners')} />

            <section className="relative isolate mb-7 overflow-hidden rounded-2xl bg-slate-900 px-6 py-8 shadow-sm sm:px-8 sm:py-10">
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-cover" style={{ backgroundImage: "url('/images/promotions-hero.jpg')", backgroundPosition: 'right 70%' }} />
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/70 to-slate-950/20" />
                <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4 sm:gap-5">
                        <div className="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-white/20 bg-white/10 text-white backdrop-blur-sm">
                            <PromotionsIcon className="h-7 w-7" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-white sm:text-3xl">{t('Promotions & Banners')}</h1>
                            <p className="mt-1.5 max-w-md text-sm leading-6 text-white/90">
                                {t('Upload and manage the banner images shown to your guests.')}
                            </p>
                            <p className="mt-2 max-w-md text-xs leading-5 text-white/75">
                                {t('Boost engagement. Drive reservations. Delight guests.')}
                            </p>
                        </div>
                    </div>

                    <div className="inline-flex max-w-full items-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-medium text-slate-500 sm:self-center">
                        <CalendarIcon className="h-4 w-4" />
                        {currentDateTime}
                    </div>
                </div>
            </section>

            <div className="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 xl:flex-row xl:items-center xl:justify-between">
                <div className="flex flex-1 flex-wrap items-center gap-3">
                    <label className="relative min-w-0 basis-full sm:min-w-[220px] sm:flex-[1.6]">
                        <span className="sr-only">{t('Search banners')}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
                        </svg>
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => onSearchChange(event.target.value)}
                            placeholder={t('Search banners by code...')}
                            className="h-11 w-full rounded-lg border-slate-200 bg-slate-50/50 pl-10 pr-4 text-sm shadow-none placeholder:text-slate-500 focus:border-slate-400 focus:bg-white focus:ring-slate-200"
                        />
                    </label>
                    <select
                        aria-label={t('Filter by status')}
                        value={filters.status ?? ''}
                        onChange={(event) => submitFilters({ status: event.target.value })}
                        className="h-11 min-w-[132px] rounded-lg border-slate-200 bg-slate-50/50 text-sm shadow-none focus:border-slate-400 focus:bg-white focus:ring-slate-200"
                    >
                        <option value="">{t('All Status')}</option>
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                    <input
                        type="date"
                        aria-label={t('Start date')}
                        value={filters.date_from ?? ''}
                        onChange={(event) => submitFilters({ date_from: event.target.value })}
                        className="h-11 rounded-lg border-slate-200 bg-slate-50/50 text-sm shadow-none focus:border-slate-400 focus:bg-white focus:ring-slate-200"
                    />
                    <span className="text-sm text-gray-400">–</span>
                    <input
                        type="date"
                        aria-label={t('End date')}
                        value={filters.date_to ?? ''}
                        onChange={(event) => submitFilters({ date_to: event.target.value })}
                        className="h-11 rounded-lg border-slate-200 bg-slate-50/50 text-sm shadow-none focus:border-slate-400 focus:bg-white focus:ring-slate-200"
                    />
                    {hasActiveFilters && (
                        <button type="button" onClick={clearFilters} className="text-sm font-semibold text-gray-500 hover:text-gray-800">
                            {t('Clear')}
                        </button>
                    )}
                </div>
                <Link
                    href={route('superadmin.promotions.create')}
                    className="inline-flex h-11 shrink-0 items-center justify-center gap-2.5 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className="h-5 w-5" aria-hidden="true">
                        <path d="M10 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v4M3 15l5-5 4 4" strokeLinecap="round" strokeLinejoin="round" />
                        <circle cx="15" cy="8" r="1.5" fill="currentColor" fillOpacity="0.15" />
                        <rect x="13" y="13" width="8" height="8" rx="2.5" fill="currentColor" fillOpacity="0.08" />
                        <path d="M17 15.25v3.5m-1.75-1.75h3.5" strokeLinecap="round" />
                    </svg>
                    {t('Create Banner')}
                </Link>
            </div>

            {promotions.data.length === 0 ? (
                <div className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm">
                    <EmptyState
                        title={t('No banners yet')}
                        description={t('Upload your first promotional banner image to get started.')}
                        actionLabel={t('Create Banner')}
                        actionHref={route('superadmin.promotions.create')}
                    />
                </div>
            ) : (
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
                        <h2 className="text-sm font-bold text-slate-900">{t('Promotion Campaigns')}</h2>
                        <span className="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                            {promotions.total} {t('Total')}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <div className="min-w-[560px]">
                            <div className="grid grid-cols-[minmax(260px,1fr)_100px_100px_64px] items-center gap-4 border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <span>{t('Campaign')}</span>
                                <span>{t('Views')}</span>
                                <span>{t('Clicks')}</span>
                                <span className="text-center">{t('Actions')}</span>
                            </div>

                            {promotions.data.map((promotion) => (
                                <div key={promotion.id} className="grid grid-cols-[minmax(260px,1fr)_100px_100px_64px] items-center gap-4 border-b border-slate-100 px-5 py-3.5 last:border-b-0 transition-colors hover:bg-slate-50 focus-within:bg-slate-50">
                                    <Link href={route('superadmin.promotions.show', promotion.id)} className="group flex min-w-0 items-center gap-3">
                                        <div className="h-14 w-20 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                            {promotion.image_url ? (
                                                <img src={promotion.image_url} alt="" className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]" />
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-[9px] text-gray-400">{t('No image')}</div>
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-slate-800 group-hover:text-[#8A3330]">
                                                {promotion.title || promotion.code}
                                            </p>
                                            {promotion.title && <p className="mt-0.5 truncate text-[10px] text-slate-500">{promotion.code}</p>}
                                        </div>
                                    </Link>

                                    <span className="flex items-center gap-2 text-sm font-medium tabular-nums text-slate-600">
                                        <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-[#f0ecfa] text-[#7257a4]">
                                            <EyeIcon className="h-3.5 w-3.5" />
                                        </span>
                                        {promotion.views_count.toLocaleString()}
                                    </span>
                                    <span className="flex items-center gap-2 text-sm font-medium tabular-nums text-slate-600">
                                        <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500">
                                            <CursorIcon className="h-3.5 w-3.5" />
                                        </span>
                                        {promotion.clicks_count.toLocaleString()}
                                    </span>
                                    <div className="flex justify-center">
                                        <Link
                                            href={route('superadmin.promotions.edit', promotion.id)}
                                            title={t('Edit')}
                                            aria-label={t('Edit')}
                                            className="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                        >
                                            <PencilIcon className="h-4 w-4" />
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            <div className="mt-5">
                <Pagination links={promotions.links} />
            </div>
        </AuthenticatedLayout>
    );
}
