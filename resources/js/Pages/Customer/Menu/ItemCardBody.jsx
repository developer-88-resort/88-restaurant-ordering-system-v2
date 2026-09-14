import { useTranslation } from '@/lib/i18n';

function ImagePlaceholderIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.2" stroke="currentColor" className="h-6 w-6 text-[#D9CCBA]">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
            />
        </svg>
    );
}

function ChevronIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    );
}

function AddToCartIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
            />
        </svg>
    );
}

export default function ItemCardBody({ item, status }) {
    const t = useTranslation();
    const orderable = status === 'available' || status === 'seasonal';

    return (
        <>
            <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#FAF6EE]">
                {item.primary_image_url ? (
                    <img src={item.primary_image_url} alt={item.name} className="h-full w-full object-cover" />
                ) : (
                    <ImagePlaceholderIcon />
                )}
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-900">{item.name}</p>
                {item.description_text && <p className="truncate text-xs text-gray-500">{item.description_text}</p>}
                {item.is_per_kilo ? (
                    <>
                        {item.effective_price_per_kilo && (
                            <p className="mt-1 text-sm font-semibold text-[#8A3330]">
                                ₱{Number(item.effective_price_per_kilo).toLocaleString('en-PH', { maximumFractionDigits: 0 })}/kg
                            </p>
                        )}
                        {item.min_weight_grams > 0 && item.effective_price_per_kilo && (
                            <p className="text-[11px] text-gray-400">
                                {t('min')} {item.min_weight_grams >= 1000 ? `${(item.min_weight_grams / 1000).toFixed(item.min_weight_grams % 1000 === 0 ? 0 : 1)} kg` : `${item.min_weight_grams} g`}
                                {' '}(~₱{((item.min_weight_grams / 1000) * item.effective_price_per_kilo).toLocaleString('en-PH', { maximumFractionDigits: 0 })})
                            </p>
                        )}
                        {item.cooking_styles?.length > 0 && (
                            <p className="mt-1 truncate text-[11px] text-gray-500">
                                {item.cooking_styles.map((s) => s.name).join(', ')}
                            </p>
                        )}
                        <p className="mt-1 inline-flex items-center gap-1 rounded-full bg-[#F3E1DC] px-2 py-0.5 text-[10px] font-semibold text-[#8A3330]">
                            {t('Ask our staff — counter service')}
                        </p>
                    </>
                ) : (
                    <p className="mt-0.5 text-sm font-semibold text-[#8A3330]">{item.price_range_label}</p>
                )}
                {status === 'out_of_stock' && (
                    <span className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500">
                        <span className="h-1.5 w-1.5 rounded-full bg-gray-400" />
                        {t('Out of Stock')}
                    </span>
                )}
                {status === 'seasonal' && (
                    <span className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
                        {t('Seasonal')}
                    </span>
                )}
            </div>
            {/* Per-kilo: no action to take here — no chevron, no add-to-cart icon. */}
            {!item.is_per_kilo && orderable && (
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F3E1DC] text-[#8A3330]">
                    {/* Chevron instead of the plus-in-circle for variant items — hints that
                        tapping opens a choice rather than adding directly to the cart. */}
                    {item.has_variants ? <ChevronIcon /> : <AddToCartIcon />}
                </span>
            )}
        </>
    );
}
