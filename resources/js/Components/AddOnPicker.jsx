import { useTranslation } from '@/lib/i18n';

// Checkbox + quantity-stepper multi-select for Menu Item Add-ons. A
// zero/blank-price add-on ("customer's choice", priced at the counter)
// renders disabled with an "Ask staff" label instead of a checkbox —
// mirrors the Cooking Style Set picker's "no fixed price" convention.
// Shared by the customer AddConfirmModal.jsx and the staff Weigh & Order
// wizard (Station.jsx).
export default function AddOnPicker({ addOns, selections, onToggle, onSetQty }) {
    const t = useTranslation();

    return (
        <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{t('Add-ons')}</p>
            <div className="space-y-2">
                {addOns.map((addOn) => {
                    const selection = selections[addOn.id];
                    const checked = !!selection?.checked;
                    const selectable = Number(addOn.price) > 0;

                    return (
                        <div
                            key={addOn.id}
                            className={`rounded-lg border px-3 py-2.5 transition ${
                                checked ? 'border-[#8A3330] bg-[#FAF6EE]' : 'border-[#E5DDD0]'
                            } ${!selectable ? 'opacity-70' : ''}`}
                        >
                            <label className={`flex items-start gap-3 ${selectable ? 'cursor-pointer' : 'cursor-default'}`}>
                                <input
                                    type="checkbox"
                                    checked={checked}
                                    disabled={!selectable}
                                    onChange={() => onToggle(addOn.id)}
                                    className="mt-0.5 h-4 w-4 shrink-0 rounded border-[#D9CCBA] text-[#8A3330] focus:ring-[#8A3330] disabled:cursor-not-allowed disabled:opacity-40"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium text-gray-900">{addOn.name}</span>
                                    {addOn.description && (
                                        <span className="block text-xs text-[#8A7B6D]">{addOn.description}</span>
                                    )}
                                </span>
                                {selectable ? (
                                    <span className="shrink-0 text-sm font-semibold text-[#8A3330]">
                                        ₱{Number(addOn.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>
                                ) : (
                                    <span className="shrink-0 text-xs font-medium text-[#8A7B6D]">{t('Ask staff')}</span>
                                )}
                            </label>
                            {checked && selectable && (
                                <div className="mt-2 flex items-center justify-end gap-3 pl-7">
                                    <button
                                        type="button"
                                        onClick={() => onSetQty(addOn.id, (selection?.qty ?? 1) - 1)}
                                        disabled={(selection?.qty ?? 1) <= 1}
                                        className="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        −
                                    </button>
                                    <span className="w-6 text-center text-sm font-semibold">{selection?.qty ?? 1}</span>
                                    <button
                                        type="button"
                                        onClick={() => onSetQty(addOn.id, (selection?.qty ?? 1) + 1)}
                                        disabled={(selection?.qty ?? 1) >= 99}
                                        className="h-7 w-7 rounded-full border border-[#D9CCBA] text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        +
                                    </button>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
