import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';

const peso = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDateTime = (iso) => iso ? new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

const CalendarIcon = ({ className = 'h-6 w-6' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
    </svg>
);

const ArrowIcon = ({ className = 'h-4 w-4' }) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
    </svg>
);

export default function Index({ quotations }) {
    const t = useTranslation();
    const totalValue = quotations.reduce((sum, quotation) => sum + Number(quotation.subtotal || 0), 0);
    const upcomingCount = quotations.filter((quotation) => quotation.scheduled_for && new Date(quotation.scheduled_for) >= new Date()).length;
    const todayCount = quotations.filter((quotation) => {
        if (!quotation.scheduled_for) return false;
        return new Date(quotation.scheduled_for).toDateString() === new Date().toDateString();
    }).length;

    return (
        <AuthenticatedLayout>
            <Head title={t('Advance Orders / Quotations')} />

            <div className="mx-auto w-full max-w-[1500px]">
                <section className="relative isolate overflow-hidden rounded-[1.75rem] bg-[#241917] px-6 py-7 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.9)] sm:px-8 lg:px-9">
                    <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-[#A84742]/40 blur-3xl" />
                    <div className="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white"><CalendarIcon className="h-7 w-7" /></span>
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#E7BBB1]">{t('Advance ordering')}</p>
                                <h1 className="mt-1 text-2xl font-bold tracking-[-0.03em] text-white">{t('Advance Orders / Quotations')}</h1>
                                <p className="mt-1.5 max-w-2xl text-sm leading-5 text-white/55">{t('Schedule customer orders, attach them to the right receipt, and keep every commitment visible.')}</p>
                            </div>
                        </div>
                        <Link href={route('quotations.create')} className="group inline-flex min-h-12 w-full shrink-0 items-center justify-center gap-2.5 whitespace-nowrap rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#6F2927] shadow-lg shadow-black/15 transition hover:bg-[#FFF8F4] active:scale-[.98] sm:w-auto">
                            <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#F3E1DC] text-base leading-none">+</span>
                            <span>{t('New Advance Order')}</span>
                            <ArrowIcon className="h-4 w-4 shrink-0 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </div>
                </section>

                <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {[
                        [t('Total quotations'), quotations.length, t('All advance orders')],
                        [t('Upcoming'), upcomingCount, t('Scheduled from now')],
                        [t('Quoted value'), peso(totalValue), todayCount ? t(':count scheduled today').replace(':count', todayCount) : t('No schedule today')],
                    ].map(([label, value, note]) => (
                        <div key={label} className="rounded-2xl border border-[#E5DDD0] bg-white px-5 py-4 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.65)]">
                            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#8A7B74]">{label}</p>
                            <div className="mt-1 flex items-end justify-between gap-3"><p className="text-2xl font-bold text-[#251C19]">{value}</p><p className="pb-0.5 text-[10px] text-[#A59891]">{note}</p></div>
                        </div>
                    ))}
                </div>

                {quotations.length === 0 ? (
                    <section className="mt-5 overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)]">
                        <div className="flex min-h-[300px] flex-col items-center justify-center px-6 py-12 text-center">
                            <span className="grid h-16 w-16 place-items-center rounded-2xl bg-[#F3E1DC] text-[#8A3330]"><CalendarIcon className="h-8 w-8" /></span>
                            <h2 className="mt-5 text-lg font-bold text-[#251C19]">{t('No advance orders yet')}</h2>
                            <p className="mt-2 max-w-md text-sm leading-6 text-[#8A7B74]">{t('Create the first quotation by choosing a table, receipt, menu items, and the requested schedule.')}</p>
                            <Link href={route('quotations.create')} className="mt-6 inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#8A3330] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#742927]">
                                {t('Create advance order')}<ArrowIcon />
                            </Link>
                        </div>
                    </section>
                ) : (
                    <section className="mt-5 overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_20px_55px_-44px_rgba(55,35,30,0.7)]">
                        <div className="flex items-center justify-between border-b border-[#EEE5DC] px-5 py-4 sm:px-6">
                            <div><h2 className="text-sm font-bold text-[#251C19]">{t('Quotation activity')}</h2><p className="mt-0.5 text-xs text-[#8A7B74]">{t('Newest advance orders appear first')}</p></div>
                            <span className="rounded-full bg-[#F7F0E3] px-2.5 py-1 text-[10px] font-bold text-[#8A7B74]">{quotations.length} {t('records')}</span>
                        </div>
                        <div className="divide-y divide-[#EEE5DC]">
                            {quotations.map((quotation) => (
                                <Link key={quotation.id} href={route('quotations.show', quotation.id)} className="group grid gap-3 px-5 py-4 transition hover:bg-[#FCF8F1] sm:grid-cols-[1.1fr_1fr_1fr_auto] sm:items-center sm:px-6">
                                    <div><p className="font-mono text-sm font-bold text-[#251C19] group-hover:text-[#8A3330]">{quotation.quotation_number}</p><p className="mt-1 text-xs text-[#8A7B74]">{quotation.customer_name ?? t('Walk-in')} · {quotation.space_label ?? '—'}</p></div>
                                    <div><p className="text-[10px] font-bold uppercase tracking-wider text-[#A59891]">{t('Scheduled for')}</p><p className="mt-1 text-sm font-semibold text-[#5F534E]">{formatDateTime(quotation.scheduled_for)}</p></div>
                                    <div className="flex items-center justify-between gap-4 sm:block"><span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${quotation.badge_classes}`}>{quotation.status_label}</span><p className="text-base font-bold text-[#8A3330] sm:mt-2">{peso(quotation.subtotal)}</p></div>
                                    <span className="hidden h-9 w-9 place-items-center rounded-full border border-[#E5DDD0] text-[#A59891] transition group-hover:border-[#8A3330] group-hover:text-[#8A3330] sm:grid"><ArrowIcon /></span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
