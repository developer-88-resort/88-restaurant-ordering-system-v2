import Modal from '@/Components/Modal';
import TableReceiptPicker from '@/Components/TableReceiptPicker';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

function newRequestId() {
    return typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;
              return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
          });
}

// The exact wording the spec requires — shown verbatim on hover AND on a
// blocked click, so it reads the same whichever way staff discover it.
const COUNTER_ONLY_NOTICE = 'it cannot be ordered unless youve call the staff or thru personal ordering';

/**
 * The picture staff recognise a dish by. Menu Management stacks these on top
 * of a tall card, but this is a picker that gets scanned rather than read, so
 * the photo sits beside the name instead — four columns of them stay short
 * even when an item carries a whole row of variant chips. Items with no photo
 * fall back to the same tinted placeholder Menu Management uses, so a gap in
 * the menu data never leaves a hole in the grid.
 */
function MenuThumb({ item, dimmed = false }) {
    return (
        <div className="relative h-[72px] w-[72px] shrink-0 overflow-hidden rounded-xl bg-slate-50">
            {item.image_url ? (
                <img
                    src={item.image_url}
                    alt={item.name}
                    loading="lazy"
                    className={`h-full w-full object-cover ${dimmed ? 'grayscale' : ''}`}
                />
            ) : (
                <div className="grid h-full w-full place-items-center bg-slate-50 text-slate-400">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth={1.35}
                        stroke="currentColor"
                        className="h-6 w-6"
                        aria-hidden="true"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5V6a1.5 1.5 0 011.5-1.5z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 8.25h.008v.008H15V8.25z" />
                    </svg>
                </div>
            )}
        </div>
    );
}

/**
 * The whole advance-order flow in one screen: pick the table, see what's
 * already open on it, choose which slip this joins (or start a new one),
 * pick items, then review and confirm. Confirming creates the Quotation
 * record AND appends the batch in the same request — there is no separate
 * "convert later" step.
 *
 * The review (chosen items, schedule, customer, and the one confirm button)
 * lives in a form opened from a bar that stays at the bottom of the screen,
 * so staff never scroll past the whole menu to reach the button.
 */
