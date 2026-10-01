import { useTranslation } from '@/lib/i18n';

const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Area chips + table grid + "new slip, or add to which open slip?" card
 * list — shared between Weigh & Order (StepDestination) and the Quotations
 * create screen, so picking a table looks and behaves identically everywhere
 * in the app that has to answer "which slip does this land on?"
 *
 * Table tiles always label as "{area} - {table}" so two same-named tables in
 * different areas are never ambiguous, and a 3rd occupancy state ("has an
 * open receipt") layers on top of the space's own available/occupied status.
 *
 * Selection is fully controlled by the parent — this component owns no
 * state of its own, so each caller's own "what happens after a table/order
 * is picked" logic (Weigh's guest picker, Quotations' item picker) stays
 * exactly where it already lives.
 */
export default function TableReceiptPicker({
    areas,
    areaId,
    onSelectArea,
    space,
    onSelectSpace,
    loading = false,
    openOrders = [],
    selectedOrderId,
    onSelectOrder,
    allowNewReceipt = false,
    newReceiptLabel,
    emptyState = null,
}) {
    const t = useTranslation();
    const area = areas.find((a) => a.id === areaId) ?? null;

    const occupancyLabel = (table) => {
        if (table.has_open_order) return t('Has an open receipt');
        return table.status === 'occupied' ? t('Occupied') : t('Available');
    };

    return (
        <>
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-700">{t('Location')}</p><h2 className="mt-1 text-base font-bold text-slate-900">{t('Choose an area')}</h2></div>
                    <span className="grid h-8 w-8 place-items-center rounded-full bg-slate-50 text-xs font-bold text-slate-700">1</span>
                </div>
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
                    {areas.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            aria-pressed={areaId === option.id}
                            onClick={() => onSelectArea(option.id)}
                            className={`min-h-12 rounded-xl border px-4 py-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 ${
                                areaId === option.id
                                    ? 'border-slate-400 bg-slate-100 text-slate-900 ring-1 ring-slate-400'
                                    : 'border-slate-200 bg-slate-50 text-gray-700 hover:border-slate-400 hover:bg-white'
                            }`}
                        >
                            {option.name}
                        </button>
                    ))}
                </div>
            </div>

            {area && (
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-700">{area.name}</p><h2 className="mt-1 text-base font-bold text-slate-900">{t('Choose a table')}</h2></div>
                        <span className="grid h-8 w-8 place-items-center rounded-full bg-slate-50 text-xs font-bold text-slate-700">2</span>
                    </div>
                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        {area.tables.map((table) => (
                            <button
                                key={table.id}
                                type="button"
                                aria-pressed={space?.id === table.id}
                                onClick={() => onSelectSpace(table)}
                                className={`min-h-[72px] rounded-xl border px-3 py-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 ${
                                    space?.id === table.id
                                        ? 'border-slate-400 bg-slate-100 text-slate-900 ring-1 ring-slate-400'
                                        : 'border-slate-200 bg-slate-50 text-gray-700 hover:border-slate-400 hover:bg-white'
                                }`}
                            >
                                {area.name} - {table.name}
                                <span
                                    className={`block text-[10px] font-medium mt-0.5 ${
                                        space?.id === table.id
                                            ? 'text-slate-600'
                                            : table.has_open_order
                                              ? 'text-slate-700'
                                              : 'text-gray-400'
                                    }`}
                                >
                                    {occupancyLabel(table)}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {loading && <p className="text-sm text-gray-500">{t('Checking the table…')}</p>}

            {/* With a "new slip" option there is always something to choose,
                so the empty state only applies to callers without one. */}
            {space && !loading && openOrders.length === 0 && !allowNewReceipt && emptyState}

            {space && !loading && (openOrders.length > 0 || allowNewReceipt) && (
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="mb-4 flex items-start justify-between gap-3">
                    <h2 className="text-sm font-bold text-slate-900">
                        {openOrders.length === 0
                            ? t('No open slips on this table yet')
                            : allowNewReceipt
                              ? t('Start a new slip, or add to an open one?')
                              : openOrders.length > 1
                                ? t('This table has more than one open slip — which one?')
                                : t('Open slip on this table')}
                    </h2>
                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-50 text-xs font-bold text-slate-700">3</span>
                    </div>
                    <div className="grid gap-2.5 lg:grid-cols-2">
                        {openOrders.map((order) => (
                            <button
                                key={order.id}
                                type="button"
                                onClick={() => onSelectOrder(order.id)}
                                className={`flex w-full items-start justify-between gap-3 rounded-xl border px-4 py-4 text-left transition ${
                                    selectedOrderId === order.id
                                        ? 'border-slate-400 bg-slate-100 ring-1 ring-slate-400'
                                        : 'border-slate-200 hover:border-slate-400'
                                }`}
                            >
                                <span>
                                    <span className="block text-sm font-bold text-gray-900">
                                        {order.slip_label ?? order.order_number}
                                        {order.slip_label && (
                                            <span className="ml-2 font-mono text-xs font-medium text-gray-400">{order.order_number}</span>
                                        )}
                                    </span>
                                    <span className="block text-xs text-gray-500">
                                        {order.channel_label} · {order.guest_label} · {order.batch_count} {t('batch(es)')}
                                    </span>
                                    {(order.items ?? []).length > 0 && (
                                        <span className="mt-1 block text-xs text-gray-400">
                                            {order.items.slice(0, 3).map((line) => line.name).join(', ')}
                                            {order.items.length > 3 ? '…' : ''}
                                        </span>
                                    )}
                                </span>
                                <span className="shrink-0 text-right">
                                    <span className="block text-sm font-bold text-slate-700">{peso(order.total_amount)}</span>
                                    <span className="block text-[10px] uppercase tracking-wide text-gray-400">
                                        {order.payment_status}
                                    </span>
                                </span>
                            </button>
                        ))}

                        {allowNewReceipt && (
                            <button
                                type="button"
                                onClick={() => onSelectOrder(null)}
                                className={`flex min-h-[72px] w-full items-center gap-3 rounded-xl border border-dashed px-4 py-3.5 text-left transition ${
                                    selectedOrderId === null
                                        ? 'border-slate-400 bg-slate-100 ring-1 ring-slate-400'
                                        : 'border-slate-200 hover:border-slate-400'
                                }`}
                            >
                                <span className="text-sm font-semibold text-gray-900">
                                    {newReceiptLabel ?? t('New party — start a new receipt')}
                                </span>
                            </button>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
