import StatCard from '@/Components/StatCard';
import SalesChart from '@/Components/SalesChart';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';

// Massage Overview: the restaurant Overview (Pages/Superadmin/Dashboard.jsx)
// laid out the same way, with the Massage department's own numbers.

function formatPeso(amount) {
    return `₱${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const icons = {
    sales: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    ),
    active: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
        </svg>
    ),
    pending: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    ),
    room: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
        </svg>
    ),
    unpaid: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    ),
    popular: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
        </svg>
    ),
    services: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    ),
    cash: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
        </svg>
    ),
    receipt: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="#8A3330" className="h-7 w-7">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25h10.176a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H15M9 3.75c0 1.036.84 1.875 1.875 1.875h2.25c1.036 0 1.875-.84 1.875-1.875M9 3.75c0-1.036.84-1.875 1.875-1.875h2.25c1.036 0 1.875.84 1.875 1.875M9 12h6m-6 3.75h6" />
        </svg>
    ),
};

function SectionLabel({ children }) {
    return (
        <div className="mb-4 flex items-center gap-2.5">
            <h2 className="text-sm font-semibold text-slate-700">{children}</h2>
        </div>
    );
}

export default function Dashboard({
    todaysSales,
    salesDeltaPercent,
    salesTrend,
    unpaidOrders,
    unpaidAmount,
    ordersToday,
    roomChargesToday,
    byMethod,
    popularThisWeek,
    servicesAvailable,
    servicesTotal,
    recentOrders,
    auth,
}) {
    const t = useTranslation();
    const collectedToday = byMethod.filter((row) => row.method !== 'room_charge').reduce((sum, row) => sum + row.total, 0);
    const hasPayments = byMethod.some((row) => row.count > 0);

    return (
        <AuthenticatedLayout>
            <Head title={t('Massage Overview')} />

            <section className="mb-8 flex flex-wrap items-center justify-between gap-5">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{t('Welcome back, :name').replace(':name', auth.user.name)}</h1>
                    <p className="mt-2 text-sm text-slate-500">{t("Overview of today's massage operations.")}</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <span className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-medium text-slate-600">
                        {new Date().toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}
                    </span>
                </div>
            </section>

            <SectionLabel>{t("Today's Operations")}</SectionLabel>
            <div className="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard icon={icons.sales} label={t("Today's Sales")} value={formatPeso(todaysSales)} footnote={t('Paid orders only')} trend={salesDeltaPercent} accent="green" />
                <StatCard icon={icons.active} label={t("Today's Massages")} value={ordersToday} footnote={t('Massage orders taken today')} accent="blue" />
                <StatCard icon={icons.pending} label={t('Unpaid Massages')} value={unpaidOrders} footnote={t('Waiting for payment')} accent="amber" />
                <StatCard icon={icons.room} label={t('Room Charges')} value={formatPeso(roomChargesToday)} footnote={t('Charged to rooms today')} accent="purple" />
            </div>

            <SectionLabel>{t('Business Snapshot')}</SectionLabel>
            <section aria-label={t('Business Snapshot')} className="mb-7 grid grid-cols-1 overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
                {[
                    { icon: icons.unpaid, label: t('Unpaid Massages'), value: formatPeso(unpaidAmount), note: t('Needs collection') },
                    { icon: icons.popular, label: t('Popular This Week'), value: popularThisWeek ? popularThisWeek.name : '—', note: popularThisWeek ? t(':qty sold').replace(':qty', popularThisWeek.qty) : t('No sales yet') },
                    { icon: icons.services, label: t('Massage Services'), value: `${servicesAvailable} / ${servicesTotal}`, note: t('Available for new orders') },
                    { icon: icons.cash, label: t('Collected today'), value: formatPeso(collectedToday), note: t('Room charges not included') },
                ].map((item) => (
                    <div key={item.label} className="flex min-w-0 items-center gap-3 border-b border-slate-100 p-5 sm:border-r xl:border-b-0 last:border-0">
                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-50 text-[#8A3330]">{item.icon}</span>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-slate-500">{item.label}</p>
                            <p className="mt-1 truncate text-lg font-semibold text-slate-800" title={String(item.value)}>{item.value}</p>
                            <p className="mt-0.5 text-xs text-slate-500">{item.note}</p>
                        </div>
                    </div>
                ))}
            </section>

            <div className="mb-7 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <SalesChart salesTrend={salesTrend} />

                <div className="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">{icons.cash}</span>
                        <div>
                            <h3 className="text-sm font-bold text-[#251C19]">{t('Payments Today')}</h3>
                            <p className="text-xs text-slate-500">{t('By mode of payment')}</p>
                        </div>
                    </div>

                    {!hasPayments ? (
                        <div className="flex min-h-[240px] flex-1 items-center justify-center text-sm text-slate-500">{t('No payments yet today.')}</div>
                    ) : (
                        <ul className="my-4 flex-1 space-y-3">
                            {byMethod.filter((row) => row.count > 0).map((row) => (
                                <li key={row.method} className="flex items-center justify-between gap-3 text-sm">
                                    <span className={`font-semibold ${row.method === 'room_charge' ? 'text-amber-700' : 'text-slate-700'}`}>
                                        {row.label} <span className="font-normal text-slate-400">· {row.count}</span>
                                    </span>
                                    <span className="font-semibold tabular-nums text-slate-900">{formatPeso(row.total)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="flex items-center justify-between border-t border-slate-100 pt-5 text-xs text-slate-600">
                        <span>{t('Total today')}</span>
                        <span className="font-semibold tabular-nums text-slate-800">{formatPeso(collectedToday + roomChargesToday)}</span>
                    </div>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 className="text-sm font-bold text-[#251C19]">{t('Recent Massage Orders')}</h3>
                        <p className="mt-0.5 text-xs text-slate-500">{t('Latest massage orders')}</p>
                    </div>
                    <a href={route('massage.orders.index')} className="whitespace-nowrap text-sm font-bold text-[#8A3330] hover:text-[#5f2120]">
                        {t('View All')} &rarr;
                    </a>
                </div>

                {recentOrders.length === 0 ? (
                    <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                        <div className="mb-4 grid h-14 w-14 place-items-center rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC]">{icons.receipt}</div>
                        <h3 className="text-base font-bold text-[#251C19]">{t('No massage orders yet.')}</h3>
                        <p className="mt-1 max-w-sm text-sm text-[#8B7D75]">{t('Massage orders taken by your team will show up here as they come in.')}</p>
                        <a
                            href={route('massage.orders.create')}
                            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-4 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927]"
                        >
                            {t('New Massage Order')}
                        </a>
                    </div>
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="min-w-full divide-y divide-slate-100">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Massage Order')}</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Room / Guest')}</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Placed')}</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Status')}</th>
                                        <th scope="col" className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Total')}</th>
                                        <th scope="col" className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recentOrders.map((order) => (
                                        <tr key={order.id} className="transition-colors duration-150 hover:bg-slate-50">
                                            <td className="px-6 py-3.5 text-sm font-semibold text-slate-800">{order.order_number}</td>
                                            <td className="px-6 py-3.5 text-sm text-[#6C5E57]">{order.location_label}</td>
                                            <td className="px-6 py-3.5 text-sm text-slate-500">{order.placed_human}</td>
                                            <td className="px-6 py-3.5">
                                                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold leading-5 ${order.status_badge_classes}`}>{order.status_label}</span>
                                            </td>
                                            <td className="px-6 py-3.5 text-right text-sm font-semibold tabular-nums text-slate-800">{formatPeso(order.total_amount)}</td>
                                            <td className="px-6 py-3.5 text-right text-sm">
                                                <a href={order.show_url} className="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                                                    {t('View')}
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="divide-y divide-slate-100 sm:hidden">
                            {recentOrders.map((order) => (
                                <a key={order.id} href={order.show_url} className="block px-4 py-3.5 transition-colors duration-150 hover:bg-slate-50">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-sm font-semibold text-slate-800">{order.order_number}</p>
                                        <span className={`inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold leading-5 ${order.status_badge_classes}`}>{order.status_label}</span>
                                    </div>
                                    <div className="mt-1 flex items-center justify-between">
                                        <span className="text-xs text-slate-500">{order.location_label}</span>
                                        <span className="text-sm font-semibold tabular-nums text-slate-800">{formatPeso(order.total_amount)}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-[#B0A49E]">{order.placed_human}</p>
                                </a>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
