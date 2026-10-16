import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';

function Icon({ name, className = 'h-5 w-5', strokeWidth = 1.8 }) {
    let content = null;

    switch (name) {
        case 'categories':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M3.75 6.75A2.25 2.25 0 016 4.5h3A2.25 2.25 0 0111.25 6.75v3A2.25 2.25 0 019 12H6a2.25 2.25 0 01-2.25-2.25v-3zm9 0A2.25 2.25 0 0115 4.5h3a2.25 2.25 0 012.25 2.25v3A2.25 2.25 0 0118 12h-3a2.25 2.25 0 01-2.25-2.25v-3zm-9 9A2.25 2.25 0 016 13.5h3a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 019 19.5H6a2.25 2.25 0 01-2.25-2.25v-1.5zm9 0A2.25 2.25 0 0115 13.5h3a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 19.5h-3a2.25 2.25 0 01-2.25-2.25v-1.5z"
                />
            );
            break;
        case 'plus':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="M10 4H5a1 1 0 0 0-1 1v5h6V4Zm10 0h-6v6h6V4ZM4 14h6v6H5a1 1 0 0 1-1-1v-5Zm13 0v6m-3-3h6" />;
            break;
        case 'arrow-left':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="M18.75 12H5.25m0 0L12 18.75M5.25 12 12 5.25" />;
            break;
        case 'box':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="m21 8.25-9-5.25-9 5.25 9 5.25 9-5.25z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 8.25v9L12 22.5l9-5.25v-9M12 13.5v9" />
                </>
            );
            break;
        case 'check':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="m5.25 12.75 4.5 4.5 9-10.5" />;
            break;
        case 'archive':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 5.25h16.5v3H3.75v-3z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 8.25v10.5h13.5V8.25M9.75 12h4.5" />
                </>
            );
            break;
        case 'edit':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125"
                />
            );
            break;
        case 'restore':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3" />;
            break;
        default:
            content = <circle cx="12" cy="12" r="8.25" />;
    }

    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={strokeWidth} stroke="currentColor" className={className} aria-hidden="true">
            {content}
        </svg>
    );
}

