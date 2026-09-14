import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

const peso = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDateTime = (iso) => iso ? new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

const ArrowIcon = ({ className = 'h-4 w-4' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
);

export default function Show({ quotation }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={quotation.quotation_number} />
            <div className="mx-auto w-full max-w-[1300px]">
                <Link href={route('quotations.index')} className="mb-4 inline-flex items-center gap-2 text-xs font-bold text-[#8A7B74] transition hover:text-[#8A3330]">
                    <ArrowIcon className="h-3.5 w-3.5 rotate-180" />{t('Back to Quotations')}
                </Link>

                <section className="relative isolate overflow-hidden rounded-[1.75rem] bg-[#241917] px-6 py-7 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.9)] sm:px-8">
                    <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl" />
                    <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#E7BBB1]">{t('Quotation record')}</p><h1 className="mt-1 font-mono text-2xl font-bold text-white">{quotation.quotation_number}</h1><p className="mt-1.5 text-sm text-white/55">{quotation.space_label ?? '—'} · {quotation.customer_name || t('Walk-in')}</p></div>
                        <span className={`inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wide ${quotation.badge_classes}`}>{quotation.status_label}</span>
                    </div>
                </section>

                <div className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
                    <section className="overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)]">
                        <div className="border-b border-[#EEE5DC] px-5 py-4 sm:px-6"><h2 className="text-sm font-bold text-[#251C19]">{t('Quoted items')}</h2><p className="mt-0.5 text-xs text-[#8A7B74]">{quotation.items.length} {t('line item(s)')}</p></div>
                        <div className="divide-y divide-[#EEE5DC]">
                            {quotation.items.map((item, index) => (
                                <div key={item.id} className="flex items-start gap-3 px-5 py-4 sm:px-6">
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#F7F0E3] text-xs font-bold text-[#8A3330]">{index + 1}</span>
                                    <div className="min-w-0 flex-1"><div className="flex justify-between gap-3"><p className="text-sm font-bold text-[#251C19]">{item.quantity}× {item.item_name}</p><p className="shrink-0 text-sm font-bold text-[#8A3330]">{peso(item.subtotal)}</p></div><p className="mt-1 text-xs text-[#8A7B74]">{peso(item.unit_price)} {t('each')}</p>{item.notes && <p className="mt-2 rounded-lg bg-[#FCF8F1] px-3 py-2 text-xs text-[#7A6D66]">{t('Note')}: {item.notes}</p>}</div>
                                </div>
                            ))}
                        </div>
                        <div className="flex items-center justify-between border-t border-[#D9CCBA] bg-[#FCF8F1] px-5 py-5 sm:px-6"><span className="text-sm font-bold text-[#5F534E]">{t('Quotation subtotal')}</span><span className="text-2xl font-bold text-[#8A3330]">{peso(quotation.subtotal)}</span></div>
                    </section>

                    <aside className="space-y-5">
                        <section className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)] sm:p-6">
                            <h2 className="text-sm font-bold text-[#251C19]">{t('Order details')}</h2>
                            <dl className="mt-4 space-y-3 text-sm">
                                <Row label={t('Customer')} value={quotation.customer_name || t('Walk-in')} />
                                {quotation.customer_contact && <Row label={t('Contact')} value={quotation.customer_contact} />}
                                <Row label={t('Scheduled for')} value={formatDateTime(quotation.scheduled_for)} highlight />
                                <Row label={t('Location')} value={quotation.space_label ?? '—'} />
                                <Row label={t('Created by')} value={quotation.created_by || '—'} />
                                <Row label={t('Created at')} value={formatDateTime(quotation.created_at)} />
                            </dl>
                            {quotation.notes && <div className="mt-4 border-t border-[#EEE5DC] pt-4"><p className="text-[10px] font-bold uppercase tracking-wider text-[#A59891]">{t('Kitchen notes')}</p><p className="mt-1.5 text-sm leading-6 text-[#5F534E]">{quotation.notes}</p></div>}
                        </section>

                        <section className="rounded-2xl border border-[#D9CCBA] bg-[#FCF8F1] p-5">
                            {quotation.converted_order ? (
                                <><p className="text-xs text-[#8A7B74]">{t('This quotation has been attached to receipt')}</p><p className="mt-1 font-mono text-base font-bold text-[#251C19]">{quotation.converted_order.order_number}</p><a href={route('orders.show', quotation.converted_order.id)} className="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#742927]">{t('View Receipt')}<ArrowIcon /></a></>
                            ) : <p className="text-sm text-[#8A7B74]">{t('This quotation was cancelled.')}</p>}
                        </section>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value, highlight = false }) {
    return <div className="flex justify-between gap-4 border-b border-[#F1E9E0] pb-2.5 last:border-0 last:pb-0"><dt className="shrink-0 text-[#8A7B74]">{label}</dt><dd className={`text-right font-semibold ${highlight ? 'text-[#8A3330]' : 'text-[#251C19]'}`}>{value}</dd></div>;
}
