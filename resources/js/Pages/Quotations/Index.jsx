import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatDateTime = (iso) =>
    iso ? new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

export default function Index({ quotations }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('Advance Orders / Quotations')} />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-gray-900">{t('Advance Orders / Quotations')}</h1>
                <Link
                    href={route('quotations.create')}
                    className="inline-flex items-center px-5 py-2.5 bg-[#8A3330] rounded-lg font-semibold text-sm text-white hover:bg-[#742927] transition"
                >
                    {t('New Advance Order')}
                </Link>
            </div>

            {quotations.length === 0 ? (
                <div className="bg-white border border-[#E5DDD0] rounded-xl py-16 text-center">
                    <p className="text-gray-500">{t('No advance orders yet.')}</p>
                </div>
            ) : (
                <>
                    {/* Desktop table */}
                    <div className="hidden sm:block overflow-x-auto bg-white border border-[#E5DDD0] rounded-xl">
                        <table className="min-w-full divide-y divide-[#E5DDD0]">
                            <thead className="bg-[#FAF6EE]">
                                <tr>
                                    {[t('Quotation'), t('Table'), t('Customer'), t('Scheduled For'), t('Subtotal'), t('Status'), '']
                                        .map((label) => (
                                            <th key={label} className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#8A7B9E]">
                                                {label}
                                            </th>
                                        ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#E5DDD0]">
                                {quotations.map((quotation) => (
                                    <tr key={quotation.id} className="hover:bg-[#FAF6EE]/60">
                                        <td className="px-4 py-3 font-mono text-sm font-semibold text-gray-900">{quotation.quotation_number}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{quotation.space_label ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{quotation.customer_name ?? t('Walk-in')}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{formatDateTime(quotation.scheduled_for)}</td>
                                        <td className="px-4 py-3 text-sm font-semibold text-[#8A3330]">{peso(quotation.subtotal)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${quotation.badge_classes}`}>
                                                {quotation.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={route('quotations.show', quotation.id)} className="text-sm font-semibold text-[#8A3330] hover:underline">
                                                {t('View')}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile cards */}
                    <div className="sm:hidden space-y-3">
                        {quotations.map((quotation) => (
                            <Link
                                key={quotation.id}
                                href={route('quotations.show', quotation.id)}
                                className="block bg-white border border-[#E5DDD0] rounded-xl p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-mono text-sm font-semibold text-gray-900">{quotation.quotation_number}</span>
                                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${quotation.badge_classes}`}>
                                        {quotation.status_label}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-gray-700">{quotation.space_label ?? '—'} · {quotation.customer_name ?? t('Walk-in')}</p>
                                <div className="mt-2 flex items-center justify-between text-sm">
                                    <span className="text-gray-500">{formatDateTime(quotation.scheduled_for)}</span>
                                    <span className="font-bold text-[#8A3330]">{peso(quotation.subtotal)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}
