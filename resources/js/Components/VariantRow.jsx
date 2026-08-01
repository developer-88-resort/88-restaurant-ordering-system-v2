import { useTranslation } from '@/lib/i18n';

export default function VariantRow({ variant, index, isDefault, onChange, onRemove, onSetDefault }) {
    const t = useTranslation();

    const previewUrl = variant.newImagePreview || (!variant.removeImage ? variant.existingImageUrl : null);

    const handleFile = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        onChange(index, { newImageFile: file, newImagePreview: URL.createObjectURL(file), removeImage: false });
    };

    return (
        <div className="flex gap-3 border border-[#E5DDD0] rounded-lg p-3 mb-3">
            <label className="shrink-0 relative h-16 w-16 rounded-md bg-[#FAF6EE] border border-dashed border-[#D9CCBA] overflow-hidden cursor-pointer flex items-center justify-center hover:border-[#8A3330]">
                {previewUrl ? (
                    <img src={previewUrl} className="absolute inset-0 h-full w-full object-cover" alt="" />
                ) : (
                    <span className="text-[9px] text-gray-400 text-center px-1 leading-tight">{t('Photo (optional)')}</span>
                )}
                <input type="file" accept="image/*" className="sr-only" onChange={handleFile} />
            </label>

            <div className="flex-1 min-w-0">
                <div className="grid grid-cols-12 gap-2">
                    <div className="col-span-1 flex flex-col items-center gap-0.5">
                        <input type="radio" checked={isDefault} onChange={() => onSetDefault(index)} className="mt-1" />
                        <span className="text-[8px] text-gray-400 uppercase tracking-wide">{t('Default')}</span>
                    </div>
                    <div className="col-span-4">
                        <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('Variant Name')}</label>
                        <input
                            type="text"
                            value={variant.name}
                            onChange={(e) => onChange(index, { name: e.target.value })}
                            placeholder={t('e.g. Spicy, Solo, Large')}
                            className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                        />
                    </div>
                    <div className="col-span-3">
                        <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('SKU')}</label>
                        <input
                            type="text"
                            value={variant.sku}
                            onChange={(e) => onChange(index, { sku: e.target.value })}
                            placeholder={t('optional')}
                            className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                        />
                    </div>
                    <div className="col-span-3">
                        <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('Price')}</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={variant.price}
                            onChange={(e) => onChange(index, { price: e.target.value })}
                            placeholder="0.00"
                            className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                        />
                    </div>
                    <div className="col-span-1 flex justify-center pt-4">
                        <button type="button" onClick={() => onRemove(index)} title={t('Remove this variant')} className="text-gray-400 hover:text-red-600">
                            ✕
                        </button>
                    </div>
                    <div className="col-span-11">
                        <label className="block text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{t('Description (optional)')}</label>
                        <input
                            type="text"
                            value={variant.description}
                            onChange={(e) => onChange(index, { description: e.target.value })}
                            placeholder={t('e.g. what makes this variant different')}
                            className="block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
