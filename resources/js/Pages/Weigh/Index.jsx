import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

const ScaleIcon = ({ className = 'h-6 w-6' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className={className}>
        <rect x="4" y="3" width="16" height="18" rx="4" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h8v5H8zM10 16h4M12 7v2" />
    </svg>
);

const ArrowIcon = ({ className = 'h-4 w-4' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
    </svg>
);

const CheckIcon = ({ className = 'h-5 w-5' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
    </svg>
);

function MetricCard({ icon, label, value, suffix, footnote, accent = false }) {
    return (
        <div className={`relative overflow-hidden rounded-2xl border bg-white p-5 shadow-sm ${accent ? 'border-amber-200' : 'border-slate-200'}`}>

            <div className="flex items-start justify-between gap-4">
                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{label}</p>
                <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${accent ? 'bg-amber-50 text-amber-600' : 'bg-slate-50 text-slate-500'}`}>
                    {icon}
                </span>
            </div>
            <p className="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900">
                {value} {suffix && <span className="text-base font-semibold text-slate-400">{suffix}</span>}
            </p>
            <p className="mt-1 text-xs leading-5 text-slate-500">{footnote}</p>
        </div>
    );
}

export default function Index({ stats, filter, pendingItems }) {
    const t = useTranslation();
    const isPendingOpen = filter === 'pending';
    const amount = Number(stats.weighedTodayAmount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return (
        <AuthenticatedLayout>
            <Head title={t('Weigh & Order')} />

            <div className="mx-auto w-full max-w-[1500px]">
                <section className="rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-7">
                    <div className="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-start gap-4 sm:items-center sm:gap-5">
                            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600">
                                <ScaleIcon className="h-6 w-6" />
                            </span>
                            <div>
                                <p className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">{t('Counter station')}</p>
                                <h1 className="text-2xl font-bold tracking-[-0.03em] text-slate-900 sm:text-3xl">{t('Weigh & Order')}</h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{t('Record the actual weight, confirm the price, and add it directly to the customer bill.')}</p>
                            </div>
                        </div>

                        <Link href={route('weigh.wizard')} className="group inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-800 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 sm:w-auto">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5 shrink-0 text-slate-500 transition-colors group-hover:text-slate-900">
                                <path d="M5 4.5h14M7 4.5l1 4h8l1-4M8 8.5l-3 5v5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5v-5l-3-5" />
                                <rect x="8" y="12" width="8" height="4.5" rx="1" fill="currentColor" fillOpacity=".06" />
                                <path d="M11 14.25h2M9 18.25h.01M15 18.25h.01" />
                            </svg>
                            {t('Start Weighing')}
                        </Link>
                    </div>
                </section>

                <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section>
                        <div className="mb-3 flex items-center justify-between gap-4 px-1">
                            <div>
                                <h2 className="text-sm font-bold text-slate-900">{t("Today's activity")}</h2>
                                <p className="mt-0.5 text-xs text-slate-500">{t('Live summary from the weighing counter')}</p>
                            </div>
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                {t('Today')}
                            </span>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <MetricCard
                                accent={stats.pendingConfirmationCount > 0}
                                label={t('Awaiting confirmation')}
                                value={stats.pendingConfirmationCount}
                                footnote={t('Lines waiting on customers')}
                                icon={<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-[18px] w-[18px]"><path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                            />
                            <MetricCard label={t('Total weight')} value={stats.weighedTodayKg} suffix={t('kg')} footnote={t('Net weight recorded today')} icon={<ScaleIcon className="h-[18px] w-[18px]" />} />
                            <MetricCard
                                label={t('Recorded value')}
                                value={`₱${amount}`}
                                footnote={t('Amount added to customer bills')}
                                icon={<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-[18px] w-[18px]"><path strokeLinecap="round" strokeLinejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                            />
                        </div>

                        <div className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="flex items-center gap-3">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </span>
                                    <div>
                                        <h2 className="text-sm font-bold text-slate-900">{t('Customer confirmations')}</h2>
                                        <p className="mt-0.5 text-xs text-slate-500">{t('Weighed lines that still need customer approval')}</p>
                                    </div>
                                </div>
                                <Link href={route('weigh.station', isPendingOpen ? {} : { filter: 'pending' })} className="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                    {isPendingOpen ? t('Hide queue') : t('View queue')}
                                    <ArrowIcon className={`h-3.5 w-3.5 transition-transform ${isPendingOpen ? '-rotate-90' : ''}`} />
                                </Link>
                            </div>

                            {!isPendingOpen ? (
                                <div className="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center sm:flex-row sm:justify-between sm:text-left">
                                    <div>
                                        <p className="text-2xl font-bold text-slate-900">{stats.pendingConfirmationCount}</p>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {stats.pendingConfirmationCount === 0 ? t('No customer confirmations need attention right now.') : t('Open the queue to review the lines waiting for approval.')}
                                        </p>
                                    </div>
                                    {stats.pendingConfirmationCount === 0 && <span className="mt-4 grid h-11 w-11 place-items-center rounded-full bg-emerald-50 text-emerald-600 sm:mt-0"><CheckIcon /></span>}
                                </div>
                            ) : pendingItems.length === 0 ? (
                                <div className="flex min-h-32 items-center justify-center gap-3 px-6 py-8 text-sm text-slate-500">
                                    <span className="grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-emerald-600"><CheckIcon /></span>
                                    {t('Nothing is waiting on a customer right now.')}
                                </div>
                            ) : (
                                <div className="divide-y divide-slate-100">
                                    {pendingItems.map((item) => (
                                        <a key={item.id} href={route('orders.show', item.order_id)} className="group flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-xs font-bold text-slate-700">#{item.id}</span>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-bold text-slate-900 group-hover:text-slate-700">{item.item_name}</p>
                                                    <p className="mt-0.5 truncate text-xs text-slate-500">{item.detail} · {item.order_number}{item.table ? ` · ${item.table}` : ''}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between gap-4 pl-12 sm:shrink-0 sm:pl-0">
                                                <p className="text-xs text-slate-500">{item.weighed_by} · {item.weighed_at}</p>
                                                <ArrowIcon className="h-3.5 w-3.5 text-slate-400 transition-transform group-hover:translate-x-1 group-hover:text-slate-700" />
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <aside className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:self-start">
                        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">{t('Quick workflow')}</p>
                        <h2 className="mt-1.5 text-lg font-bold text-slate-900">{t('From scale to bill')}</h2>
                        <p className="mt-1 text-xs leading-5 text-slate-500">{t('Complete each weighing in three simple stages.')}</p>

                        <ol className="relative mt-6 space-y-5 before:absolute before:bottom-5 before:left-[17px] before:top-5 before:w-px before:bg-slate-200">
                            {[
                                [t('Select the item'), t('Choose the fish or meat and cooking style.')],
                                [t('Enter scale reading'), t('Record the exact weight and displayed amount.')],
                                [t('Attach to the bill'), t('Select the table or create a walk-in order.')],
                            ].map(([title, description], index) => (
                                <li key={title} className="relative flex gap-3.5">
                                    <span className="z-10 grid h-9 w-9 shrink-0 place-items-center rounded-full border-4 border-white bg-slate-100 text-xs font-semibold text-slate-600 shadow-sm">{index + 1}</span>
                                    <div className="pt-1">
                                        <p className="text-sm font-bold text-slate-900">{title}</p>
                                        <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
