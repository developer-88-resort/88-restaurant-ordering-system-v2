import { useTranslation } from '@/lib/i18n';
import { useEffect } from 'react';

/**
 * Tablet PIN entry: masked dots over a 3×4 keypad (same keys as the Weigh
 * keypad) and a confirm button. A physical keyboard works too — digits,
 * Backspace, Enter.
 *
 * Controlled: the digits live in the parent's `value`.
 */
export default function PinPad({ value, onChange, onSubmit, length, submitLabel, disabled = false, error = null, modern = false }) {
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
        modern ? 'h-14 rounded-xl border border-slate-200 bg-white text-2xl font-medium tabular-nums text-slate-800 shadow-sm transition duration-150 hover:border-slate-300 hover:bg-slate-50 active:scale-[.97] active:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 select-none disabled:cursor-wait disabled:opacity-50' : 'h-16 rounded-xl border border-[#D9CCBA] bg-white text-2xl font-semibold text-gray-800 active:bg-[#F3E1DC] active:scale-[.97] transition select-none disabled:opacity-50';

    return (
        <div>
            <div className={modern ? "flex min-h-16 items-center justify-center gap-4 rounded-xl border border-slate-100 bg-slate-50" : "flex justify-center gap-3"} aria-label={`${t('PIN digits entered')}: ${value.length}`} role="status">
                {Array.from({ length: length.max }, (_, i) => (
                    <span
                        key={i}
                        className={`h-4 w-4 rounded-full border-2 transition ${
                            error
                                ? 'border-red-500 bg-red-500'
                                : i < value.length
                                  ? (modern ? 'border-slate-800 bg-slate-800' : 'border-[#8A3330] bg-[#8A3330]')
                                  : i < length.min
                                    ? (modern ? 'border-slate-400' : 'border-[#B9A89A]')
                                    : (modern ? 'border-dashed border-slate-300' : 'border-dashed border-[#D9CCBA]')
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
                <button type="button" disabled={disabled} onClick={clear} className={`${keyClass} !text-sm ${modern ? '!text-slate-500' : 'text-[#8A3330]'}`}>
                    {t('Clear')}
                </button>
                <button type="button" disabled={disabled} onClick={() => press('0')} className={keyClass}>
                    0
                </button>
                <button type="button" disabled={disabled} onClick={backspace} className={`${keyClass} flex items-center justify-center`} aria-label={t('Backspace')}>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="h-6 w-6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M9.2 5.5h10.3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9.2a2 2 0 0 1-1.5-.68L3 12l4.7-5.82a2 2 0 0 1 1.5-.68Z" fill="currentColor" fillOpacity=".04" />
                        <path d="m12 9 6 6m0-6-6 6" />
                    </svg>
                </button>
            </div>

            <button
                type="button"
                onClick={submit}
                disabled={disabled || !ready}
                className={modern ? "mt-5 min-h-12 w-full rounded-xl bg-slate-800 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" : "mt-4 w-full rounded-xl bg-[#8A3330] py-3.5 font-semibold text-white transition hover:bg-[#742927] disabled:opacity-50"}
            >
                {submitLabel}
            </button>
        </div>
    );
}
