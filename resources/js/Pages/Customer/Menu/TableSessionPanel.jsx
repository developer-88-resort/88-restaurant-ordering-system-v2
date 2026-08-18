import { useTranslation } from '@/lib/i18n';
import { useState } from 'react';

function TableIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-4 w-4">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z"
            />
        </svg>
    );
}

function QrIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-3.5 w-3.5">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3.75 4.5A.75.75 0 014.5 3.75h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5zM3.75 15A.75.75 0 014.5 14.25h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75V15zM14.25 4.5a.75.75 0 01.75-.75h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5z"
            />
            <path strokeLinecap="round" strokeLinejoin="round" d="M14.25 14.25h2.25v2.25h-2.25zM17.25 17.25h2.25v2.25h-2.25zM14.25 19.5h2.25M19.5 14.25v2.25" />
        </svg>
    );
}

export default function TableSessionPanel({ space, guestLabel, sessionOrderCount, sessionTotal, previousOrders, joinQrUrl, onReorderBatch }) {
    const t = useTranslation();
    const [sharePanelOpen, setSharePanelOpen] = useState(false);
    const [myOrdersOpen, setMyOrdersOpen] = useState(false);

    return (
        <div className="mx-auto max-w-5xl px-4 pt-3">
            <div className="rounded-2xl border border-[#E5DDD0] bg-white px-4 py-3 shadow-[0_10px_28px_-22px_rgba(55,35,30,0.5)] sm:px-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#1F3D2B]/10 text-[#1F3D2B]">
                            <TableIcon />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-[#251C19]">
                                {space.name} <span className="font-normal text-[#8A7B6D]">· {guestLabel}</span>
                            </p>
                            {sessionOrderCount > 0 && (
                                <p className="text-xs text-[#8A7B6D]">
                                    {t('Table total so far')}: <span className="font-semibold text-[#8A3330]">₱{sessionTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                    {' '}({sessionOrderCount} {sessionOrderCount === 1 ? t('order') : t('orders')})
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        {previousOrders.length > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    setMyOrdersOpen((v) => !v);
                                    setSharePanelOpen(false);
                                }}
                                className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                    myOrdersOpen ? 'border-[#8A3330] bg-[#FAF6EE] text-[#8A3330]' : 'border-[#D9CCBA] text-gray-700 hover:border-[#8A3330]'
                                }`}
                            >
                                {t('My Orders')} ({previousOrders.length})
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => {
                                setSharePanelOpen((v) => !v);
                                setMyOrdersOpen(false);
                            }}
                            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                sharePanelOpen ? 'border-[#8A3330] bg-[#8A3330] text-white' : 'border-[#8A3330] text-[#8A3330] hover:bg-[#FAF6EE]'
                            }`}
                        >
                            <QrIcon />
                            <span className="hidden sm:inline">{t('Share Table QR')}</span>
                            <span className="sm:hidden">{t('Share')}</span>
                        </button>
                    </div>
                </div>

                {sharePanelOpen && (
                    <div className="mt-3 flex flex-col items-center gap-4 border-t border-dashed border-[#E5DDD0] pt-3 sm:flex-row">
                        <img src={joinQrUrl} alt={t('Join Table Session QR')} className="h-36 w-36 shrink-0 rounded-xl border border-[#E5DDD0] bg-white p-1.5" />
                        <div className="space-y-1 text-sm text-[#8A7B6D]">
                            <p className="font-semibold text-[#251C19]">{t('Ordering together?')}</p>
                            <p>
                                {t(
                                    'Let your companions scan this QR with their own phones — everyone gets their own cart, and all orders stay under :table.',
                                ).replace(':table', space.name)}
                            </p>
                        </div>
                    </div>
                )}

                {myOrdersOpen && (
                    <div className="mt-3 space-y-2.5 border-t border-dashed border-[#E5DDD0] pt-3">
                        {previousOrders.map((order) => (
                            <div key={`${order.batch}-${order.number}`} className="rounded-xl border border-[#E5DDD0] bg-[#FCFAF7] px-3.5 py-2.5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-[#251C19]">
                                            {t('Batch')} #{order.batch} <span className="text-xs font-normal text-gray-400">{order.number} · {order.placedAt}</span>
                                        </p>
                                        <p className="text-xs text-[#8A7B6D]">
                                            {order.status} · {order.paymentStatus} · ₱{Number(order.total).toFixed(2)}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <a href={order.statusUrl} className="text-xs font-medium text-[#8A3330] hover:underline">
                                            {t('Track')}
                                        </a>
                                        <button
                                            type="button"
                                            onClick={() => onReorderBatch(order.items)}
                                            className="rounded-full border border-[#8A3330] px-2.5 py-1 text-xs font-semibold text-[#8A3330] transition hover:bg-[#FAF6EE]"
                                        >
                                            {t('Order Again')}
                                        </button>
                                    </div>
                                </div>
                                <p className="mt-1 truncate text-xs text-gray-500">{order.items.map((i) => `${i.qty}× ${i.name}`).join(', ')}</p>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
