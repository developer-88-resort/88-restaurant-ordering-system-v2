import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function AvailabilityMenu({ item, status, options, onSelect }) {
    const [open, setOpen] = useState(false);
    const current = options.find((o) => o.value === status) ?? options[0];

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className={`inline-flex items-center gap-1 text-[10px] font-semibold rounded-full py-1 pl-2 pr-1.5 shadow-sm ${current.badgeClasses}`}
            >
                {current.label}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor" className="h-2.5 w-2.5">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="absolute z-50 top-full right-0 mt-1.5 w-40 rounded-lg bg-white border border-[#E5DDD0] shadow-lg py-1">
                        {options.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => {
                                    onSelect(item.id, option.value);
                                    setOpen(false);
                                }}
                                className={`w-full flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-[#FAF6EE] text-left ${
                                    status === option.value ? 'font-semibold text-gray-900' : 'text-gray-600'
                                }`}
                            >
                                <span className={`h-1.5 w-1.5 rounded-full shrink-0 ${option.dotClass}`} />
                                {option.label}
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

const DOT_CLASSES = {
    available: 'bg-green-500',
    out_of_stock: 'bg-gray-400',
    seasonal: 'bg-amber-500',
    hidden: 'bg-slate-500',
};

function ItemCard({ item, canManageMenu, showArchived, statuses, options, onSelectAvailability, t }) {
    return (
        <div className="group bg-white border border-[#E5DDD0] rounded-xl overflow-hidden flex flex-col shadow-sm hover:shadow-md hover:border-[#D9CCBA] transition-all duration-200">
            <div className="aspect-[4/3] bg-gradient-to-br from-[#FAF6EE] to-[#F1E9DA] relative overflow-hidden">
                {item.primary_image_url ? (
                    <img src={item.primary_image_url} alt={item.name} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                ) : (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.2" stroke="currentColor" className="h-6 w-6 text-[#D9CCBA]">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                    </div>
                )}

                <div className="absolute top-1.5 left-1.5 flex flex-col gap-1 items-start">
                    {item.is_featured && (
                        <span className="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-amber-500 text-white shadow-sm">{t('Featured')}</span>
                    )}
                    {item.is_best_seller && (
                        <span className="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-[#8A3330] text-white shadow-sm">{t('Best Seller')}</span>
                    )}
                </div>

                {showArchived ? (
                    <span className="absolute top-1.5 right-1.5 inline-flex items-center px-2 py-1 text-[10px] font-semibold rounded-full bg-slate-700 text-white shadow-sm">
                        {t('Archived')}
                    </span>
                ) : (
                    canManageMenu && (
                        <div className="absolute top-1.5 right-1.5">
                            <AvailabilityMenu item={item} status={statuses[item.id]} options={options} onSelect={onSelectAvailability} />
                        </div>
                    )
                )}
            </div>

            <div className="p-3 flex-1 flex flex-col">
                <h3 className="text-[15px] font-bold text-gray-900 leading-snug truncate" title={item.name}>
                    {item.name}
                </h3>
                {item.description && (
                    <p className="text-xs text-gray-500 truncate mt-0.5" title={item.description}>
                        {item.description}
                    </p>
                )}
                <div className="flex items-center gap-1.5 mt-1">
                    {item.prep_time_minutes && <span className="text-[10px] text-gray-400">{item.prep_time_minutes} {t('min prep')}</span>}
                    {item.has_variants && <span className="text-[10px] font-semibold text-[#8A7B9E]">{item.variants_count} {t('variants')}</span>}
                </div>

                <div className="mt-auto pt-2 flex items-end justify-between">
                    <span className="text-base font-bold text-[#8A3330]">{item.price_range_label}</span>
                    {canManageMenu && (
                        <div className="flex items-center">
                            {showArchived ? (
                                <button
                                    type="button"
                                    title={t('Restore')}
                                    onClick={() => router.patch(route('menu-items.restore', item.id))}
                                    className="p-1 rounded-md text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-3.5 w-3.5">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                    </svg>
                                </button>
                            ) : (
                                <>
                                    <Link href={route('menu-items.edit', item.id)} title={t('Edit')} className="p-1 rounded-md text-gray-400 hover:text-[#8A3330] hover:bg-[#8A3330]/5 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-3.5 w-3.5">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                    </Link>
                                    <button
                                        type="button"
                                        title={t('Archive')}
                                        onClick={() => {
                                            if (confirm(t('Archive this item?') + '\n' + t('You can restore :name later from the Archived tab.').replace(':name', item.name))) {
                                                router.delete(route('menu-items.destroy', item.id));
                                            }
                                        }}
                                        className="p-1 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-3.5 w-3.5">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25-2.25m-2.25 2.25V6.75M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                        </svg>
                                    </button>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Index({ items, categories, hasCategories, showArchived, archivedCount, filters, availabilityOptions, auth }) {
    const t = useTranslation();
    const { csrf_token: csrfToken } = usePage().props;
    const canManageMenu = ['superadmin', 'admin'].includes(auth.user.role);

    const [search, setSearch] = useState(filters.q ?? '');
    const searchTimer = useRef(null);

    const options = availabilityOptions.map((o) => ({ ...o, dotClass: DOT_CLASSES[o.value] ?? 'bg-gray-400' }));

    const [statuses, setStatuses] = useState(() => Object.fromEntries(items.map((i) => [i.id, i.availability_status])));

    useEffect(() => {
        setStatuses(Object.fromEntries(items.map((i) => [i.id, i.availability_status])));
    }, [items]);

    useEffect(() => {
        window.Echo.channel('menu').listen('.MenuItemAvailabilityChanged', (e) => {
            setStatuses((current) => (e.menu_item_id in current ? { ...current, [e.menu_item_id]: e.availability_status } : current));
        });

        const leave = () => window.Echo.leave('menu');
        turboCleanup(leave);

        return leave;
    }, []);

    const submitFilters = (overrides) => {
        const data = {
            q: search,
            category_id: filters.category_id ?? '',
            availability: filters.availability ?? '',
            sort: filters.sort ?? '',
            featured: filters.featured ?? false,
            ...(showArchived ? { archived: 1 } : {}),
            ...overrides,
        };

        router.get(route('menu-items.index'), data, { preserveState: true, preserveScroll: true, replace: true });
    };

    const onSearchChange = (value) => {
        setSearch(value);
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => submitFilters({ q: value }), 400);
    };

    const hasActiveFilters = (filters.q ?? '') !== '' || (filters.category_id ?? '') !== '' || (filters.availability ?? '') !== '' || (filters.sort ?? '') !== '' || (filters.featured ?? false);

    const selectAvailability = (itemId, value) => {
        const previous = statuses[itemId];
        setStatuses((current) => ({ ...current, [itemId]: value }));

        fetch(route('menu-items.set-availability', itemId), {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status: value }),
        }).catch(() => setStatuses((current) => ({ ...current, [itemId]: previous })));
    };

    const groupedItems = categories
        .map((category) => ({
            category,
            items: items.filter((item) => item.menu_category_id === category.id),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <AuthenticatedLayout>
            <Head title={t('Menu Management')} />

            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{t('Menu Management')}</h1>
                    <p className="text-sm text-gray-500 mt-1">{t('Manage your menu items and categories.')}</p>
                </div>
                {canManageMenu && (
                    <div className="flex flex-wrap items-center gap-4">
                        <Link href={route('menu-categories.index')} className="text-sm text-[#8A3330] hover:underline font-medium">
                            {t('Manage Categories')}
                        </Link>
                        {!hasCategories ? (
                            <span className="inline-flex items-center px-4 py-2 bg-gray-300 rounded-md font-semibold text-xs text-white uppercase tracking-widest cursor-not-allowed">
                                {t('New Item')}
                            </span>
                        ) : (
                            <Link href={route('menu-items.create')}>
                                <span className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150">
                                    {t('New Item')}
                                </span>
                            </Link>
                        )}
                    </div>
                )}
            </div>

            {!hasCategories && (
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center rounded-xl border border-[#E5DDD0] bg-white p-5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5 text-amber-600">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div className="flex-1">
                        <p className="font-semibold text-gray-900">{t('No categories yet')}</p>
                        <p className="mt-0.5 text-sm text-gray-500">{t('You need at least one category before you can add menu items.')}</p>
                    </div>
                    {canManageMenu && (
                        <Link href={route('menu-categories.create')} className="shrink-0">
                            <span className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150">
                                {t('New Category')}
                            </span>
                        </Link>
                    )}
                </div>
            )}

            {/* Filter toolbar */}
            <div className="mb-6 space-y-3">
                <div className="flex flex-col sm:flex-row gap-3">
                    <div className="relative flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="#a99c8f" className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 pointer-events-none">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder={t('Search menu...')}
                            autoComplete="off"
                            className="w-full rounded-xl border border-[#D9CCBA] bg-white pl-11 pr-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 outline-none focus:border-[#8A3330] focus:ring-2 focus:ring-[#8A3330]"
                        />
                    </div>

                    <select
                        value={filters.category_id ?? ''}
                        onChange={(e) => submitFilters({ category_id: e.target.value })}
                        className="rounded-xl border-[#D9CCBA] text-sm text-gray-700 focus:border-[#8A3330] focus:ring-[#8A3330]"
                    >
                        <option value="">{t('All Categories')}</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.availability ?? ''}
                        onChange={(e) => submitFilters({ availability: e.target.value })}
                        className="rounded-xl border-[#D9CCBA] text-sm text-gray-700 focus:border-[#8A3330] focus:ring-[#8A3330]"
                    >
                        <option value="">{t('Any Availability')}</option>
                        {options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.sort ?? ''}
                        onChange={(e) => submitFilters({ sort: e.target.value })}
                        className="rounded-xl border-[#D9CCBA] text-sm text-gray-700 focus:border-[#8A3330] focus:ring-[#8A3330]"
                    >
                        <option value="">{t('Sort Order')}</option>
                        <option value="name_asc">{t('Name (A-Z)')}</option>
                        <option value="price_asc">{t('Price (Low to High)')}</option>
                        <option value="price_desc">{t('Price (High to Low)')}</option>
                        <option value="prep_asc">{t('Prep Time (Fast First)')}</option>
                        <option value="newest">{t('Newest First')}</option>
                    </select>

                    <button
                        type="button"
                        onClick={() => submitFilters({})}
                        className="hidden sm:inline-flex items-center px-4 py-2 rounded-xl bg-[#8A3330] text-white text-sm font-semibold hover:bg-[#742927] transition"
                    >
                        {t('Search')}
                    </button>
                </div>

                <div className="flex flex-wrap items-center gap-4 text-sm">
                    <label className="inline-flex items-center gap-1.5 text-gray-600 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={filters.featured ?? false}
                            onChange={(e) => submitFilters({ featured: e.target.checked })}
                            className="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]"
                        />
                        {t('Featured only')}
                    </label>

                    {hasActiveFilters && (
                        <Link
                            href={route('menu-items.index', showArchived ? { archived: 1 } : {})}
                            className="text-[#8A3330] hover:underline font-medium"
                        >
                            {t('Clear filters')}
                        </Link>
                    )}

                    <span className="ml-auto inline-flex rounded-full border border-[#E5DDD0] bg-white p-0.5">
                        <Link
                            href={route('menu-items.index')}
                            className={`px-3 py-1 rounded-full text-xs font-semibold transition ${!showArchived ? 'bg-[#8A3330] text-white' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            {t('Active')}
                        </Link>
                        <Link
                            href={route('menu-items.index', { archived: 1 })}
                            className={`px-3 py-1 rounded-full text-xs font-semibold transition ${showArchived ? 'bg-[#8A3330] text-white' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            {t('Archived')} ({archivedCount})
                        </Link>
                    </span>
                </div>
            </div>

            {items.length === 0 ? (
                <EmptyState
                    title={showArchived ? t('No archived items') : t('No menu items found')}
                    description={showArchived ? t('Items you archive will show up here.') : t('Try adjusting your search or filters, or add your first menu item.')}
                    actionLabel={showArchived || !hasCategories || !canManageMenu ? null : t('New Item')}
                    actionHref={showArchived || !hasCategories || !canManageMenu ? null : route('menu-items.create')}
                />
            ) : (
                groupedItems.map((group) => (
                    <div key={group.category.id} className="mb-9">
                        <div className="flex items-center gap-3 mb-4">
                            <h2 className="text-lg font-bold text-gray-900">{group.category.name}</h2>
                            <span className="text-xs font-medium text-gray-400">
                                {group.items.length} {group.items.length === 1 ? t('item') : t('items')}
                            </span>
                            <div className="flex-1 h-px bg-[#E5DDD0]" />
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                            {group.items.map((item) => (
                                <ItemCard
                                    key={item.id}
                                    item={item}
                                    canManageMenu={canManageMenu}
                                    showArchived={showArchived}
                                    statuses={statuses}
                                    options={options}
                                    onSelectAvailability={selectAvailability}
                                    t={t}
                                />
                            ))}
                        </div>
                    </div>
                ))
            )}
        </AuthenticatedLayout>
    );
}
