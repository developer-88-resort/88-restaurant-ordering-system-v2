import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const DOT_CLASSES = {
    available: 'bg-emerald-500',
    out_of_stock: 'bg-slate-400',
    seasonal: 'bg-amber-500',
    hidden: 'bg-slate-600',
};

const STATUS_TONES = {
    available: {
        bar: 'bg-emerald-500',
        dot: 'bg-emerald-500',
        soft: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    },
    out_of_stock: {
        bar: 'bg-slate-400',
        dot: 'bg-slate-400',
        soft: 'border-slate-200 bg-slate-100 text-slate-600',
    },
    seasonal: {
        bar: 'bg-amber-500',
        dot: 'bg-amber-500',
        soft: 'border-amber-200 bg-amber-50 text-amber-700',
    },
    hidden: {
        bar: 'bg-slate-700',
        dot: 'bg-slate-600',
        soft: 'border-slate-300 bg-slate-100 text-slate-700',
    },
};

function Icon({ name, className = 'h-5 w-5', strokeWidth = 1.8 }) {
    let content = null;

    switch (name) {
        case 'menu':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 5.25h15m-15 6.75h15m-15 6.75h15" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 3.75v3M12 10.5v3M16.5 17.25v3" />
                </>
            );
            break;
        case 'plus':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />;
            break;
        case 'categories':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M3.75 6.75A2.25 2.25 0 016 4.5h3A2.25 2.25 0 0111.25 6.75v3A2.25 2.25 0 019 12H6a2.25 2.25 0 01-2.25-2.25v-3zm9 0A2.25 2.25 0 0115 4.5h3a2.25 2.25 0 012.25 2.25v3A2.25 2.25 0 0118 12h-3a2.25 2.25 0 01-2.25-2.25v-3zm-9 9A2.25 2.25 0 016 13.5h3a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 019 19.5H6a2.25 2.25 0 01-2.25-2.25v-1.5zm9 0A2.25 2.25 0 0115 13.5h3a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 19.5h-3a2.25 2.25 0 01-2.25-2.25v-1.5z"
                />
            );
            break;
        case 'search':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z"
                />
            );
            break;
        case 'chevron':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="m6.75 9 5.25 5.25L17.25 9" />;
            break;
        case 'filter':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M10.5 6h9.75M3.75 6H6m4.5 12h9.75M3.75 18H6m8.25-6h6M3.75 12h6m-1.5-3.75v-4.5m7.5 12v4.5"
                />
            );
            break;
        case 'archive':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 5.25h16.5v3H3.75v-3z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 8.25v10.5h13.5V8.25M9.75 12h4.5" />
                </>
            );
            break;
        case 'edit':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM16.862 4.487 19.5 7.125"
                />
            );
            break;
        case 'restore':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 010 12h-3"
                />
            );
            break;
        case 'image':
            content = (
                <>
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="m2.25 15.75 5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909"
                    />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5V6a1.5 1.5 0 011.5-1.5z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 8.25h.008v.008H15V8.25z" />
                </>
            );
            break;
        case 'clock':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </>
            );
            break;
        case 'layers':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="m3.75 9 8.25-4.5L20.25 9 12 13.5 3.75 9z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="m3.75 13.5 8.25 4.5 8.25-4.5M3.75 18l8.25 4.5L20.25 18" />
                </>
            );
            break;
        case 'scale':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v18M5.25 7.5h13.5" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="m5.25 7.5-3 5.25h6l-3-5.25zm13.5 0-3 5.25h6l-3-5.25z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21h9" />
                </>
            );
            break;
        case 'sparkles':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="m9.813 15.904-.41 1.035a.75.75 0 01-1.396 0l-.41-1.035a4.5 4.5 0 00-2.51-2.51l-1.035-.41a.75.75 0 010-1.396l1.035-.41a4.5 4.5 0 002.51-2.51l.41-1.035a.75.75 0 011.396 0l.41 1.035a4.5 4.5 0 002.51 2.51l1.035.41a.75.75 0 010 1.396l-1.035.41a4.5 4.5 0 00-2.51 2.51zm6.75-7.5-.205.518a.75.75 0 01-1.396 0l-.205-.518a2.25 2.25 0 00-1.255-1.255l-.518-.205a.75.75 0 010-1.396l.518-.205a2.25 2.25 0 001.255-1.255l.205-.518a.75.75 0 011.396 0l.205.518a2.25 2.25 0 001.255 1.255l.518.205a.75.75 0 010 1.396l-.518.205a2.25 2.25 0 00-1.255 1.255z"
                />
            );
            break;
        case 'check':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="m5.25 12.75 4.5 4.5 9-10.5" />;
            break;
        case 'x':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />;
            break;
        case 'warning':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 16.5h.008v.008H12V16.5z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </>
            );
            break;
        case 'box':
            content = (
                <>
                    <path strokeLinecap="round" strokeLinejoin="round" d="m21 8.25-9-5.25-9 5.25 9 5.25 9-5.25z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 8.25v9L12 22.5l9-5.25v-9M12 13.5v9" />
                </>
            );
            break;
        case 'tag':
            content = (
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M2.25 12.76V6.75A2.25 2.25 0 014.5 4.5h6.01a2.25 2.25 0 011.59.659l7.341 7.341a2.25 2.25 0 010 3.182l-3.759 3.759a2.25 2.25 0 01-3.182 0l-7.341-7.34a2.25 2.25 0 01-.659-1.591z"
                />
            );
            break;
        case 'arrow':
            content = <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 12h13.5m-5.25-5.25L18.75 12l-5.25 5.25" />;
            break;
        default:
            content = <circle cx="12" cy="12" r="8.25" />;
    }

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={strokeWidth}
            stroke="currentColor"
            className={className}
            aria-hidden="true"
        >
            {content}
        </svg>
    );
}