function MetricCard({ icon, label, value, detail, accent = 'brand' }) {
    const accents = {
        brand: 'bg-slate-100 text-slate-900',
        green: 'bg-emerald-50 text-emerald-600',
        amber: 'bg-amber-50 text-amber-600',
        stone: 'bg-slate-100 text-slate-500',
    };

    return (
        <div className="flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 backdrop-blur-sm">
            <div className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${accents[accent]}`}>
                <Icon name={icon} className="h-5 w-5" strokeWidth={1.8} />
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500">{label}</p>
                <div className="mt-0.5 flex min-w-0 items-baseline gap-1.5">
                    <p className="text-lg font-semibold leading-none text-slate-900">{value}</p>
                    {detail ? <p className="truncate text-[10px] font-medium text-slate-500">{detail}</p> : null}
                </div>
            </div>
        </div>
    );
}

export default function Index({ categories, showArchived, archivedCount }) {
    const t = useTranslation();

    const totalItems = useMemo(() => categories.reduce((sum, c) => sum + (c.menu_items_count ?? 0), 0), [categories]);
    const activeCount = useMemo(() => categories.filter((c) => c.is_active).length, [categories]);

    const toggleStatus = (category) => {
        router.patch(route('menu-categories.toggle-status', category.id), {}, { preserveScroll: true });
    };

    const archive = (category) => {
        if (confirm(`${t('Archive this category?')}\n${t('You can restore :name later from the Archived tab.').replace(':name', category.name)}`)) {
            router.delete(route('menu-categories.destroy', category.id), { preserveScroll: true });
        }
    };

    const restore = (category) => {
        router.patch(route('menu-categories.restore', category.id), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('Menu Categories')} />

            <div className="space-y-7 pb-10">
                {/* Hero header — matches the Menu Management catalog workspace look */}
                <header className="relative isolate overflow-hidden rounded-2xl border border-slate-200 bg-white px-5 py-6 shadow-sm sm:px-7 sm:py-7 lg:px-9">

                    <div className="relative">
                        <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                            <div className="flex max-w-2xl items-start gap-4 sm:gap-5">
                                <div className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-900 shadow-inner backdrop-blur-sm sm:h-16 sm:w-16">
                                    <Icon name="categories" className="h-7 w-7" strokeWidth={1.65} />
                                </div>

                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#64748B]">
                                            {t('Catalog workspace')}
                                        </span>
                                        <span className="h-1 w-1 rounded-full bg-slate-300" />
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">
                                            <span className={`h-1.5 w-1.5 rounded-full ${showArchived ? 'bg-slate-300' : 'bg-emerald-300'}`} />
                                            {showArchived ? t('Archived view') : t('Active categories')}
                                        </span>
                                    </div>

                                    <h1 className="mt-2 text-2xl font-semibold tracking-[-0.035em] text-slate-900 sm:text-3xl lg:text-[2.2rem]">
                                        {t('Menu Categories')}
                                    </h1>
                                    <p className="mt-2 max-w-xl text-sm leading-6 text-slate-500 sm:text-[15px]">
                                        {t('Organize your catalog into groups like Appetizers, Main Course, or Drinks.')}
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-col gap-2.5 sm:flex-row xl:justify-end">
                                <Link
                                    href={route('menu-items.index')}
                                    className="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-bold text-slate-500 backdrop-blur-sm transition hover:-translate-y-0.5 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                >
                                    <Icon name="arrow-left" className="h-4 w-4" strokeWidth={2} />
                                    {t('Back to Menu Items')}
                                </Link>

                                <Link
                                    href={route('menu-categories.create')}
                                    className="group inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                >
                                    <span className="grid h-6 w-6 place-items-center rounded-lg bg-white/10">
                                        <Icon name="plus" className="h-3.5 w-3.5" strokeWidth={2.5} />
                                    </span>
                                    {t('New Category')}
                                </Link>
                            </div>
                        </div>

                        <div className="mt-7 grid gap-3 sm:grid-cols-3 xl:grid-cols-3">
                            <MetricCard icon="categories" label={t('Categories shown')} value={categories.length} detail={showArchived ? t('archived') : t('active')} accent="stone" />
                            <MetricCard icon="box" label={t('Items linked')} value={totalItems} detail={t('across these categories')} />
                            <MetricCard icon={showArchived ? 'archive' : 'check'} label={showArchived ? t('Archived') : t('Active')} value={showArchived ? archivedCount : activeCount} detail={showArchived ? t('stored categories') : t('ready to use')} accent={showArchived ? 'amber' : 'green'} />
                        </div>
                    </div>
                </header>

                {/* Content card */}
                <section className="rounded-2xl border border-[#E2E8F0] bg-white/95 p-4 shadow-sm sm:p-5">
                    <div className="mb-4 flex flex-col gap-3 border-b border-[#E2E8F0] pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#F1F5F9] text-[#1E293B]">
                                <Icon name="categories" className="h-5 w-5" strokeWidth={1.8} />
                            </div>
                            <div>
                                <h2 className="text-sm font-semibold text-[#0F172A]">{t('All categories')}</h2>
                                <p className="mt-0.5 text-xs text-[#64748B]">{t('Sort order controls where each one appears on the menu')}</p>
                            </div>
                        </div>

                        <span className="inline-flex w-fit rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-1">
                            <Link
                                href={route('menu-categories.index')}
                                className={`rounded-xl px-3.5 py-2 text-xs font-bold transition ${!showArchived ? 'bg-[#1E293B] text-white shadow-sm' : 'text-[#64748B] hover:text-[#3D2C1E]'}`}
                            >
                                {t('Active')}
                            </Link>
                            <Link
                                href={route('menu-categories.index', { archived: 1 })}
                                className={`rounded-xl px-3.5 py-2 text-xs font-bold transition ${showArchived ? 'bg-[#1E293B] text-white shadow-sm' : 'text-[#64748B] hover:text-[#3D2C1E]'}`}
                            >
                                {t('Archived')} ({archivedCount})
                            </Link>
                        </span>
                    </div>

                    {categories.length === 0 ? (
                        <EmptyState
                            title={showArchived ? t('No archived categories') : t('No menu categories yet')}
                            description={showArchived ? t('Categories you archive will show up here.') : t('Create categories like Appetizers, Main Course, or Drinks to organize your menu items.')}
                            actionLabel={showArchived ? null : t('New Category')}
                            actionHref={showArchived ? null : route('menu-categories.create')}
                        />
                    ) : (
                        <>
                            {/* Desktop table */}
                            <div className="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] sm:block">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-[#E2E8F0]">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Name')}</th>
                                                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Items')}</th>
                                                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Sort Order')}</th>
                                                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Status')}</th>
                                                <th className="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Actions')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#E2E8F0] bg-white">
                                            {categories.map((category) => (
                                                <tr key={category.id} className="transition hover:bg-slate-50/70">
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-[#0F172A]">{category.name}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-[#64748B]">{category.menu_items_count}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-[#64748B]">{category.sort_order}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {showArchived ? (
                                                            <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">
                                                                <span className="h-1.5 w-1.5 rounded-full bg-slate-500" />
                                                                {t('Archived')}
                                                            </span>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => toggleStatus(category)}
                                                                className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold transition ${
                                                                    category.is_active
                                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                                        : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200'
                                                                }`}
                                                            >
                                                                <span className={`h-1.5 w-1.5 rounded-full ${category.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`} />
                                                                {category.is_active ? t('Active') : t('Inactive')}
                                                            </button>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                        {showArchived ? (
                                                            <button type="button" onClick={() => restore(category)} className="inline-flex items-center gap-1.5 font-bold text-emerald-700 hover:text-emerald-900">
                                                                <Icon name="restore" className="h-3.5 w-3.5" strokeWidth={2} />
                                                                {t('Restore')}
                                                            </button>
                                                        ) : (
                                                            <div className="flex items-center justify-end gap-2">
                                                                <Link href={route('menu-categories.edit', category.id)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                                                    <Icon name="edit" className="h-3.5 w-3.5" strokeWidth={2} />
                                                                    {t('Edit')}
                                                                </Link>
                                                                <button type="button" onClick={() => archive(category)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">
                                                                    <Icon name="archive" className="h-3.5 w-3.5" strokeWidth={2} />
                                                                    {t('Archive')}
                                                                </button>
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Mobile cards */}
                            <div className="space-y-3 sm:hidden">
                                {categories.map((category) => (
                                    <div key={category.id} className="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-bold text-[#0F172A]">{category.name}</p>
                                                <p className="mt-1 text-xs text-[#64748B]">
                                                    {category.menu_items_count} {t('items')} &middot; {t('Sort')} {category.sort_order}
                                                </p>
                                            </div>
                                            {showArchived ? (
                                                <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">
                                                    {t('Archived')}
                                                </span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleStatus(category)}
                                                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold ${
                                                        category.is_active
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                            : 'border-slate-200 bg-slate-100 text-slate-600'
                                                    }`}
                                                >
                                                    {category.is_active ? t('Active') : t('Inactive')}
                                                </button>
                                            )}
                                        </div>

                                        <div className="mt-3 flex items-center justify-end gap-2 border-t border-[#E2E8F0] pt-3 text-sm">
                                            {showArchived ? (
                                                <button type="button" onClick={() => restore(category)} className="font-bold text-emerald-700 hover:text-emerald-900">
                                                    {t('Restore')}
                                                </button>
                                            ) : (
                                                <>
                                                    <Link href={route('menu-categories.edit', category.id)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                                        {t('Edit')}
                                                    </Link>
                                                    <button type="button" onClick={() => archive(category)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-medium text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">
                                                        {t('Archive')}
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
