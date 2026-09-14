import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const peso = (value) =>
    `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

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

const CopyIcon = ({ className = 'h-4 w-4' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V10.875c0-.621.504-1.125 1.125-1.125H8.25m7.5 7.5h3.375c.621 0 1.125-.504 1.125-1.125v-9.75c0-.621-.504-1.125-1.125-1.125h-9.75c-.621 0-1.125.504-1.125 1.125V9.75m7.5 7.5h-6.375A1.125 1.125 0 018.25 16.125V9.75" />
    </svg>
);

function PricingCard({ item, value, isEditable, onChange, t }) {
    const currentPrice = item.price === null ? item.default_price_per_kilo : item.price;
    const hasDailyPrice = item.price !== null;

    return (
        <article className="overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_20px_55px_-44px_rgba(55,35,30,0.75)]">
            <div className="flex items-start justify-between gap-4 border-b border-[#EEE5DC] px-5 py-4">
                <div className="flex min-w-0 items-center gap-3">
                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                        <ScaleIcon className="h-5 w-5" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="truncate text-sm font-bold text-[#251C19]">{item.name}</h2>
                        <p className="mt-0.5 truncate text-[10px] font-bold uppercase tracking-[0.13em] text-[#9A8B84]">
                            {item.category_name ?? t('Uncategorized')}
                        </p>
                    </div>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold ${hasDailyPrice ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                    {hasDailyPrice ? t('Daily rate set') : t('Using menu default')}
                </span>
            </div>

            <div className="grid items-center gap-4 px-5 py-5 sm:grid-cols-[1fr_auto_1.15fr]">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8B84]">{t("Yesterday's price")}</p>
                    <p className="mt-1.5 text-xl font-bold text-[#5F534E]">{peso(item.yesterday_price)}<span className="ml-1 text-xs font-semibold text-[#A59891]">/{t('kg')}</span></p>
                    <p className="mt-1 text-[10px] text-[#A59891]">{item.yesterday_was_set ? t('Saved daily rate') : t('Menu default')}</p>
                </div>

                <span className="hidden h-9 w-9 place-items-center rounded-full bg-[#F7F0E3] text-[#A59891] sm:grid">
                    <ArrowIcon />
                </span>

                <div>
                    <label htmlFor={`price-${item.id}`} className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#8A3330]">
                        {t("Today's price")}
                    </label>
                    {isEditable ? (
                        <div className="relative mt-1.5">
                            <span className="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm font-bold text-[#8A3330]">₱</span>
                            <input
                                id={`price-${item.id}`}
                                type="number"
                                step="0.01"
                                min="10"
                                max="10000"
                                inputMode="decimal"
                                value={value}
                                onChange={(e) => onChange(item.id, e.target.value)}
                                placeholder={item.default_price_per_kilo.toFixed(2)}
                                aria-label={`${t("Today's Price")} — ${item.name}`}
                                className="w-full rounded-xl border-[#D9CCBA] py-3 pl-8 pr-14 text-right text-lg font-bold tabular-nums text-[#251C19] shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]"
                            />
                            <span className="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-xs font-semibold text-[#A59891]">/{t('kg')}</span>
                        </div>
                    ) : (
                        <p className="mt-1.5 text-xl font-bold text-[#8A3330]">{peso(currentPrice)}<span className="ml-1 text-xs font-semibold text-[#A59891]">/{t('kg')}</span></p>
                    )}
                    {!hasDailyPrice && <p className="mt-1 text-[10px] text-[#A59891]">{t('Not set — using menu default')}</p>}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-[#EEE5DC] bg-[#FCF8F1] px-5 py-3 text-[10px] text-[#8A7B74]">
                <span><strong className="font-bold text-[#5F534E]">{t('Default')}:</strong> {peso(item.default_price_per_kilo)}/{t('kg')}</span>
                <span className="hidden h-3 w-px bg-[#D9CCBA] sm:block" />
                <span><strong className="font-bold text-[#5F534E]">{t('Set by')}:</strong> {item.set_by ?? '—'}</span>
                {item.set_at && <span className="ml-auto">{item.set_at}</span>}
            </div>
        </article>
    );
}

export default function Index({ date, today, isEditable, items }) {
    const t = useTranslation();
    const [prices, setPrices] = useState(() =>
        Object.fromEntries(items.map((item) => [item.id, item.price ?? ''])),
    );
    const [processing, setProcessing] = useState(false);

    const setPrice = (id, value) => setPrices((current) => ({ ...current, [id]: value }));
    const copyFromYesterday = () => {
        setPrices(Object.fromEntries(items.map((item) => [item.id, Number(item.yesterday_price).toFixed(2)])));
    };
    const changeDate = (value) => router.get(route('weigh.prices.index'), { date: value }, { preserveState: false });

    const dirtyCount = items.filter((item) => {
        const initial = item.price === null ? '' : Number(item.price);
        const current = prices[item.id] === '' ? '' : Number(prices[item.id]);
        return initial !== current;
    }).length;
    const setCount = items.filter((item) => item.price !== null).length;
    const fallbackCount = items.length - setCount;

    const save = (event) => {
        event.preventDefault();
        router.post(
            route('weigh.prices.store'),
            {
                date,
                prices: items.map((item) => ({
                    menu_item_id: item.id,
                    price_per_kilo: prices[item.id] === '' ? null : prices[item.id],
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('Daily Market Prices')} />

            <div className="mx-auto w-full max-w-[1500px]">
                <section className="relative isolate overflow-hidden rounded-[1.75rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.9)] sm:px-8 lg:px-9">
                    <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl" />
                    <div className="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-center gap-4">
                            <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white">
                                <ScaleIcon className="h-7 w-7" />
                            </span>
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#E7BBB1]">{t('Pricing control')}</p>
                                <h1 className="mt-1 text-2xl font-bold tracking-[-0.03em] text-white">{t('Daily Market Prices')}</h1>
                                <p className="mt-1.5 max-w-2xl text-xs leading-5 text-white/55 sm:text-sm">{t('Set the per-kilo rate used by the weighing station and customer bills.')}</p>
                            </div>
                        </div>

                        <div className="rounded-xl border border-white/15 bg-white/10 p-2 backdrop-blur-sm">
                            <label htmlFor="date" className="mb-1 block px-1 text-[9px] font-bold uppercase tracking-[0.16em] text-white/50">{t('Effective date')}</label>
                            <input
                                id="date"
                                type="date"
                                value={date}
                                max={today}
                                onChange={(e) => changeDate(e.target.value)}
                                className="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm font-semibold text-[#251C19] shadow-sm focus:ring-2 focus:ring-[#D98D7C] sm:w-auto"
                            />
                        </div>
                    </div>
                </section>

                <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {[
                        [t('Market items'), items.length, t('Per-kilo menu items')],
                        [t('Daily rates set'), setCount, t('Saved for this date')],
                        [t('Using defaults'), fallbackCount, t('No daily override yet')],
                    ].map(([label, value, note], index) => (
                        <div key={label} className={`rounded-2xl border bg-white px-5 py-4 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.65)] ${index === 2 && fallbackCount > 0 ? 'border-amber-200' : 'border-[#E5DDD0]'}`}>
                            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">{label}</p>
                            <div className="mt-1 flex items-end justify-between gap-3">
                                <p className="text-2xl font-bold text-[#251C19]">{value}</p>
                                <p className="pb-0.5 text-[10px] text-[#A59891]">{note}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {!isEditable && (
                    <div className="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-800">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="mt-0.5 h-5 w-5 shrink-0"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9 0a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                        <span>{t('Viewing a past day. These prices are a record of what was actually charged and cannot be edited.')}</span>
                    </div>
                )}

                {items.length === 0 ? (
                    <div className="mt-5"><EmptyState title={t('No per-kilo items yet')} description={t('Set a menu item\'s Pricing Type to "Per Kilo (Weighed)" and it will appear here.')} /></div>
                ) : (
                    <form onSubmit={save} className="mt-5">
                        <div className="mb-3 flex items-center justify-between gap-4 px-1">
                            <div>
                                <h2 className="text-sm font-bold text-[#251C19]">{t('Item rates')}</h2>
                                <p className="mt-0.5 text-xs text-[#8A7B74]">{t('Compare yesterday and enter today’s selling price per kilogram.')}</p>
                            </div>
                            {isEditable && dirtyCount > 0 && (
                                <span className="rounded-full bg-[#F3E1DC] px-2.5 py-1 text-[10px] font-bold text-[#8A3330]">{dirtyCount} {t('unsaved')}</span>
                            )}
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {items.map((item) => (
                                <PricingCard key={item.id} item={item} value={prices[item.id]} isEditable={isEditable} onChange={setPrice} t={t} />
                            ))}
                        </div>

                        {isEditable && (
                            <div className="sticky bottom-4 z-10 mt-5 flex flex-col gap-3 rounded-2xl border border-[#D9CCBA] bg-white/95 px-4 py-3.5 shadow-[0_18px_45px_-24px_rgba(55,35,30,0.35)] backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <p className="text-xs text-[#8A7B74]">
                                    {dirtyCount > 0 ? t(':count item(s) have unsaved changes.').replace(':count', dirtyCount) : t('All displayed prices are up to date.')}
                                </p>
                                <div className="flex items-center gap-2.5">
                                    <button type="button" onClick={copyFromYesterday} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[#D9CCBA] px-4 py-2.5 text-xs font-bold text-[#6F2927] transition hover:bg-[#FCF8F1]">
                                        <CopyIcon />
                                        {t('Copy yesterday')}
                                    </button>
                                    <button type="submit" disabled={processing || dirtyCount === 0} className="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#8A3330] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#742927] disabled:cursor-not-allowed disabled:opacity-40">
                                        {processing ? t('Saving…') : t('Save prices')}
                                    </button>
                                </div>
                            </div>
                        )}
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