function AvailabilityMenu({ item, status, options, onSelect, disabled = false }) {
    const [open, setOpen] = useState(false);
    const current = options.find((option) => option.value === status) ??
        options[0] ?? {
            label: status || '—',
            badgeClasses: 'border border-slate-200 bg-slate-100 text-slate-600',
            dotClass: 'bg-slate-400',
        };

    useEffect(() => {
        if (!open) return undefined;

        const handleEscape = (event) => {
            if (event.key === 'Escape') setOpen(false);
        };

        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, [open]);

    return (
        <div className="relative z-40">
            <button
                type="button"
                disabled={disabled}
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => setOpen((value) => !value)}
                className={`inline-flex min-h-8 items-center gap-1.5 rounded-full border border-white/70 px-2.5 py-1 text-[10px] font-bold shadow-sm backdrop-blur-md transition hover:-translate-y-0.5 disabled:cursor-wait disabled:opacity-70 ${current.badgeClasses}`}
            >
                {disabled ? (
                    <span className="h-2.5 w-2.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                ) : (
                    <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${current.dotClass ?? 'bg-slate-400'}`} />
                )}
                <span className="max-w-24 truncate">{current.label}</span>
                <Icon name="chevron" className={`h-3 w-3 transition-transform ${open ? 'rotate-180' : ''}`} strokeWidth={2.4} />
            </button>

            {open && !disabled && (
                <>
                    <button
                        type="button"
                        aria-label="Close availability menu"
                        className="fixed inset-0 z-40 cursor-default"
                        onClick={() => setOpen(false)}
                    />

                    <div
                        role="menu"
                        className="absolute right-0 top-full z-50 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm"
                    >
                        <div className="px-2.5 pb-1.5 pt-1">
                            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Availability</p>
                        </div>

                        {options.map((option) => {
                            const selected = status === option.value;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="menuitem"
                                    onClick={() => {
                                        if (!selected) onSelect(item.id, option.value);
                                        setOpen(false);
                                    }}
                                    className={`flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left text-xs transition ${
                                        selected
                                            ? 'bg-slate-50 font-bold text-slate-500'
                                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-500'
                                    }`}
                                >
                                    <span className={`h-2 w-2 shrink-0 rounded-full ${option.dotClass}`} />
                                    <span className="min-w-0 flex-1 truncate">{option.label}</span>
                                    {selected && <Icon name="check" className="h-4 w-4 text-slate-700" strokeWidth={2.2} />}
                                </button>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}

function StaticAvailabilityPill({ status, options }) {
    const current = options.find((option) => option.value === status) ??
        options[0] ?? {
            label: status || '—',
            badgeClasses: 'border border-slate-200 bg-slate-100 text-slate-600',
            dotClass: 'bg-slate-400',
        };

    return (
        <span className={`inline-flex min-h-8 items-center gap-1.5 rounded-full border border-white/70 px-2.5 py-1 text-[10px] font-bold shadow-sm backdrop-blur-md ${current.badgeClasses}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${current.dotClass}`} />
            {current.label}
        </span>
    );
}

function ItemBadge({ tone, icon, children, as: Component = 'span', ...props }) {
    const tones = {
        teal: 'border-teal-200/80 bg-teal-600 text-white',
        danger: 'border-red-500 bg-red-600 text-white hover:bg-red-700',
        amber: 'border-amber-300 bg-amber-400 text-slate-500',
        brand: 'border-slate-200 bg-slate-700 text-white',
        slate: 'border-slate-600 bg-slate-700 text-white',
    };

    return (
        <Component
            {...props}
            className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.08em] shadow-sm backdrop-blur-sm transition ${tones[tone]}`}
        >
            {icon && <Icon name={icon} className="h-3 w-3" strokeWidth={2.2} />}
            {children}
        </Component>
    );
}

function ItemCard({
    item,
    canManageMenu,
    showArchived,
    statuses,
    options,
    updating,
    onSelectAvailability,
    onArchive,
    onRestore,
    t,
}) {
    const status = statuses[item.id] ?? item.availability_status;
    const statusTone = STATUS_TONES[status] ?? STATUS_TONES.out_of_stock;

    return (
        <>
        {/* Compact horizontal row — portrait phones/tablets get a lot of
            these stacked in one column, so the tall image-on-top card below
            (built for wide grids) would mean endless scrolling here instead. */}
        <article className="group relative flex gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:hidden">
            <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-50">
                {item.primary_image_url ? (
                    <img
                        src={item.primary_image_url}
                        alt={item.name}
                        loading="lazy"
                        className={`h-full w-full object-cover ${showArchived ? 'grayscale-[35%]' : ''}`}
                    />
                ) : (
                    <div className="grid h-full w-full place-items-center bg-slate-50 text-slate-500">
                        <Icon name="image" className="h-6 w-6" strokeWidth={1.35} />
                    </div>
                )}

                {item.is_per_kilo && (
                    <span className="absolute bottom-1 left-1 grid h-5 w-5 place-items-center rounded-md bg-teal-600 text-white shadow-sm">
                        <Icon name="scale" className="h-3 w-3" strokeWidth={2.2} />
                    </span>
                )}
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="min-w-0 truncate text-sm font-semibold tracking-[-0.01em] text-slate-900" title={item.name}>
                        {item.name}
                    </h3>

                    {showArchived ? (
                        <ItemBadge tone="slate" icon="archive">
                            {t('Archived')}
                        </ItemBadge>
                    ) : canManageMenu ? (
                        <AvailabilityMenu
                            item={item}
                            status={status}
                            options={options}
                            disabled={updating}
                            onSelect={onSelectAvailability}
                        />
                    ) : (
                        <StaticAvailabilityPill status={status} options={options} />
                    )}
                </div>

                <p className="mt-0.5 truncate text-xs text-slate-500">
                    {item.description_text || t('No description added')}
                </p>

                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    {item.needs_setup && (
                        <ItemBadge as={Link} href={route('menu-items.edit', item.id)} tone="danger" icon="warning">
                            {t('Needs setup')}
                        </ItemBadge>
                    )}
                    {item.is_featured && (
                        <ItemBadge tone="amber" icon="sparkles">
                            {t('Featured')}
                        </ItemBadge>
                    )}
                    {item.is_best_seller && <ItemBadge tone="brand">{t('Best Seller')}</ItemBadge>}
                </div>

                <div className="mt-2 flex items-center justify-between gap-2">
                    <p className="text-base font-semibold tracking-[-0.02em] text-slate-700">{item.price_range_label}</p>

                    {canManageMenu && (
                        <div className="flex shrink-0 items-center gap-1.5">
                            {showArchived ? (
                                <button
                                    type="button"
                                    onClick={() => onRestore(item)}
                                    className="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-700"
                                >
                                    <Icon name="restore" className="h-3.5 w-3.5" strokeWidth={2} />
                                    {t('Restore')}
                                </button>
                            ) : (
                                <>
                                    <Link
                                        href={route('menu-items.edit', item.id)}
                                        preserveScroll
                                        className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500"
                                        aria-label={t('Edit')}
                                        title={t('Edit')}
                                    >
                                        <Icon name="edit" className="h-3.5 w-3.5" strokeWidth={2} />
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => onArchive(item)}
                                        className="grid h-8 w-8 place-items-center rounded-lg border border-red-100 bg-white text-red-600"
                                        aria-label={t('Archive')}
                                        title={t('Archive')}
                                    >
                                        <Icon name="archive" className="h-3.5 w-3.5" strokeWidth={2} />
                                    </button>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </article>

        {/* Full image-on-top card — sm and up (tablet landscape, laptop, desktop grids). */}
        <article className="group relative z-0 hidden min-h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:z-20 hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-sm focus-within:z-30 sm:flex">
            <div className={`absolute inset-x-7 top-0 h-1 rounded-b-full ${showArchived ? 'bg-slate-500' : statusTone.bar}`} />

            <div className="relative px-3 pt-3">
                <div className="relative aspect-[16/11] overflow-hidden rounded-[1.25rem] bg-slate-50">
                    {item.primary_image_url ? (
                        <img
                            src={item.primary_image_url}
                            alt={item.name}
                            loading="lazy"
                            className={`h-full w-full object-cover transition duration-500 group-hover:scale-[1.045] ${showArchived ? 'grayscale-[35%]' : ''}`}
                        />
                    ) : (
                        <div className="absolute inset-0 overflow-hidden bg-slate-50">
                            <div className="absolute -right-8 -top-8 h-28 w-28 rounded-full border border-slate-200/10" />
                            <div className="absolute -bottom-10 -left-10 h-32 w-32 rounded-full border border-slate-200/10" />
                            <div className="absolute inset-0 grid place-items-center">
                                <div className="grid h-16 w-16 place-items-center rounded-[1.4rem] border border-white/80 bg-white/70 text-slate-500 shadow-sm backdrop-blur-sm">
                                    <Icon name="image" className="h-7 w-7" strokeWidth={1.35} />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/30 to-transparent opacity-0 transition group-hover:opacity-100" />

                    <div className="absolute left-2.5 top-2.5 flex max-w-[70%] flex-wrap items-start gap-1.5">
                        {item.is_per_kilo && (
                            <ItemBadge tone="teal" icon="scale">
                                {t('Per Kilo')}
                            </ItemBadge>
                        )}

                        {item.needs_setup && (
                            <ItemBadge as={Link} href={route('menu-items.edit', item.id)} tone="danger" icon="warning">
                                {t('Needs setup')}
                            </ItemBadge>
                        )}

                        {item.is_featured && (
                            <ItemBadge tone="amber" icon="sparkles">
                                {t('Featured')}
                            </ItemBadge>
                        )}

                        {item.is_best_seller && <ItemBadge tone="brand">{t('Best Seller')}</ItemBadge>}
                    </div>
                </div>

                <div className="absolute right-5 top-5 z-30">
                    {showArchived ? (
                        <ItemBadge tone="slate" icon="archive">
                            {t('Archived')}
                        </ItemBadge>
                    ) : canManageMenu ? (
                        <AvailabilityMenu
                            item={item}
                            status={status}
                            options={options}
                            disabled={updating}
                            onSelect={onSelectAvailability}
                        />
                    ) : (
                        <StaticAvailabilityPill status={status} options={options} />
                    )}
                </div>
            </div>

            <div className="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                <div className="min-w-0">
                    <h3 className="truncate text-[15px] font-semibold tracking-[-0.015em] text-slate-900" title={item.name}>
                        {item.name}
                    </h3>

                    {item.description_text ? (
                        <p
                            title={item.description_text}
                            className="mt-1 min-h-9 text-xs leading-[1.15rem] text-slate-500"
                            style={{
                                display: '-webkit-box',
                                WebkitBoxOrient: 'vertical',
                                WebkitLineClamp: 2,
                                overflow: 'hidden',
                            }}
                        >
                            {item.description_text}
                        </p>
                    ) : (
                        <p className="mt-1 min-h-9 text-xs leading-[1.15rem] text-slate-500">{t('No description added')}</p>
                    )}
                </div>

                <div className="mt-3 flex min-h-6 flex-wrap items-center gap-1.5">
                    {item.prep_time_minutes ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-500">
                            <Icon name="clock" className="h-3 w-3 text-slate-500" strokeWidth={2} />
                            {item.prep_time_minutes} {t('min prep')}
                        </span>
                    ) : null}

                    {item.has_variants ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-500">
                            <Icon name="layers" className="h-3 w-3" strokeWidth={1.9} />
                            {item.variants_count} {t('variants')}
                        </span>
                    ) : null}

                    {item.is_per_kilo ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-1 text-[10px] font-semibold text-teal-700">
                            <Icon name="scale" className="h-3 w-3" strokeWidth={1.9} />
                            {t('Weighed in person')}
                        </span>
                    ) : null}
                </div>

                <div className="mt-auto flex items-end justify-between gap-3 pt-4">
                    <div>
                        <p className="text-[9px] font-bold uppercase tracking-[0.16em] text-slate-500">{t('Selling price')}</p>
                        <p className="mt-0.5 text-lg font-semibold tracking-[-0.025em] text-slate-700">{item.price_range_label}</p>
                    </div>

                    {!showArchived && (
                        <span className={`inline-flex h-8 w-8 items-center justify-center rounded-xl border ${statusTone.soft}`} title={options.find((option) => option.value === status)?.label ?? status}>
                            <span className={`h-2 w-2 rounded-full ${statusTone.dot}`} />
                        </span>
                    )}
                </div>

                {canManageMenu && (
                    <div className="mt-4 border-t border-slate-200 pt-3">
                        {showArchived ? (
                            <button
                                type="button"
                                onClick={() => onRestore(item)}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100"
                            >
                                <Icon name="restore" className="h-4 w-4" strokeWidth={2} />
                                {t('Restore item')}
                            </button>
                        ) : (
                            <div className="grid grid-cols-2 gap-2">
                                <Link
                                    href={route('menu-items.edit', item.id)}
                                    preserveScroll
                                    className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-500 transition hover:border-slate-200 hover:bg-slate-50 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-400/10"
                                >
                                    <Icon name="edit" className="h-3.5 w-3.5" strokeWidth={2} />
                                    {t('Edit')}
                                </Link>

                                <button
                                    type="button"
                                    onClick={() => onArchive(item)}
                                    className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-red-100 bg-white px-3 py-2 text-xs font-bold text-red-600 transition hover:border-red-200 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-100"
                                >
                                    <Icon name="archive" className="h-3.5 w-3.5" strokeWidth={2} />
                                    {t('Archive')}
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </article>
        </>
    );
}

function MetricCard({ icon, label, value, detail, accent = 'brand' }) {
    const accents = {
        brand: 'bg-slate-100 text-slate-600',
        green: 'bg-emerald-50 text-emerald-600',
        amber: 'bg-amber-50 text-amber-700',
        stone: 'bg-slate-100 text-slate-600',
    };

    return (
        <div className="flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 backdrop-blur-sm">
            <div className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${accents[accent]}`}>
                <Icon name={icon} className="h-5 w-5" strokeWidth={1.8} />
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500">{label}</p>
                <div className="mt-0.5 flex min-w-0 items-baseline gap-1.5">
                    <p className="text-lg font-semibold leading-none text-slate-900">{value}</p>
                    {detail ? <p className="truncate text-[10px] font-medium text-slate-500">{detail}</p> : null}
                </div>
            </div>
        </div>
    );
}

function FilterSelect({ label, value, onChange, children }) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{label}</span>
            <span className="relative block">
                <select
                    value={value}
                    onChange={onChange}
                    className="h-11 w-full appearance-none bg-none rounded-xl border border-slate-200 bg-slate-50 py-2 pl-3.5 pr-9 text-sm font-semibold text-slate-500 shadow-sm outline-none transition hover:border-slate-200 focus:border-slate-200 focus:ring-4 focus:ring-slate-400/10"
                >
                    {children}
                </select>
                <Icon name="chevron" className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" strokeWidth={2} />
            </span>
        </label>
    );
}

function FilterChip({ children, onRemove }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-500 shadow-sm">
            {children}
            <button
                type="button"
                onClick={onRemove}
                className="grid h-4 w-4 place-items-center rounded-full text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                aria-label="Remove filter"
            >
                <Icon name="x" className="h-2.5 w-2.5" strokeWidth={2.5} />
            </button>
        </span>
    );
}

function ToastStack({ toasts, onDismiss }) {
    return (
        <div className="pointer-events-none fixed inset-x-4 bottom-4 z-[80] flex flex-col items-end gap-3 sm:left-auto sm:right-5 sm:w-[390px]">
            {toasts.map((toast) => {
                const isError = toast.tone === 'error';

                return (
                    <div
                        key={toast.id}
                        className="pointer-events-auto flex w-full items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm"
                    >
                        <div className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${isError ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'}`}>
                            <Icon name={isError ? 'warning' : 'check'} className="h-[18px] w-[18px]" strokeWidth={2.2} />
                        </div>
                        <p className="min-w-0 flex-1 pt-1 text-sm font-semibold leading-5 text-slate-900">{toast.message}</p>
                        <button
                            type="button"
                            onClick={() => onDismiss(toast.id)}
                            className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-50 hover:text-slate-500"
                            aria-label="Dismiss notification"
                        >
                            <Icon name="x" className="h-3.5 w-3.5" strokeWidth={2.2} />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}

function MenuEmptyState({ title, description, actionLabel, actionHref, onAction, icon = 'menu' }) {
    const actionClasses =
        'inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-400/15';

    return (
        <section className="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-white px-6 py-16 text-center shadow-sm sm:px-10">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(138,51,48,0.10),transparent_34%),radial-gradient(circle_at_bottom_left,rgba(214,185,145,0.16),transparent_38%)]" />
            <div className="relative mx-auto max-w-xl">
                <div className="mx-auto grid h-16 w-16 place-items-center rounded-[1.35rem] border border-slate-200 bg-slate-50 text-slate-700 shadow-sm">
                    <Icon name={icon} className="h-7 w-7" strokeWidth={1.6} />
                </div>
                <h2 className="mt-5 text-2xl font-semibold tracking-[-0.03em] text-slate-900">{title}</h2>
                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{description}</p>

                {actionLabel && (actionHref || onAction) ? (
                    <div className="mt-7">
                        {actionHref ? (
                            <Link href={actionHref} className={actionClasses}>
                                <Icon name="plus" className="h-4 w-4" strokeWidth={2.3} />
                                {actionLabel}
                            </Link>
                        ) : (
                            <button type="button" onClick={onAction} className={actionClasses}>
                                <Icon name="filter" className="h-4 w-4" strokeWidth={2.1} />
                                {actionLabel}
                            </button>
                        )}
                    </div>
                ) : null}
            </div>
        </section>
    );
}

export default function Index({
    items = [],
    categories = [],
    hasCategories = false,
    showArchived = false,
    archivedCount = 0,
    pricingTab = 'fixed',
    pricingCounts = { fixed: 0, per_kilo: 0 },
    filters = {},
    availabilityOptions = [],
    auth,
}) {
    const t = useTranslation();
    const { csrf_token: csrfToken } = usePage().props;
    const canManageMenu = ['superadmin', 'admin'].includes(auth?.user?.role);

    const [search, setSearch] = useState(filters.q ?? '');
    const [statuses, setStatuses] = useState(() => Object.fromEntries(items.map((item) => [item.id, item.availability_status])));
    const [updatingItemIds, setUpdatingItemIds] = useState(() => new Set());
    const [toasts, setToasts] = useState([]);
    const [isFiltering, setIsFiltering] = useState(false);

    const searchTimer = useRef(null);
    const toastTimers = useRef(new Map());

    const options = useMemo(
        () =>
            availabilityOptions.map((option) => ({
                ...option,
                dotClass: DOT_CLASSES[option.value] ?? 'bg-slate-400',
            })),
        [availabilityOptions],
    );

    const groupedItems = useMemo(
        () =>
            categories
                .map((category) => ({
                    category,
                    items: items.filter((item) => String(item.menu_category_id) === String(category.id)),
                }))
                .filter((group) => group.items.length > 0),
        [categories, items],
    );

    const featuredOnly = filters.featured === true || filters.featured === 1 || filters.featured === '1';
    const availableCount = items.filter((item) => (statuses[item.id] ?? item.availability_status) === 'available').length;
    const featuredCount = items.filter((item) => item.is_featured).length;

    const selectedCategory = categories.find((category) => String(category.id) === String(filters.category_id ?? ''));
    const selectedAvailability = options.find((option) => option.value === (filters.availability ?? ''));

    const sortLabels = {
        name_asc: t('Name (A-Z)'),
        price_asc: t('Price (Low to High)'),
        price_desc: t('Price (High to Low)'),
        prep_asc: t('Prep Time (Fast First)'),
        newest: t('Newest First'),
    };

    const hasActiveFilters =
        search.trim() !== '' ||
        (filters.category_id ?? '') !== '' ||
        (filters.availability ?? '') !== '' ||
        (filters.sort ?? '') !== '' ||
        featuredOnly;

    const activeFilterCount = [
        search.trim() !== '',
        (filters.category_id ?? '') !== '',
        (filters.availability ?? '') !== '',
        (filters.sort ?? '') !== '',
        featuredOnly,
    ].filter(Boolean).length;

    const dismissToast = (id) => {
        const timer = toastTimers.current.get(id);
        if (timer) clearTimeout(timer);
        toastTimers.current.delete(id);
        setToasts((current) => current.filter((toast) => toast.id !== id));
    };

    const pushToast = (message, tone = 'success') => {
        const id = `${Date.now()}-${Math.random()}`;
        setToasts((current) => [...current, { id, message, tone }]);

        const timer = setTimeout(() => {
            setToasts((current) => current.filter((toast) => toast.id !== id));
            toastTimers.current.delete(id);
        }, 5000);

        toastTimers.current.set(id, timer);
    };

    useEffect(() => {
        setSearch(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        setStatuses(Object.fromEntries(items.map((item) => [item.id, item.availability_status])));
    }, [items]);

    useEffect(() => {
        if (!window.Echo) return undefined;

        const channel = window.Echo.channel('menu');

        channel.listen('.MenuItemAvailabilityChanged', (event) => {
            setStatuses((current) =>
                event.menu_item_id in current
                    ? { ...current, [event.menu_item_id]: event.availability_status }
                    : current,
            );
        });

        const leave = () => window.Echo?.leave('menu');
        turboCleanup(leave);

        return leave;
    }, []);

    useEffect(
        () => () => {
            clearTimeout(searchTimer.current);
            toastTimers.current.forEach((timer) => clearTimeout(timer));
            toastTimers.current.clear();
        },
        [],
    );

    const submitFilters = (overrides = {}) => {
        clearTimeout(searchTimer.current);

        const data = {
            q: search,
            category_id: filters.category_id ?? '',
            availability: filters.availability ?? '',
            sort: filters.sort ?? '',
            featured: featuredOnly ? 1 : '',
            pricing: pricingTab,
            ...(showArchived ? { archived: 1 } : {}),
            ...overrides,
        };

        router.get(route('menu-items.index'), data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsFiltering(true),
            onFinish: () => setIsFiltering(false),
        });
    };

    const onSearchChange = (value) => {
        setSearch(value);
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => submitFilters({ q: value }), 400);
    };

    const clearFilters = () => {
        clearTimeout(searchTimer.current);
        setSearch('');

        router.get(
            route('menu-items.index'),
            { pricing: pricingTab, ...(showArchived ? { archived: 1 } : {}) },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setIsFiltering(true),
                onFinish: () => setIsFiltering(false),
            },
        );
    };

    const selectAvailability = async (itemId, value) => {
        const previous = statuses[itemId];
        const item = items.find((candidate) => candidate.id === itemId);
        const selectedOption = options.find((option) => option.value === value);

        setStatuses((current) => ({ ...current, [itemId]: value }));
        setUpdatingItemIds((current) => new Set(current).add(itemId));

        try {
            const response = await fetch(route('menu-items.set-availability', itemId), {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({ status: value }),
            });

            if (!response.ok) throw new Error(`Availability update failed with status ${response.status}`);

            pushToast(
                `${item?.name ?? t('Menu item')} · ${selectedOption?.label ?? t('Availability updated')}`,
                'success',
            );
        } catch (error) {
            setStatuses((current) => ({ ...current, [itemId]: previous }));
            pushToast(t('Unable to update availability. Please try again.'), 'error');
        } finally {
            setUpdatingItemIds((current) => {
                const next = new Set(current);
                next.delete(itemId);
                return next;
            });
        }
    };

    const archiveItem = (item) => {
        const confirmed = window.confirm(
            `${t('Archive this item?')}\n${t('You can restore :name later from the Archived tab.').replace(':name', item.name)}`,
        );

        if (!confirmed) return;

        router.delete(route('menu-items.destroy', item.id), {
            preserveScroll: true,
            onSuccess: () => pushToast(`${item.name} · ${t('Archived')}`, 'success'),
            onError: () => pushToast(t('Unable to archive this item. Please try again.'), 'error'),
        });
    };

    const restoreItem = (item) => {
        router.patch(
            route('menu-items.restore', item.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => pushToast(`${item.name} · ${t('Restored')}`, 'success'),
                onError: () => pushToast(t('Unable to restore this item. Please try again.'), 'error'),
            },
        );
    };

    let emptyActionLabel = null;
    let emptyActionHref = null;
    let emptyActionHandler = null;

    if (!showArchived && hasActiveFilters) {
        emptyActionLabel = t('Clear filters');
        emptyActionHandler = clearFilters;
    } else if (!showArchived && !hasCategories && canManageMenu) {
        emptyActionLabel = t('New Category');
        emptyActionHref = route('menu-categories.create');
    } else if (!showArchived && hasCategories && canManageMenu) {
        emptyActionLabel = t('New Item');
        emptyActionHref = route('menu-items.create');
    }

    return (
        <AuthenticatedLayout>
            <Head title={t('Menu Management')} />

            <ToastStack toasts={toasts} onDismiss={dismissToast} />

            <div className="space-y-7 pb-10">
                <header className="rounded-2xl border border-slate-200 bg-white px-5 py-6 shadow-sm sm:px-7">
                    <div className="relative">
                        <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                            <div className="flex max-w-2xl items-start gap-4 sm:gap-5">
                                <div className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-600 shadow-sm backdrop-blur-sm sm:h-16 sm:w-16">
                                    <Icon name="menu" className="h-7 w-7" strokeWidth={1.65} />
                                </div>

                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                                            {t('Catalog workspace')}
                                        </span>
                                        <span className="h-1 w-1 rounded-full bg-slate-50" />
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                                            <span className={`h-1.5 w-1.5 rounded-full ${showArchived ? 'bg-slate-300' : 'bg-emerald-300'}`} />
                                            {showArchived ? t('Archived view') : t('Active menu')}
                                        </span>
                                    </div>

                                    <h1 className="mt-2 text-2xl font-semibold tracking-[-0.035em] text-slate-900 sm:text-3xl">
                                        {t('Menu Management')}
                                    </h1>
                                    <p className="mt-2 max-w-xl text-sm leading-6 text-slate-600 sm:text-[15px]">
                                        {t('Manage pricing, availability, variants and presentation from one organized catalog.')}
                                    </p>
                                </div>
                            </div>

                            {canManageMenu && (
                                <div className="flex flex-col gap-2.5 sm:flex-row xl:justify-end">
                                    <Link
                                        href={route('menu-categories.index')}
                                        className="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-600 backdrop-blur-sm transition hover:-translate-y-0.5 hover:bg-slate-50 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-300"
                                    >
                                        <Icon name="categories" className="h-4 w-4" strokeWidth={1.9} />
                                        {t('Manage Categories')}
                                    </Link>

                                    {!hasCategories ? (
                                        <span
                                            aria-disabled="true"
                                            className="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-600"
                                            title={t('Create a category before adding menu items.')}
                                        >
                                            <Icon name="plus" className="h-4 w-4" strokeWidth={2.2} />
                                            {t('New Item')}
                                        </span>
                                    ) : (
                                        <Link
                                            href={route('menu-items.create')}
                                            className="group inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-500 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-300"
                                        >
                                            <span className="grid h-6 w-6 place-items-center rounded-lg bg-slate-700/10">
                                                <Icon name="plus" className="h-3.5 w-3.5" strokeWidth={2.5} />
                                            </span>
                                            {t('New Item')}
                                            <Icon name="arrow" className="h-4 w-4 transition-transform group-hover:translate-x-0.5" strokeWidth={2} />
                                        </Link>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <MetricCard icon="box" label={t('Items shown')} value={items.length} detail={hasActiveFilters ? t('filtered') : t('current view')} />
                            <MetricCard icon="categories" label={t('Categories')} value={categories.length} detail={t('organized groups')} accent="stone" />
                            <MetricCard icon="check" label={t('Available now')} value={availableCount} detail={t('ready to sell')} accent="green" />
                            <MetricCard icon="archive" label={t('Archived')} value={archivedCount} detail={featuredCount ? `${featuredCount} ${t('featured shown')}` : t('stored items')} accent="amber" />
                        </div>
                    </div>
                </header>

                {!hasCategories && (
                    <section className="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-slate-50 p-5 shadow-sm sm:p-6">
                        <div className="pointer-events-none absolute -right-10 -top-12 h-40 w-40 rounded-full bg-amber-200/35 blur-3xl" />
                        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-amber-200 bg-white/80 text-amber-700 shadow-sm">
                                <Icon name="warning" className="h-5 w-5" strokeWidth={1.9} />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold text-slate-900">{t('Build your menu structure first')}</p>
                                <p className="mt-1 text-sm leading-6 text-slate-500">
                                    {t('Create at least one category before adding menu items to the catalog.')}
                                </p>
                            </div>
                            {canManageMenu && (
                                <Link
                                    href={route('menu-categories.create')}
                                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-400/15"
                                >
                                    <Icon name="plus" className="h-4 w-4" strokeWidth={2.2} />
                                    {t('New Category')}
                                </Link>
                            )}
                        </div>
                    </section>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm sm:p-5">
                    <div className="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="grid h-10 w-10 place-items-center rounded-xl bg-slate-50 text-slate-700">
                                <Icon name="filter" className="h-5 w-5" strokeWidth={1.8} />
                            </div>
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900">{t('Find and organize items')}</h2>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {activeFilterCount > 0
                                        ? `${activeFilterCount} ${activeFilterCount === 1 ? t('filter active') : t('filters active')}`
                                        : t('Browse the complete menu catalog')}
                                </p>
                            </div>
                        </div>

                        <div className="inline-flex w-fit rounded-2xl border border-slate-200 bg-slate-50 p-1">
                            <Link
                                href={route('menu-items.index', { pricing: pricingTab })}
                                className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition ${
                                    !showArchived
                                        ? 'bg-white text-slate-700 shadow-sm'
                                        : 'text-slate-500 hover:bg-white/70 hover:text-slate-500'
                                }`}
                            >
                                <span className={`h-1.5 w-1.5 rounded-full ${!showArchived ? 'bg-emerald-500' : 'bg-slate-50'}`} />
                                {t('Active')}
                            </Link>
                            <Link
                                href={route('menu-items.index', { pricing: pricingTab, archived: 1 })}
                                className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition ${
                                    showArchived
                                        ? 'bg-white text-slate-700 shadow-sm'
                                        : 'text-slate-500 hover:bg-white/70 hover:text-slate-500'
                                }`}
                            >
                                <Icon name="archive" className="h-3.5 w-3.5" strokeWidth={2} />
                                {t('Archived')}
                                <span className="grid min-w-5 place-items-center rounded-full bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">
                                    {archivedCount}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <div className="mb-4 inline-flex w-fit rounded-2xl border border-slate-200 bg-slate-50 p-1">
                        <Link
                            href={route('menu-items.index', showArchived ? { pricing: 'fixed', archived: 1 } : { pricing: 'fixed' })}
                            className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition ${
                                pricingTab === 'fixed'
                                    ? 'bg-white text-slate-700 shadow-sm'
                                    : 'text-slate-500 hover:bg-white/70 hover:text-slate-500'
                            }`}
                        >
                            {t('Fixed Price')}
                            <span className="grid min-w-5 place-items-center rounded-full bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">
                                {pricingCounts.fixed}
                            </span>
                        </Link>
                        <Link
                            href={route('menu-items.index', showArchived ? { pricing: 'per_kilo', archived: 1 } : { pricing: 'per_kilo' })}
                            className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition ${
                                pricingTab === 'per_kilo'
                                    ? 'bg-white text-slate-700 shadow-sm'
                                    : 'text-slate-500 hover:bg-white/70 hover:text-slate-500'
                            }`}
                        >
                            <Icon name="scale" className="h-3.5 w-3.5" strokeWidth={2} />
                            {t('Per Kilo')}
                            <span className="grid min-w-5 place-items-center rounded-full bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">
                                {pricingCounts.per_kilo}
                            </span>
                        </Link>
                    </div>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitFilters({ q: search });
                        }}
                        className="grid gap-3 xl:grid-cols-[minmax(320px,1fr)_190px_190px_210px_auto]"
                    >
                        <label className="block min-w-0">
                            <span className="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">{t('Search catalog')}</span>
                            <span className="relative block">
                                <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-500" strokeWidth={1.8} />
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) => onSearchChange(event.target.value)}
                                    placeholder={t('Search by item name or description...')}
                                    autoComplete="off"
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-10 pr-10 text-sm font-medium text-slate-500 placeholder:text-slate-500 shadow-sm outline-none transition hover:border-slate-200 focus:border-slate-200 focus:ring-4 focus:ring-slate-400/10"
                                />
                                {search ? (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearch('');
                                            submitFilters({ q: '' });
                                        }}
                                        className="absolute right-3 top-1/2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                                        aria-label={t('Clear search')}
                                    >
                                        <Icon name="x" className="h-3.5 w-3.5" strokeWidth={2.2} />
                                    </button>
                                ) : null}
                            </span>
                        </label>

                        <FilterSelect
                            label={t('Category')}
                            value={filters.category_id ?? ''}
                            onChange={(event) => submitFilters({ category_id: event.target.value })}
                        >
                            <option value="">{t('All Categories')}</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </FilterSelect>

                        <FilterSelect
                            label={t('Availability')}
                            value={filters.availability ?? ''}
                            onChange={(event) => submitFilters({ availability: event.target.value })}
                        >
                            <option value="">{t('Any Availability')}</option>
                            {options.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </FilterSelect>

                        <FilterSelect
                            label={t('Sort by')}
                            value={filters.sort ?? ''}
                            onChange={(event) => submitFilters({ sort: event.target.value })}
                        >
                            <option value="">{t('Sort Order')}</option>
                            <option value="name_asc">{t('Name (A-Z)')}</option>
                            <option value="price_asc">{t('Price (Low to High)')}</option>
                            <option value="price_desc">{t('Price (High to Low)')}</option>
                            <option value="prep_asc">{t('Prep Time (Fast First)')}</option>
                            <option value="newest">{t('Newest First')}</option>
                        </FilterSelect>

                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={isFiltering}
                                className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-70 xl:w-auto"
                            >
                                {isFiltering ? (
                                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/35 border-t-white" />
                                ) : (
                                    <Icon name="search" className="h-4 w-4" strokeWidth={2} />
                                )}
                                {t('Apply')}
                            </button>
                        </div>
                    </form>

                    <div className="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                role="switch"
                                aria-checked={featuredOnly}
                                onClick={() => submitFilters({ featured: featuredOnly ? '' : 1 })}
                                className={`inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-bold transition ${
                                    featuredOnly
                                        ? 'border-amber-300 bg-amber-50 text-amber-800'
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-amber-300 hover:bg-amber-50/60'
                                }`}
                            >
                                <span className={`relative h-5 w-9 rounded-full transition ${featuredOnly ? 'bg-amber-400' : 'bg-slate-50'}`}>
                                    <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-all ${featuredOnly ? 'left-[18px]' : 'left-0.5'}`} />
                                </span>
                                <Icon name="sparkles" className="h-3.5 w-3.5" strokeWidth={1.9} />
                                {t('Featured only')}
                            </button>

                            {search.trim() ? <FilterChip onRemove={() => { setSearch(''); submitFilters({ q: '' }); }}>{t('Search')}: “{search.trim()}”</FilterChip> : null}
                            {selectedCategory ? <FilterChip onRemove={() => submitFilters({ category_id: '' })}>{selectedCategory.name}</FilterChip> : null}
                            {selectedAvailability ? <FilterChip onRemove={() => submitFilters({ availability: '' })}>{selectedAvailability.label}</FilterChip> : null}
                            {(filters.sort ?? '') !== '' ? <FilterChip onRemove={() => submitFilters({ sort: '' })}>{sortLabels[filters.sort] ?? filters.sort}</FilterChip> : null}

                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                >
                                    <Icon name="x" className="h-3.5 w-3.5" strokeWidth={2.2} />
                                    {t('Clear all')}
                                </button>
                            )}
                        </div>

                        <p className="text-xs font-semibold text-slate-500">
                            <span className="font-semibold text-slate-900">{items.length}</span>{' '}
                            {items.length === 1 ? t('item shown') : t('items shown')}
                        </p>
                    </div>
                </section>

                {items.length === 0 ? (
                    <MenuEmptyState
                        icon={showArchived ? 'archive' : 'search'}
                        title={showArchived ? t('No archived items') : t('No menu items found')}
                        description={
                            showArchived
                                ? t('Items you archive will appear here and can be restored at any time.')
                                : hasActiveFilters
                                  ? t('No items match the current search and filter combination.')
                                  : t('Add your first menu item to begin building the catalog.')
                        }
                        actionLabel={emptyActionLabel}
                        actionHref={emptyActionHref}
                        onAction={emptyActionHandler}
                    />
                ) : (
                    <div className="space-y-10">
                        {groupedItems.map((group, groupIndex) => (
                            <section key={group.category.id} id={`menu-category-${group.category.id}`} className="scroll-mt-6">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm">
                                        <span className="text-xs font-semibold">{String(groupIndex + 1).padStart(2, '0')}</span>
                                    </div>
                                    <div className="min-w-0">
                                        <h2 className="truncate text-lg font-semibold tracking-[-0.025em] text-slate-900 sm:text-xl">{group.category.name}</h2>
                                        <p className="mt-0.5 text-xs font-medium text-slate-500">
                                            {group.items.length} {group.items.length === 1 ? t('item') : t('items')}
                                        </p>
                                    </div>
                                    <div className="h-px flex-1 bg-gradient-to-r from-[#DED1C5] to-transparent" />
                                    <span className="hidden items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500 sm:inline-flex">
                                        <Icon name="tag" className="h-3 w-3 text-slate-700" strokeWidth={2} />
                                        {t('Category')}
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                                    {group.items.map((item) => (
                                        <ItemCard
                                            key={item.id}
                                            item={item}
                                            canManageMenu={canManageMenu}
                                            showArchived={showArchived}
                                            statuses={statuses}
                                            options={options}
                                            updating={updatingItemIds.has(item.id)}
                                            onSelectAvailability={selectAvailability}
                                            onArchive={archiveItem}
                                            onRestore={restoreItem}
                                            t={t}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}