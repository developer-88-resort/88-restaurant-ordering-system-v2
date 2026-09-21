import { useTranslation } from '@/lib/i18n';
import { useEffect } from 'react';

/**
 * Tablet PIN entry: masked dots over a 3×4 keypad (same keys as the Weigh
 * keypad) and a confirm button. A physical keyboard works too — digits,
 * Backspace, Enter.
 *
 * Controlled: the digits live in the parent's `value`.
 */
export default function PinPad({ value, onChange, onSubmit, length, submitLabel, disabled = false, error = null }) {
    const t = useTranslation();
    const ready = value.length >= length.min;

    const press = (digit) => {
        if (!disabled && value.length < length.max) onChange(value + digit);
    };
    const backspace = () => !disabled && onChange(value.slice(0, -1));
    const clear = () => !disabled && onChange('');
    const submit = () => !disabled && ready && onSubmit();

    useEffect(() => {
        const onKey = (e) => {
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
            if (/^\d$/.test(e.key)) press(e.key);
            else if (e.key === 'Backspace') backspace();
            else if (e.key === 'Escape') clear();
            else if (e.key === 'Enter') submit();
            else return;
            e.preventDefault();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    const keyClass =
        'h-16 rounded-xl border border-[#D9CCBA] bg-white text-2xl font-semibold text-gray-800 active:bg-[#F3E1DC] active:scale-[.97] transition select-none disabled:opacity-50';

    return (
        <div>
            <div className="flex justify-center gap-3" aria-label={`${t('PIN digits entered')}: ${value.length}`} role="status">
                {Array.from({ length: length.max }, (_, i) => (
                    <span
                        key={i}
                        className={`h-4 w-4 rounded-full border-2 transition ${
                            error
                                ? 'border-red-500 bg-red-500'
                                : i < value.length
                                  ? 'border-[#8A3330] bg-[#8A3330]'
                                  : i < length.min
                                    ? 'border-[#B9A89A]'
                                    : 'border-dashed border-[#D9CCBA]'
                        }`}
                    />
                ))}
            </div>

            <p className="mt-3 min-h-[1.25rem] text-center text-sm font-medium text-red-600" aria-live="assertive">
                {error}
            </p>

            <div className="mt-3 grid grid-cols-3 gap-2.5">
                {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((digit) => (
                    <button key={digit} type="button" disabled={disabled} onClick={() => press(String(digit))} className={keyClass}>
                        {digit}
                    </button>
                ))}
                <button type="button" disabled={disabled} onClick={clear} className={`${keyClass} text-base text-[#8A3330]`}>
                    {t('Clear')}
                </button>
                <button type="button" disabled={disabled} onClick={() => press('0')} className={keyClass}>
                    0
                </button>
                <button type="button" disabled={disabled} onClick={backspace} className={`${keyClass} text-base`} aria-label={t('Backspace')}>
                    ⌫
                </button>
            </div>

            <button
                type="button"
                onClick={submit}
                disabled={disabled || !ready}
                className="mt-4 w-full rounded-xl bg-[#8A3330] py-3.5 font-semibold text-white transition hover:bg-[#742927] disabled:opacity-50"
            >
                {submitLabel}
            </button>
        </div>
    );
}
