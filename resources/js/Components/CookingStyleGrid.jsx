import { useTranslation } from '@/lib/i18n';

// Pill-button multi-select for Cooking Styles — used by the Menu Category
// form (the source of truth for which styles a category's per-kilo items
// offer) and previously lived inline in the Menu Item form before that
// curation moved from item to category.
export default function CookingStyleGrid({ styles, selectedIds, onToggle }) {
    const t = useTranslation();

    if (styles.length === 0) {
        return <p className="text-sm text-gray-400">{t('No cooking styles have been set up yet.')}</p>;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {styles.map((style) => {
                const selected = selectedIds.includes(style.id);

                return (
                    <button
                        key={style.id}
                        type="button"
                        onClick={() => onToggle(style.id)}
                        aria-pressed={selected}
                        className={`px-3 py-1.5 rounded-full border text-sm font-medium transition ${
                            selected
                                ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-sm'
                                : 'border-[#D9CCBA] text-gray-600 hover:border-[#8A3330]'
                        }`}
                    >
                        {style.name}
                        {style.surcharge > 0 && (
                            <span className={selected ? 'ml-1 text-white/80' : 'ml-1 text-gray-400'}>
                                +₱{style.surcharge.toFixed(2)}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
