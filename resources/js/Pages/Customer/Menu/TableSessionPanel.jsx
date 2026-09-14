import { useTranslation } from '@/lib/i18n';
import { useState } from 'react';

export default function TableSessionPanel({ space, guestLabel, sessionOrderCount, sessionTotal, previousOrders, joinQrUrl, onReorderBatch }) {
    const t = useTranslation();
    const [sharePanelOpen, setSharePanelOpen] = useState(false);
    const [myOrdersOpen, setMyOrdersOpen] = useState(false);

    return (
        <div className="mx-auto max-w-5xl px-4 pt-3">
            <div className="rounded-xl border border-[#E5DDD0] bg-white px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900">
                            {space.name} · {guestLabel}
                        </p>
                        {sessionOrderCount > 0 && (
                            <p className="text-xs text-[#8A7B6D]">
                                {t('Table total so far')}: ₱{sessionTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (
                                {sessionOrderCount} {sessionOrderCount === 1 ? t('order') : t('orders')})
                            </p>
                        )}
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        {previousOrders.length > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    setMyOrdersOpen((v) => !v);
                                    setSharePanelOpen(false);
                                }}
                                className="rounded-full border border-[#D9CCBA] px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-[#8A3330]"
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
                            className="rounded-full border border-[#8A3330] px-3 py-1.5 text-xs font-semibold text-[#8A3330] transition hover:bg-[#FAF6EE]"
                        >
                            ⊞ {t('Share Table QR')}
                        </button>
                    </div>
                </div>

                {sharePanelOpen && (
                    <div className="mt-3 flex flex-col items-center gap-4 border-t border-dashed border-[#E5DDD0] pt-3 sm:flex-row">
                        <img src={joinQrUrl} alt={t('Join Table Session QR')} className="h-40 w-40 rounded-lg border border-[#E5DDD0] bg-white" />
                        <div className="space-y-1 text-sm text-[#8A7B6D]">
                            <p className="font-semibold text-gray-900">{t('Ordering together?')}</p>
                            <p>
                                {t(
                                    'Let your companions scan this QR with their own phones — everyone gets their own cart, and all orders stay under :table.',
                                ).replace(':table', space.name)}
                            </p>
                        </div>
                    </div>
                )}

                {myOrdersOpen && (
                    <div className="mt-3 space-y-3 border-t border-dashed border-[#E5DDD0] pt-3">
                        {previousOrders.map((order) => (
                            <div key={`${order.batch}-${order.number}`} className="rounded-lg border border-[#E5DDD0] px-3 py-2.5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-gray-900">
                                            {t('Batch')} #{order.batch} <span className="text-xs font-normal text-gray-400">{order.number} · {order.placedAt}</span>
                                        </p>
                                        <p className="text-xs text-[#8A7B6D]">
                                            {order.status} · {order.paymentStatus} · ₱{Number(order.total).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <a href={order.statusUrl} data-turbo="false" className="text-xs font-medium text-[#8A3330] hover:underline">
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
