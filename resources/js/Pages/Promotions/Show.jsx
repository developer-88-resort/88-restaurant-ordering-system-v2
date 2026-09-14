import ConfirmDialog from '@/Components/ConfirmDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—');

const STATUS_DOT = {
    active: 'bg-emerald-400',
    scheduled: 'bg-sky-300',
    expired: 'bg-red-300',
    draft: 'bg-white/40',
    disabled: 'bg-white/40',
};

const PromotionsIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" />
    </svg>
);

const PencilIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM16.862 4.487 19.5 7.125" />
    </svg>
);

const TrashIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
    </svg>
);

const LinkIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
    </svg>
);

const CalendarIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
    </svg>
);

const InfoIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
    </svg>
);

export default function Show({ promotion }) {
    const t = useTranslation();
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const destroy = () => {
        setConfirmingDelete(false);
        router.delete(route('superadmin.promotions.destroy', promotion.id), {
            onSuccess: () => router.visit(route('superadmin.promotions.index')),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={promotion.title || promotion.code} />

            <section className="relative isolate mb-6 overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-6 shadow-[0_28px_65px_-36px_rgba(36,25,23,0.85)] sm:px-8 sm:py-7">
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

                <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4 sm:gap-5">
                        <div className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-white backdrop-blur-sm sm:h-16 sm:w-16">
                            <PromotionsIcon className="h-7 w-7" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2.5">
                                <p className="font-mono text-lg font-bold tracking-tight text-white">{promotion.code}</p>
                                <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/85 backdrop-blur-sm">
                                    <span className="relative flex h-2 w-2">
                                        {promotion.status === 'active' && (
                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-40" />
                                        )}
                                        <span className={`relative inline-flex h-2 w-2 rounded-full ${STATUS_DOT[promotion.status] ?? 'bg-white/40'}`} />
                                    </span>
                                    {promotion.status_label}
                                </span>
                            </div>
                            {promotion.title && <p className="mt-1.5 text-sm text-white/55">{promotion.title}</p>}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('superadmin.promotions.edit', promotion.id)}
                            className="group inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm font-bold text-[#7B2D2A] shadow-[0_16px_32px_-18px_rgba(0,0,0,0.75)] transition hover:-translate-y-0.5 hover:bg-[#FFF7F3] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15"
                        >
                            <PencilIcon className="h-4 w-4" /> {t('Edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setConfirmingDelete(true)}
                            className="inline-flex items-center gap-2 rounded-2xl border border-red-300/25 bg-red-500/10 px-4 py-2.5 text-sm font-bold text-red-200 backdrop-blur-sm transition hover:bg-red-500/20 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/15"
                        >
                            <TrashIcon className="h-4 w-4" /> {t('Delete')}
                        </button>
                    </div>
                </div>
            </section>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div className="space-y-5 lg:col-span-2">
                    <div className="flex items-center justify-center overflow-hidden rounded-[1.75rem] border border-[#E5DDD0] bg-gradient-to-br from-[#FAF6EE] to-[#F1E9DA] p-4 shadow-[0_18px_45px_-38px_rgba(55,35,30,0.6)]">
                        {promotion.image_url ? (
                            <img src={promotion.image_url} alt="" className="max-h-[480px] w-full rounded-xl object-contain" />
                        ) : (
                            <div className="flex h-56 w-full items-center justify-center text-sm text-gray-400">{t('No image')}</div>
                        )}
                    </div>

                    {promotion.banner_cta_url && (
                        <a
                            href={promotion.banner_cta_url}
                            target="_blank"
                            rel="noreferrer"
                            className="group flex items-center gap-3 rounded-2xl border border-[#E5DDD0] bg-white px-5 py-4 shadow-[0_10px_30px_-23px_rgba(55,35,30,0.48)] transition hover:-translate-y-0.5 hover:border-[#d7c8bc]"
                        >
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330] transition group-hover:scale-105">
                                <LinkIcon className="h-5 w-5" />
                            </span>
                            <span className="min-w-0">
                                <span className="block text-[10px] font-bold uppercase tracking-[0.15em] text-[#92847e]">{t('Link URL')}</span>
                                <span className="block truncate text-sm font-semibold text-[#8A3330]">{promotion.banner_cta_url}</span>
                            </span>
                        </a>
                    )}

                    {promotion.description && (
                        <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_10px_30px_-23px_rgba(55,35,30,0.48)]">
                            <div className="mb-3 flex items-center gap-2.5">
                                <span className="h-5 w-1 rounded-full bg-[#8A3330]" />
                                <h3 className="text-xs font-bold uppercase tracking-[0.16em] text-[#8A7B74]">{t('Description')}</h3>
                            </div>
                            <p className="whitespace-pre-line text-sm text-[#6C5E57]">{promotion.description}</p>
                        </div>
                    )}
                </div>

                <div className="space-y-5">
                    <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_10px_30px_-23px_rgba(55,35,30,0.48)]">
                        <div className="mb-4 flex items-center gap-2.5">
                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                                <CalendarIcon className="h-4.5 w-4.5" />
                            </span>
                            <h3 className="text-sm font-bold text-[#251C19]">{t('Schedule')}</h3>
                        </div>
                        <dl className="space-y-2.5 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-[#9A8B84]">{t('Starts')}</dt>
                                <dd className="font-semibold text-[#251C19]">{promotion.starts_at ? formatDateTime(promotion.starts_at) : t('Immediately')}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-[#9A8B84]">{t('Ends')}</dt>
                                <dd className="font-semibold text-[#251C19]">{promotion.ends_at ? formatDateTime(promotion.ends_at) : t('Open-ended')}</dd>
                            </div>
                        </dl>
                    </div>

                    <div className="rounded-2xl border border-[#E5DDD0] bg-white p-5 shadow-[0_10px_30px_-23px_rgba(55,35,30,0.48)]">
                        <div className="mb-4 flex items-center gap-2.5">
                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                                <InfoIcon className="h-4.5 w-4.5" />
                            </span>
                            <h3 className="text-sm font-bold text-[#251C19]">{t('Details')}</h3>
                        </div>
                        <dl className="space-y-2.5 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-[#9A8B84]">{t('Created by')}</dt>
                                <dd className="font-semibold text-[#251C19]">{promotion.creator_name ?? '—'}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-[#9A8B84]">{t('Created')}</dt>
                                <dd className="text-right text-[#6C5E57]">{formatDateTime(promotion.created_at)}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-[#9A8B84]">{t('Last updated')}</dt>
                                <dd className="text-right text-[#6C5E57]">{formatDateTime(promotion.updated_at)}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                show={confirmingDelete}
                title={t('Delete this banner?')}
                message={t('This cannot be undone.')}
                confirmLabel={t('Delete')}
                onConfirm={destroy}
                onCancel={() => setConfirmingDelete(false)}
            />
        </AuthenticatedLayout>
    );
}
