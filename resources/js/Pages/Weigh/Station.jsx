import { ToastList } from '@/Components/Toast';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Keypad from './Keypad';
import StepDestination from './StepDestination';
import { peso, previewTotal, useWeighInput } from './useWeighDraft';

const STEPS = ['Item', 'Weight & Amount', 'Cooking', 'Where', 'Confirm'];

const QUICK_NOTES = ['Konting asin lang', 'Walang sili', 'Hiwain ng maliit'];

// v1 stored the entire menu item object, including its price_per_kilo and
// min_weight_grams — if the Daily Market Price changed while a draft sat in
// a browser, restoring it re-rendered the whole page from stale pricing
// data while the server (correctly) computed from the current rate. v2
// stores only an id plus a snapshot of the rate/minimum AT SAVE TIME, used
// purely to detect drift — the item itself is always re-resolved fresh from
// the `categories` prop on restore, never read from localStorage.
const DRAFT_KEY_V1 = 'weigh-station-draft-v1';
const DRAFT_KEY = 'weigh-station-draft-v2';

const clearDraft = () => {
    localStorage.removeItem(DRAFT_KEY);
    localStorage.removeItem(DRAFT_KEY_V1);
};

function idempotencyKey() {
    return typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;
              return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
          });
}

export default function Station({ categories, areas, requiresCustomerConfirmation, weighed }) {
    const t = useTranslation();
    const { csrf_token: csrfToken } = usePage().props;

    // "Weigh another for this table" from the order page's success banner
    // lands here with ?table=<id> so step 4 can skip re-picking the table.
    const initialTableId = useMemo(() => new URLSearchParams(window.location.search).get('table'), []);

    const [step, setStep] = useState(1);
    const [item, setItem] = useState(null);
    const [search, setSearch] = useState('');

    // Step 3 — cooking
    const [styleId, setStyleId] = useState('');
    const [cookingNote, setCookingNote] = useState('');

    // Step 4 — destination
    const [destination, setDestination] = useState('dine_in');
    const [target, setTarget] = useState({ order: null, guestId: null, tableName: null });
    const [takeout, setTakeout] = useState({ name: '', contact: '' });

    const [variance, setVariance] = useState(null);
    const [varianceReason, setVarianceReason] = useState('');
    const [amountSource, setAmountSource] = useState('typed');
    const [requestKey, setRequestKey] = useState(idempotencyKey());

    const [submitting, setSubmitting] = useState(false);
    const [serverErrors, setServerErrors] = useState([]);
    const [blockedOrder, setBlockedOrder] = useState(false);
    const [draftRestored, setDraftRestored] = useState(false);
    const [rateChangeBanner, setRateChangeBanner] = useState(null);
    const [toasts, setToasts] = useState([]);

    const pushToast = (message) =>
        setToasts((current) => [...current, { id: Date.now() + Math.random(), type: 'error', message }]);
    const dismissToast = (id) => setToasts((current) => current.filter((toast) => toast.id !== id));

    const weigh = useWeighInput({
        minWeightGrams: item?.min_weight_grams ?? 250,
        blocksBelowMinimum: weighed?.blocks_below_minimum ?? true,
    });

    // Every menu item currently offered at the station, keyed by id — the
    // ONLY source of truth a restored draft is allowed to read pricing/
    // validation data from. The draft itself may only carry an id plus a
    // snapshot for drift detection; the item is always re-resolved fresh
    // here, from the categories this page just loaded from the server.
    const itemsById = useMemo(() => {
        const map = new Map();
        categories.forEach((category) => category.items.forEach((menuItem) => map.set(menuItem.id, menuItem)));
        return map;
    }, [categories]);

    // Restore an in-progress weigh-in that got interrupted (crash, refresh,
    // closed tab). The physical weighing (steps 1-3) is the expensive part
    // to redo, so losing the page must not mean walking back to the scale.
    // Step 4+ is deliberately never restored — which table/order to add to
    // depends on live server state that must always be re-checked fresh.
    useEffect(() => {
        let draft;
        try {
            const raw = localStorage.getItem(DRAFT_KEY);
            draft = raw ? JSON.parse(raw) : null;
        } catch {
            draft = null;
        }
        // Old-shape drafts are dropped outright rather than half-parsed —
        // v1 stored a whole item object under a different key.
        localStorage.removeItem(DRAFT_KEY_V1);

        if (!draft || draft.v !== 2 || !draft.menuItemId) return;

        const resolved = itemsById.get(draft.menuItemId);

        if (!resolved) {
            clearDraft();
            pushToast(t('This item is no longer available. Starting a new weigh-in.'));
            return;
        }

        if (resolved.needs_setup) {
            clearDraft();
            pushToast(t('This item still needs setup before it can be weighed. Starting a new weigh-in.'));
            return;
        }

        const hasStyleSelected = draft.styleId !== undefined && draft.styleId !== null && draft.styleId !== '';
        const styleStillValid = !hasStyleSelected || resolved.cooking_styles.some((s) => s.id === Number(draft.styleId));
        const rateChanged = Number(resolved.price_per_kilo) !== Number(draft.rateSnapshot);
        const minChanged = Number(resolved.min_weight_grams) !== Number(draft.minWeightSnapshot);

        setItem(resolved);
        setCookingNote(draft.cookingNote ?? '');
        weigh.setGrams(draft.gramsRaw ?? '');
        weigh.setAmount(draft.centavosRaw ?? '');
        weigh.setPieces(draft.pieces ?? 1);
        setDraftRestored(true);

        if (rateChanged || minChanged) {
            // The staff physically weighed something — keep the weight and
            // amount, but send them back to double-check it against the
            // rate that's actually going to be charged now.
            setStyleId(styleStillValid ? (draft.styleId ?? '') : '');
            setStep(2);
            setVariance(null);
            setAmountSource('typed');
            setRateChangeBanner({
                beforeRate: Number(draft.rateSnapshot),
                afterRate: Number(resolved.price_per_kilo),
                beforeMin: Number(draft.minWeightSnapshot),
                afterMin: Number(resolved.min_weight_grams),
            });
        } else if (hasStyleSelected && !styleStillValid) {
            setStyleId('');
            setStep(3);
        } else {
            setStyleId(draft.styleId ?? '');
            setStep(Math.min(draft.step ?? 1, 3));
        }
        // Mount-only: this restores whatever was saved before this page load.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Keep the draft current while there's a weigh-in worth protecting. Only
    // an id and a rate/minimum SNAPSHOT are persisted — never the item
    // object itself — so a restore can never render from stale pricing.
    useEffect(() => {
        if (!item || submitting) return;

        localStorage.setItem(DRAFT_KEY, JSON.stringify({
            v: 2,
            menuItemId: item.id,
            styleId,
            cookingNote,
            step: Math.min(step, 3),
            gramsRaw: weigh.gramsRaw,
            centavosRaw: weigh.centavosRaw,
            pieces: weigh.pieces,
            rateSnapshot: item.price_per_kilo,
            minWeightSnapshot: item.min_weight_grams,
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item, styleId, cookingNote, step, weigh.gramsRaw, weigh.centavosRaw, weigh.pieces, submitting]);

    const referenceRate = item?.price_per_kilo ?? 0;
    const style = useMemo(
        () => item?.cooking_styles.find((s) => s.id === Number(styleId)) ?? null,
        [item, styleId],
    );
    const surcharge = (style?.surcharge ?? 0) * Math.max(1, weigh.pieces);
    const lineTotal = weigh.amountCharged + surcharge;

    const belowMinimumPreview = useMemo(() => {
        if (!item || weigh.netGrams === 0 || weigh.netGrams >= item.min_weight_grams) return null;
        if (weighed?.blocks_below_minimum) return null;

        return previewTotal({ net: item.min_weight_grams, pricePerKilo: referenceRate }).toFixed(2);
    }, [item, weigh.netGrams, weighed, referenceRate]);

    // The live "Expected ₱177.00 ✓" check — asks the server rather than
    // re-implementing the variance rules here, so this screen can never
    // promise a line the server then refuses.
    const checkTimer = useRef(null);
    useEffect(() => {
        if (!item || weigh.netGrams <= 0 || weigh.amountCharged <= 0) {
            setVariance(null);
            return;
        }

        clearTimeout(checkTimer.current);
        checkTimer.current = setTimeout(async () => {
            try {
                const response = await fetch(route('weigh.check-variance'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        menu_item_id: item.id,
                        net_grams: weigh.netGrams,
                        amount_charged: weigh.amountCharged,
                    }),
                });
                setVariance(response.ok ? await response.json() : null);
            } catch {
                setVariance(null);
            }
        }, 250);

        return () => clearTimeout(checkTimer.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item, weigh.netGrams, weigh.amountCharged]);

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
        setStyleId('');
        setCookingNote('');
        setDestination('dine_in');
        setTarget({ order: null, guestId: null, tableName: null });
        setTakeout({ name: '', contact: '' });
        setServerErrors([]);
        setBlockedOrder(false);
        setVariance(null);
        setVarianceReason('');
        setAmountSource('typed');
        setRequestKey(idempotencyKey());
        setDraftRestored(false);
        setRateChangeBanner(null);
        weigh.reset();
        clearDraft();
    };

    const chooseItem = (chosen) => {
        if (chosen.needs_setup) return;

        setItem(chosen);
        setStyleId(chosen.cooking_styles.length === 1 ? String(chosen.cooking_styles[0].id) : '');
        setVariance(null);
        setVarianceReason('');
        setAmountSource('typed');
        setRateChangeBanner(null);
        weigh.reset();
        setStep(2);
    };

    const useExpected = () => {
        if (!variance) return;
        weigh.setTarget('amount');
        // Centavo buffer: ₱177.00 -> "17700". Set directly in one call —
        // looping `pressDigit` per character reads the same pre-update
        // buffer on every iteration (they all fire in the same synchronous
        // batch), so it doesn't accumulate digit-by-digit like a real key
        // press does.
        weigh.setAmount(Math.round(Number(variance.computed_amount) * 100).toString());
        setAmountSource('computed');
    };

    const varianceReasonValid = varianceReason.trim().length > 0;
    const varianceBlocksSubmit = variance
        ? (!variance.passes && !varianceReasonValid) || (variance.requires_override && !variance.can_override)
        : false;

    const canContinue = () => {
        if (step === 1) return item !== null;
        if (step === 2) return weigh.isValid && !varianceBlocksSubmit;
        if (step === 3) return styleId !== '';
        if (step === 4) return target.order !== null && (destination === 'takeout' || target.guestId !== null);
        return true;
    };

    const submit = async () => {
        if (submitting || !target.order || varianceBlocksSubmit) return;
        setSubmitting(true);
        setServerErrors([]);
        setBlockedOrder(false);

        try {
            const response = await fetch(route('orders.items.store', target.order.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Idempotency-Key': requestKey,
                },
                body: JSON.stringify({
                    menu_item_id: item.id,
                    line_type: 'weighed',
                    net_grams: weigh.netGrams,
                    amount_charged: weigh.amountCharged,
                    amount_source: amountSource,
                    pieces: weigh.pieces,
                    cooking_style_id: style.id,
                    cooking_note: cookingNote.trim() || null,
                    variance_reason: varianceReason.trim() || null,
                    ordered_by_guest_id:
                        destination === 'dine_in' && target.guestId !== 'walk-in' ? target.guestId : null,
                    confirmation_status: requiresCustomerConfirmation ? 'pending_customer' : 'confirmed',
                }),
            });

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const errors = body.errors ?? (body.message ? { order: [body.message] } : {});
                setServerErrors(Object.values(errors).flat());

                // The order became unusable mid-wizard (session closed, paid,
                // etc.) — send staff back to step 4 to pick another order
                // rather than discarding everything they already keyed in.
                if (errors.order) {
                    setBlockedOrder(true);
                    setStep(4);
                }

                return;
            }

            const body = await response.json();
            clearDraft();

            // A REAL browser navigation, not an Inertia visit: /orders/{id}
            // is still a Blade-rendered page, a different render stack from
            // this one, so this must not go through Inertia's XHR visit —
            // that would either error out or, worse, half-render a page
            // built for a different script bundle inside this one.
            const params = new URLSearchParams({ weighed: body.item.id });
            window.location.href = `${route('orders.show', body.order.id)}?${params.toString()}`;
        } catch {
            // Network failure: nothing was cleared, and the idempotency key
            // is unchanged, so pressing "Add to Order" again is a safe retry.
            setServerErrors([t('Could not reach the server. Check the connection and press Add to Order to retry.')]);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('Weigh & Order')} />

            <ToastList toasts={toasts} onDismiss={dismissToast} />

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

            {draftRestored && (
                <div className="mb-4 flex items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <span>{t('Restored your previous weigh-in — pick up where you left off.')}</span>
                    <button
                        type="button"
                        onClick={startOver}
                        className="shrink-0 font-medium underline hover:no-underline"
                    >
                        {t('Discard')}
                    </button>
                </div>
            )}

            {serverErrors.length > 0 && (
                <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 space-y-1">
                    {serverErrors.map((message, i) => (
                        <p key={i}>{message}</p>
                    ))}
                </div>
            )}

            {rateChangeBanner && step === 2 && (
                <div className="mb-4 flex items-start justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <span>
                        {t('The rate for this item changed since this draft was saved')} ({peso(rateChangeBanner.beforeRate)} → {peso(rateChangeBanner.afterRate)}
                        {rateChangeBanner.beforeMin !== rateChangeBanner.afterMin
                            ? `, ${t('minimum')} ${rateChangeBanner.beforeMin} g → ${rateChangeBanner.afterMin} g`
                            : ''}
                        ). {t('Please review the amount before continuing.')}
                    </span>
                    <button
                        type="button"
                        onClick={() => setRateChangeBanner(null)}
                        className="shrink-0 font-medium underline hover:no-underline"
                    >
                        {t('Dismiss')}
                    </button>
                </div>
            )}

            {blockedOrder && step === 4 && (
                <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    {t('That order can no longer take this line. Pick another order below, or start a new one — nothing you entered was lost.')}
                </div>
            )}

            {/* ---------------- Step 1 — item ---------------- */}
            {step === 1 && (
                <section>
                    <input
                        type="search"
                        autoFocus
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
                                            disabled={menuItem.needs_setup}
                                            className={`group relative bg-white border rounded-xl overflow-hidden text-left shadow-sm transition min-h-[64px] ${
                                                menuItem.needs_setup
                                                    ? 'border-[#E5DDD0] opacity-60 cursor-not-allowed'
                                                    : 'border-[#E5DDD0] hover:border-[#8A3330] hover:shadow-md active:scale-[.98]'
                                            }`}
                                        >
                                            {menuItem.needs_setup && (
                                                <span className="absolute right-2 top-2 z-10 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700">
                                                    {t('Needs setup')}
                                                </span>
                                            )}
                                            <div className="aspect-[4/3] bg-gradient-to-br from-[#FAF6EE] to-[#F1E9DA]">
                                                {menuItem.image_url && (
                                                    <img src={menuItem.image_url} alt={menuItem.name} className="h-full w-full object-cover" />
                                                )}
                                            </div>
                                            <div className="p-3">
                                                <p className="text-[15px] font-bold text-gray-900 leading-snug">{menuItem.name}</p>
                                                {menuItem.needs_setup ? (
                                                    <a
                                                        href={menuItem.edit_url}
                                                        onClick={(e) => e.stopPropagation()}
                                                        className="mt-1 inline-block text-xs font-semibold text-[#8A3330] hover:underline"
                                                    >
                                                        {t('Finish setup')} →
                                                    </a>
                                                ) : (
                                                    <>
                                                        <p className="mt-1 text-base font-bold text-[#8A3330]">
                                                            {peso(menuItem.price_per_kilo)}
                                                            <span className="text-xs font-semibold text-[#8A7B6D]"> / {t('kg')}</span>
                                                        </p>
                                                        <p className="text-[11px] text-gray-400">{t('min')} {menuItem.min_weight_grams} g</p>
                                                    </>
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))
                    )}
                </section>
            )}

            {/* ---------------- Step 2 — weight & amount ---------------- */}
            {step === 2 && item && (
                <section className="grid gap-5 lg:grid-cols-2 max-w-4xl">
                    <div className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                        <div className="flex items-start gap-4">
                            {item.image_url && (
                                <img src={item.image_url} alt={item.name} className="h-14 w-14 rounded-lg object-cover shrink-0" />
                            )}
                            <div>
                                <h2 className="text-base font-semibold text-gray-900">{item.name}</h2>
                                <p className="text-sm text-[#8A7B6D]">
                                    {t("Today's rate")}: <span className="font-semibold text-[#8A3330]">{peso(referenceRate)}/{t('kg')}</span>
                                </p>
                            </div>
                        </div>

                        <div className="mt-5 grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                onClick={() => weigh.setTarget('grams')}
                                className={`flex flex-col items-start rounded-lg border px-4 py-3 text-left transition min-h-[64px] ${
                                    weigh.target === 'grams' ? 'border-[#8A3330] ring-2 ring-[#8A3330]/20' : 'border-[#E5DDD0]'
                                }`}
                            >
                                <span className="text-xs font-semibold text-gray-500">{t('Weight from the scale')}</span>
                                <span className="text-2xl font-bold tabular-nums text-gray-900">{weigh.gramsRaw || '0'} <span className="text-sm font-medium text-gray-400">g</span></span>
                            </button>
                            <button
                                type="button"
                                onClick={() => weigh.setTarget('amount')}
                                className={`flex flex-col items-start rounded-lg border px-4 py-3 text-left transition min-h-[64px] ${
                                    weigh.target === 'amount' ? 'border-[#8A3330] ring-2 ring-[#8A3330]/20' : 'border-[#E5DDD0]'
                                }`}
                            >
                                <span className="text-xs font-semibold text-gray-500">{t('Amount on the scale')}</span>
                                <span className="text-2xl font-bold tabular-nums text-gray-900">₱{weigh.amountFormatted}</span>
                            </button>
                        </div>

                        <div className="mt-3 flex items-center justify-between rounded-lg border border-[#E5DDD0] px-4 py-3">
                            <span className="text-sm font-medium text-gray-700">{t('Pieces')}</span>
                            <div className="flex items-center gap-3">
                                <button type="button" onClick={() => weigh.setPieces((p) => Math.max(1, p - 1))}
                                        className="h-11 w-11 rounded-full border border-[#D9CCBA] text-xl text-gray-600 active:bg-gray-100">−</button>
                                <span className="w-8 text-center text-xl font-bold">{weigh.pieces}</span>
                                <button type="button" onClick={() => weigh.setPieces((p) => Math.min(999, p + 1))}
                                        className="h-11 w-11 rounded-full border border-[#D9CCBA] text-xl text-gray-600 active:bg-gray-100">+</button>
                            </div>
                        </div>

                        <Keypad
                            onDigit={(d) => { weigh.pressDigit(d); if (weigh.target === 'amount') setAmountSource('typed'); }}
                            onBackspace={() => { weigh.pressBackspace(); if (weigh.target === 'amount') setAmountSource('typed'); }}
                            onClear={() => { weigh.clear(); if (weigh.target === 'amount') setAmountSource('typed'); }}
                        />
                    </div>

                    <div className="bg-white border border-[#E5DDD0] rounded-xl p-6 flex flex-col">
                        <p className="text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('System check')}</p>

                        {!variance ? (
                            <div className="mt-3 rounded-lg bg-[#FAF6EE] border border-[#E5DDD0] px-4 py-6 text-center text-sm text-gray-400">
                                {t('Enter the weight and amount from the scale.')}
                            </div>
                        ) : variance.requires_override && !variance.can_override ? (
                            <div className="mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-4 text-sm text-red-800">
                                <p className="font-bold">✗ {t('Supervisor approval is required for a difference this large.')}</p>
                                <p className="mt-1 text-xs text-red-600">{t('Call a supervisor to record this line.')}</p>
                            </div>
                        ) : variance.passes ? (
                            <div className="mt-3 rounded-lg bg-green-50 border border-green-100 px-4 py-3 text-sm text-green-700">
                                <p className="font-semibold">✓ {t('Expected')} {peso(variance.computed_amount)} {t('at')} {peso(referenceRate)}/{t('kg')} — {t('match')}</p>
                            </div>
                        ) : (
                            <div className="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-4 py-4 text-sm text-amber-800">
                                <p className="font-semibold">
                                    ! {t('Expected')} {peso(variance.computed_amount)} — {peso(variance.absolute_variance ?? Math.abs(variance.variance_amount))} {t('difference')} ({Math.abs(variance.variance_percent)}%)
                                </p>
                                {variance.requires_override && (
                                    <p className="mt-1 text-xs font-semibold text-red-700">{t('This also needs supervisor approval, on top of a reason.')}</p>
                                )}
                                <label className="mt-3 block text-xs font-semibold uppercase tracking-wide text-amber-700">{t('Reason (required)')}</label>
                                <input
                                    type="text"
                                    value={varianceReason}
                                    onChange={(e) => setVarianceReason(e.target.value)}
                                    maxLength={500}
                                    placeholder={t('e.g. Customer asked for a bigger cut')}
                                    className="mt-1 w-full text-sm rounded-lg border-amber-300 focus:border-amber-500 focus:ring-amber-500"
                                />
                            </div>
                        )}

                        {belowMinimumPreview && (
                            <p className="mt-3 text-xs font-medium text-amber-700">
                                {t('Below minimum — will be billed as :min g (₱:amount).', { min: item.min_weight_grams, amount: belowMinimumPreview })}
                            </p>
                        )}

                        {weigh.errors.map((error) => (
                            <p key={error.key} className="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm font-medium text-red-700">
                                {t(error.message)}
                            </p>
                        ))}

                        <div className="mt-4 rounded-xl bg-[#FAF6EE] border border-[#E5DDD0] px-5 py-6 text-center">
                            <p className="text-sm text-[#8A7B6D]">{(weigh.netGrams / 1000).toFixed(3)} {t('kg')}</p>
                            <p className="mt-1 text-4xl font-bold text-[#8A3330]">{peso(lineTotal)}</p>
                            {surcharge > 0 && (
                                <p className="mt-1 text-xs text-[#8A7B6D]">{t('includes cooking')} {peso(surcharge)}</p>
                            )}
                        </div>

                        {variance && (
                            <button
                                type="button"
                                onClick={useExpected}
                                className="mt-3 text-xs font-medium text-[#8A3330] hover:underline self-start"
                            >
                                {t('Use expected')} ({peso(variance.computed_amount)})
                            </button>
                        )}

                        <p className="mt-auto pt-4 text-xs text-gray-400">
                            {t('Minimum')} {item.min_weight_grams} g · {t('the scale reading is accepted as-is')}
                        </p>
                    </div>
                </section>
            )}

            {/* ---------------- Step 3 — cooking ---------------- */}
            {step === 3 && item && (
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
                                        className={`rounded-xl border px-4 py-4 text-left transition active:scale-[.98] min-h-[64px] ${
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
                        <div className="mt-2 flex flex-wrap gap-2">
                            {QUICK_NOTES.map((note) => (
                                <button
                                    key={note}
                                    type="button"
                                    onClick={() => setCookingNote((current) => (current ? `${current}, ${note}` : note))}
                                    className="rounded-full border border-[#D9CCBA] px-3 py-1.5 text-xs font-medium text-gray-600 hover:border-[#8A3330] hover:text-[#8A3330]"
                                >
                                    + {t(note)}
                                </button>
                            ))}
                        </div>
                        <textarea
                            id="cooking_note"
                            rows={2}
                            value={cookingNote}
                            onChange={(e) => setCookingNote(e.target.value)}
                            maxLength={1000}
                            placeholder={t('e.g. light salt, no chili, cut small')}
                            className="mt-2 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-3"
                        />
                    </div>

                    {surcharge > 0 && (
                        <div className="mt-5 rounded-lg border border-dashed border-[#D9CCBA] px-4 py-3 text-sm">
                            <div className="flex justify-between text-[#8A7B6D]">
                                <span>{item.name} {(weigh.netGrams / 1000).toFixed(3)} {t('kg')}</span>
                                <span>{peso(weigh.amountCharged)}</span>
                            </div>
                            <div className="flex justify-between text-[#8A7B6D]">
                                <span>{style?.name} × {weigh.pieces}</span>
                                <span>{peso(surcharge)}</span>
                            </div>
                            <div className="mt-1 flex justify-between border-t border-dashed border-[#D9CCBA] pt-1 font-bold text-gray-900">
                                <span>{t('Total')}</span>
                                <span>{peso(lineTotal)}</span>
                            </div>
                        </div>
                    )}
                </section>
            )}

            {/* ---------------- Step 4 — where and who ---------------- */}
            {step === 4 && (
                <StepDestination
                    areas={areas}
                    destination={destination}
                    onDestinationChange={setDestination}
                    target={target}
                    onTargetChange={setTarget}
                    takeout={takeout}
                    onTakeoutChange={setTakeout}
                    initialTableId={initialTableId}
                />
            )}

            {/* ---------------- Step 5 — confirm ---------------- */}
            {step === 5 && item && (
                <section className="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden max-w-xl">
                    {variance && !variance.passes && (
                        <div className="px-6 py-3 bg-amber-50 border-b border-amber-200 text-sm text-amber-800">
                            {t('Different from the expected')} {peso(variance.computed_amount)}. {t('Reason')}: {varianceReason}
                        </div>
                    )}

                    <div className="px-6 py-5 bg-[#FAF6EE] border-b border-[#E5DDD0]">
                        <div className="flex items-center justify-between">
                            <p className="text-lg font-bold text-gray-900">{item.name}</p>
                            <span className="inline-flex px-2 py-0.5 rounded bg-teal-100 text-teal-800 text-[10px] font-bold uppercase">{t('Weighed')}</span>
                        </div>
                        <p className="text-sm text-[#8A7B6D]">
                            {(weigh.netGrams / 1000).toFixed(3)} {t('kg')} × {peso(referenceRate)}/{t('kg')} ...... {peso(weigh.amountCharged)}
                        </p>
                        {surcharge > 0 && (
                            <p className="text-sm text-[#8A7B6D]">{style?.name} .......................... {peso(surcharge)}</p>
                        )}
                        {cookingNote.trim() && <p className="mt-1 text-xs text-gray-500">{t('Note')}: {cookingNote.trim()}</p>}
                    </div>

                    <dl className="px-6 py-5 space-y-2 text-sm border-b border-[#E5DDD0]">
                        <Row label={t('Adding to')} value={target.order?.order_number} />
                        <Row label={t('Table')} value={destination === 'dine_in' ? target.tableName : t('Take-out')} />
                        <Row
                            label={t('For')}
                            value={
                                destination === 'takeout'
                                    ? takeout.name || t('Walk-in')
                                    : target.guestId === 'walk-in'
                                      ? t('Walk-in')
                                      : target.guestLabel
                            }
                        />
                        <Row label={t('Weighed by')} value={`${usePage().props.auth.user.name} · ${t('In person')}`} />
                    </dl>

                    <div className="px-6 py-5 flex items-center justify-between">
                        <span className="text-sm font-semibold text-gray-700">{t('Line total')}</span>
                        <span className="text-3xl font-bold text-[#8A3330]">{peso(lineTotal)}</span>
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

                {step < 5 && (
                    <button
                        type="button"
                        onClick={() => setStep((s) => Math.min(5, s + 1))}
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

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-gray-500 shrink-0">{label}</dt>
            <dd className="text-right font-medium text-gray-900">{value}</dd>
        </div>
    );
}
