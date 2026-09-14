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

            <section className="relative isolate mb-6 min-h-[210px] overflow-hidden rounded-[24px] bg-[#3c1418] px-6 py-6 shadow-[0_24px_55px_-34px_rgba(79,25,27,0.75)] sm:px-7 sm:py-7">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 bg-cover"
                    style={{ backgroundImage: "url('/images/promotions-hero.jpg')", backgroundPosition: 'right 70%' }}
                />
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#2f1013] from-10% via-[#2f1013]/80 via-45% to-transparent" />

                <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4 sm:gap-5">
                        <div className="grid h-16 w-16 shrink-0 place-items-center rounded-2xl border border-white/10 bg-white/10 text-white shadow-inner backdrop-blur-sm">
                            <PromotionsIcon className="h-7 w-7" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">{t('Promotions & Banners')}</h1>
                            <p className="mt-1.5 max-w-md text-sm leading-6 text-white/90">
                                {t('Upload and manage the banner images shown to your guests.')}
                            </p>
                            <p className="mt-2 max-w-md text-xs leading-5 text-white/65">
                                {t('Boost engagement. Drive reservations. Delight guests.')}
                            </p>
                        </div>
                    </div>

                    <div className="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-white/30 bg-white/90 px-4 py-2 text-xs font-semibold text-[#3c3030] shadow-sm backdrop-blur-sm sm:self-end">
                        <CalendarIcon className="h-4 w-4" />
                        {currentDateTime}
                    </div>
                </div>
            </section>

            <div className="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-wrap items-center gap-3">
                    <label className="relative min-w-[240px] flex-[1.6]">
                        <span className="sr-only">{t('Search banners')}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9a8f89]">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
                        </svg>
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => onSearchChange(event.target.value)}
                            placeholder={t('Search banners by code...')}
                            className="h-11 w-full rounded-lg border-[#e4ddd7] bg-white pl-10 pr-4 text-sm shadow-sm placeholder:text-[#9a8f89] focus:border-[#8A3330] focus:ring-[#8A3330]"
                        />
                    </label>
                    <select
                        value={filters.status ?? ''}
                        onChange={(event) => submitFilters({ status: event.target.value })}
                        className="h-11 min-w-[132px] rounded-lg border-[#e4ddd7] bg-white text-sm shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]"
                    >
                        <option value="">{t('All Status')}</option>
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                    <input
                        type="date"
                        value={filters.date_from ?? ''}
                        onChange={(event) => submitFilters({ date_from: event.target.value })}
                        className="h-11 rounded-lg border-[#e4ddd7] bg-white text-sm shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]"
                    />
                    <span className="text-sm text-gray-400">–</span>
                    <input
                        type="date"
                        value={filters.date_to ?? ''}
                        onChange={(event) => submitFilters({ date_to: event.target.value })}
                        className="h-11 rounded-lg border-[#e4ddd7] bg-white text-sm shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]"
                    />
                    {hasActiveFilters && (
                        <button type="button" onClick={clearFilters} className="text-sm font-semibold text-gray-500 hover:text-gray-800">
                            {t('Clear')}
                        </button>
                    )}
                </div>
                <Link
                    href={route('superadmin.promotions.create')}
                    className="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-[#86312f] to-[#a33c38] px-6 text-sm font-semibold text-white shadow-[0_8px_18px_-10px_rgba(138,51,48,0.9)] transition hover:from-[#742927] hover:to-[#8f322f]"
                >
                    + {t('Create Banner')}
                </Link>
            </div>

            {promotions.data.length === 0 ? (
                <div className="overflow-hidden rounded-[18px] border border-[#e9e2dc] bg-white shadow-[0_12px_35px_-28px_rgba(55,35,30,0.5)]">
                    <EmptyState
                        title={t('No banners yet')}
                        description={t('Upload your first promotional banner image to get started.')}
                        actionLabel={t('Create Banner')}
                        actionHref={route('superadmin.promotions.create')}
                    />
                </div>
            ) : (
                <div className="overflow-hidden rounded-2xl border border-[#e7e1dc] bg-white shadow-[0_10px_30px_-24px_rgba(67,39,32,0.45)]">
                    <div className="flex items-center gap-2.5 border-b border-[#eee9e5] px-5 py-3.5">
                        <h2 className="text-sm font-bold text-[#332b29]">{t('Promotion Campaigns')}</h2>
                        <span className="rounded-md bg-[#f8e9e7] px-2 py-0.5 text-[10px] font-semibold text-[#a34a44]">
                            {promotions.total} {t('Total')}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <div className="min-w-[560px]">
                            <div className="grid grid-cols-[minmax(260px,1fr)_100px_100px_64px] items-center gap-4 border-b border-[#eee9e5] px-5 py-2.5 text-[9px] font-semibold uppercase tracking-[0.13em] text-[#958883]">
                                <span>{t('Campaign')}</span>
                                <span>{t('Views')}</span>
                                <span>{t('Clicks')}</span>
                                <span className="text-center">{t('Actions')}</span>
                            </div>

                            {promotions.data.map((promotion) => (
                                <div key={promotion.id} className="grid grid-cols-[minmax(260px,1fr)_100px_100px_64px] items-center gap-4 border-b border-[#f0ebe7] px-5 py-2.5 last:border-b-0 hover:bg-[#fdfbf9]">
                                    <Link href={route('superadmin.promotions.show', promotion.id)} className="group flex min-w-0 items-center gap-3">
                                        <div className="h-11 w-[70px] shrink-0 overflow-hidden rounded-lg border border-[#e8e1dc] bg-[#FAF6EE]">
                                            {promotion.image_url ? (
                                                <img src={promotion.image_url} alt="" className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]" />
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-[9px] text-gray-400">{t('No image')}</div>
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-semibold text-[#3f3734] group-hover:text-[#8A3330]">
                                                {promotion.title || promotion.code}
                                            </p>
                                            {promotion.title && <p className="mt-0.5 truncate text-[10px] text-[#9a8f89]">{promotion.code}</p>}
                                        </div>
                                    </Link>

                                    <span className="flex items-center gap-2 text-xs font-medium tabular-nums text-[#554b47]">
                                        <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-[#f0ecfa] text-[#7257a4]">
                                            <EyeIcon className="h-3.5 w-3.5" />
                                        </span>
                                        {promotion.views_count.toLocaleString()}
                                    </span>
                                    <span className="flex items-center gap-2 text-xs font-medium tabular-nums text-[#554b47]">
                                        <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-[#f8e9e7] text-[#a34740]">
                                            <CursorIcon className="h-3.5 w-3.5" />
                                        </span>
                                        {promotion.clicks_count.toLocaleString()}
                                    </span>
                                    <div className="flex justify-center">
                                        <Link
                                            href={route('superadmin.promotions.edit', promotion.id)}
                                            title={t('Edit')}
                                            aria-label={t('Edit')}
                                            className="grid h-8 w-8 place-items-center rounded-lg border border-[#e5dfda] bg-white text-[#776c67] shadow-sm transition hover:border-[#cfaeaa] hover:bg-[#fff8f7] hover:text-[#8A3330]"
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