export default function Create({ areas, categories }) {
    const t = useTranslation();

    const [requestId] = useState(newRequestId);

    const [areaId, setAreaId] = useState(null);
    const [space, setSpace] = useState(null);
    const [loadingReceipts, setLoadingReceipts] = useState(false);
    const [openOrders, setOpenOrders] = useState([]);
    // null = no table yet, 'new' = start a new slip on the table (the
    // default), a number = the exact open slip this batch is added to.
    const [target, setTarget] = useState(null);

    const [cart, setCart] = useState([]);
    const [search, setSearch] = useState('');
    const [counterOnlyNotice, setCounterOnlyNotice] = useState(false);

    const [customerName, setCustomerName] = useState('');
    const [customerContact, setCustomerContact] = useState('');
    const [scheduledFor, setScheduledFor] = useState('');
    const [notes, setNotes] = useState('');

    const [reviewOpen, setReviewOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const loadTable = async (table) => {
        setSpace(table);
        setOpenOrders([]);
        setTarget(null);
        setLoadingReceipts(true);
        setErrors({});

        try {
            const response = await fetch(route('quotations.table-receipts', table.id), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            const payload = await response.json();
            const orders = payload.open_orders ?? [];
            setOpenOrders(orders);

            // A new slip is the default — its own kitchen ticket. Staff can
            // still pick one of the table's open slips to add to instead.
            setTarget('new');
        } catch (error) {
            // Leave target as null rather than guessing "new receipt" —
            // if this table actually has an open bill and we can't see
            // it, defaulting to "new" would open a duplicate receipt for
            // the same table.
            setErrors({ target: t('Could not check this table for an open receipt. Please select the table again.') });
        } finally {
            setLoadingReceipts(false);
        }
    };

    const addItem = (menuItem, variant = null) => {
        if (menuItem.counter_only) {
            setCounterOnlyNotice(true);
            return;
        }

        const key = `${menuItem.id}:${variant?.id ?? ''}`;

        setCart((current) => {
            const existing = current.find((line) => line.key === key);
            if (existing) {
                return current.map((line) => (line.key === key ? { ...line, quantity: line.quantity + 1 } : line));
            }

            return [
                ...current,
                {
                    key,
                    menuItemId: menuItem.id,
                    variantId: variant?.id ?? null,
                    name: variant ? `${menuItem.name} — ${variant.name}` : menuItem.name,
                    unitPrice: variant ? variant.price : menuItem.price,
                    quantity: 1,
                    notes: '',
                },
            ];
        });
    };

    /**
     * Tapping anywhere on the card adds the item, the same as the pill —
     * staff were aiming at a small link and missing it. An item with
     * options adds the one the server would have chosen for a blank
     * variant anyway (its default, else the first), and its chips are
     * still there for picking a different one. The pill and the chips
     * stop the click from bubbling so a tap never adds twice.
     */
    const addFromCard = (menuItem) => {
        const variant = menuItem.variants.find((option) => option.is_default) ?? menuItem.variants[0] ?? null;

        addItem(menuItem, variant);
    };

    const changeQuantity = (key, delta) => {
        setCart((current) =>
            current
                .map((line) => (line.key === key ? { ...line, quantity: line.quantity + delta } : line))
                .filter((line) => line.quantity > 0),
        );
    };

    const removeLine = (key) => {
        setCart((current) => current.filter((line) => line.key !== key));
    };

    const changeLineNotes = (key, value) => {
        setCart((current) => current.map((line) => (line.key === key ? { ...line, notes: value } : line)));
    };

    const total = useMemo(() => cart.reduce((sum, line) => sum + Number(line.unitPrice) * line.quantity, 0), [cart]);
    const itemCount = useMemo(() => cart.reduce((sum, line) => sum + line.quantity, 0), [cart]);

    const filteredCategories = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return categories;
        return categories
            .map((category) => ({ ...category, items: category.items.filter((item) => item.name.toLowerCase().includes(q)) }))
            .filter((category) => category.items.length > 0);
    }, [categories, search]);

    const selectedOrder = typeof target === 'number' ? openOrders.find((order) => order.id === target) : null;
    const nextBatchNumber = selectedOrder ? (selectedOrder.batch_count ?? 0) + 1 : 1;

    const tableLabel = space ? `${areas.find((a) => a.id === areaId)?.name ?? ''} - ${space.name}` : '';
    const destinationLabel = target === 'new' ? t('New slip') : (selectedOrder?.slip_label ?? selectedOrder?.order_number ?? '');
    const destinationSentence =
        target === 'new'
            ? t('This advance order will be a new slip for this table.')
            : t('This advance order will be added to :slip (order :number) as Batch :batch.')
                  .replace(':slip', selectedOrder?.slip_label ?? '')
                  .replace(':number', selectedOrder?.order_number ?? '')
                  .replace(':batch', nextBatchNumber);

    const canSubmit = space && target !== null && cart.length > 0 && scheduledFor.length > 0 && !processing;

    // Everything the server rejected, except the schedule, which is shown
    // under its own field.
    const otherErrors = Object.entries(errors)
        .filter(([field]) => field !== 'scheduled_for')
        .map(([, message]) => message);

    const submit = (e) => {
        e?.preventDefault();
        if (!canSubmit) return;

        setProcessing(true);
        setErrors({});

        router.post(
            route('quotations.store'),
            {
                space_id: space.id,
                target,
                request_id: requestId,
                customer_name: customerName || null,
                customer_contact: customerContact || null,
                scheduled_for: scheduledFor,
                notes: notes || null,
                items: cart.map((line) => ({
                    menu_item_id: line.menuItemId,
                    menu_item_variant_id: line.variantId,
                    quantity: line.quantity,
                    notes: line.notes || null,
                })),
            },
            {
                onError: (serverErrors) => {
                    setErrors(serverErrors);
                    // The form is where the fix happens, so keep it open.
                    setReviewOpen(true);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const inputClass = 'mt-2 block min-h-11 w-full rounded-xl border-slate-200 bg-slate-50/50 py-2.5 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:ring-slate-200';

    return (
        <AuthenticatedLayout>
            <Head title={t('New Advance Order')} />

            <div className="mx-auto w-full max-w-[1500px]">
            <section className="mb-6">
                <div className="relative flex items-center gap-4 pb-6 pt-2">
                    <span className="relative grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-7 w-7"><path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                    </span>
                    <div className="relative">
                        <p className="text-xs font-medium uppercase tracking-wider text-slate-500">{t('Advance ordering')}</p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{t('New Advance Order')}</h1>
                        <p className="mt-1.5 text-sm text-slate-500">{t('Choose the destination, build the order, and confirm the schedule.')}</p>
                    </div>
                </div>
                <div className="grid grid-cols-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    {[t('Location'), t('Receipt'), t('Items'), t('Schedule')].map((label, index) => {
                        const reached = index === 0 || (index === 1 && space) || (index >= 2 && space && target !== null);
                        return <div key={label} className={`flex items-center justify-center gap-2 border-r border-slate-100 px-2 py-4 text-[10px] font-semibold last:border-0 sm:text-xs ${reached ? 'bg-slate-50 text-slate-900' : 'text-slate-400'}`}><span className={`grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] ${reached ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500'}`}>{index + 1}</span><span className="inline">{label}</span></div>;
                    })}
                </div>
            </section>

            {errors.target && (
                <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {errors.target}
                </div>
            )}

            <div className="space-y-5">
                {/* Hakbang 1 + 2 + 3 — table, open receipts, destination */}
                <TableReceiptPicker
                    areas={areas}
                    areaId={areaId}
                    onSelectArea={(id) => {
                        setAreaId(id);
                        setSpace(null);
                        setOpenOrders([]);
                        setTarget(null);
                    }}
                    space={space}
                    onSelectSpace={(table) => loadTable(table)}
                    loading={loadingReceipts}
                    openOrders={openOrders}
                    selectedOrderId={target === 'new' ? null : target}
                    onSelectOrder={(orderId) => setTarget(orderId === null ? 'new' : orderId)}
                    allowNewReceipt
                    newReceiptLabel={t('New slip for this table')}
                    emptyState={
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-gray-700">
                            {t('This table has no open receipt. This advance order will start a new one.')}
                        </div>
                    }
                />

                {space && target !== null && (
                    <p className="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm">
                        {destinationSentence}
                    </p>
                )}

                {/* Hakbang 4 — items; the review form opens from the bar below */}
                {space && target !== null && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="mb-4"><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-700">{t('Menu selection')}</p><h2 className="mt-1 text-base font-bold text-slate-900">{t('Add items')}</h2></div>

                        {/* Stays put while the menu scrolls. The list runs to a
                            few hundred items, and staff were having to scroll
                            all the way back up to look one up mid-order. The
                            negative margins let the bar span the card's full
                            width so items pass cleanly behind it; top-16 clears
                            the layout's own sticky top bar and z-20 keeps this
                            underneath it. */}
                        <div className="sticky top-16 z-20 -mx-5 mb-4 border-b border-slate-100 bg-white/95 px-5 py-3 backdrop-blur-md sm:-mx-6 sm:px-6">
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('Search items…')}
                                className="block w-full sm:max-w-sm border-gray-300 focus:border-slate-400 focus:ring-slate-200 rounded-lg text-sm py-2.5"
                            />
                        </div>

                        {counterOnlyNotice && (
                            <p className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2.5 text-xs text-amber-800">
                                {t(COUNTER_ONLY_NOTICE)}
                            </p>
                        )}

                        <div className="space-y-6">
                            {filteredCategories.map((category) => (
                                <div key={category.id}>
                                    <h3 className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{category.name}</h3>
                                    {/* One column fewer than before at each stop:
                                        the photo takes real width, and variant
                                        chips need somewhere to sit. */}
                                    <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                        {category.items.map((item) => {
                                            const inCart = cart
                                                .filter((line) => line.menuItemId === item.id)
                                                .reduce((sum, line) => sum + line.quantity, 0);

                                            return (
                                                <div
                                                    key={item.id}
                                                    title={item.counter_only ? t(COUNTER_ONLY_NOTICE) : undefined}
                                                    role="button"
                                                    tabIndex={0}
                                                    onClick={() => addFromCard(item)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter' || e.key === ' ') {
                                                            e.preventDefault();
                                                            addFromCard(item);
                                                        }
                                                    }}
                                                    className={`group relative flex gap-3 rounded-2xl border p-3 text-sm transition ${
                                                        item.counter_only
                                                            ? 'border-slate-200 bg-gray-50 opacity-60 cursor-not-allowed'
                                                            : inCart > 0
                                                              ? 'cursor-pointer border-[#8A3330] bg-white ring-1 ring-[#8A3330]/15'
                                                              : 'cursor-pointer border-slate-200 bg-slate-50 hover:border-slate-400 hover:bg-white'
                                                    }`}
                                                >
                                                    <MenuThumb item={item} dimmed={item.counter_only} />

                                                    <div className="min-w-0 flex-1">
                                                        <p className={`font-semibold text-gray-900 ${inCart > 0 ? 'pr-7' : ''}`}>{item.name}</p>
                                                        {item.counter_only ? (
                                                            <p className="mt-1 text-[11px] font-medium text-amber-700">{t('Counter only')}</p>
                                                        ) : item.variants.length > 0 ? (
                                                            <div className="mt-1.5 flex flex-wrap gap-1.5">
                                                                {item.variants.map((variant) => (
                                                                    <button
                                                                        key={variant.id}
                                                                        type="button"
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            addItem(item, variant);
                                                                        }}
                                                                        className="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:border-slate-400 hover:text-slate-700"
                                                                    >
                                                                        {variant.name} · {peso(variant.price)}
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    addItem(item);
                                                                }}
                                                                className="mt-1.5 inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:border-slate-400 hover:bg-slate-800 hover:text-white"
                                                            >
                                                                {t('Add')} · {peso(item.price)}
                                                            </button>
                                                        )}
                                                    </div>

                                                    {inCart > 0 && (
                                                        <span className="absolute right-2.5 top-2.5 grid h-6 min-w-6 place-items-center rounded-full bg-slate-800 px-1.5 text-xs font-bold text-white">
                                                            {inCart}
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Always in reach at the bottom of the screen, however long the menu is. */}
                {space && target !== null && (
                    <div className="sticky bottom-4 z-20">
                        <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-lg shadow-slate-200/60 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="grid h-11 min-w-11 place-items-center rounded-xl border border-slate-200 bg-slate-50 px-2 text-lg font-bold">
                                    {itemCount}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold">{t('Advance order')} · {tableLabel}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {destinationLabel} · {peso(total)}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setReviewOpen(true)}
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-slate-400"
                            >
                                {t('View advance order')}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                )}
            </div>
            </div>

            <Modal show={reviewOpen} onClose={() => !processing && setReviewOpen(false)} maxWidth="2xl">
                <form onSubmit={submit} className="flex max-h-[calc(100dvh-3rem)] flex-col">
                    <div className="relative overflow-hidden border-b border-slate-100 bg-white px-5 py-5 text-slate-900 sm:px-6">
                        <div className="relative flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">{t('Advance order')}</p>
                                <h2 className="mt-1 truncate text-lg font-bold">{tableLabel}</h2>
                                <p className="mt-0.5 text-xs leading-5 text-slate-500">{destinationSentence}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => !processing && setReviewOpen(false)}
                                aria-label={t('Close')}
                                className="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div className="flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                        {otherErrors.length > 0 && (
                            <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {otherErrors.map((message) => (
                                    <p key={message}>{message}</p>
                                ))}
                            </div>
                        )}

                        <section>
                            <h3 className="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                {t('Order lines')}
                            </h3>
                            {cart.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-gray-500">
                                    {t('No items yet — add them from the menu.')}
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {cart.map((line) => (
                                        <div key={line.key} className="rounded-xl border border-slate-200 bg-white p-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-semibold text-gray-900">{line.name}</p>
                                                    <p className="text-xs text-gray-500">{peso(line.unitPrice)}</p>
                                                </div>
                                                <span className="shrink-0 text-sm font-bold text-slate-700">
                                                    {peso(Number(line.unitPrice) * line.quantity)}
                                                </span>
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <div className="flex items-center gap-1.5">
                                                    <button type="button" onClick={() => changeQuantity(line.key, -1)} aria-label="−" className="h-9 w-9 rounded-lg border border-slate-200 text-base text-gray-600 hover:border-slate-400">−</button>
                                                    <span className="w-8 text-center text-sm font-bold">{line.quantity}</span>
                                                    <button type="button" onClick={() => changeQuantity(line.key, 1)} aria-label="+" className="h-9 w-9 rounded-lg border border-slate-200 text-base text-gray-600 hover:border-slate-400">+</button>
                                                </div>
                                                <input
                                                    type="text"
                                                    value={line.notes}
                                                    onChange={(e) => changeLineNotes(line.key, e.target.value)}
                                                    placeholder={t('Note (optional)')}
                                                    className="min-w-0 flex-1 rounded-lg border-gray-200 py-2 text-xs focus:border-slate-400 focus:ring-slate-200"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => removeLine(line.key)}
                                                    className="text-xs font-semibold text-red-600 hover:underline"
                                                >
                                                    {t('Remove')}
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section>
                            <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                {t('Schedule & customer')}
                            </h3>
                            <p className="mb-3 mt-1 text-xs text-gray-500">
                                {t('Customer name and contact number are optional — leave them blank if the guest did not give them.')}
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="scheduled_for" className="block text-sm font-medium text-gray-700">{t('Scheduled Date and Time')}</label>
                                    <input
                                        id="scheduled_for"
                                        type="datetime-local"
                                        value={scheduledFor}
                                        onChange={(e) => setScheduledFor(e.target.value)}
                                        className={inputClass}
                                    />
                                    {errors.scheduled_for && <p className="mt-1 text-xs text-red-600">{errors.scheduled_for}</p>}
                                </div>
                                <div>
                                    <label htmlFor="customer_name" className="block text-sm font-medium text-gray-700">
                                        {t('Customer name')} <span className="font-normal text-gray-400">({t('optional')})</span>
                                    </label>
                                    <input
                                        id="customer_name"
                                        type="text"
                                        value={customerName}
                                        onChange={(e) => setCustomerName(e.target.value)}
                                        className={inputClass}
                                    />
                                </div>
                                <div>
                                    <label htmlFor="customer_contact" className="block text-sm font-medium text-gray-700">
                                        {t('Contact number')} <span className="font-normal text-gray-400">({t('optional')})</span>
                                    </label>
                                    <input
                                        id="customer_contact"
                                        type="tel"
                                        value={customerContact}
                                        onChange={(e) => setCustomerContact(e.target.value)}
                                        className={inputClass}
                                    />
                                </div>
                                <div>
                                    <label htmlFor="notes" className="block text-sm font-medium text-gray-700">{t('Note for the kitchen (optional)')}</label>
                                    <input
                                        id="notes"
                                        type="text"
                                        value={notes}
                                        onChange={(e) => setNotes(e.target.value)}
                                        className={inputClass}
                                    />
                                </div>
                            </div>
                        </section>
                    </div>

                    {/* The confirm button never scrolls out of view. */}
                    <div className="border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <dl className="mb-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <dt className="text-gray-500">{t('Receipt')}</dt>
                            <dd className="text-right font-medium text-gray-900">{destinationLabel}</dd>
                            <dt className="text-gray-500">{t('Scheduled for')}</dt>
                            <dd className="text-right font-medium text-gray-900">{scheduledFor ? scheduledFor.replace('T', ' ') : '—'}</dd>
                            <dt className="text-base font-bold text-gray-900">{t('Total')}</dt>
                            <dd className="text-right text-base font-bold text-slate-700">{peso(total)}</dd>
                        </dl>

                        {!processing && (cart.length === 0 || !scheduledFor) && (
                            <p className="mb-3 text-xs font-medium text-amber-700">
                                {cart.length === 0 ? t('Add at least one item.') : t('Set the scheduled date and time.')}
                            </p>
                        )}

                        <div className="flex flex-col-reverse gap-2.5 sm:flex-row">
                            <button
                                type="button"
                                onClick={() => setReviewOpen(false)}
                                disabled={processing}
                                className="min-h-12 rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                {t('Keep adding items')}
                            </button>
                            <button
                                type="submit"
                                disabled={!canSubmit}
                                className="min-h-12 flex-1 rounded-xl bg-slate-800 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {processing
                                    ? t('Saving…')
                                    : target === 'new'
                                      ? t('CREATE RECEIPT FOR THIS ADVANCE ORDER')
                                      : t('ADD TO THIS RECEIPT')}
                            </button>
                        </div>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
