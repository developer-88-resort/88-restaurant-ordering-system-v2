import { useTranslation } from '@/lib/i18n';

/**
 * Tablet numeric keypad. Deliberately dumb: it only ever calls back with a
 * digit or an edit action, so the weight itself lives in one place
 * (useWeighInput) and a digital-scale feed can replace this component
 * without anything else changing.
 */
export default function Keypad({ onDigit, onBackspace, onClear }) {
    const t = useTranslation();

    const keyClass =
        'h-16 rounded-xl border border-[#D9CCBA] bg-white text-2xl font-semibold text-gray-800 active:bg-[#F3E1DC] active:scale-[.97] transition select-none';

    return (
        <div className="mt-5 grid grid-cols-3 gap-2.5">
            {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((digit) => (
                <button key={digit} type="button" onClick={() => onDigit(String(digit))} className={keyClass}>
                    {digit}
                </button>
            ))}
            <button type="button" onClick={onClear} className={`${keyClass} text-base text-[#8A3330]`}>
                {t('Clear')}
            </button>
            <button type="button" onClick={() => onDigit('0')} className={keyClass}>
                0
            </button>
            <button type="button" onClick={onBackspace} className={`${keyClass} text-base`} aria-label={t('Backspace')}>
                ⌫
            </button>
        </div>
    );
}
