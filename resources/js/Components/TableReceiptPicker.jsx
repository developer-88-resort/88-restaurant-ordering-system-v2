import { useTranslation } from '@/lib/i18n';

const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Area chips + table grid + "which open receipt does this join" card list —
 * shared between Weigh & Order (StepDestination) and the Quotations create
 * screen, so picking a table looks and behaves identically everywhere in the
 * app that has to answer "which receipt does this land on?"
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
            <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Area')}</h2>
                <div className="flex flex-wrap gap-2.5">
                    {areas.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            onClick={() => onSelectArea(option.id)}
                            className={`px-5 py-3 rounded-lg border text-sm font-semibold transition ${
                                areaId === option.id
                                    ? 'border-[#8A3330] bg-[#8A3330] text-white'
                                    : 'border-[#D9CCBA] text-gray-700 hover:border-[#8A3330]'
                            }`}
                        >
                            {option.name}
                        </button>
                    ))}
                </div>
            </div>

            {area && (
                <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                    <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Table')}</h2>
                    <div className="grid grid-cols-3 sm:grid-cols-5 gap-2.5">
                        {area.tables.map((table) => (
                            <button
                                key={table.id}
                                type="button"
                                onClick={() => onSelectSpace(table)}
                                className={`rounded-lg border px-3 py-4 text-sm font-semibold transition ${
                                    space?.id === table.id
                                        ? 'border-[#8A3330] bg-[#8A3330] text-white'
                                        : 'border-[#D9CCBA] text-gray-700 hover:border-[#8A3330]'
                                }`}
                            >
                                {area.name} - {table.name}
                                <span
                                    className={`block text-[10px] font-medium mt-0.5 ${
                                        space?.id === table.id
                                            ? 'text-white/70'
                                            : table.has_open_order
                                              ? 'text-[#8A3330]'
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

            {space && !loading && openOrders.length === 0 && emptyState}

            {space && !loading && openOrders.length > 0 && (
                <div className="rounded-xl border border-[#E5DDD0] bg-white p-5">
                    <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">
                        {openOrders.length > 1
                            ? t('This table has more than one open receipt — which one?')
                            : t('Open receipt on this table')}
                    </h2>
                    <div className="space-y-2.5">
                        {openOrders.map((order) => (
                            <button
                                key={order.id}
                                type="button"
                                onClick={() => onSelectOrder(order.id)}
                                className={`flex w-full items-start justify-between gap-3 rounded-lg border px-4 py-3.5 text-left transition ${
                                    selectedOrderId === order.id
                                        ? 'border-[#8A3330] bg-[#F3E1DC]'
                                        : 'border-[#E5DDD0] hover:border-[#8A3330]'
                                }`}
                            >
                                <span>
                                    <span className="block font-mono text-sm font-semibold text-gray-900">{order.order_number}</span>
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
                                    <span className="block text-sm font-bold text-[#8A3330]">{peso(order.total_amount)}</span>
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
                                className={`flex w-full items-center gap-3 rounded-lg border border-dashed px-4 py-3.5 text-left transition ${
                                    selectedOrderId === null
                                        ? 'border-[#8A3330] bg-[#F3E1DC]'
                                        : 'border-[#D9CCBA] hover:border-[#8A3330]'
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
