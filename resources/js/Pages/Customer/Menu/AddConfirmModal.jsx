import AddOnPicker from '@/Components/AddOnPicker';
import { useTranslation } from '@/lib/i18n';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { Fragment, useEffect, useState } from 'react';
import { cartLineTotal } from './cartLine';

function CloseIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="3" stroke="white" className="h-3 w-3">
            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
    );
}

function CartIcon() {
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

// The single unified "add this to cart?" step for both plain and variant
// items — mirrors resources/views/components/menu/add-confirm-modal.blade.php.
// Wrapped in Headless UI's Dialog directly (not the generic
// Components/Modal.jsx, since that one is hard-coded to a centered
// max-width card and this needs the bottom-sheet/right-panel layout) for
// its built-in focus trap and Escape-to-close.
export default function AddConfirmModal({ item, status, onClose, onConfirm }) {
    const t = useTranslation();
    const [variantId, setVariantId] = useState(null);
    const [qty, setQty] = useState(1);
    const [notes, setNotes] = useState('');
    const [error, setError] = useState(null);
    // Keyed by add-on id -> { checked, qty }. Only checked entries are sent.
    const [addOnSelections, setAddOnSelections] = useState({});

    useEffect(() => {
        if (!item) return;
        const defaultVariant = item.has_variants ? (item.variants.find((v) => v.is_default) ?? item.variants[0] ?? null) : null;
        setVariantId(defaultVariant?.id ?? null);
        setQty(1);
        setNotes('');
        setError(null);
        setAddOnSelections({});
    }, [item]);

    const available = status === 'available' || status === 'seasonal';
    const selectedVariant = item?.has_variants ? (item.variants.find((v) => v.id === variantId) ?? null) : null;
    const unitPrice = item ? (item.has_variants ? (selectedVariant?.price ?? 0) : item.price) : 0;
    const selectedAddOns = (item?.add_ons ?? [])
        .filter((addOn) => addOnSelections[addOn.id]?.checked)
        .map((addOn) => ({ id: addOn.id, name: addOn.name, price: addOn.price, qty: addOnSelections[addOn.id]?.qty ?? 1 }));
    const subtotal = cartLineTotal({ price: unitPrice, qty, addOns: selectedAddOns });

    const toggleAddOn = (addOnId) => {
        const addOn = item?.add_ons?.find((a) => a.id === addOnId);
        if (!addOn || Number(addOn.price) <= 0) return;
        setAddOnSelections((current) => {
            const existing = current[addOnId];
            return { ...current, [addOnId]: { checked: !existing?.checked, qty: existing?.qty ?? 1 } };
        });
    };

    const setAddOnQty = (addOnId, qty) => {
        setAddOnSelections((current) => ({ ...current, [addOnId]: { checked: true, qty: Math.min(99, Math.max(1, qty)) } }));
    };

    const handleConfirm = () => {
        if (!item || !available) return;
        if (item.has_variants && !variantId) {
            setError(t('Please choose an option before adding to cart.'));
            return;
        }
        onConfirm({
            id: item.id,
            variantId: selectedVariant?.id ?? null,
            name: selectedVariant ? `${item.name} — ${selectedVariant.name}` : item.name,
            price: unitPrice,
            qty,
            notes,
            addOns: selectedAddOns,
        });
    };

    return (
        <Transition show={item !== null} as={Fragment}>
            <Dialog as="div" className="fixed inset-0 z-50" onClose={onClose}>
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

                <div className="fixed inset-0 flex items-end justify-center sm:items-stretch sm:justify-end">
                    <TransitionChild
                        as={Fragment}
                        enter="transition ease-out duration-250"
                        enterFrom="opacity-0 translate-y-6 sm:translate-y-0 sm:translate-x-full"
                        enterTo="opacity-100 translate-y-0 sm:translate-x-0"
                        leave="transition ease-in duration-150"
                        leaveFrom="opacity-100 translate-y-0 sm:translate-x-0"
                        leaveTo="opacity-0 translate-y-6 sm:translate-y-0 sm:translate-x-full"
                    >
                        <DialogPanel className="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:h-full sm:max-h-full sm:w-full sm:max-w-md sm:rounded-t-none sm:rounded-l-2xl">
                            {item && (
                                <>
                                    <div className="flex shrink-0 items-start gap-3 border-b border-[#E5DDD0] px-5 pb-4 pt-5">
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#FAF6EE]">
                                            {item.primary_image_url && <img src={item.primary_image_url} className="h-full w-full object-cover" alt={item.name} />}
                                        </div>
                                        <div className="min-w-0 flex-1 pt-0.5">
                                            <DialogTitle className="font-semibold text-gray-900">{item.name}</DialogTitle>
                                            {item.description && (
                                                <div className="rich-text mt-0.5 text-sm text-gray-500" dangerouslySetInnerHTML={{ __html: item.description }} />
                                            )}
                                            <p className="mt-0.5 text-sm text-[#8A7B6D]">{t('Add this item to your cart?')}</p>
                                        </div>
                                        <button type="button" onClick={onClose} aria-label={t('Close')} className="shrink-0 text-gray-400 hover:text-gray-600">
                                            <CloseIcon />
                                        </button>
                                    </div>

                                    <div className="flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-4">
                                        {!available ? (
                                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                                                {t('Currently unavailable')}
                                            </div>
                                        ) : (
                                            <>
                                                {item.has_variants && (
                                                    <div>
                                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{t('Choose an option')}</p>
                                                        <div className="space-y-2">
                                                            {item.variants.map((variant) => (
                                                                <button
                                                                    key={variant.id}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setVariantId(variant.id);
                                                                        setError(null);
                                                                    }}
                                                                    className={`flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition ${
                                                                        variantId === variant.id
                                                                            ? 'border-[#8A3330] bg-[#FAF6EE] ring-1 ring-[#8A3330]'
                                                                            : 'border-[#E5DDD0] hover:border-[#8A3330]'
                                                                    }`}
                                                                >
                                                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-[#FAF6EE]">
                                                                        {variant.image_url && <img src={variant.image_url} className="h-full w-full object-cover" alt="" />}
                                                                    </div>
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="text-sm font-medium text-gray-900">{variant.name}</p>
                                                                        {variant.description && (
                                                                            <p className="mt-0.5 line-clamp-2 text-xs text-[#8A7B6D]">{variant.description}</p>
                                                                        )}
                                                                    </div>
                                                                    <span className="shrink-0 text-sm font-semibold text-[#8A3330]">₱{Number(variant.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                                    <span
                                                                        className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${
                                                                            variantId === variant.id ? 'border-[#8A3330] bg-[#8A3330]' : 'border-[#D9CCBA]'
                                                                        }`}
                                                                    >
                                                                        {variantId === variant.id && <CheckIcon />}
                                                                    </span>
                                                                </button>
                                                            ))}
                                                        </div>
                                                        {error && <p className="mt-2 text-xs font-medium text-red-600">{error}</p>}
                                                    </div>
                                                )}

                                                {item.add_ons?.length > 0 && (
                                                    <AddOnPicker
                                                        addOns={item.add_ons}
                                                        selections={addOnSelections}
                                                        onToggle={toggleAddOn}
                                                        onSetQty={setAddOnQty}
                                                    />
                                                )}

                                                <div className="flex items-center justify-between">
                                                    <p className="text-sm font-medium text-gray-900">{t('Quantity')}</p>
                                                    <div className="flex items-center gap-3">
                                                        <button
                                                            type="button"
                                                            onClick={() => setQty((q) => Math.max(1, q - 1))}
                                                            disabled={qty <= 1}
                                                            className="h-8 w-8 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            −
                                                        </button>
                                                        <span className="w-6 text-center text-sm font-semibold">{qty}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => setQty((q) => Math.min(99, q + 1))}
                                                            disabled={qty >= 99}
                                                            className="h-8 w-8 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            +
                                                        </button>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label className="mb-1.5 block text-sm font-medium text-gray-900">{t('Special instructions (optional)')}</label>
                                                    <textarea
                                                        value={notes}
                                                        onChange={(e) => setNotes(e.target.value)}
                                                        rows={2}
                                                        maxLength={255}
                                                        placeholder={t('e.g. no onions, less spicy')}
                                                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]"
                                                    />
                                                </div>

                                                <div className="flex items-center justify-between border-t border-dashed border-[#E5DDD0] pt-3">
                                                    <span className="font-semibold text-gray-900">{t('Subtotal')}</span>
                                                    <span className="text-lg font-bold text-[#8A3330]">₱{subtotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    <div className="flex shrink-0 gap-3 border-t border-[#E5DDD0] px-5 py-4">
                                        <button
                                            type="button"
                                            onClick={onClose}
                                            className="flex-1 rounded-lg border border-[#D9CCBA] px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"
                                        >
                                            {t('Cancel')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleConfirm}
                                            disabled={!available}
                                            className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-[#8A3330] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <CartIcon />
                                            {t('Yes, Add to Cart')}
                                        </button>
                                    </div>
                                </>
                            )}
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
