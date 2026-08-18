import InputError from '@/Components/InputError';
import { useTranslation } from '@/lib/i18n';

function CloseIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

function CartOutlineIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-6 w-6">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
            />
        </svg>
    );
}

// Sticky bottom cart: full-width bar + bottom sheet on mobile, floating
// pill + right-side panel on desktop — mirrors the "Sticky bottom cart"
// block in customer/menu.blade.php.
export default function CartDrawer({ cart, notes, onNotesChange, cartOpen, onToggle, total, count, isEmpty, onIncrement, onDecrement, onPlaceOrder, errors }) {
    const t = useTranslation();
    const eachLabel = t('each');

    if (isEmpty) return null;

    return (
        <div className="fixed inset-x-0 bottom-0 z-40 sm:inset-x-auto sm:bottom-4 sm:right-4">
            {cartOpen && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => onToggle(false)} />
                    <div className="fixed inset-x-0 bottom-0 z-50 flex max-h-[70vh] flex-col overflow-hidden rounded-t-2xl border-t border-[#E5DDD0] bg-white shadow-2xl sm:inset-x-auto sm:bottom-0 sm:right-0 sm:top-0 sm:h-full sm:max-h-full sm:w-full sm:max-w-md sm:rounded-t-none sm:rounded-l-2xl sm:border-l sm:border-t-0">
                        <div className="flex shrink-0 items-center justify-between border-b border-[#E5DDD0] p-4">
                            <h3 className="font-semibold text-gray-900">{t('Your Order')}</h3>
                            <button type="button" onClick={() => onToggle(false)} className="text-gray-400 hover:text-gray-600">
                                <CloseIcon />
                            </button>
                        </div>

                        <div className="flex-1 space-y-3 overflow-y-auto overscroll-contain p-4">
                            {cart.map((line, index) => (
                                <div key={index}>
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-gray-900">{line.name}</p>
                                            <p className="text-xs text-gray-500">
                                                ₱{line.price.toFixed(2)} {eachLabel}
                                            </p>
                                            {line.notes && <p className="mt-0.5 text-xs italic text-gray-400">{line.notes}</p>}
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => onDecrement(index)}
                                                className="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50"
                                            >
                                                −
                                            </button>
                                            <span className="w-5 text-center text-sm font-medium">{line.qty}</span>
                                            <button
                                                type="button"
                                                onClick={() => onIncrement(index)}
                                                className="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <div className="pt-1">
                                <label htmlFor="cart-notes" className="block text-sm font-medium text-gray-700">
                                    {t('Notes (optional)')}
                                </label>
                                <textarea
                                    id="cart-notes"
                                    rows={2}
                                    value={notes}
                                    onChange={(e) => onNotesChange(e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]"
                                />
                                <InputError message={errors?.items} className="mt-2" />
                                <InputError message={errors?.notes} className="mt-2" />
                            </div>
                        </div>

                        <div className="shrink-0 border-t border-[#E5DDD0] p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <span className="font-semibold text-gray-900">{t('Total')}</span>
                                <span className="text-lg font-bold text-[#8A3330]">₱{total.toFixed(2)}</span>
                            </div>
                            <button
                                type="button"
                                disabled={isEmpty}
                                onClick={onPlaceOrder}
                                className="flex w-full items-center justify-center rounded-lg bg-[#8A3330] px-4 py-3 font-semibold text-white transition hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {t('Place Order')}
                            </button>
                        </div>
                    </div>
                </>
            )}

            {!cartOpen && (
                <button
                    type="button"
                    onClick={() => onToggle(true)}
                    className="flex w-full items-center justify-between bg-[#8A3330] px-4 py-3.5 text-white shadow-2xl transition hover:bg-[#742927] sm:w-auto sm:gap-4 sm:rounded-full sm:px-5 sm:py-3"
                >
                    <span className="flex items-center gap-3">
                        <span className="relative shrink-0">
                            <CartOutlineIcon />
                            {count > 0 && (
                                <span className="absolute -right-2 -top-2 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-white px-1 text-[11px] font-bold leading-none text-[#8A3330]">
                                    {count}
                                </span>
                            )}
                        </span>
                        <span className="text-sm font-semibold">₱{total.toFixed(2)}</span>
                    </span>
                    <span className="text-sm font-semibold sm:ms-1">{t('View Order')}</span>
                </button>
            )}
        </div>
    );
}
