import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

const ScaleIcon = ({ className = 'h-6 w-6' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" />
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
        <div className={`relative overflow-hidden rounded-2xl border bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.75)] ${accent ? 'border-[#D9B5AC]' : 'border-[#E5DDD0]'}`}>
            {accent && <span aria-hidden="true" className="absolute inset-y-0 left-0 w-1 bg-[#8A3330]" />}
            <div className="flex items-start justify-between gap-4">
                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">{label}</p>
                <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${accent ? 'bg-[#F3E1DC] text-[#8A3330]' : 'bg-[#F7F0E3] text-[#7A6D66]'}`}>
                    {icon}
                </span>
            </div>
            <p className={`mt-3 text-3xl font-bold tracking-tight ${accent ? 'text-[#8A3330]' : 'text-[#251C19]'}`}>
                {value} {suffix && <span className="text-base font-semibold text-[#A59891]">{suffix}</span>}
            </p>
            <p className="mt-1 text-xs leading-5 text-[#8A7B74]">{footnote}</p>
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
                <section className="relative isolate overflow-hidden rounded-[1.75rem] bg-[#241917] px-6 py-7 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.9)] sm:px-8 lg:px-10 lg:py-9">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.06]"
                        style={{
                            backgroundImage: 'linear-gradient(rgba(255,255,255,.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.7) 1px, transparent 1px)',
                            backgroundSize: '28px 28px',
                        }}
                    />
                    <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl" />

                    <div className="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-start gap-4 sm:items-center sm:gap-5">
                            <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white sm:h-16 sm:w-16">
                                <ScaleIcon className="h-7 w-7 sm:h-8 sm:w-8" />
                            </span>
                            <div>
                                <p className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-[#E7BBB1]">{t('Counter station')}</p>
                                <h1 className="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">{t('Weigh & Order')}</h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-white/55">{t('Record the actual weight, confirm the price, and add it directly to the customer bill.')}</p>
                            </div>
                        </div>

                        <Link href={route('weigh.wizard')} className="group inline-flex min-h-12 w-full items-center justify-center gap-3 rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-[#6F2927] shadow-lg shadow-black/15 transition hover:bg-[#FFF8F4] active:scale-[.98] sm:w-auto">
                            {t('Start Weighing')}
                            <ArrowIcon className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </div>
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section>
                        <div className="mb-3 flex items-center justify-between gap-4 px-1">
                            <div>
                                <h2 className="text-sm font-bold text-[#251C19]">{t("Today's activity")}</h2>
                                <p className="mt-0.5 text-xs text-[#8A7B74]">{t('Live summary from the weighing counter')}</p>
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

                        <div className="mt-5 overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_18px_45px_-38px_rgba(55,35,30,0.65)]">
                            <div className="flex flex-col gap-3 border-b border-[#EEE5DC] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="flex items-center gap-3">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </span>
                                    <div>
                                        <h2 className="text-sm font-bold text-[#251C19]">{t('Customer confirmations')}</h2>
                                        <p className="mt-0.5 text-xs text-[#8A7B74]">{t('Weighed lines that still need customer approval')}</p>
                                    </div>
                                </div>
                                <Link href={route('weigh.station', isPendingOpen ? {} : { filter: 'pending' })} className="inline-flex items-center gap-2 text-xs font-bold text-[#8A3330] hover:text-[#6F2927]">
                                    {isPendingOpen ? t('Hide queue') : t('View queue')}
                                    <ArrowIcon className={`h-3.5 w-3.5 transition-transform ${isPendingOpen ? '-rotate-90' : ''}`} />
                                </Link>
                            </div>

                            {!isPendingOpen ? (
                                <div className="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center sm:flex-row sm:justify-between sm:text-left">
                                    <div>
                                        <p className="text-2xl font-bold text-[#251C19]">{stats.pendingConfirmationCount}</p>
                                        <p className="mt-1 text-sm text-[#8A7B74]">
                                            {stats.pendingConfirmationCount === 0 ? t('No customer confirmations need attention right now.') : t('Open the queue to review the lines waiting for approval.')}
                                        </p>
                                    </div>
                                    {stats.pendingConfirmationCount === 0 && <span className="mt-4 grid h-11 w-11 place-items-center rounded-full bg-emerald-50 text-emerald-600 sm:mt-0"><CheckIcon /></span>}
                                </div>
                            ) : pendingItems.length === 0 ? (
                                <div className="flex min-h-32 items-center justify-center gap-3 px-6 py-8 text-sm text-[#8A7B74]">
                                    <span className="grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-emerald-600"><CheckIcon /></span>
                                    {t('Nothing is waiting on a customer right now.')}
                                </div>
                            ) : (
                                <div className="divide-y divide-[#EEE5DC]">
                                    {pendingItems.map((item) => (
                                        <a key={item.id} href={route('orders.show', item.order_id)} className="group flex flex-col gap-3 px-5 py-4 transition hover:bg-[#FCF8F1] sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#F7F0E3] text-xs font-bold text-[#8A3330]">#{item.id}</span>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-bold text-[#251C19] group-hover:text-[#8A3330]">{item.item_name}</p>
                                                    <p className="mt-0.5 truncate text-xs text-[#8A7B74]">{item.detail} · {item.order_number}{item.table ? ` · ${item.table}` : ''}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between gap-4 pl-12 sm:shrink-0 sm:pl-0">
                                                <p className="text-xs text-[#9A8B84]">{item.weighed_by} · {item.weighed_at}</p>
                                                <ArrowIcon className="h-3.5 w-3.5 text-[#B8AAA2] transition-transform group-hover:translate-x-1 group-hover:text-[#8A3330]" />
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <aside className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.65)] sm:p-6 xl:self-start">
                        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8A7B74]">{t('Quick workflow')}</p>
                        <h2 className="mt-1.5 text-lg font-bold text-[#251C19]">{t('From scale to bill')}</h2>
                        <p className="mt-1 text-xs leading-5 text-[#8A7B74]">{t('Complete each weighing in three simple stages.')}</p>

                        <ol className="relative mt-6 space-y-5 before:absolute before:bottom-5 before:left-[17px] before:top-5 before:w-px before:bg-[#E5DDD0]">
                            {[
                                [t('Select the item'), t('Choose the fish or meat and cooking style.')],
                                [t('Enter scale reading'), t('Record the exact weight and displayed amount.')],
                                [t('Attach to the bill'), t('Select the table or create a walk-in order.')],
                            ].map(([title, description], index) => (
                                <li key={title} className="relative flex gap-3.5">
                                    <span className="z-10 grid h-9 w-9 shrink-0 place-items-center rounded-full border-4 border-white bg-[#8A3330] text-xs font-bold text-white shadow-sm">{index + 1}</span>
                                    <div className="pt-1">
                                        <p className="text-sm font-bold text-[#251C19]">{title}</p>
                                        <p className="mt-1 text-xs leading-5 text-[#8A7B74]">{description}</p>
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
