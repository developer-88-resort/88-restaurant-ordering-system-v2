import { useTranslation } from '@/lib/i18n';

export default function CategoryReferenceList({ categories }) {
    const t = useTranslation();

    return (
        <div className="bg-white border border-[#E5DDD0] rounded-xl p-6">
            <h3 className="text-sm font-semibold text-gray-900">{t('Existing Categories')}</h3>
            <p className="text-xs text-gray-500 mt-1">{t('Reference for naming and sort order.')}</p>

            {categories.length === 0 ? (
                <p className="mt-4 text-sm text-gray-400">{t('No categories yet — this will be your first one.')}</p>
            ) : (
                <ul className="mt-4 space-y-2">
                    {categories.map((category) => (
                        <li key={category.id} className="flex items-center justify-between gap-3 text-sm">
                            <span className={`text-gray-700 truncate ${category.is_active ? '' : 'opacity-50'}`}>
                                {category.name}
                                {!category.is_active && <span className="text-[10px] text-gray-400"> ({t('inactive')})</span>}
                            </span>
                            <span className="shrink-0 font-mono text-xs text-[#8A7B9E] bg-[#FAF6EE] rounded px-2 py-0.5">{category.sort_order}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
