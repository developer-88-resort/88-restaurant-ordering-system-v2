import StatCard from '@/Components/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Area, AreaChart, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts';

/**
 * Smoothly tweens a stat from its previous value to the next one instead of
 * hard-cutting — starts from 0 on first mount, then from whatever the last
 * settled value was on every later change (e.g. the Echo-driven partial
 * reload bringing in a fresh number), so the dashboard never "jumps".
 */
function useCountUp(target, duration = 1100) {
    const [value, setValue] = useState(0);
    const fromRef = useRef(0);
    const frameRef = useRef(null);

    useEffect(() => {
        const from = fromRef.current;
        const to = target;

        if (from === to) return undefined;

        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - (1 - progress) ** 3;
            setValue(from + (to - from) * eased);

            if (progress < 1) {
                frameRef.current = requestAnimationFrame(tick);
            } else {
                fromRef.current = to;
            }
        };

        frameRef.current = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frameRef.current);
    }, [target, duration]);

    return value;
}

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
    space: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" />
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
    admin: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
        </svg>
    ),
    staff: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    ),
    home: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.6" stroke="currentColor" className="h-7 w-7">
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0l8.955 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
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
        <div className="mb-3 flex items-center gap-2.5">
            <span className="h-5 w-1 rounded-full bg-[#8A3330]" />
            <h2 className="text-xs font-bold uppercase tracking-[0.16em] text-[#8A7B74]">{children}</h2>
        </div>
    );
}

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

    const animatedSales = useCountUp(todaysSales);
    const animatedActive = useCountUp(activeOrders);
    const animatedPending = useCountUp(pendingOrders);
    const animatedOccupied = useCountUp(occupiedSpaces);
    const animatedUnpaid = useCountUp(unpaidOrders);
    const animatedAdmin = useCountUp(adminCount);
    const animatedStaff = useCountUp(staffCount);

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

            {/* Header */}
            <section className="relative isolate mb-7 overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.7) 1px, transparent 1px)',
                        backgroundSize: '28px 28px',
                    }}
                />
                <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl" />
                <div aria-hidden="true" className="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-white/5 blur-3xl" />

                <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4 sm:gap-5">
                        <div className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                            {icons.home}
                        </div>
                        <div>
                            <h1 className="text-xl font-bold tracking-[-0.025em] text-white sm:text-2xl">
                                {t('Welcome back, :name').replace(':name', auth.user.name)}
                            </h1>
                            <p className="mt-1.5 text-sm leading-6 text-white/55">{t("Overview of today's restaurant operations.")}</p>
                        </div>
                    </div>

                    <span className="inline-flex w-fit items-center gap-1.5 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white/80 backdrop-blur-sm">
                        {new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                    </span>
                </div>
            </section>

            {/* Today's operations */}
            <SectionLabel>{t("Today's Operations")}</SectionLabel>
            <div className="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={icons.sales}
                    label={t("Today's Sales")}
                    value={formatPeso(animatedSales)}
                    footnote={t('Paid orders only')}
                    trend={salesDeltaPercent}
                    accent="green"
                />
                <StatCard icon={icons.active} label={t('Active Orders')} value={Math.round(animatedActive)} footnote={t('In progress right now')} accent="blue" />
                <StatCard icon={icons.pending} label={t('Pending Orders')} value={Math.round(animatedPending)} footnote={t('Awaiting kitchen')} accent="amber" />
                <StatCard
                    icon={icons.space}
                    label={t('Space Occupancy')}
                    value={
                        <>
                            {Math.round(animatedOccupied)} <span className="text-base font-normal text-[#B0A49E]">/ {totalSpaces}</span>
                        </>
                    }
                    footnote={t('Spaces occupied')}
                    accent="purple"
                />
            </div>

            {/* Business snapshot */}
            <SectionLabel>{t('Business Snapshot')}</SectionLabel>
            <div className="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard icon={icons.unpaid} label={t('Unpaid Orders')} value={Math.round(animatedUnpaid)} footnote={t('Needs collection')} accent="rose" />
                <StatCard
                    icon={icons.popular}
                    label={t('Popular This Week')}
                    value={
                        <span className="text-lg">
                            {popularThisWeek ? `${popularThisWeek.category_name ?? t('Uncategorized')} — ${popularThisWeek.item_name}` : '—'}
                        </span>
                    }
                    footnote={popularThisWeek ? t(':qty sold').replace(':qty', popularThisWeek.total_qty) : t('No sales yet')}
                    accent="amber"
                />
                <StatCard icon={icons.admin} label={t('Admin Accounts')} value={Math.round(animatedAdmin)} footnote={t('With portal access')} accent="slate" />
                <StatCard icon={icons.staff} label={t('Staff Accounts')} value={Math.round(animatedStaff)} footnote={t('With portal access')} accent="slate" />
            </div>

            {/* Charts */}
            <div className="mb-7 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-6 lg:col-span-2">
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-4.5 w-4.5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                        </span>
                        <div>
                            <h3 className="text-sm font-bold text-[#251C19]">{t('Sales — Last 7 Days')}</h3>
                            <p className="text-xs text-[#9A8B84]">{t('Paid orders only')}</p>
                        </div>
                    </div>
                    <div className="mt-4 h-56">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={salesTrend} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="salesFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#8A3330" stopOpacity={0.35} />
                                        <stop offset="100%" stopColor="#8A3330" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <XAxis dataKey="label" tick={{ fontSize: 12, fill: '#8A7B6D' }} axisLine={false} tickLine={false} />
                                <Tooltip formatter={(value) => formatPeso(value)} labelStyle={{ color: '#333' }} contentStyle={{ borderRadius: 12, borderColor: '#E5DDD0' }} />
                                <Area type="monotone" dataKey="total" stroke="#8A3330" strokeWidth={2.5} fill="url(#salesFill)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)] sm:p-6">
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-4.5 w-4.5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                            </svg>
                        </span>
                        <div>
                            <h3 className="text-sm font-bold text-[#251C19]">{t('Orders In Progress')}</h3>
                            <p className="text-xs text-[#9A8B84]">{t('By status, right now')}</p>
                        </div>
                    </div>

                    {orderStatusBreakdown.every((s) => s.count === 0) ? (
                        <div className="flex h-56 items-center justify-center text-sm text-[#B0A49E]">{t('No orders in progress')}</div>
                    ) : (
                        <div className="mt-2 h-56">
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
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1.5">
                        {orderStatusBreakdown.map((entry) => (
                            <span key={entry.status} className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#7A6D66]">
                                <span className={`h-2 w-2 rounded-full ${entry.color}`} />
                                {entry.label} ({entry.count})
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            {/* Recent orders */}
            <div className="overflow-hidden rounded-[1.75rem] border border-[#E5DDD0] bg-white shadow-[0_22px_60px_-48px_rgba(55,35,30,0.7)]">
                <div className="flex items-center justify-between border-b border-[#EEE5DC] px-6 py-4">
                    <div>
                        <h3 className="text-sm font-bold text-[#251C19]">{t('Recent Orders')}</h3>
                        <p className="mt-0.5 text-xs text-[#9A8B84]">{t('Latest activity across all locations')}</p>
                    </div>
                    <a href={route('orders.index')} className="whitespace-nowrap text-sm font-bold text-[#8A3330] hover:text-[#5f2120]">
                        {t('View All')} &rarr;
                    </a>
                </div>

                {recentOrders.length === 0 ? (
                    <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                        <div className="mb-4 grid h-14 w-14 place-items-center rounded-2xl border border-[#8A3330]/10 bg-[#F3E1DC]">
                            {icons.receipt}
                        </div>
                        <h3 className="text-base font-bold text-[#251C19]">{t('No orders yet')}</h3>
                        <p className="mt-1 max-w-sm text-sm text-[#8B7D75]">{t('Orders placed by your team will show up here as they come in.')}</p>
                        <a
                            href={route('orders.create')}
                            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#8A3330] px-4 py-2.5 text-sm font-bold text-white shadow-[0_12px_24px_-16px_rgba(138,51,48,0.9)] transition hover:-translate-y-0.5 hover:bg-[#742927]"
                        >
                            {t('New Order')}
                        </a>
                    </div>
                ) : (
                    <>
                        {/* Desktop table */}
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="min-w-full divide-y divide-[#EEE5DC]">
                                <thead className="bg-[#FAF6EE]">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t('Order')}</th>
                                        <th className="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t('Location')}</th>
                                        <th className="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t('Placed')}</th>
                                        <th className="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t('Status')}</th>
                                        <th className="px-6 py-3 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t('Total')}</th>
                                        <th className="px-6 py-3 text-right text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#EEE5DC]">
                                    {recentOrders.map((order) => (
                                        <tr key={order.id} className="transition hover:bg-[#FAF6EE]">
                                            <td className="px-6 py-3.5 font-mono text-sm font-bold text-[#251C19]">{order.order_number}</td>
                                            <td className="px-6 py-3.5 text-sm text-[#6C5E57]">{order.location_label}</td>
                                            <td className="px-6 py-3.5 text-sm text-[#9A8B84]">{order.placed_human}</td>
                                            <td className="px-6 py-3.5">
                                                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold leading-5 ${order.status_badge_classes}`}>
                                                    {order.status_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3.5 text-right text-sm font-bold text-[#251C19]">{formatPeso(order.total_amount)}</td>
                                            <td className="px-6 py-3.5 text-right text-sm">
                                                <a href={order.show_url} className="font-bold text-[#8A3330] hover:text-[#5f2120]">
                                                    {t('View')}
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile cards */}
                        <div className="divide-y divide-[#EEE5DC] sm:hidden">
                            {recentOrders.map((order) => (
                                <a key={order.id} href={order.show_url} className="block px-4 py-3.5 transition hover:bg-[#FAF6EE]">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-mono text-sm font-bold text-[#251C19]">{order.order_number}</p>
                                        <span className={`inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold leading-5 ${order.status_badge_classes}`}>
                                            {order.status_label}
                                        </span>
                                    </div>
                                    <div className="mt-1 flex items-center justify-between">
                                        <span className="text-xs text-[#9A8B84]">{order.location_label}</span>
                                        <span className="text-sm font-bold text-[#251C19]">{formatPeso(order.total_amount)}</span>
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
