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

        try {
            const response = await fetch(route('quotations.table-receipts', table.id), {
                headers: { Accept: 'application/json' },
            });
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

            <div className="mb-5">
                <h1 className="text-2xl font-bold text-gray-900">{t('New Advance Order')}</h1>
                <p className="mt-1 text-sm text-gray-500">
                    {t('Pick the table, choose which receipt this joins, then add the items.')}
                </p>
            </div>

            {errors.target && (
                <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {errors.target}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5 max-w-4xl">
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
                    newReceiptLabel={t('BAGONG PARTIDO — GUMAWA NG BAGONG RESIBO')}
                    emptyState={
                        <div className="rounded-xl border border-[#E5DDD0] bg-[#FAF6EE] p-5 text-sm text-gray-700">
                            {t('Walang bukas na resibo ang table na ito. Ang advance order na ito ang bubuo ng bagong resibo.')}
                        </div>
                    }
                />

                {space && target !== null && (
                    <p className="rounded-lg border border-dashed border-[#D9CCBA] bg-white px-4 py-3 text-sm text-gray-700">
                        {target === 'new'
                            ? t('Gagawa ng bagong resibo para sa advance order na ito.')
                            : t('Papasok ang advance order na ito sa resibong :number bilang Batch :batch.')
                                  .replace(':number', selectedOrder?.order_number ?? '')
                                  .replace(':batch', nextBatchNumber)}
                    </p>
                )}

                {/* Hakbang 4 — items, schedule, confirm */}
                {space && target !== null && (
                    <>
                        <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                            <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Items')}</h2>

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
                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                            {category.items.map((item) => (
                                                <div
                                                    key={item.id}
                                                    title={item.counter_only ? t(COUNTER_ONLY_NOTICE) : undefined}
                                                    onClick={() => item.counter_only && setCounterOnlyNotice(true)}
                                                    className={`rounded-lg border px-3 py-2.5 text-sm ${
                                                        item.counter_only
                                                            ? 'border-[#E5DDD0] bg-gray-50 opacity-60 cursor-not-allowed'
                                                            : 'border-[#E5DDD0]'
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

                        {cart.length > 0 && (
                            <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Order lines')}</h2>
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

                        <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                            <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Schedule & customer')}</h2>
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
                        <div className="rounded-xl bg-[#FAF6EE] border border-[#E5DDD0] px-5 py-4">
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

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={!canSubmit}
                                className="inline-flex items-center px-8 py-3.5 bg-[#8A3330] rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-[#742927] transition disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                {processing
                                    ? t('Saving…')
                                    : target === 'new'
                                      ? t('GUMAWA NG RESIBO PARA SA ADVANCE ORDER NA ITO')
                                      : t('IDAGDAG SA RESIBONG ITO')}
                            </button>
                        </div>
                    </>
                )}
            </form>
        </AuthenticatedLayout>
    );
}
