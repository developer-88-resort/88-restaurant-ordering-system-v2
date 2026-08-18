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
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    );
}

export default function ItemCardBody({ item, status }) {
    const t = useTranslation();
    const orderable = status === 'available' || status === 'seasonal';

    return (
        <>
            <div className="flex h-[72px] w-[72px] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#FAF6EE] sm:h-20 sm:w-20">
                {item.primary_image_url ? (
                    <img src={item.primary_image_url} alt={item.name} className="h-full w-full object-cover" loading="lazy" />
                ) : (
                    <ImagePlaceholderIcon />
                )}
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-[#251C19] sm:text-[15px]">{item.name}</p>
                {item.description && <p className="mt-0.5 truncate text-xs text-[#8A7B6D]">{item.description}</p>}
                {item.is_per_kilo ? (
                    <>
                        <p className="mt-1 text-xs leading-4 text-[#8A7B6D]">{t('Priced per kilogram (market price). Please order in person so staff can weigh it for you.')}</p>
                        {item.effective_price_per_kilo && (
                            <p className="mt-1 text-xs font-semibold text-[#1F3D2B]">
                                ~₱{Number(item.effective_price_per_kilo).toLocaleString('en-PH', { maximumFractionDigits: 0 })}/kg {t('today')}
                            </p>
                        )}
                    </>
                ) : (
                    <p className="mt-1 text-sm font-bold text-[#8A3330]">{item.price_range_label}</p>
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
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#F3E1DC] text-[#8A3330] transition-transform group-hover:scale-105">
                    {/* Chevron instead of the plus for variant items — hints that
                        tapping opens a choice rather than adding directly to the cart. */}
                    {item.has_variants ? <ChevronIcon /> : <AddToCartIcon />}
                </span>
            )}
        </>
    );
}
