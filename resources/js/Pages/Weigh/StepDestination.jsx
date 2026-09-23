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
 * A weighed item goes on a NEW slip for the table by default — its own
 * kitchen ticket. The table's open slips are listed too, so staff can add
 * the item to one of them instead when it belongs there.
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
    const [newSlip, setNewSlip] = useState(null);
    const [busy, setBusy] = useState(false);

    // What the table's next slip will be — the default destination.
    const newSlipFor = (data) => {
        const label = t('Slip #:number').replace(':number', data.next_slip_number ?? 1);

        return { id: null, is_new_slip: true, order_number: label, slip_label: label, items: [], total_amount: 0 };
    };

    // With exactly one guest on the tab, or none at all (a table staff
    // seated), "who ordered this?" has only one answer and fills itself in.
    const autoGuest = (tableSession) => {
        if (tableSession?.guests.length === 1) {
            return { guestId: tableSession.guests[0].id, guestLabel: tableSession.guests[0].label };
        }

        return { guestId: tableSession?.guests.length ? null : 'walk-in', guestLabel: null };
    };

    const loadTable = async (chosen) => {
        setSpace(chosen);
        setSession(null);
        setOpenOrders([]);
        setNewSlip(null);
        setLoading(true);
        onTargetChange({ order: null, guestId: null, tableName: chosen.name, spaceId: chosen.id });

        try {
            const response = await fetch(route('weigh.tables.session', chosen.id), {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            const slip = newSlipFor(data);

            setSession(data.session);
            setOpenOrders(data.open_orders ?? []);
            setNewSlip(slip);
            onTargetChange({ order: slip, ...autoGuest(data.session), tableName: chosen.name, spaceId: chosen.id });
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

    // null = the new slip.
    const selectOrder = (order) => {
        onTargetChange({
            order: order ?? newSlip,
            ...autoGuest(session),
            tableName: space.name,
            spaceId: space.id,
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

    const startTakeout = async () => {
        const data = await post(route('weigh.walk-in'), {
            customer_name: takeout.name || null,
            contact_number: takeout.contact || null,
        });
        onTargetChange({ order: data.order, guestId: null, tableName: null });
    };

    return (
        <section className="space-y-5">
            {/* Destination toggle */}
            <div className="inline-flex rounded-xl border border-[#D9CCBA] bg-white p-1 shadow-sm">
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
                            setSpace(null);
                        }}
                        className={`rounded-lg px-6 py-2.5 text-sm font-semibold transition ${
                            destination === option.value ? 'bg-[#8A3330] text-white shadow-sm' : 'text-gray-600'
                        }`}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            {destination === 'takeout' ? (
                <div className="space-y-4 rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)] sm:p-6">
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
                        }}
                        space={space}
                        onSelectSpace={(table) => loadTable(table)}
                        loading={loading}
                        openOrders={openOrders}
                        selectedOrderId={target.order?.id ?? null}
                        onSelectOrder={(orderId) => selectOrder(orderId === null ? null : openOrders.find((o) => o.id === orderId))}
                        allowNewReceipt
                        newReceiptLabel={`${t('New slip')} · ${newSlip?.slip_label ?? ''}`}
                    />

                    {/* Guests + running order, once a single bill is settled on. */}
                    {target.order && (
                        <div className="grid gap-5 lg:grid-cols-2">
                            <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)]">
                                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">
                                    {t('Who ordered this?')}
                                </h2>
                                {!session || session.guests.length === 0 ? (
                                    // Staff-created order with no guest records: the only
                                    // meaningful choice is "Walk-in" — there is no guest to pick.
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onTargetChange({ ...target, guestId: 'walk-in', guestLabel: null })
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
                                                    onTargetChange({ ...target, guestId: guest.id, guestLabel: guest.label })
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

                            <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)]">
                                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">
                                    {t('Their running order')}
                                </h2>
                                <p className="text-sm font-semibold text-gray-900">
                                    {target.order.is_new_slip
                                        ? `${t('New slip')} · ${target.order.slip_label}`
                                        : target.order.slip_label
                                          ? `${target.order.slip_label} · ${target.order.order_number}`
                                          : target.order.order_number}
                                </p>
                                {target.order.is_new_slip && (
                                    <p className="mt-2 text-sm text-gray-500">
                                        {t('Nothing on it yet — this weighed item will be its first line.')}
                                    </p>
                                )}
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
