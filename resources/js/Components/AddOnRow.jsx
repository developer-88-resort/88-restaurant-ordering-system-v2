import { useTranslation } from '@/lib/i18n';

export default function AddOnRow({ addOn, index, onChange, onRemove }) {
    const t = useTranslation();

    return (
        <div className="flex flex-wrap items-end gap-2 border border-[#E5DDD0] rounded-lg p-3 mb-3">
            <div className="flex-1 min-w-[10rem]">
                <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('Add-on Name')}</label>
                <input
                    type="text"
                    value={addOn.name}
                    onChange={(e) => onChange(index, { name: e.target.value })}
                    placeholder={t('e.g. Crispy Pata (1 pc.)')}
                    className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                />
            </div>
            <div className="w-32 shrink-0">
                <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('Price (optional)')}</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={addOn.price}
                    onChange={(e) => onChange(index, { price: e.target.value })}
                    placeholder={t('e.g. blank for "customer\'s choice"')}
                    className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                />
            </div>
            <button
                type="button"
                onClick={() => onRemove(index)}
                title={t('Remove this add-on')}
                className="shrink-0 pb-2 text-gray-400 hover:text-red-600"
            >
                ✕
            </button>
            <div className="w-full">
                <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('Description (optional)')}</label>
                <input
                    type="text"
                    value={addOn.description}
                    onChange={(e) => onChange(index, { description: e.target.value })}
                    placeholder={t('e.g. 150g per order')}
                    className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                />
            </div>
        </div>
    );
}
