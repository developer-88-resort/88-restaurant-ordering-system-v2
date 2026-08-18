import { router } from '@inertiajs/react';

function normalizeStatus(status) {
    if (status && typeof status === 'object') {
        return String(status.value ?? status.status ?? '');
    }

    return String(status ?? '');
}

function getStatusTheme(status) {
    const value = normalizeStatus(status).toLowerCase();

    const themes = {
        available: {
            bar: 'bg-emerald-500',
            icon: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
            badge: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            dot: 'bg-emerald-500',
            panel: 'border-emerald-100 bg-emerald-50/70',
            select: 'text-emerald-800',
        },
        occupied: {
            bar: 'bg-orange-500',
            icon: 'bg-orange-50 text-orange-700 ring-1 ring-orange-100',
            badge: 'border-orange-200 bg-orange-50 text-orange-700',
            dot: 'bg-orange-500',
            panel: 'border-orange-100 bg-orange-50/70',
            select: 'text-orange-800',
        },
        reserved: {
            bar: 'bg-blue-500',
            icon: 'bg-blue-50 text-blue-700 ring-1 ring-blue-100',
            badge: 'border-blue-200 bg-blue-50 text-blue-700',
            dot: 'bg-blue-500',
            panel: 'border-blue-100 bg-blue-50/70',
            select: 'text-blue-800',
        },
        maintenance: {
            bar: 'bg-red-500',
            icon: 'bg-red-50 text-red-700 ring-1 ring-red-100',
            badge: 'border-red-200 bg-red-50 text-red-700',
            dot: 'bg-red-500',
            panel: 'border-red-100 bg-red-50/70',
            select: 'text-red-800',
        },
        disabled: {
            bar: 'bg-gray-500',
            icon: 'bg-gray-100 text-gray-700 ring-1 ring-gray-200',
            badge: 'border-gray-200 bg-gray-100 text-gray-700',
            dot: 'bg-gray-500',
            panel: 'border-gray-200 bg-gray-50',
            select: 'text-gray-800',
        },
    };

    return (
        themes[value] ?? {
            bar: 'bg-[#8A3330]',
            icon: 'bg-[#F3E1DC] text-[#8A3330] ring-1 ring-[#E9D3CC]',
            badge: 'border-[#E6CBC3] bg-[#F8EAE6] text-[#8A3330]',
            dot: 'bg-[#8A3330]',
            panel: 'border-[#EADBD2] bg-[#FAF6EE]',
            select: 'text-[#6E2927]',
        }
    );
}

function SpaceIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.8"
            stroke="currentColor"
            className="h-5 w-5"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6.75h16v4.5H4v-4.5z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M7 11.25v7.5m10-7.5v7.5M5.5 18.75h13" />
        </svg>
    );
}

function QrIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.8"
            stroke="currentColor"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75h6v6h-6v-6zm10.5 0h6v6h-6v-6zm-10.5 10.5h6v6h-6v-6z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M14.25 14.25h2.25v2.25h-2.25v-2.25zm3.75 0h2.25v2.25H18v-2.25zm-3.75 3.75h2.25v2.25h-2.25V18zm3.75 0h2.25v2.25H18V18z" />
        </svg>
    );
}

function EditIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.8"
            stroke="currentColor"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 7.125 16.862 4.487M18 14.25v4.125A2.625 2.625 0 0 1 15.375 21h-9.75A2.625 2.625 0 0 1 3 18.375v-9.75A2.625 2.625 0 0 1 5.625 6H9.75" />
        </svg>
    );
}

function DeleteIcon() {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.8"
            stroke="currentColor"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-10.978.397c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m6.478.165V4.477c0-1.18-.91-2.165-2.09-2.202a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.202v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
        </svg>
    );
}

