import { useTranslation } from '@/lib/i18n';

export default function CategoryReferenceList({ categories }) {
    const t = useTranslation();

    return (
        <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
            <h3 className="text-sm font-semibold text-gray-900">{t('Existing Categories')}</h3>
            <p className="text-xs text-gray-500 mt-1">{t('Reference for naming and sort order.')}</p>

            {categories.length === 0 ? (
                <p className="mt-4 text-sm text-gray-400">{t('No categories yet — this will be your first one.')}</p>
            ) : (
                <ul className="mt-4 grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-x-5">
                    {categories.map((category) => (
                        <li key={category.id} className="flex items-center justify-between gap-3 min-w-0 border-t border-[#E5DDD0]/70 py-3 text-sm">
                            <span className={`min-w-0 break-words text-gray-700 ${category.is_active ? '' : 'opacity-50'}`}>
                                {category.name}
                                {!category.is_active && <span className="text-[10px] text-gray-400"> ({t('inactive')})</span>}
                            </span>
                            <span className="shrink-0 min-w-7 text-center font-mono text-xs text-[#8A3330] bg-[#FAF6EE] rounded px-2 py-1">{category.sort_order}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
