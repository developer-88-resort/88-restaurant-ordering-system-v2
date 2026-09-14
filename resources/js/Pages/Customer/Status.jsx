import { ToastList } from '@/Components/Toast';
import CustomerLayout from '@/Layouts/CustomerLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STEP_INDEX = { pending: 0, preparing: 1, ready: 2, served: 3, completed: 3 };

const peso = (value) => `₱${Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Status({ order: initialOrder, totals, invoice }) {
    const t = useTranslation();

    const [status, setStatus] = useState(initialOrder.status);
    const [paymentStatus, setPaymentStatus] = useState(initialOrder.payment_status);
    const [hasReceipt, setHasReceipt] = useState(initialOrder.has_receipt);
    const [toasts, setToasts] = useState([]);

    const paymentMeta = {
        unpaid: { label: t('Unpaid'), dot: 'bg-red-500' },
        paid: { label: t('Paid'), dot: 'bg-green-500' },
        voided: { label: t('Payment Voided'), dot: 'bg-gray-500' },
    };

    const steps = [
        { value: 'pending', label: t('Pending') },
        { value: 'preparing', label: t('Preparing') },
        { value: 'ready', label: t('Ready') },
        { value: 'served', label: t('Served') },
    ];

    const currentStepIndex = STEP_INDEX[status] ?? 0;

    const pushToast = (message) => {
        const id = Date.now() + Math.random();
        setToasts((current) => [...current, { id, type: 'success', message }]);
        setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 5000);
    };

    useEffect(() => {
        if (!window.Echo) return undefined;

        const channelName = `order.${initialOrder.public_token}`;
        const channel = window.Echo.channel(channelName);

        channel.listen('.CustomerOrderStatusUpdated', (event) => {
            setPaymentStatus((current) => {
                if (current !== 'paid' && event.payment_status === 'paid') {
                    pushToast(t('Payment received. Thank you!'));
                }
                return event.payment_status;
            });
            setStatus(event.status);
            setHasReceipt(event.has_receipt);
        });

        const leave = () => window.Echo?.leave(channelName);
        turboCleanup(leave);

        return leave;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initialOrder.public_token]);

    return (
        <CustomerLayout title={t('Order Status')} locationLabel={initialOrder.location_label}>
            <div className="mx-auto max-w-2xl px-4 py-6 pb-10">
                <ToastList toasts={toasts} onDismiss={(id) => setToasts((current) => current.filter((toast) => toast.id !== id))} />

                <div className="rounded-xl border border-[#E5DDD0] bg-white p-6 text-center">
                    <p className="text-xs uppercase tracking-wide text-[#8A7B9E]">{t('Order')}</p>
                    <p className="mt-1 text-2xl font-bold text-gray-900">{initialOrder.number}</p>
                    {initialOrder.batch_number && (
                        <p className="mt-1 text-xs font-semibold text-teal-700">
                            {t('Batch')} #{initialOrder.batch_number}
                            {initialOrder.guest_label && <> · {initialOrder.guest_label}</>}
                        </p>
                    )}
                </div>

                {status === 'cancelled' && (
                    <div className="mt-6 rounded-xl border border-red-200 bg-red-50 p-6 text-center">
                        <p className="font-semibold text-red-800">{t('This order was cancelled.')}</p>
                        <p className="mt-1 text-sm text-red-700">{t('Please ask our staff if you have any questions.')}</p>
                    </div>
                )}

                {status === 'completed' && (
                    <div className="mt-6 rounded-xl border border-[#E5C9BE] bg-[#F3E1DC] p-6 text-center">
                        <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#8A3330]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="white" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <p className="mt-3 font-semibold text-[#8A3330]">{t('Thank you for dining with us!')}</p>
                        <p className="mt-1 text-sm text-[#8A3330]/80">
                            {t('We hope you enjoyed your meal at 88 Hot Spring Resort. See you again soon!')}
                        </p>

                        {initialOrder.order_again_url && (
                            <Link
                                href={initialOrder.order_again_url}
                                data-turbo="false"
                                className="mt-5 inline-flex items-center justify-center rounded-lg bg-[#8A3330] px-5 py-2.5 font-semibold text-white transition hover:bg-[#742927]"
                            >
                                {t('Order Again')}
                            </Link>
                        )}
                    </div>
                )}

                {status !== 'cancelled' && status !== 'completed' && (
                    <div className="mt-6 rounded-xl border border-[#E5DDD0] bg-white p-6">
                        <div className="flex items-start">
                            {steps.map((step, i) => (
                                <div key={step.value} className="relative flex flex-1 flex-col items-center">
                                    {i < steps.length - 1 && (
                                        <div className={`absolute left-1/2 top-4 h-0.5 w-full ${i < currentStepIndex ? 'bg-[#8A3330]' : 'bg-gray-200'}`} />
                                    )}
                                    <div
                                        className={`z-10 flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors ${
                                            i <= currentStepIndex ? 'bg-[#8A3330] text-white' : 'bg-gray-100 text-gray-400'
                                        }`}
                                    >
                                        {i + 1}
                                    </div>
                                    <span
                                        className={`mt-2 text-center text-xs font-medium transition-colors ${
                                            i <= currentStepIndex ? 'text-[#8A3330]' : 'text-gray-400'
                                        }`}
                                    >
                                        {step.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="mt-6 overflow-hidden rounded-xl border border-[#E5DDD0] bg-white">
                    <div className="border-b border-[#E5DDD0] px-6 py-4">
                        <h3 className="font-semibold text-gray-900">{t('Order Details')}</h3>
                    </div>
                    <div className="divide-y divide-[#E5DDD0]">
                        {initialOrder.items.map((item) => (
                            <div key={item.id} className="px-6 py-3">
                                <div className="flex items-center justify-between gap-3">
                                    <p className={`text-sm font-medium ${item.is_fully_cancelled ? 'text-gray-400' : 'text-gray-900'}`}>
                                        <span className={`font-semibold ${item.is_fully_cancelled ? 'line-through' : ''}`}>{item.quantity}&times;</span>{' '}
                                        <span className={item.is_fully_cancelled ? 'line-through' : ''}>{item.name}</span>
                                        {item.is_fully_cancelled ? (
                                            <span className="ml-1 inline-flex rounded bg-red-100 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase text-red-700">
                                                {t('Cancelled')}
                                            </span>
                                        ) : (
                                            item.cancelled_quantity > 0 && (
                                                <span className="ml-1 inline-flex rounded bg-red-100 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase text-red-700">
                                                    −{item.cancelled_quantity} {t('cancelled')}
                                                </span>
                                            )
                                        )}
                                        {item.notes && <span className="mt-0.5 block text-xs font-normal italic text-gray-400">{item.notes}</span>}
                                    </p>
                                    <span className={`shrink-0 text-sm font-semibold ${item.is_fully_cancelled ? 'text-gray-400 line-through' : 'text-gray-900'}`}>
                                        {peso(item.subtotal)}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Every figure below comes from OrderTotals, the same
                        read-model the cashier screen and the receipt use. */}
                    <div className="space-y-1.5 bg-[#FAF6EE] px-6 py-4 text-sm">
                        {totals.has_cancellations && (
                            <>
                                <div className="flex justify-between text-[#8A7B6D]">
                                    <span>{t('Original Subtotal')}</span>
                                    <span>{peso(totals.original_subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-red-600">
                                    <span>{t('Cancelled Items')}</span>
                                    <span>−{peso(totals.cancelled_amount)}</span>
                                </div>
                            </>
                        )}
                        <div className={`flex justify-between ${totals.has_cancellations ? 'text-[#8A7B6D]' : 'font-semibold text-gray-900'}`}>
                            <span>{totals.has_cancellations ? t('Active Subtotal') : t('Subtotal')}</span>
                            <span>{peso(totals.active_subtotal)}</span>
                        </div>

                        {invoice ? (
                            <>
                                {invoice.discount_amount > 0 && (
                                    <div className="flex justify-between text-[#8A3330]">
                                        <span>{t('Discount')}</span>
                                        <span>−{peso(invoice.discount_amount)}</span>
                                    </div>
                                )}
                                {invoice.is_vat && (
                                    <>
                                        <div className="flex justify-between text-xs text-gray-400">
                                            <span>{t('VATable Sales')}</span>
                                            <span>{peso(invoice.vatable_sales)}</span>
                                        </div>
                                        <div className="flex justify-between text-xs text-gray-400">
                                            <span>{t('VAT (:rate%)').replace(':rate', invoice.tax_rate.toFixed(0))}</span>
                                            <span>{peso(invoice.vat_amount)}</span>
                                        </div>
                                    </>
                                )}

                                <div className="flex justify-between border-t border-dashed border-[#D9CCBA] pt-1.5 font-semibold text-gray-900">
                                    <span>{t('Total Amount Due')}</span>
                                    <span className="text-lg font-bold text-[#8A3330]">{peso(totals.payable_total)}</span>
                                </div>
                                <div className="flex justify-between text-[#8A7B6D]">
                                    <span>{t('Amount Paid')}</span>
                                    <span>{peso(totals.amount_paid)}</span>
                                </div>

                                {totals.has_refund_due && (
                                    // An item was cancelled after this invoice was issued: the invoice
                                    // stands as printed, and the difference is owed back at the counter.
                                    <div className="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                                        <div className="flex justify-between font-bold text-amber-800">
                                            <span>{t('Refund Due')}</span>
                                            <span>{peso(totals.refund_due)}</span>
                                        </div>
                                        <p className="mt-1 text-xs text-amber-700">
                                            {t('An item was cancelled after payment. Please claim this refund from our staff.')}
                                        </p>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="flex justify-between border-t border-dashed border-[#D9CCBA] pt-1.5 font-semibold text-gray-900">
                                <span>{t('Total')}</span>
                                <span className="text-lg font-bold text-[#8A3330]">{peso(totals.payable_total)}</span>
                            </div>
                        )}
                    </div>
                </div>

                <div className="mt-4 flex items-center justify-between gap-3 rounded-xl border border-[#E5DDD0] bg-white px-6 py-4">
                    <div className="flex items-center gap-2">
                        <span className={`h-2.5 w-2.5 rounded-full ${paymentMeta[paymentStatus]?.dot ?? 'bg-gray-400'}`} />
                        <span className="text-sm font-medium text-gray-700">{paymentMeta[paymentStatus]?.label ?? paymentStatus}</span>
                    </div>
                    {hasReceipt && (
                        <a href={initialOrder.receipt_url} data-turbo="false" className="text-sm font-semibold text-[#8A3330] hover:underline">
                            {t('View Receipt')}
                        </a>
                    )}
                </div>

                {initialOrder.notes && (
                    <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{initialOrder.notes}</div>
                )}
            </div>
        </CustomerLayout>
    );
}
