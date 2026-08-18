import TableReceiptPicker from '@/Components/TableReceiptPicker';
import { useTranslation } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { peso } from './useWeighDraft';

/**
 * Step 4 — where the line goes and who ordered it.
 *
 * The verification half matters more than the picking half: before a
 * weighed line is committed, staff should be able to see the guests who
 * actually joined this table and what is already on their bill, so a ₱900
 * fish can't quietly land on the wrong party's receipt.
 *
 * A table can carry MORE than one open bill — a staff-created walk-in
 * order alongside a QR guest session, say — so the lookup always returns
 * every still-billable order on the table and this component asks which
 * one when there is more than one, rather than silently guessing.
 */
export default function StepDestination({
    areas,
    destination,
    onDestinationChange,
    target,
    onTargetChange,
    takeout,
    onTakeoutChange,
    initialTableId,
}) {
    const t = useTranslation();
    const { csrf_token: csrfToken } = usePage().props;

    const [areaId, setAreaId] = useState(null);
    const [space, setSpace] = useState(null);
    const [session, setSession] = useState(null);
    const [openOrders, setOpenOrders] = useState([]);
    const [loading, setLoading] = useState(false);
    const [noOrders, setNoOrders] = useState(false);
    const [busy, setBusy] = useState(false);

    const loadTable = async (chosen) => {
        setSpace(chosen);
        setSession(null);
        setOpenOrders([]);
        setNoOrders(false);
        setLoading(true);
        onTargetChange({ order: null, guestId: null, tableName: chosen.name });

        try {
            const response = await fetch(route('weigh.tables.session', chosen.id), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();

            setSession(data.session);
            setOpenOrders(data.open_orders ?? []);

            if ((data.open_orders ?? []).length === 0) {
                setNoOrders(true);
                return;
            }

            // The common case: exactly one open bill. Select it automatically
            // but still show it, so staff can see what they're adding to.
            // A staff-created order with no guest records has only one
            // possible "who" — 'walk-in' — so that much can resolve itself too.
            if (data.open_orders.length === 1) {
                onTargetChange({
                    order: data.order,
                    guestId: data.session?.guests.length === 1
                        ? data.session.guests[0].id
                        : (data.session ? null : 'walk-in'),
                    guestLabel: data.session?.guests.length === 1 ? data.session.guests[0].label : null,
                    tableName: chosen.name,
                });
            }
        } finally {
            setLoading(false);
        }
    };

    // "Weigh another for this table" from the order page's success toast
    // lands here with the table pre-selected, so staff don't have to
    // re-pick the area and table for a second fish at the same party.
    useEffect(() => {
        if (!initialTableId || destination !== 'dine_in') return;

        for (const candidate of areas) {
            const match = candidate.tables.find((tbl) => tbl.id === Number(initialTableId));
            if (match) {
                setAreaId(candidate.id);
                loadTable(match);
                break;
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initialTableId]);

    const selectOrder = (order) => {
        onTargetChange({
            order,
            guestId: session?.guests.length === 1 ? session.guests[0].id : (session ? null : 'walk-in'),
            guestLabel: session?.guests.length === 1 ? session.guests[0].label : null,
            tableName: space.name,
        });
    };

    const post = async (url, body = {}) => {
        setBusy(true);
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(body),
            });

            return await response.json();
        } finally {
            setBusy(false);
        }
    };

    const openSession = async () => {
        const data = await post(route('weigh.tables.open-session', space.id));
        setNoOrders(false);
        await loadTable(space);
        onTargetChange((current) => ({ ...current, order: data.order, tableName: space.name }));
    };

    const createWalkIn = async () => {
        const data = await post(route('weigh.walk-in'), { customer_name: takeout.name || null });
        onDestinationChange('takeout');
        onTargetChange({ order: data.order, guestId: null, tableName: null });
        setNoOrders(false);
    };

    const startTakeout = async () => {
        const data = await post(route('weigh.walk-in'), {
            customer_name: takeout.name || null,
            contact_number: takeout.contact || null,
        });
        onTargetChange({ order: data.order, guestId: null, tableName: null });
    };

    return (
        <section className="space-y-5 max-w-3xl">
            {/* Destination toggle */}
            <div className="inline-flex rounded-lg border border-[#D9CCBA] p-1 bg-[#FAF6EE]">
                {[
                    { value: 'dine_in', label: t('Dine-in') },
                    { value: 'takeout', label: t('Take-out') },
                ].map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => {
                            onDestinationChange(option.value);
                            onTargetChange({ order: null, guestId: null, tableName: null });
                            setSession(null);
                            setOpenOrders([]);
                            setNoOrders(false);
                            setSpace(null);
                        }}
                        className={`px-6 py-2.5 text-sm font-semibold rounded-md transition ${
                            destination === option.value ? 'bg-[#8A3330] text-white shadow-sm' : 'text-gray-600'
                        }`}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            {destination === 'takeout' ? (
                <div className="bg-white border border-[#E5DDD0] rounded-xl p-6 space-y-4">
                    <h2 className="text-base font-semibold text-gray-900">{t('Take-out customer')}</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="customer_name" className="block text-sm font-medium text-gray-700">{t('Customer name')}</label>
                            <input
                                id="customer_name"
                                type="text"
                                value={takeout.name}
                                onChange={(e) => onTakeoutChange({ ...takeout, name: e.target.value })}
                                className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-3"
                            />
                        </div>
                        <div>
                            <label htmlFor="contact" className="block text-sm font-medium text-gray-700">{t('Contact number')}</label>
                            <input
                                id="contact"
                                type="tel"
                                value={takeout.contact}
                                onChange={(e) => onTakeoutChange({ ...takeout, contact: e.target.value })}
                                className="mt-1 block w-full border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-lg py-3"
                            />
                        </div>
                    </div>

                    {target.order ? (
                        <p className="rounded-lg bg-[#FAF6EE] border border-[#E5DDD0] px-4 py-3 text-sm">
                            {t('Will be added to')} <strong className="font-mono">{target.order.order_number}</strong>
                        </p>
                    ) : (
                        <button
                            type="button"
                            onClick={startTakeout}
                            disabled={busy}
                            className="px-5 py-3 rounded-lg bg-[#8A3330] text-sm font-semibold text-white hover:bg-[#742927] disabled:opacity-60"
                        >
                            {t('Start take-out order')}
                        </button>
                    )}
                </div>
            ) : (
                <>
                    <TableReceiptPicker
                        areas={areas}
                        areaId={areaId}
                        onSelectArea={(id) => {
                            setAreaId(id);
                            setSpace(null);
                            setSession(null);
                            setOpenOrders([]);
                            setNoOrders(false);
                        }}
                        space={space}
                        onSelectSpace={(table) => loadTable(table)}
                        loading={loading}
                        openOrders={openOrders}
                        selectedOrderId={target.order?.id ?? null}
                        onSelectOrder={(orderId) => selectOrder(openOrders.find((o) => o.id === orderId))}
                        allowNewReceipt={false}
                        emptyState={
                            // No open order at all — offer to start one, whatever the reason.
                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
                                <p className="font-semibold text-amber-900">
                                    {t('No open order at :table').replace(':table', space?.name ?? '')}
                                </p>
                                <p className="mt-1 text-sm text-amber-800">
                                    {t('There is nothing to add to yet — seat a guest or start a walk-in bill for this table.')}
                                </p>
                                <div className="mt-4 flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        onClick={openSession}
                                        disabled={busy}
                                        className="px-5 py-3 rounded-lg bg-[#8A3330] text-sm font-semibold text-white hover:bg-[#742927] disabled:opacity-60"
                                    >
                                        {t('Open a session')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={createWalkIn}
                                        disabled={busy}
                                        className="px-5 py-3 rounded-lg border border-[#D9CCBA] bg-white text-sm font-semibold text-gray-700 hover:border-[#8A3330] disabled:opacity-60"
                                    >
                                        {t('Create a walk-in order')}
                                    </button>
                                </div>
                            </div>
                        }
                    />

                    {/* Guests + running order, once a single bill is settled on. */}
                    {target.order && (
                        <div className="grid gap-5 lg:grid-cols-2">
                            <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">
                                    {t('Who ordered this?')}
                                </h2>
                                {!session || session.guests.length === 0 ? (
                                    // Staff-created order with no guest records: the only
                                    // meaningful choice is "Walk-in" — there is no guest to pick.
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onTargetChange({
                                                order: target.order,
                                                guestId: 'walk-in',
                                                guestLabel: null,
                                                tableName: space.name,
                                            })
                                        }
                                        className={`flex w-full items-center gap-3 rounded-lg border px-4 py-3.5 text-left transition ${
                                            target.guestId === 'walk-in'
                                                ? 'border-[#8A3330] bg-[#F3E1DC]'
                                                : 'border-[#E5DDD0] hover:border-[#8A3330]'
                                        }`}
                                    >
                                        <span className="grid h-9 w-9 place-items-center rounded-full bg-[#8A3330] text-sm font-bold text-white">
                                            •
                                        </span>
                                        <span className="text-sm font-medium text-gray-900">
                                            {t('Walk-in')}
                                        </span>
                                    </button>
                                ) : (
                                    <div className="space-y-2.5">
                                        {session.guests.map((guest) => (
                                            <button
                                                key={guest.id}
                                                type="button"
                                                onClick={() =>
                                                    onTargetChange({
                                                        order: target.order,
                                                        guestId: guest.id,
                                                        guestLabel: guest.label,
                                                        tableName: space.name,
                                                    })
                                                }
                                                className={`flex w-full items-center gap-3 rounded-lg border px-4 py-3.5 text-left transition ${
                                                    target.guestId === guest.id
                                                        ? 'border-[#8A3330] bg-[#F3E1DC]'
                                                        : 'border-[#E5DDD0] hover:border-[#8A3330]'
                                                }`}
                                            >
                                                <span className="grid h-9 w-9 place-items-center rounded-full bg-[#8A3330] text-sm font-bold text-white">
                                                    {guest.number}
                                                </span>
                                                <span className="text-sm font-medium text-gray-900">{guest.label}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="bg-white border border-[#E5DDD0] rounded-xl p-5">
                                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">
                                    {t('Their running order')}
                                </h2>
                                <p className="font-mono text-sm font-semibold text-gray-900">{target.order.order_number}</p>
                                <ul className="mt-3 space-y-1.5">
                                    {(target.order.items ?? []).map((line) => (
                                        <li
                                            key={line.id}
                                            className={`flex justify-between gap-3 text-sm ${line.cancelled ? 'text-gray-400 line-through' : 'text-gray-700'}`}
                                        >
                                            <span>
                                                {line.is_weighed ? '' : `${line.quantity}× `}
                                                {line.name}
                                                {line.detail && <span className="block text-xs text-gray-400">{line.detail}</span>}
                                            </span>
                                            <span className="shrink-0">{peso(line.subtotal)}</span>
                                        </li>
                                    ))}
                                </ul>
                                <div className="mt-3 flex justify-between border-t border-dashed border-[#D9CCBA] pt-2 text-sm font-bold text-gray-900">
                                    <span>{t('Running total')}</span>
                                    <span>{peso(target.order.total_amount)}</span>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </section>
    );
}
