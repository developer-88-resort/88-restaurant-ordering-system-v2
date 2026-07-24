import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ categories, showArchived, archivedCount }) {
    const t = useTranslation();

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

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-2xl font-bold text-gray-900">{t('Menu Categories')}</h1>
                <div className="flex flex-wrap items-center gap-4">
                    <Link href={route('menu-items.index')} className="text-sm text-[#8A3330] hover:underline font-medium">
                        {t('Back to Menu Items')}
                    </Link>
                    <Link href={route('menu-categories.create')}>
                        <span className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150">
                            {t('New Category')}
                        </span>
                    </Link>
                </div>
            </div>

            <div className="mb-4 flex justify-end">
                <span className="inline-flex rounded-full border border-[#E5DDD0] bg-white p-0.5">
                    <Link
                        href={route('menu-categories.index')}
                        className={`px-3 py-1 rounded-full text-xs font-semibold transition ${!showArchived ? 'bg-[#8A3330] text-white' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        {t('Active')}
                    </Link>
                    <Link
                        href={route('menu-categories.index', { archived: 1 })}
                        className={`px-3 py-1 rounded-full text-xs font-semibold transition ${showArchived ? 'bg-[#8A3330] text-white' : 'text-gray-500 hover:text-gray-700'}`}
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
                    <div className="hidden sm:block bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-[#E5DDD0]">
                                <thead className="bg-[#FAF6EE]">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Name')}</th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Items')}</th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Sort Order')}</th>
                                        <th className="px-6 py-3 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Status')}</th>
                                        <th className="px-6 py-3 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#E5DDD0]">
                                    {categories.map((category) => (
                                        <tr key={category.id} className="hover:bg-[#FAF6EE]">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{category.name}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{category.menu_items_count}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{category.sort_order}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {showArchived ? (
                                                    <span className="inline-flex px-2.5 py-0.5 text-xs font-semibold leading-5 rounded-full bg-slate-700 text-white">{t('Archived')}</span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleStatus(category)}
                                                        className={`inline-flex px-2.5 py-0.5 text-xs font-semibold leading-5 rounded-full ${
                                                            category.is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
                                                        }`}
                                                    >
                                                        {category.is_active ? t('Active') : t('Inactive')}
                                                    </button>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                                {showArchived ? (
                                                    <button type="button" onClick={() => restore(category)} className="text-green-700 hover:text-green-900">
                                                        {t('Restore')}
                                                    </button>
                                                ) : (
                                                    <>
                                                        <Link href={route('menu-categories.edit', category.id)} className="text-[#8A3330] hover:text-[#5f2120]">
                                                            {t('Edit')}
                                                        </Link>
                                                        <button type="button" onClick={() => archive(category)} className="text-red-600 hover:text-red-900">
                                                            {t('Archive')}
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Mobile cards */}
                    <div className="sm:hidden space-y-3">
                        {categories.map((category) => (
                            <div key={category.id} className="bg-white border border-[#E5DDD0] rounded-xl p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-gray-900">{category.name}</p>
                                        <p className="mt-1 text-xs text-gray-500">
                                            {category.menu_items_count} {t('items')} &middot; {t('Sort')} {category.sort_order}
                                        </p>
                                    </div>
                                    {showArchived ? (
                                        <span className="inline-flex px-2.5 py-0.5 text-xs font-semibold leading-5 rounded-full bg-slate-700 text-white shrink-0">{t('Archived')}</span>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => toggleStatus(category)}
                                            className={`shrink-0 inline-flex px-2.5 py-0.5 text-xs font-semibold leading-5 rounded-full ${
                                                category.is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
                                            }`}
                                        >
                                            {category.is_active ? t('Active') : t('Inactive')}
                                        </button>
                                    )}
                                </div>

                                <div className="mt-3 pt-3 border-t border-[#E5DDD0] flex items-center justify-end gap-4 text-sm">
                                    {showArchived ? (
                                        <button type="button" onClick={() => restore(category)} className="text-green-700 hover:text-green-900">
                                            {t('Restore')}
                                        </button>
                                    ) : (
                                        <>
                                            <Link href={route('menu-categories.edit', category.id)} className="text-[#8A3330] hover:text-[#5f2120]">
                                                {t('Edit')}
                                            </Link>
                                            <button type="button" onClick={() => archive(category)} className="text-red-600 hover:text-red-900">
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
        </AuthenticatedLayout>
    );
}