export default function ListView({
    t,
    spaces,
    canManageSpaces,
    statusOptions = [],
    onStatusChange,
}) {
    const handleDelete = (space) => {
        const confirmed = window.confirm(
            `${t('Delete this space?')}\n${t('This will permanently remove :name.').replace(':name', space.name)}`,
        );

        if (!confirmed) return;

        router.delete(route('spaces.destroy', space.id), {
            preserveScroll: true,
        });
    };

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
            {spaces.map((space) => {
                const statusValue = normalizeStatus(space.status);
                const statusMeta = statusOptions.find(
                    (option) => String(option.value) === statusValue,
                );
                const statusLabel =
                    space.status_label ?? statusMeta?.label ?? statusValue;
                const theme = getStatusTheme(statusValue);
                const hasCurrentStatus = statusOptions.some(
                    (option) => String(option.value) === statusValue,
                );

                return (
                    <article
                        key={space.id}
                        className="group relative overflow-hidden rounded-[1.6rem] border border-[#E5DDD0] bg-white shadow-[0_18px_45px_-38px_rgba(55,35,30,0.72)] transition duration-200 hover:-translate-y-1 hover:border-[#D4C3B3] hover:shadow-[0_30px_65px_-42px_rgba(55,35,30,0.6)]"
                    >
                        <div className="p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex min-w-0 items-center gap-3">
                                    <div
                                        className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${theme.icon}`}
                                    >
                                        <SpaceIcon />
                                    </div>

                                    <div className="min-w-0">
                                        <h3 className="truncate text-base font-bold tracking-[-0.02em] text-[#241917]">
                                            {space.name}
                                        </h3>
                                        <p className="mt-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#9A8C85]">
                                            {t('Space')} #{space.id}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    className={`inline-flex max-w-[44%] shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] ${theme.badge}`}
                                    title={statusLabel}
                                >
                                    <span
                                        className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                                            space.status_capsule_class ?? theme.dot
                                        }`}
                                    />
                                    <span className="truncate">{statusLabel}</span>
                                </span>
                            </div>

                            <div className={`mt-5 rounded-2xl border p-3.5 ${theme.panel}`}>
                                <div className="mb-2.5 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#93857E]">
                                            {t('Current status')}
                                        </p>
                                        <p className="mt-0.5 text-xs font-semibold text-[#554943]">
                                            {t('Change availability instantly')}
                                        </p>
                                    </div>

                                    <span
                                        className={`h-2.5 w-2.5 shrink-0 rounded-full shadow-[0_0_0_4px_rgba(255,255,255,0.78)] ${
                                            space.status_capsule_class ?? theme.dot
                                        }`}
                                    />
                                </div>

                                <div className="relative">
                                    {/* bg-none: @tailwindcss/forms already paints its own
                                        arrow into every <select> via background-image — left
                                        as-is, it sat directly under the custom SVG chevron
                                        below and doubled up into a broken-looking icon. */}
                                    <select
                                        value={statusValue}
                                        onChange={(event) =>
                                            onStatusChange(space.id, event.target.value)
                                        }
                                        className={`h-11 w-full appearance-none bg-none rounded-xl border border-white/90 bg-white pl-3.5 pr-10 text-sm font-bold shadow-sm focus:border-[#8A3330] focus:ring-[#8A3330]/20 ${theme.select}`}
                                    >
                                        {!hasCurrentStatus && statusValue && (
                                            <option value={statusValue}>{statusLabel}</option>
                                        )}

                                        {statusOptions.map((status) => (
                                            <option
                                                key={status.value}
                                                value={String(status.value)}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        strokeWidth="2"
                                        stroke="currentColor"
                                        className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#7E716B]"
                                        aria-hidden="true"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 9-7.5 7.5L4.5 9" />
                                    </svg>
                                </div>
                            </div>

                            {canManageSpaces && (
                                <div className="mt-4 flex items-center gap-2 border-t border-[#EEE6DC] pt-4">
                                    <a
                                        href={route('spaces.print', space.id)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        data-turbo="false"
                                        className="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-xl border border-[#E2D7CA] bg-white px-3 text-xs font-bold text-[#6A5C55] transition hover:border-[#8A3330]/35 hover:bg-[#FAF6EE] hover:text-[#8A3330]"
                                    >
                                        <QrIcon />
                                        <span className="truncate">{t('QR Code')}</span>
                                    </a>

                                    <a
                                        href={route('spaces.edit', space.id)}
                                        data-turbo="false"
                                        className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#E2D7CA] bg-white text-[#8A3330] transition hover:border-[#8A3330]/35 hover:bg-[#FAF6EE]"
                                        aria-label={`${t('Edit')} ${space.name}`}
                                        title={t('Edit')}
                                    >
                                        <EditIcon />
                                    </a>

                                    <button
                                        type="button"
                                        onClick={() => handleDelete(space)}
                                        className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-rose-100 bg-rose-50 text-rose-600 transition hover:border-rose-200 hover:bg-rose-100 hover:text-rose-700"
                                        aria-label={`${t('Delete')} ${space.name}`}
                                        title={t('Delete')}
                                    >
                                        <DeleteIcon />
                                    </button>
                                </div>
                            )}
                        </div>
                    </article>
                );
            })}
        </div>
    );
}