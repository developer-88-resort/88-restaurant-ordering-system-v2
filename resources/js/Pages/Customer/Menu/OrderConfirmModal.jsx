import { useTranslation } from '@/lib/i18n';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { Fragment } from 'react';
import { cartLineTotal } from './cartLine';

function CloseIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

function CheckCircleIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function PinIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-3 w-3">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
        </svg>
    );
}

function SpinnerIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}

// Final "Confirm Your Order" review before submit — mirrors
// resources/views/components/order-confirm-modal.blade.php. Uses Headless
// UI's Dialog directly (not Components/Modal.jsx) for the mobile
// bottom-sheet / desktop centered-card layout; Dialog's built-in focus trap
// and Escape handling replace the hand-rolled Alpine focus-trap this modal
// used to need.
export default function OrderConfirmModal({ show, cart, notes, total, count, location, submitting, onClose, onSubmit }) {
    const t = useTranslation();
    const eachLabel = t('each');
    const close = () => {
        if (!submitting) onClose();
    };

    return (
        <Transition show={show} as={Fragment}>
            <Dialog as="div" className="fixed inset-0 z-50" onClose={close}>
                <TransitionChild
                    as={Fragment}
                    enter="transition ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="transition ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-end justify-center sm:items-center">
                    <TransitionChild
                        as={Fragment}
                        enter="transition ease-out duration-250"
                        enterFrom="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="transition ease-in duration-150"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-6 sm:translate-y-2 sm:scale-95"
                    >
                        <DialogPanel className="relative flex max-h-[85vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-w-md sm:rounded-2xl">
                            <div className="flex items-start gap-3 border-b border-[#E5DDD0] px-5 pb-4 pt-5">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#F3E1DC] text-[#8A3330]">
                                    <CheckCircleIcon />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <DialogTitle className="font-semibold text-gray-900">{t('Confirm Your Order')}</DialogTitle>
                                    <p className="mt-0.5 text-xs text-[#8A7B6D]">{t('Please review before sending to the kitchen.')}</p>
                                    {location && (
                                        <span className="mt-2 inline-flex items-center gap-1 rounded-full bg-[#F3E1DC] px-2 py-0.5 text-xs font-medium text-[#8A3330]">
                                            <PinIcon />
                                            {location}
                                        </span>
                                    )}
                                </div>
                                <button
                                    type="button"
                                    onClick={close}
                                    disabled={submitting}
                                    aria-label={t('Close')}
                                    className="shrink-0 text-gray-400 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <CloseIcon />
                                </button>
                            </div>

                            <div className="divide-y divide-[#F0E6D8] overflow-y-auto px-5 py-3">
                                {cart.map((line, index) => (
                                    <div key={index} className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                        <div className="flex min-w-0 items-start gap-2">
                                            <span className="mt-0.5 flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-[#F3E1DC] px-1 text-xs font-semibold tabular-nums text-[#8A3330]">
                                                {line.qty}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-gray-900">{line.name}</p>
                                                <p className="text-xs tabular-nums text-[#8A7B6D]">
                                                    ₱{line.price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} {eachLabel}
                                                </p>
                                                {(line.addOns ?? []).length > 0 && (
                                                    <ul className="mt-0.5 space-y-0.5">
                                                        {line.addOns.map((addOn) => (
                                                            <li key={addOn.id} className="text-xs tabular-nums text-[#8A7B6D]">
                                                                + {addOn.qty}× {addOn.name} (₱{(addOn.price * addOn.qty).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                                {line.notes && <p className="mt-0.5 text-xs italic text-gray-400">{line.notes}</p>}
                                            </div>
                                        </div>
                                        <p className="shrink-0 text-sm font-semibold tabular-nums text-gray-900">₱{cartLineTotal(line).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                                    </div>
                                ))}

                                {notes.trim().length > 0 && (
                                    <div className="py-3">
                                        <p className="mb-1 text-xs font-medium text-[#8A7B6D]">{t('Notes')}</p>
                                        <p className="text-sm text-gray-700">{notes}</p>
                                    </div>
                                )}
                            </div>

                            <div className="border-t border-[#E5DDD0] bg-[#FAF6EE] px-5 py-4">
                                <div className="mb-4 flex items-end justify-between">
                                    <div>
                                        <span className="font-semibold text-gray-900">{t('Total')}</span>
                                        <p className="text-xs text-[#8A7B6D]">
                                            {count} {t('items')}
                                        </p>
                                    </div>
                                    <span className="text-lg font-bold tabular-nums text-[#8A3330]">₱{total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex gap-3">
                                    <button
                                        type="button"
                                        disabled={submitting}
                                        onClick={close}
                                        className="flex-1 rounded-lg border border-[#D9CCBA] bg-white px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {t('Edit Order')}
                                    </button>
                                    <button
                                        type="button"
                                        disabled={submitting}
                                        onClick={onSubmit}
                                        className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-[#8A3330] px-4 py-3 font-semibold text-white transition hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        {submitting && <SpinnerIcon />}
                                        {submitting ? t('Placing Order...') : t('Confirm & Place')}
                                    </button>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
