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
 * The whole advance-order flow in one screen: pick the table, see what's
 * already open on it, choose which receipt this joins (or start a new
 * one), pick items + a schedule, one button. Pressing it creates the
 * Quotation record AND appends the batch in the same request — there is no
 * separate "convert later" step.
 */
export default function Create({ areas, categories }) {
    const t = useTranslation();

    const [requestId] = useState(newRequestId);

    const [areaId, setAreaId] = useState(null);
    const [space, setSpace] = useState(null);
    const [loadingReceipts, setLoadingReceipts] = useState(false);
    const [openOrders, setOpenOrders] = useState([]);
    // null = unresolved (staff must choose), 'new' = start a fresh receipt,
    // a number = the exact order id this batch joins.
    const [target, setTarget] = useState(null);

    const [cart, setCart] = useState([]);
    const [search, setSearch] = useState('');
    const [counterOnlyNotice, setCounterOnlyNotice] = useState(false);

    const [customerName, setCustomerName] = useState('');
    const [customerContact, setCustomerContact] = useState('');
    const [scheduledFor, setScheduledFor] = useState('');
    const [notes, setNotes] = useState('');

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

            if (orders.length === 0) {
                // Nothing open — the only possible destination is a new
                // receipt, so there's nothing for staff to decide here.
                setTarget('new');
            } else if (orders.length === 1) {
                // Pre-selected but still changeable (Section 3): staff can
                // still pick "new receipt" instead if that single bill is
                // the wrong one.
                setTarget(orders[0].id);
            }
            // 2+ open receipts: leave target unresolved on purpose — no
            // auto-pick, staff must choose (Test 3).
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

    const changeQuantity = (key, delta) => {
        setCart((current) =>
            current
                .map((line) => (line.key === key ? { ...line, quantity: line.quantity + delta } : line))
                .filter((line) => line.quantity > 0),
        );
    };

    const changeLineNotes = (key, value) => {
        setCart((current) => current.map((line) => (line.key === key ? { ...line, notes: value } : line)));
    };

    const total = useMemo(() => cart.reduce((sum, line) => sum + Number(line.unitPrice) * line.quantity, 0), [cart]);

    const filteredCategories = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return categories;
        return categories
            .map((category) => ({ ...category, items: category.items.filter((item) => item.name.toLowerCase().includes(q)) }))
            .filter((category) => category.items.length > 0);
    }, [categories, search]);

    const selectedOrder = typeof target === 'number' ? openOrders.find((order) => order.id === target) : null;
    const nextBatchNumber = selectedOrder ? (selectedOrder.batch_count ?? 0) + 1 : 1;

    const canSubmit = space && target !== null && cart.length > 0 && scheduledFor.length > 0 && !processing;

    const submit = (e) => {
        e.preventDefault();
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
                onError: (serverErrors) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('New Advance Order')} />

            <div className="mx-auto w-full max-w-[1500px]">
            <section className="mb-5 overflow-hidden rounded-[1.75rem] bg-[#241917] shadow-[0_28px_65px_-36px_rgba(36,25,23,0.9)]">
                <div className="relative flex items-center gap-4 px-6 py-6 sm:px-8">
                    <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-[#A84742]/40 blur-3xl" />
                    <span className="relative grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-7 w-7"><path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                    </span>
                    <div className="relative">
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#E7BBB1]">{t('Advance ordering')}</p>
                        <h1 className="mt-1 text-2xl font-bold tracking-[-0.03em] text-white">{t('New Advance Order')}</h1>
                        <p className="mt-1.5 text-sm text-white/55">{t('Choose the destination, build the order, and confirm the schedule.')}</p>
                    </div>
                </div>
                <div className="grid grid-cols-4 border-t border-white/10 bg-white/[0.04]">
                    {[t('Location'), t('Receipt'), t('Items'), t('Schedule')].map((label, index) => {
                        const reached = index === 0 || (index === 1 && space) || (index >= 2 && space && target !== null);
                        return <div key={label} className={`flex items-center justify-center gap-2 border-r border-white/10 px-2 py-3 text-[10px] font-bold uppercase tracking-wider last:border-0 sm:text-xs ${reached ? 'text-white' : 'text-white/30'}`}><span className={`grid h-5 w-5 place-items-center rounded-full text-[10px] ${reached ? 'bg-[#A84742]' : 'bg-white/10'}`}>{index + 1}</span><span className="hidden sm:inline">{label}</span></div>;
                    })}
                </div>
            </section>

            {errors.target && (
                <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {errors.target}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
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
                    newReceiptLabel={t('NEW PARTY — CREATE NEW RECEIPT')}
                    emptyState={
                        <div className="rounded-xl border border-[#E5DDD0] bg-[#FAF6EE] p-5 text-sm text-gray-700">
                            {t('This table has no open receipt. This advance order will start a new one.')}
                        </div>
                    }
                />

                {space && target !== null && (
                    <p className="rounded-xl border border-dashed border-[#D9CCBA] bg-white px-4 py-3 text-sm text-gray-700 shadow-sm">
                        {target === 'new'
                            ? t('A new receipt will be created for this advance order.')
                            : t('This advance order will be added to receipt :number as Batch :batch.')
                                  .replace(':number', selectedOrder?.order_number ?? '')
                                  .replace(':batch', nextBatchNumber)}
                    </p>
                )}

                {/* Hakbang 4 — items, schedule, confirm */}
                {space && target !== null && (
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(380px,460px)] xl:items-start">
                        <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)] sm:p-6">
                            <div className="mb-4"><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#8A3330]">{t('Menu selection')}</p><h2 className="mt-1 text-base font-bold text-[#251C19]">{t('Add items')}</h2></div>

                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('Search items…')}
                                className="mb-4 block w-full sm:max-w-sm border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg text-sm py-2.5"
                            />

                            {counterOnlyNotice && (
                                <p className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2.5 text-xs text-amber-800">
                                    {t(COUNTER_ONLY_NOTICE)}
                                </p>
                            )}

                            <div className="space-y-6">
                                {filteredCategories.map((category) => (
                                    <div key={category.id}>
                                        <h3 className="mb-2 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{category.name}</h3>
                                        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                            {category.items.map((item) => (
                                                <div
                                                    key={item.id}
                                                    title={item.counter_only ? t(COUNTER_ONLY_NOTICE) : undefined}
                                                    onClick={() => item.counter_only && setCounterOnlyNotice(true)}
                                                    className={`min-h-[92px] rounded-xl border px-4 py-3.5 text-sm transition ${
                                                        item.counter_only
                                                            ? 'border-[#E5DDD0] bg-gray-50 opacity-60 cursor-not-allowed'
                                                            : 'border-[#E5DDD0] bg-[#FCF8F1] hover:border-[#8A3330] hover:bg-white'
                                                    }`}
                                                >
                                                    <p className="font-semibold text-gray-900">{item.name}</p>
                                                    {item.counter_only ? (
                                                        <p className="mt-1 text-[11px] font-medium text-amber-700">{t('Counter only')}</p>
                                                    ) : item.variants.length > 0 ? (
                                                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                                                            {item.variants.map((variant) => (
                                                                <button
                                                                    key={variant.id}
                                                                    type="button"
                                                                    onClick={() => addItem(item, variant)}
                                                                    className="rounded-full border border-[#D9CCBA] px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:border-[#8A3330] hover:text-[#8A3330]"
                                                                >
                                                                    {variant.name} · {peso(variant.price)}
                                                                </button>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => addItem(item)}
                                                            className="mt-1 text-xs font-semibold text-[#8A3330] hover:underline"
                                                        >
                                                            {t('Add')} · {peso(item.price)}
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <aside className="space-y-5 xl:sticky xl:top-20">
                        {cart.length > 0 && (
                            <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)] sm:p-6">
                                <h2 className="mb-3 text-sm font-bold text-[#251C19]">{t('Order lines')}</h2>
                                <div className="space-y-3">
                                    {cart.map((line) => (
                                        <div key={line.key} className="flex items-start justify-between gap-3 border-b border-dashed border-[#E5DDD0] pb-3 last:border-0 last:pb-0">
                                            <div className="flex-1">
                                                <p className="text-sm font-semibold text-gray-900">{line.name}</p>
                                                <input
                                                    type="text"
                                                    value={line.notes}
                                                    onChange={(e) => changeLineNotes(line.key, e.target.value)}
                                                    placeholder={t('Note (optional)')}
                                                    className="mt-1 block w-full max-w-xs border-gray-200 text-xs rounded-md py-1.5 focus:border-[#8A3330] focus:ring-[#8A3330]"
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <button type="button" onClick={() => changeQuantity(line.key, -1)} className="h-7 w-7 rounded-full border border-[#D9CCBA] text-sm text-gray-600 hover:border-[#8A3330]">−</button>
                                                <span className="w-6 text-center text-sm font-semibold">{line.quantity}</span>
                                                <button type="button" onClick={() => changeQuantity(line.key, 1)} className="h-7 w-7 rounded-full border border-[#D9CCBA] text-sm text-gray-600 hover:border-[#8A3330]">+</button>
                                            </div>
                                            <span className="w-20 shrink-0 text-right text-sm font-bold text-[#8A3330]">
                                                {peso(Number(line.unitPrice) * line.quantity)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)] sm:p-6">
                            <div className="mb-4"><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#8A3330]">{t('Order details')}</p><h2 className="mt-1 text-base font-bold text-[#251C19]">{t('Schedule & customer')}</h2></div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="scheduled_for" className="block text-sm font-medium text-gray-700">{t('Scheduled Date and Time')}</label>
                                    <input
                                        id="scheduled_for"
                                        type="datetime-local"
                                        value={scheduledFor}
                                        onChange={(e) => setScheduledFor(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-2.5"
                                    />
                                    {errors.scheduled_for && <p className="mt-1 text-xs text-red-600">{errors.scheduled_for}</p>}
                                </div>
                                <div>
                                    <label htmlFor="customer_name" className="block text-sm font-medium text-gray-700">{t('Customer name')}</label>
                                    <input
                                        id="customer_name"
                                        type="text"
                                        value={customerName}
                                        onChange={(e) => setCustomerName(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-2.5"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="customer_contact" className="block text-sm font-medium text-gray-700">{t('Contact number')}</label>
                                    <input
                                        id="customer_contact"
                                        type="tel"
                                        value={customerContact}
                                        onChange={(e) => setCustomerContact(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-2.5"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="notes" className="block text-sm font-medium text-gray-700">{t('Note for the kitchen (optional)')}</label>
                                    <input
                                        id="notes"
                                        type="text"
                                        value={notes}
                                        onChange={(e) => setNotes(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-2.5"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Confirmation summary — visible before the one press, not a second dialog */}
                        <div className="rounded-2xl border border-[#D9CCBA] bg-[#FCF8F1] px-5 py-5 sm:px-6">
                            <h2 className="mb-4 text-sm font-bold text-[#251C19]">{t('Advance order summary')}</h2>
                            <dl className="space-y-1.5 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">{t('Table')}</dt>
                                    <dd className="font-medium text-gray-900">{areas.find((a) => a.id === areaId)?.name} - {space?.name}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">{t('Receipt')}</dt>
                                    <dd className="font-medium text-gray-900">
                                        {target === 'new' ? t('New receipt') : selectedOrder?.order_number}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">{t('Lines')}</dt>
                                    <dd className="font-medium text-gray-900">{cart.length}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">{t('Scheduled for')}</dt>
                                    <dd className="font-medium text-gray-900">{scheduledFor || '—'}</dd>
                                </div>
                                <div className="flex justify-between border-t border-dashed border-[#D9CCBA] pt-1.5 text-base font-bold text-gray-900">
                                    <dt>{t('Total')}</dt>
                                    <dd>{peso(total)}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className="flex items-center justify-end rounded-2xl border border-[#E5DDD0] bg-white p-4 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)]">
                            <button
                                type="submit"
                                disabled={!canSubmit}
                                className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#8A3330] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {processing
                                    ? t('Saving…')
                                    : target === 'new'
                                      ? t('CREATE RECEIPT FOR THIS ADVANCE ORDER')
                                      : t('ADD TO THIS RECEIPT')}
                            </button>
                        </div>
                        </aside>
                    </div>
                )}
            </form>
            </div>
        </AuthenticatedLayout>
    );
}
