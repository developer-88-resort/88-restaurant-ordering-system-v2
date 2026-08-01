import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Keypad from './Keypad';
import StepDestination from './StepDestination';
import { peso, previewTotal, useWeighInput } from './useWeighDraft';

const STEPS = ['Item', 'Price', 'Weight', 'Cooking', 'Where', 'Confirm'];

export default function Station({ categories, areas, can, requiresCustomerConfirmation }) {
    const t = useTranslation();

    const [step, setStep] = useState(1);
    const [item, setItem] = useState(null);
    const [search, setSearch] = useState('');

    // Step 2 — price
    const [priceRaw, setPriceRaw] = useState('');
    const [overriding, setOverriding] = useState(false);
    const [overrideReason, setOverrideReason] = useState('');

    // Step 4 — cooking
    const [styleId, setStyleId] = useState('');
    const [cookingNote, setCookingNote] = useState('');

    // Step 5 — destination
    const [destination, setDestination] = useState('dine_in');
    const [target, setTarget] = useState({ order: null, guestId: null, tableName: null });
    const [takeout, setTakeout] = useState({ name: '', contact: '' });

    const [submitting, setSubmitting] = useState(false);
    const [serverErrors, setServerErrors] = useState([]);

    const weigh = useWeighInput({
        minWeightGrams: item?.min_weight_grams ?? 250,
        weightStepGrams: item?.weight_step_grams ?? 10,
        allowTare: item?.allow_tare ?? false,
    });

    const pricePerKilo = overriding && priceRaw !== '' ? Number(priceRaw) : (item?.price_per_kilo ?? 0);
    const style = useMemo(
        () => item?.cooking_styles.find((s) => s.id === Number(styleId)) ?? null,
        [item, styleId],
    );
    const surcharge = style?.surcharge ?? 0;
    const total = previewTotal({ net: weigh.net, pricePerKilo, surcharge, pieces: weigh.pieces });

    const filteredCategories = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return categories;
        return categories
            .map((c) => ({ ...c, items: c.items.filter((i) => i.name.toLowerCase().includes(q)) }))
            .filter((c) => c.items.length > 0);
    }, [categories, search]);

    const startOver = () => {
        setStep(1);
        setItem(null);
        setSearch('');
        setPriceRaw('');
        setOverriding(false);
        setOverrideReason('');
        setStyleId('');
        setCookingNote('');
        setDestination('dine_in');
        setTarget({ order: null, guestId: null, tableName: null });
        setTakeout({ name: '', contact: '' });
        setServerErrors([]);
        weigh.reset();
    };

    const chooseItem = (chosen) => {
        setItem(chosen);
        setPriceRaw(String(chosen.price_per_kilo));
        setOverriding(false);
        setOverrideReason('');
        setStyleId(chosen.cooking_styles.length === 1 ? String(chosen.cooking_styles[0].id) : '');
        weigh.reset();
        setStep(2);
    };

    const canContinue = () => {
        if (step === 1) return item !== null;
        if (step === 2) return pricePerKilo > 0 && (!overriding || overrideReason.trim() !== '');
        if (step === 3) return weigh.isValid;
        if (step === 4) return styleId !== '';
        if (step === 5) return target.order !== null && (destination === 'takeout' || target.guestId !== null);
        return true;
    };

    const submit = () => {
        if (submitting || !target.order) return;
        setSubmitting(true);
        setServerErrors([]);

        router.post(
            route('orders.items.store', target.order.id),
            {
                menu_item_id: item.id,
                line_type: 'weighed',
                weight_grams: weigh.gross,
                tare_grams: item.allow_tare ? weigh.tare : 0,
                pieces: weigh.pieces,
                cooking_style_id: style.id,
                cooking_note: cookingNote.trim() || null,
                ordered_by_guest_id: destination === 'dine_in' ? target.guestId : null,
                confirmation_status: requiresCustomerConfirmation ? 'pending_customer' : 'confirmed',
                ...(overriding
                    ? { price_per_kilo_snapshot: pricePerKilo, price_override_reason: overrideReason.trim() }
                    : {}),
            },
            {
                preserveScroll: true,
                onError: (errors) => setServerErrors(Object.values(errors)),
                onSuccess: () => startOver(),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('Weigh & Order')} />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-gray-900">{t('Weigh & Order')}</h1>
                {item && (
                    <button type="button" onClick={startOver} className="text-sm font-medium text-gray-500 hover:text-gray-800">
                        {t('Start over')}
                    </button>
                )}
            </div>

            {/* Progress */}
            <ol className="mb-6 flex items-center gap-1 overflow-x-auto pb-1">
                {STEPS.map((label, index) => {
                    const n = index + 1;
                    const done = n < step;
                    const current = n === step;

                    return (
                        <li key={label} className="flex items-center gap-1 shrink-0">
                            <button
                                type="button"
                                disabled={n > step}
                                onClick={() => n < step && setStep(n)}
                                className={`flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold transition ${
                                    current
                                        ? 'bg-[#8A3330] text-white shadow-sm'
                                        : done
                                          ? 'bg-[#F3E1DC] text-[#8A3330] hover:bg-[#e9d2cc]'
                                          : 'bg-white text-gray-400 border border-[#E5DDD0]'
                                }`}
                            >
                                <span className={`grid h-5 w-5 place-items-center rounded-full text-[11px] ${current ? 'bg-white/20' : done ? 'bg-[#8A3330] text-white' : 'bg-gray-100'}`}>
                                    {done ? '✓' : n}
                                </span>
                                <span className="hidden sm:inline">{t(label)}</span>
                            </button>
                            {n < STEPS.length && <span className="h-px w-3 bg-[#D9CCBA]" />}
                        </li>
                    );
                })}
            </ol>

            {serverErrors.length > 0 && (
                <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 space-y-1">
                    {serverErrors.map((message, i) => (
                        <p key={i}>{message}</p>
                    ))}
                </div>
            )}

            {/* ---------------- Step 1 — item ---------------- */}
            {step === 1 && (
                <section>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('Search items…')}
                        className="mb-5 block w-full sm:max-w-sm border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg shadow-sm text-base py-3"
                    />

                    {filteredCategories.length === 0 ? (
                        <div className="bg-white border border-[#E5DDD0] rounded-xl py-16 text-center">
                            <p className="text-gray-500">{t('No per-kilo items found.')}</p>
                            <p className="mt-1 text-sm text-gray-400">{t('Set a menu item\'s Pricing Type to "Per Kilo (Weighed)" first.')}</p>
                        </div>
                    ) : (
                        filteredCategories.map((category) => (
                            <div key={category.id} className="mb-7">
                                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{category.name}</h2>
                                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                    {category.items.map((menuItem) => (
                                        <button
                                            key={menuItem.id}
                                            type="button"
                                            onClick={() => chooseItem(menuItem)}
                                            className="group bg-white border border-[#E5DDD0] rounded-xl overflow-hidden text-left shadow-sm hover:border-[#8A3330] hover:shadow-md transition active:scale-[.98]"
                                        >
                                            <div className="aspect-[4/3] bg-gradient-to-br from-[#FAF6EE] to-[#F1E9DA]">
                                                {menuItem.image_url && (
                                                    <img src={menuItem.image_url} alt={menuItem.name} className="h-full w-full object-cover" />
                                                )}
                                            </div>
                                            <div className="p-3">
                                                <p className="text-[15px] font-bold text-gray-900 leading-snug">{menuItem.name}</p>
                                                <p className="mt-1 text-base font-bold text-[#8A3330]">
                                                    {peso(menuItem.price_per_kilo)}
                                                    <span className="text-xs font-semibold text-[#8A7B6D]"> / {t('kg')}</span>
                                                </p>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))
                    )}
                </section>
            )}

            {/* ---------------- Step 2 — price ---------------- */}
            {step === 2 && item && (
                <section className="bg-white border border-[#E5DDD0] rounded-xl p-6 max-w-xl">
                    <h2 className="text-base font-semibold text-gray-900">{t('Price per kilo')}</h2>
                    <p className="mt-1 text-sm text-gray-500">{item.name}</p>

                    <div className="mt-5 rounded-xl bg-[#FAF6EE] border border-[#E5DDD0] px-5 py-6 text-center">
                        <p className="text-4xl font-bold text-[#8A3330]">{peso(pricePerKilo)}</p>
                        <p className="mt-1 text-sm text-[#8A7B6D]">{t("today's rate per kilo")}</p>
                    </div>

                    {!can.overridePrice ? (
                        <p className="mt-4 text-xs text-gray-400">
                            {t("This is the rate set for today on the Daily Market Prices page. Only a manager can change it for a single item.")}
                        </p>
                    ) : !overriding ? (
                        <button
                            type="button"
                            onClick={() => setOverriding(true)}
                            className="mt-4 text-sm font-medium text-[#8A3330] hover:underline"
                        >
                            {t('Use a different price for this item')}
                        </button>
                    ) : (
                        <div className="mt-4 space-y-3">
                            <div>
                                <label htmlFor="override" className="block text-sm font-medium text-gray-700">{t('Price per kilo (₱)')}</label>
                                <input
                                    id="override"
                                    type="number"
                                    step="0.01"
                                    min="10"
                                    max="10000"
                                    value={priceRaw}
                                    onChange={(e) => setPriceRaw(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg text-lg py-3"
                                />
                            </div>
                            <div>
                                <label htmlFor="reason" className="block text-sm font-medium text-gray-700">{t('Reason')}</label>
                                <input
                                    id="reason"
                                    type="text"
                                    value={overrideReason}
                                    onChange={(e) => setOverrideReason(e.target.value)}
                                    maxLength={255}
                                    placeholder={t('e.g. Damaged tail, agreed discount with the customer')}
                                    className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-3"
                                />
                                <p className="mt-1 text-xs text-gray-400">{t('Required. Recorded against this line with your name.')}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => {
                                    setOverriding(false);
                                    setPriceRaw(String(item.price_per_kilo));
                                    setOverrideReason('');
                                }}
                                className="text-sm text-gray-500 hover:text-gray-800"
                            >
                                {t("Back to today's rate")}
                            </button>
                        </div>
                    )}
                </section>
            )}

            {/* ---------------- Step 3 — weight ---------------- */}
            {step === 3 && item && (
                <section className="grid gap-5 lg:grid-cols-2 max-w-4xl">
                    <div className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <h2 className="text-base font-semibold text-gray-900">{t('Weight')}</h2>

                        <div className="mt-4 space-y-3">
                            <FieldButton
                                label={t('Gross (g)')}
                                value={weigh.grossRaw || '0'}
                                active={weigh.target === 'gross'}
                                onClick={() => weigh.setTarget('gross')}
                            />
                            {item.allow_tare && (
                                <FieldButton
                                    label={t('Tare (g)')}
                                    value={weigh.tareRaw || '0'}
                                    active={weigh.target === 'tare'}
                                    onClick={() => weigh.setTarget('tare')}
                                />
                            )}
                            <div className="flex items-center justify-between rounded-lg border border-[#E5DDD0] px-4 py-3">
                                <span className="text-sm font-medium text-gray-700">{t('Pieces')}</span>
                                <div className="flex items-center gap-3">
                                    <button type="button" onClick={() => weigh.setPieces((p) => Math.max(1, p - 1))}
                                            className="h-11 w-11 rounded-full border border-[#D9CCBA] text-xl text-gray-600 active:bg-gray-100">−</button>
                                    <span className="w-8 text-center text-xl font-bold">{weigh.pieces}</span>
                                    <button type="button" onClick={() => weigh.setPieces((p) => Math.min(999, p + 1))}
                                            className="h-11 w-11 rounded-full border border-[#D9CCBA] text-xl text-gray-600 active:bg-gray-100">+</button>
                                </div>
                            </div>
                        </div>

                        <Keypad
                            onDigit={weigh.pressDigit}
                            onBackspace={weigh.pressBackspace}
                            onClear={weigh.clear}
                        />
                    </div>

                    <div className="bg-white border border-[#E5DDD0] rounded-xl p-6 flex flex-col">
                        <p className="text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Computed')}</p>
                        <div className="mt-3 rounded-xl bg-[#FAF6EE] border border-[#E5DDD0] px-5 py-8 text-center">
                            <p className="text-lg text-[#8A7B6D]">
                                {(weigh.net / 1000).toFixed(3)} {t('kg')} × {peso(pricePerKilo)}/{t('kg')}
                            </p>
                            <p className="mt-2 text-5xl font-bold text-[#8A3330]">{peso(total)}</p>
                            {surcharge > 0 && (
                                <p className="mt-2 text-sm text-[#8A7B6D]">
                                    {t('includes cooking')} {peso(surcharge * Math.max(1, weigh.pieces))}
                                </p>
                            )}
                        </div>

                        {item.allow_tare && weigh.tare > 0 && (
                            <p className="mt-3 text-sm text-[#8A7B6D]">
                                {weigh.gross} g − {weigh.tare} g {t('tare')} = <strong>{weigh.net} g {t('net')}</strong>
                            </p>
                        )}

                        <div className="mt-3 space-y-2">
                            {weigh.errors.map((error) => (
                                <p key={error.key} className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm font-medium text-red-700">
                                    {t(error.message)}
                                </p>
                            ))}
                        </div>

                        <p className="mt-auto pt-4 text-xs text-gray-400">
                            {t('Minimum')} {item.min_weight_grams} g · {t('steps of')} {item.weight_step_grams} g
                        </p>
                    </div>
                </section>
            )}

            {/* ---------------- Step 4 — cooking ---------------- */}
            {step === 4 && item && (
                <section className="bg-white border border-[#E5DDD0] rounded-xl p-6 max-w-xl">
                    <h2 className="text-base font-semibold text-gray-900">{t('How should this be cooked?')}</h2>

                    {item.cooking_styles.length === 0 ? (
                        <p className="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                            {t('This item has no cooking styles set up yet. Add them on the menu item first.')}
                        </p>
                    ) : (
                        <div className="mt-4 grid grid-cols-2 gap-3">
                            {item.cooking_styles.map((option) => {
                                const selected = Number(styleId) === option.id;

                                return (
                                    <button
                                        key={option.id}
                                        type="button"
                                        onClick={() => setStyleId(String(option.id))}
                                        className={`rounded-xl border px-4 py-4 text-left transition active:scale-[.98] ${
                                            selected
                                                ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-sm'
                                                : 'border-[#D9CCBA] hover:border-[#8A3330]'
                                        }`}
                                    >
                                        <span className="block text-base font-semibold">{option.name}</span>
                                        <span className={`block text-xs mt-0.5 ${selected ? 'text-white/80' : 'text-gray-400'}`}>
                                            {option.surcharge > 0 ? `+${peso(option.surcharge)} ${t('per piece')}` : t('no extra charge')}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    <div className="mt-5">
                        <label htmlFor="cooking_note" className="block text-sm font-medium text-gray-700">
                            {t('Note for the kitchen (optional)')}
                        </label>
                        <textarea
                            id="cooking_note"
                            rows={2}
                            value={cookingNote}
                            onChange={(e) => setCookingNote(e.target.value)}
                            maxLength={1000}
                            placeholder={t('e.g. light salt, no chili, cut small')}
                            className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-3"
                        />
                    </div>

                    {surcharge > 0 && (
                        <div className="mt-5 rounded-lg border border-dashed border-[#D9CCBA] px-4 py-3 text-sm">
                            <div className="flex justify-between text-[#8A7B6D]">
                                <span>{t('Weight')}</span>
                                <span>{peso(total - surcharge * Math.max(1, weigh.pieces))}</span>
                            </div>
                            <div className="flex justify-between text-[#8A7B6D]">
                                <span>{style?.name} × {weigh.pieces}</span>
                                <span>{peso(surcharge * Math.max(1, weigh.pieces))}</span>
                            </div>
                            <div className="mt-1 flex justify-between border-t border-dashed border-[#D9CCBA] pt-1 font-bold text-gray-900">
                                <span>{t('Total')}</span>
                                <span>{peso(total)}</span>
                            </div>
                        </div>
                    )}
                </section>
            )}

            {/* ---------------- Step 5 — where and who ---------------- */}
            {step === 5 && (
                <StepDestination
                    areas={areas}
                    destination={destination}
                    onDestinationChange={setDestination}
                    target={target}
                    onTargetChange={setTarget}
                    takeout={takeout}
                    onTakeoutChange={setTakeout}
                />
            )}

            {/* ---------------- Step 6 — confirm ---------------- */}
            {step === 6 && item && (
                <section className="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden max-w-xl">
                    <div className="px-6 py-5 bg-[#FAF6EE] border-b border-[#E5DDD0]">
                        <p className="text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Adding to')}</p>
                        <p className="mt-1 text-lg font-bold text-gray-900 font-mono">{target.order?.order_number}</p>
                        <p className="text-sm text-[#8A7B6D]">
                            {destination === 'dine_in' ? target.tableName : t('Take-out')}
                            {destination === 'dine_in' && target.guestLabel ? ` · ${target.guestLabel}` : ''}
                            {destination === 'takeout' && takeout.name ? ` · ${takeout.name}` : ''}
                        </p>
                    </div>

                    <dl className="px-6 py-5 space-y-3 text-sm">
                        <Row label={t('Item')} value={item.name} />
                        <Row label={t('Net weight')} value={`${weigh.net} g (${(weigh.net / 1000).toFixed(3)} kg)`} />
                        {item.allow_tare && weigh.tare > 0 && (
                            <Row label={t('Gross / Tare')} value={`${weigh.gross} g / ${weigh.tare} g`} />
                        )}
                        <Row label={t('Price per kilo')} value={`${peso(pricePerKilo)} / ${t('kg')}`} />
                        {overriding && <Row label={t('Price reason')} value={overrideReason} />}
                        <Row label={t('Cooking')} value={`${style?.name}${weigh.pieces > 1 ? ` × ${weigh.pieces}` : ''}`} />
                        {cookingNote.trim() && <Row label={t('Note')} value={cookingNote.trim()} />}
                        {surcharge > 0 && (
                            <Row label={t('Cooking charge')} value={peso(surcharge * Math.max(1, weigh.pieces))} />
                        )}
                    </dl>

                    <div className="px-6 py-5 border-t border-[#E5DDD0] flex items-center justify-between">
                        <span className="text-sm font-semibold text-gray-700">{t('Line total')}</span>
                        <span className="text-3xl font-bold text-[#8A3330]">{peso(total)}</span>
                    </div>

                    {requiresCustomerConfirmation && (
                        <p className="px-6 pb-5 -mt-2 text-xs text-amber-700">
                            {t('This line will wait for the customer to confirm before the kitchen starts.')}
                        </p>
                    )}

                    <div className="px-6 py-4 bg-[#FAF6EE] border-t border-[#E5DDD0] flex items-center justify-end gap-3">
                        <button type="button" onClick={startOver} className="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {t('Cancel')}
                        </button>
                        <button
                            type="button"
                            onClick={submit}
                            disabled={submitting}
                            className="inline-flex items-center px-6 py-3 bg-[#8A3330] rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-[#742927] transition disabled:opacity-60"
                        >
                            {submitting ? t('Adding…') : t('Add to Order')}
                        </button>
                    </div>
                </section>
            )}

            {/* Nav */}
            <div className="sticky bottom-0 z-10 -mx-4 sm:-mx-6 mt-6 border-t border-[#E5DDD0] bg-[#F7F0E3]/95 backdrop-blur px-4 sm:px-6 py-4 flex items-center justify-between">
                <button
                    type="button"
                    onClick={() => setStep((s) => Math.max(1, s - 1))}
                    disabled={step === 1}
                    className="px-5 py-3 rounded-lg border border-[#D9CCBA] text-sm font-semibold text-gray-700 disabled:opacity-40 disabled:cursor-not-allowed active:bg-white"
                >
                    {t('Back')}
                </button>

                {step < 6 && (
                    <button
                        type="button"
                        onClick={() => setStep((s) => Math.min(6, s + 1))}
                        disabled={!canContinue()}
                        className="px-8 py-3 rounded-lg bg-[#8A3330] text-sm font-semibold text-white uppercase tracking-widest hover:bg-[#742927] disabled:opacity-40 disabled:cursor-not-allowed transition"
                    >
                        {t('Next')}
                    </button>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function FieldButton({ label, value, active, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left transition ${
                active ? 'border-[#8A3330] ring-2 ring-[#8A3330]/20' : 'border-[#E5DDD0]'
            }`}
        >
            <span className="text-sm font-medium text-gray-700">{label}</span>
            <span className="text-2xl font-bold tabular-nums text-gray-900">{value}</span>
        </button>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-gray-500 shrink-0">{label}</dt>
            <dd className="text-right font-medium text-gray-900">{value}</dd>
        </div>
    );
}
