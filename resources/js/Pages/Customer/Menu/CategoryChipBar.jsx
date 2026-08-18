import { useTranslation } from '@/lib/i18n';

export default function CategoryChipBar({ categories, selectedCategory, onSelect, chipBarRef }) {
    const t = useTranslation();
    const visibleCategories = categories.filter((category) => !category.items.every((item) => item.is_per_kilo));

    const chipClass = (active) =>
        `shrink-0 whitespace-nowrap rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors ${
            active ? 'border-[#8A3330] bg-[#8A3330] text-white' : 'border-[#E5DDD0] bg-white text-gray-600 hover:border-[#8A3330]'
        }`;

    return (
        <div className="sticky top-16 z-30 border-b border-[#E5DDD0] bg-[#F7F0E3]/95 backdrop-blur-sm">
            <div ref={chipBarRef} className="no-scrollbar mx-auto flex max-w-5xl items-center gap-2 overflow-x-auto px-4 py-2.5">
                <button type="button" id="chip-all" onClick={() => onSelect('all')} className={chipClass(selectedCategory === 'all')}>
                    {t('All')}
                </button>
                {visibleCategories.map((category) => (
                    <button
                        key={category.id}
                        type="button"
                        id={`chip-${category.id}`}
                        onClick={() => onSelect(category.id)}
                        className={chipClass(selectedCategory === category.id)}
                    >
                        {category.name}
                    </button>
                ))}
            </div>
        </div>
    );
}
