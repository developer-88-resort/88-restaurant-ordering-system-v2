import DateRangeFilter from '@/Components/DateRangeFilter';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const peso = (value) =>
    `₱${Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const STATUS_BADGE = {
    active: 'bg-emerald-50 text-emerald-700',
    pending_confirmation: 'bg-amber-50 text-amber-700',
    rejected: 'bg-rose-50 text-rose-700',
    voided: 'bg-gray-100 text-gray-600',
};

export default function Log({
    filters,
    range,
    selectedMonth,
    selectedDate,
    calendarMonth,
    rangeLabel,
    items,
    weighedByUsers,
    channels,
    statuses,
    totals,
    logs,
}) {
    const t = useTranslation();

    const [localFilters, setLocalFilters] = useState({
        item_id: filters.item_id ?? '',
        weighed_by: filters.weighed_by ?? '',
        channel: filters.channel ?? '',
        status: filters.status ?? '',
        show_voided: !!filters.show_voided,
    });

    const [dateParams, setDateParams] = useState({
        range: !selectedMonth && !selectedDate ? range : undefined,
        month: selectedMonth ?? undefined,
        date: selectedDate ?? undefined,
    });

    const applyFilters = (overrides = {}, dateOverride = null) => {
        const nextFilters = { ...localFilters, ...overrides };
        const nextDate = dateOverride ?? dateParams;
        setLocalFilters(nextFilters);

        router.get(
            route('superadmin.reports.weighed-lines'),
            { ...nextDate, ...nextFilters },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const changeDateRange = (params) => {
        const nextDate = { range: undefined, month: undefined, date: undefined, ...params };
        setDateParams(nextDate);
        applyFilters({}, nextDate);
    };

    const exportParams = { ...dateParams, ...localFilters };

    return (
        <AuthenticatedLayout>
            <Head title={t('Weighed Lines')} />

            <div className="mb-4 inline-flex rounded-xl border border-[#E5DDD0] bg-white p-1">
                {/* A real browser navigation, not an Inertia visit: Reports'
                    Overview tab is still a Blade-rendered page, a different
                    render stack from this one. */}
                <a
                    href={route('superadmin.reports.index', dateParams)}
                    className="rounded-lg px-4 py-2 text-sm font-semibold text-[#6C5E57] transition hover:bg-[#FAF6EE]"
                >
                    {t('Overview')}
                </a>
                <span className="rounded-lg bg-[#8A3330] px-4 py-2 text-sm font-bold text-white">{t('Weighed Lines')}</span>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{t('Weighed Lines')}</h1>
                    <p className="text-sm text-gray-500 mt-1 max-w-2xl">
                        {t('Every weighed line — what the scale said, what was charged, and how far the two sat apart.')}
                    </p>
                </div>

                <div className="flex shrink-0 gap-2">
                    <a
                        href={route('superadmin.reports.weighed-lines.export-csv', exportParams)}
                        className="inline-flex items-center px-4 py-2 bg-white border border-[#E5DDD0] rounded-md font-semibold text-xs text-[#6C5E57] uppercase tracking-widest hover:bg-[#FAF6EE] transition"
                    >
                        {t('Export CSV')}
                    </a>
                    <a
                        href={route('superadmin.reports.weighed-lines.export-pdf', exportParams)}
                        className="inline-flex items-center px-4 py-2 bg-[#8A3330] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#742927] transition"
                    >
                        {t('Export PDF')}
                    </a>
                </div>
            </div>

            <DateRangeFilter
                range={range}
                selectedMonth={selectedMonth}
                selectedDate={selectedDate}
                calendarMonth={calendarMonth}
                onChange={changeDateRange}
            />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label={t('Lines')} value={totals.total_lines} footnote={rangeLabel} accent="brand" icon={<ListIcon />} />
                <StatCard label={t('Total kg')} value={`${totals.total_kg.toFixed(3)} kg`} accent="blue" icon={<ScaleIcon />} />
                <StatCard label={t('Total Charged')} value={peso(totals.total_charged)} accent="green" icon={<PesoIcon />} />
                <StatCard
                    label={t('Total Variance')}
                    value={`${totals.total_variance >= 0 ? '+' : ''}${peso(totals.total_variance)}`}
                    accent={Math.abs(totals.total_variance) > 0.004 ? 'amber' : 'slate'}
                    icon={<VarianceIcon />}
                />
            </div>

            <div className="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-[#E5DDD0] bg-white p-4">
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">{t('Item')}</label>
                    <select
                        value={localFilters.item_id}
                        onChange={(e) => applyFilters({ item_id: e.target.value })}
                        className="border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                    >
                        <option value="">{t('All items')}</option>
                        {items.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">{t('Weighed By')}</label>
                    <select
                        value={localFilters.weighed_by}
                        onChange={(e) => applyFilters({ weighed_by: e.target.value })}
                        className="border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                    >
                        <option value="">{t('Anyone')}</option>
                        {weighedByUsers.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">{t('Channel')}</label>
                    <select
                        value={localFilters.channel}
                        onChange={(e) => applyFilters({ channel: e.target.value })}
                        className="border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                    >
                        <option value="">{t('All channels')}</option>
                        {channels.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">{t('Status')}</label>
                    <select
                        value={localFilters.status}
                        onChange={(e) => applyFilters({ status: e.target.value })}
                        className="border-gray-300 focus:border-[#8A3330] focus:ring-[#8A3330] rounded-md shadow-sm text-sm"
                    >
                        <option value="">{t('Active + Awaiting (default)')}</option>
                        {Object.entries(statuses).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </div>

                <label className="flex items-center gap-2 pb-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={localFilters.show_voided}
                        onChange={(e) => applyFilters({ show_voided: e.target.checked })}
                        className="rounded border-gray-300 text-[#8A3330] focus:ring-[#8A3330]"
                    />
                    {t('Show voided')}
                </label>
            </div>

            {logs.data.length === 0 ? (
                <EmptyState
                    title={t('No weighed lines for this period')}
                    description={t('Weighed lines recorded at the Weigh Station will show up here.')}
                />
            ) : (
                <>
                    <div className="bg-white border border-[#E5DDD0] rounded-xl overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-[#E5DDD0]">
                                <thead className="bg-[#FAF6EE]">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Date & Time')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Item')}</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Weight')}</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Rate/kg')}</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Expected')}</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Charged')}</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Variance')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Order #')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Table')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Weighed By')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Channel')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{t('Status')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#E5DDD0]">
                                    {logs.data.map((row) => (
                                        <tr key={row.order_item_id} className={row.over_tolerance ? 'bg-amber-50/70' : undefined}>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{row.weighed_at}</td>
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{row.item_name}</td>
                                            <td className="px-4 py-3 text-right text-sm text-gray-700">
                                                {row.kg} kg
                                                <span className="block text-[10px] text-gray-400">{row.net_grams} g</span>
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm text-gray-700">{peso(row.reference_price_per_kilo)}</td>
                                            <td className="px-4 py-3 text-right text-sm text-gray-700">{peso(row.computed_amount)}</td>
                                            <td className="px-4 py-3 text-right text-sm font-semibold text-gray-900">{peso(row.amount_charged)}</td>
                                            <td className={`px-4 py-3 text-right text-sm font-semibold ${row.over_tolerance ? 'text-amber-700' : 'text-gray-500'}`}>
                                                {row.variance_amount >= 0 ? '+' : ''}
                                                {peso(row.variance_amount)}
                                                <span className="block text-[10px] font-normal">
                                                    {row.variance_percent >= 0 ? '+' : ''}
                                                    {Number(row.variance_percent).toFixed(1)}%
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{row.order_number}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{row.space_name ?? '—'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{row.weighed_by_name ?? '—'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{row.entry_mode_label}</td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ${STATUS_BADGE[row.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                    {row.status_label}
                                                </span>
                                                {row.status === 'voided' && row.void_reason && (
                                                    <span className="block mt-1 max-w-[16rem] text-[11px] text-gray-500">{row.void_reason}</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <Pagination links={logs.links} />
                </>
            )}
        </AuthenticatedLayout>
    );
}

function ListIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
    );
}

function ScaleIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" />
        </svg>
    );
}

function PesoIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function VarianceIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className="h-5 w-5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
        </svg>
    );
}
