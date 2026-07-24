import StatCard from '@/Components/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Area, AreaChart, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts';

const LIVE_STAT_KEYS = [
    'todaysSales',
    'yesterdaysSales',
    'salesDeltaPercent',
    'salesTrend',
    'orderStatusBreakdown',
    'activeOrders',
    'pendingOrders',
    'unpaidOrders',
    'popularThisWeek',
    'totalSpaces',
    'occupiedSpaces',
    'recentOrders',
    'adminCount',
    'staffCount',
];

const statusDotToHex = {
    'bg-amber-500': '#f59e0b',
    'bg-blue-500': '#3b82f6',
    'bg-purple-500': '#a855f7',
    'bg-teal-500': '#14b8a6',
};

function formatPeso(amount) {
    return `₱${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const icons = {
    sales: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    ),
    active: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
        </svg>
    ),
    pending: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    ),
    space: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" />
        </svg>
    ),
    unpaid: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    ),
    popular: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
        </svg>
    ),
    admin: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
        </svg>
    ),
    staff: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    ),
};

export default function Dashboard({
    todaysSales,
    salesDeltaPercent,
    salesTrend,
    orderStatusBreakdown,
    activeOrders,
    pendingOrders,
    unpaidOrders,
    popularThisWeek,
    totalSpaces,
    occupiedSpaces,
    recentOrders,
    adminCount,
    staffCount,
    auth,
}) {
    const t = useTranslation();

    useEffect(() => {
        // A lightweight "ping" event — the actual numbers are always
        // recomputed server-side via a partial reload, so the payload here
        // carries no data and there's no risk of the two ever drifting apart.
        window.Echo.private('dashboard-stats').listen('.DashboardStatsChanged', () => {
            router.reload({ only: LIVE_STAT_KEYS });
        });

        const leave = () => window.Echo.leave('dashboard-stats');

        // See the same note in AuthenticatedLayout.jsx — Turbo Drive can
        // navigate away from this page without unmounting React.
        turboCleanup(leave);

        return leave;
    }, []);

    return (
        <AuthenticatedLayout>
            <Head title={t('Dashboard')} />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        {t('Welcome back, :name').replace(':name', auth.user.name)}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">{t("Overview of today's restaurant operations.")}</p>
                </div>
                <span className="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-[#F3E1DC] text-[#8A3330]">
                    {new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                </span>
            </div>

            {/* Today's operations */}
            <h2 className="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{t("Today's Operations")}</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    icon={icons.sales}
                    label={t("Today's Sales")}
                    value={formatPeso(todaysSales)}
                    footnote={t('Paid orders only')}
                    trend={salesDeltaPercent}
                    delay={0}
                />
                <StatCard icon={icons.active} label={t('Active Orders')} value={activeOrders} footnote={t('In progress right now')} delay={80} />
                <StatCard icon={icons.pending} label={t('Pending Orders')} value={pendingOrders} footnote={t('Awaiting kitchen')} delay={160} />
                <StatCard
                    icon={icons.space}
                    label={t('Space Occupancy')}
                    value={
                        <>
                            {occupiedSpaces} <span className="text-base font-normal text-gray-400">/ {totalSpaces}</span>
                        </>
                    }
                    footnote={t('Spaces occupied')}
                    delay={240}
                />
            </div>

            {/* Business snapshot */}
            <h2 className="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{t('Business Snapshot')}</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard icon={icons.unpaid} label={t('Unpaid Orders')} value={unpaidOrders} footnote={t('Needs collection')} delay={320} />
                <StatCard
                    icon={icons.popular}
                    label={t('Popular This Week')}
                    value={
                        <span className="text-lg">
                            {popularThisWeek ? `${popularThisWeek.category_name ?? t('Uncategorized')} — ${popularThisWeek.item_name}` : '—'}
                        </span>
                    }
                    footnote={popularThisWeek ? t(':qty sold').replace(':qty', popularThisWeek.total_qty) : t('No sales yet')}
                    delay={400}
                />
                <StatCard icon={icons.admin} label={t('Admin Accounts')} value={adminCount} footnote={t('With portal access')} delay={480} />
                <StatCard icon={icons.staff} label={t('Staff Accounts')} value={staffCount} footnote={t('With portal access')} delay={560} />
            </div>

            {/* Charts */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <div className="lg:col-span-2 bg-white border border-[#E5DDD0] rounded-xl p-5 shadow-sm animate-fade-slide-up" style={{ animationDelay: '640ms' }}>
                    <h3 className="font-semibold text-gray-900 mb-1">{t('Sales — Last 7 Days')}</h3>
                    <p className="text-xs text-gray-400 mb-4">{t('Paid orders only')}</p>
                    <div className="h-56">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={salesTrend} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="salesFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#8A3330" stopOpacity={0.35} />
                                        <stop offset="100%" stopColor="#8A3330" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <XAxis dataKey="label" tick={{ fontSize: 12, fill: '#8A7B6D' }} axisLine={false} tickLine={false} />
                                <Tooltip formatter={(value) => formatPeso(value)} labelStyle={{ color: '#333' }} contentStyle={{ borderRadius: 8, borderColor: '#E5DDD0' }} />
                                <Area type="monotone" dataKey="total" stroke="#8A3330" strokeWidth={2} fill="url(#salesFill)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="bg-white border border-[#E5DDD0] rounded-xl p-5 shadow-sm animate-fade-slide-up" style={{ animationDelay: '720ms' }}>
                    <h3 className="font-semibold text-gray-900 mb-1">{t('Orders In Progress')}</h3>
                    <p className="text-xs text-gray-400 mb-4">{t('By status, right now')}</p>
                    {orderStatusBreakdown.every((s) => s.count === 0) ? (
                        <div className="h-56 flex items-center justify-center text-sm text-gray-400">{t('No orders in progress')}</div>
                    ) : (
                        <div className="h-56">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={orderStatusBreakdown}
                                        dataKey="count"
                                        nameKey="label"
                                        innerRadius={45}
                                        outerRadius={75}
                                        paddingAngle={2}
                                    >
                                        {orderStatusBreakdown.map((entry) => (
                                            <Cell key={entry.status} fill={statusDotToHex[entry.color] ?? '#8A3330'} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                    <div className="flex flex-wrap gap-x-4 gap-y-1.5 mt-2">
                        {orderStatusBreakdown.map((entry) => (
                            <span key={entry.status} className="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                <span className={`h-2 w-2 rounded-full ${entry.color}`} />
                                {entry.label} ({entry.count})
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            {/* Recent orders */}
            <div className="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden shadow-sm animate-fade-slide-up" style={{ animationDelay: '800ms' }}>
                <div className="px-6 py-4 border-b border-[#E5DDD0] flex items-center justify-between">
                    <div>
                        <h3 className="font-semibold text-gray-900">{t('Recent Orders')}</h3>
                        <p className="text-xs text-gray-400 mt-0.5">{t('Latest activity across all locations')}</p>
                    </div>
                    <a href={route('orders.index')} className="text-sm text-[#8A3330] hover:text-[#5f2120] font-medium whitespace-nowrap">
                        {t('View All')} &rarr;
                    </a>
                </div>

                {recentOrders.length === 0 ? (
                    <div className="py-16 px-6 flex flex-col items-center justify-center text-center">
                        <div className="h-14 w-14 rounded-full bg-[#F3E1DC] flex items-center justify-center mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="#8A3330" className="h-7 w-7">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25h10.176a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H15M9 3.75c0 1.036.84 1.875 1.875 1.875h2.25c1.036 0 1.875-.84 1.875-1.875M9 3.75c0-1.036.84-1.875 1.875-1.875h2.25c1.036 0 1.875.84 1.875 1.875M9 12h6m-6 3.75h6" />
                            </svg>
                        </div>
                        <h3 className="text-base font-semibold text-gray-900">{t('No orders yet')}</h3>
                        <p className="mt-1 text-sm text-gray-500 max-w-sm">{t('Orders placed by your team will show up here as they come in.')}</p>
                        <a href={route('orders.create')} className="mt-6 inline-flex items-center px-4 py-2 bg-[#8A3330] hover:bg-[#742927] text-white text-sm font-semibold rounded-lg transition">
                            {t('New Order')}
                        </a>
                    </div>
                ) : (
                    <>
                        {/* Desktop table */}
                        <div className="hidden sm:block overflow-x-auto">
                            <table className="min-w-full divide-y divide-[#E5DDD0]">
                                <thead className="bg-[#FAF6EE]">
                                    <tr>
                                        <th className="px-6 py-2.5 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Order')}</th>
                                        <th className="px-6 py-2.5 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Location')}</th>
                                        <th className="px-6 py-2.5 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Placed')}</th>
                                        <th className="px-6 py-2.5 text-left text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Status')}</th>
                                        <th className="px-6 py-2.5 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Total')}</th>
                                        <th className="px-6 py-2.5 text-right text-xs font-semibold text-[#8A7B9E] uppercase tracking-wider">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#E5DDD0]">
                                    {recentOrders.map((order) => (
                                        <tr key={order.id} className="hover:bg-[#FAF6EE] transition-colors">
                                            <td className="px-6 py-3 text-sm font-mono font-medium text-gray-900">{order.order_number}</td>
                                            <td className="px-6 py-3 text-sm text-gray-600">{order.location_label}</td>
                                            <td className="px-6 py-3 text-sm text-gray-500">{order.placed_human}</td>
                                            <td className="px-6 py-3">
                                                <span className={`inline-flex px-2.5 py-0.5 text-xs font-semibold leading-5 rounded-full ${order.status_badge_classes}`}>
                                                    {order.status_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3 text-sm font-semibold text-gray-900 text-right">{formatPeso(order.total_amount)}</td>
                                            <td className="px-6 py-3 text-right text-sm">
                                                <a href={order.show_url} className="text-[#8A3330] hover:text-[#5f2120] font-medium">
                                                    {t('View')}
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile cards */}
                        <div className="sm:hidden divide-y divide-[#E5DDD0]">
                            {recentOrders.map((order) => (
                                <a key={order.id} href={order.show_url} className="block px-4 py-3 hover:bg-[#FAF6EE]">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-sm font-mono font-medium text-gray-900">{order.order_number}</p>
                                        <span className={`inline-flex px-2.5 py-0.5 text-xs font-semibold leading-5 rounded-full shrink-0 ${order.status_badge_classes}`}>
                                            {order.status_label}
                                        </span>
                                    </div>
                                    <div className="mt-1 flex items-center justify-between">
                                        <span className="text-xs text-gray-500">{order.location_label}</span>
                                        <span className="text-sm font-semibold text-gray-900">{formatPeso(order.total_amount)}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-gray-400">{order.placed_human}</p>
                                </a>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
