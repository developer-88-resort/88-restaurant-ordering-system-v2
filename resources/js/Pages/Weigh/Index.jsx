import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

/**
 * /weigh — the landing page the sidebar's "Weigh & Order" link opens.
 *
 * Deliberately not the wizard itself: a staff member glancing at this page
 * mid-shift should see the day's numbers (how many lines are still
 * waiting on a customer, how much has moved through the scale today)
 * before committing to the six-step flow. ?filter=pending drills into the
 * concrete list, so a future Dashboard card can link straight to it.
 */
export default function Index({ stats, filter, pendingItems }) {
    const t = useTranslation();

    return (
        <AuthenticatedLayout>
            <Head title={t('Weigh & Order')} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-2xl font-bold text-gray-900">{t('Weigh & Order')}</h1>
                <Link
                    href={route('weigh.wizard')}
                    className="inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-[#8A3330] text-white text-base font-bold uppercase tracking-widest shadow-sm hover:bg-[#742927] transition active:scale-[.98]"
                >
                    {t('Start Weighing')}
                </Link>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 max-w-3xl">
                <Link
                    href={route('weigh.station', { filter: 'pending' })}
                    className={`block bg-white border rounded-xl p-6 transition hover:border-[#8A3330] hover:shadow-md ${
                        filter === 'pending' ? 'border-[#8A3330] ring-2 ring-[#8A3330]/20' : 'border-[#E5DDD0]'
                    }`}
                >
                    <p className="text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Awaiting customer confirmation')}</p>
                    <p className="mt-2 text-4xl font-bold text-[#8A3330]">{stats.pendingConfirmationCount}</p>
                    <p className="mt-1 text-sm text-gray-500">{t('Lines weighed but not yet accepted')}</p>
                </Link>

                <div className="bg-white border border-[#E5DDD0] rounded-xl p-6">
                    <p className="text-xs font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Weighed today')}</p>
                    <p className="mt-2 text-4xl font-bold text-gray-900">{stats.weighedTodayKg} <span className="text-lg font-semibold text-gray-400">{t('kg')}</span></p>
                    <p className="mt-1 text-sm text-gray-500">
                        ₱{Number(stats.weighedTodayAmount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </p>
                </div>
            </div>

            {filter === 'pending' && (
                <div className="mt-8 max-w-3xl">
                    <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-[#8A7B9E]">{t('Waiting for the customer')}</h2>

                    {pendingItems.length === 0 ? (
                        <p className="text-sm text-gray-500">{t('Nothing is waiting on a customer right now.')}</p>
                    ) : (
                        <div className="bg-white border border-[#E5DDD0] rounded-xl divide-y divide-[#E5DDD0]">
                            {pendingItems.map((item) => (
                                // A plain <a>, not Inertia's <Link>: orders.show is a
                                // Blade-rendered page, a different render stack from this
                                // one, so this needs a real browser navigation rather than
                                // an Inertia XHR visit that expects an Inertia response back.
                                <a
                                    key={item.id}
                                    href={route('orders.show', item.order_id)}
                                    className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-[#FAF6EE]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-gray-900">{item.item_name}</p>
                                        <p className="text-xs text-gray-500">
                                            {item.detail} · {item.order_number} {item.table ? `· ${item.table}` : ''}
                                        </p>
                                    </div>
                                    <p className="shrink-0 text-xs text-gray-400">
                                        {item.weighed_by} · {item.weighed_at}
                                    </p>
                                </a>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
