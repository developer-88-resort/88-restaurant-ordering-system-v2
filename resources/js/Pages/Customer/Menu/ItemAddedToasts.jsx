import { useTranslation } from '@/lib/i18n';

function SuccessIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="size-[18px] shrink-0 overflow-visible fill-green-700" viewBox="0 0 330 330" aria-hidden="true">
            <path d="M165 0C74.019 0 0 74.019 0 165s74.019 165 165 165 165-74.019 165-165S255.981 0 165 0m0 300c-74.44 0-135-60.561-135-135S90.56 30 165 30s135 60.561 135 135-60.561 135-135 135" />
            <path d="m226.872 106.664-84.854 84.853-38.89-38.891c-5.857-5.857-15.355-5.858-21.213-.001-5.858 5.858-5.858 15.355 0 21.213l49.496 49.498a15 15 0 0 0 10.606 4.394h.001c3.978 0 7.793-1.581 10.606-4.393l95.461-95.459c5.858-5.858 5.858-15.355 0-21.213s-15.355-5.859-21.213-.001" />
        </svg>
    );
}

function WarningIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="size-[18px] shrink-0 overflow-visible fill-yellow-700" viewBox="0 0 486.463 486.463" aria-hidden="true">
            <path d="M243.225 333.382c-13.6 0-25 11.4-25 25s11.4 25 25 25c13.1 0 25-11.4 24.4-24.4.6-14.3-10.7-25.6-24.4-25.6" />
            <path d="M474.625 421.982c15.7-27.1 15.8-59.4.2-86.4l-156.6-271.2c-15.5-27.3-43.5-43.5-74.9-43.5s-59.4 16.3-74.9 43.4l-156.8 271.5c-15.6 27.3-15.5 59.8.3 86.9 15.6 26.8 43.5 42.9 74.7 42.9h312.8c31.3 0 59.4-16.3 75.2-43.6m-34-19.6c-8.7 15-24.1 23.9-41.3 23.9h-312.8c-17 0-32.3-8.7-40.8-23.4-8.6-14.9-8.7-32.7-.1-47.7l156.8-271.4c8.5-14.9 23.7-23.7 40.9-23.7 17.1 0 32.4 8.9 40.9 23.8l156.7 271.4c8.4 14.6 8.3 32.2-.3 47.1" />
            <path d="M237.025 157.882c-11.9 3.4-19.3 14.2-19.3 27.3.6 7.9 1.1 15.9 1.7 23.8 1.7 30.1 3.4 59.6 5.1 89.7.6 10.2 8.5 17.6 18.7 17.6s18.2-7.9 18.7-18.2c0-6.2 0-11.9.6-18.2 1.1-19.3 2.3-38.6 3.4-57.9.6-12.5 1.7-25 2.3-37.5 0-4.5-.6-8.5-2.3-12.5-5.1-11.2-17-16.9-28.9-14.1" />
        </svg>
    );
}

function CloseTinyIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="size-2.5 cursor-pointer fill-slate-500" aria-hidden="true" viewBox="0 0 329.269 329">
            <path d="M194.8 164.77 323.013 36.555c8.343-8.34 8.343-21.825 0-30.164-8.34-8.34-21.825-8.34-30.164 0L164.633 134.605 36.422 6.391c-8.344-8.34-21.824-8.34-30.164 0-8.344 8.34-8.344 21.824 0 30.164l128.21 128.215L6.259 292.984c-8.344 8.34-8.344 21.825 0 30.164a21.27 21.27 0 0 0 15.082 6.25c5.46 0 10.922-2.09 15.082-6.25l128.21-128.214 128.216 128.214a21.27 21.27 0 0 0 15.082 6.25c5.46 0 10.922-2.09 15.082-6.25 8.343-8.34 8.343-21.824 0-30.164zm0 0" />
        </svg>
    );
}

// Top-center "item added / out of stock" toast queue — visually distinct
// from Components/Toast.jsx's bottom-right flash-message toasts, so this is
// a small sibling rather than a reuse. Mirrors the "Item added" toasts
// block in customer/menu.blade.php.
export default function ItemAddedToasts({ toasts, onDismiss }) {
    const t = useTranslation();

    if (!toasts.length) return null;

    return (
        <div className="pointer-events-none fixed inset-x-0 top-20 z-[60] flex flex-col items-center gap-3 px-4">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    role="alert"
                    className="pointer-events-auto relative w-full max-w-xs rounded-md border border-slate-300 bg-white p-4 shadow-xs sm:w-80"
                >
                    <div className="flex items-start gap-2.5">
                        {toast.type === 'success' ? <SuccessIcon /> : <WarningIcon />}
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium leading-tight text-slate-900">{toast.name}</p>
                            <p className="mt-1 text-xs text-slate-600">{toast.type === 'success' ? t('was added to your cart') : t('Out of Stock')}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => onDismiss(toast.id)}
                            aria-label={t('Dismiss')}
                            className="ml-auto flex shrink-0 items-center rounded opacity-70 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8A3330]"
                        >
                            <CloseTinyIcon />
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}
