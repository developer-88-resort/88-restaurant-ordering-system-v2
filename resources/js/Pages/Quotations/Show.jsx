import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatDateTime = (iso) =>
    iso ? new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

export default function Show({ quotation }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={quotation.quotation_number} />

            <div className="mb-5">
                <Link href={route('quotations.index')} className="text-sm font-medium text-gray-500 hover:text-gray-800">
                    ← {t('Back to Quotations')}
                </Link>
            </div>

            <div className="max-w-2xl bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
                <div className="px-6 py-5 bg-[#FAF6EE] border-b border-[#E5DDD0] flex items-start justify-between gap-3">
                    <div>
                        <p className="font-mono text-lg font-bold text-gray-900">{quotation.quotation_number}</p>
                        <p className="mt-0.5 text-sm text-gray-600">{quotation.space_label ?? '—'}</p>
                    </div>
                    <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${quotation.badge_classes}`}>
                        {quotation.status_label}
                    </span>
                </div>

                <dl className="px-6 py-5 space-y-2 text-sm border-b border-[#E5DDD0]">
                    <Row label={t('Customer name')} value={quotation.customer_name || t('Walk-in')} />
                    {quotation.customer_contact && <Row label={t('Contact number')} value={quotation.customer_contact} />}
                    <Row label={t('Scheduled for')} value={formatDateTime(quotation.scheduled_for)} />
                    <Row label={t('Created by')} value={quotation.created_by || '—'} />
                    <Row label={t('Created at')} value={formatDateTime(quotation.created_at)} />
                    {quotation.notes && <Row label={t('Notes')} value={quotation.notes} />}
                </dl>

                <div className="px-6 py-5 border-b border-[#E5DDD0]">
                    <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Items')}</h2>
                    <ul className="space-y-2">
                        {quotation.items.map((item) => (
                            <li key={item.id} className="flex justify-between gap-3 text-sm">
                                <span className="text-gray-700">
                                    {item.quantity}× {item.item_name}
                                    {item.notes && <span className="block text-xs text-gray-400">{item.notes}</span>}
                                </span>
                                <span className="shrink-0 font-medium text-gray-900">{peso(item.subtotal)}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3 flex justify-between border-t border-dashed border-[#D9CCBA] pt-2 text-sm font-bold text-gray-900">
                        <span>{t('Subtotal')}</span>
                        <span>{peso(quotation.subtotal)}</span>
                    </div>
                </div>

                <div className="px-6 py-4 bg-[#FAF6EE] flex justify-end">
                    {quotation.converted_order ? (
                        // A real page navigation, not an Inertia visit — the order
                        // detail page is a different (Blade) render stack.
                        <a
                            href={route('orders.show', quotation.converted_order.id)}
                            className="inline-flex items-center px-6 py-3 bg-[#8A3330] rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-[#742927] transition"
                        >
                            {t('View Receipt')} — {quotation.converted_order.order_number}
                        </a>
                    ) : (
                        <span className="text-sm text-gray-500">{t('This quotation was cancelled.')}</span>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-gray-500 shrink-0">{label}</dt>
            <dd className="text-right font-medium text-gray-900">{value}</dd>
        </div>
    );
}
