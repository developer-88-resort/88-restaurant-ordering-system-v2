import CategoryReferenceList from '@/Components/CategoryReferenceList';
import { useTranslation } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

export default function CategoryForm({ title, existingCategories, data, setData, errors, processing, onSubmit, submitLabel }) {
    const t = useTranslation();

    return (
        <div className="flex flex-col lg:flex-row gap-6 items-start">
            <div className="flex-1 max-w-2xl w-full">
                <h1 className="text-2xl font-bold text-gray-900 mb-6">{title}</h1>

                <div className="bg-white border border-[#E5DDD0] rounded-xl p-8">
                    <form onSubmit={onSubmit}>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div className="sm:col-span-2">
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700">{t('Name')}</label>
                                <input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                />
                                {errors.name && <p className="text-sm text-red-600 mt-2">{errors.name}</p>}
                            </div>

                            <div>
                                <label htmlFor="sort_order" className="block text-sm font-medium text-gray-700">{t('Sort Order')}</label>
                                <input
                                    id="sort_order"
                                    type="number"
                                    min="0"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', e.target.value)}
                                    className="block mt-1 w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm"
                                />
                                {errors.sort_order && <p className="text-sm text-red-600 mt-2">{errors.sort_order}</p>}
                            </div>
                        </div>

                        <div className="mt-5 flex items-center">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 text-[#8A3330] shadow-sm focus:ring-[#8A3330]"
                            />
                            <label htmlFor="is_active" className="ms-2 text-sm text-gray-600">{t('Active')}</label>
                        </div>

                        <div className="flex items-center justify-end mt-8 space-x-3 border-t border-[#E5DDD0] pt-6">
                            <Link href={route('menu-categories.index')} className="text-sm text-gray-600 hover:text-gray-900">
                                {t('Cancel')}
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition ease-in-out duration-150 disabled:opacity-70"
                            >
                                {submitLabel}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div className="w-full lg:w-72 shrink-0">
                <CategoryReferenceList categories={existingCategories} />
            </div>
        </div>
    );
}
