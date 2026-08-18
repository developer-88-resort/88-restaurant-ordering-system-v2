import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import ListView from './ListView';

function newSpaceHref(area) {
    return area.default_category_id
        ? route('spaces.create', { category_id: area.default_category_id })
        : route('spaces.create', { area_id: area.id });
}

function normalizeStatus(status) {
    if (status && typeof status === 'object') {
        return String(status.value ?? status.status ?? '');
    }

    return String(status ?? '');
}

function isAvailableStatus(status) {
    return normalizeStatus(status).toLowerCase() === 'available';
}

function SpaceIcon({ className = 'h-6 w-6' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.7"
            stroke="currentColor"
            className={className}
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6.75h16v4.5H4v-4.5z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M7 11.25v7.5m10-7.5v7.5M5.5 18.75h13" />
        </svg>
    );
}

function BuildingIcon({ className = 'h-6 w-6' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.7"
            stroke="currentColor"
            className={className}
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 21h16.5M5.25 3.75h13.5V21H5.25V3.75z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 7.5h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5m-7.5 3.75h1.5m4.5 0h1.5" />
        </svg>
    );
}

function CheckIcon({ className = 'h-6 w-6' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.8"
            stroke="currentColor"
            className={className}
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
        </svg>
    );
}

function PlusIcon({ className = 'h-4 w-4' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="2.2"
            stroke="currentColor"
            className={className}
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    );
}

function EmptyWorkspace({ t, canManageSpaces }) {
    return (
        <section className="relative overflow-hidden rounded-[2rem] border border-[#E6DCCF] bg-white px-6 py-14 shadow-[0_28px_80px_-54px_rgba(55,35,30,0.65)] sm:px-10 sm:py-16">
            <div className="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-[#8A3330]/10 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-[#F3E1DC] blur-3xl" />

            <div className="relative mx-auto flex max-w-2xl flex-col items-center text-center">
                <div className="grid h-16 w-16 place-items-center rounded-[1.4rem] bg-[#241917] text-white shadow-[0_20px_42px_-22px_rgba(36,25,23,0.9)]">
                    <BuildingIcon className="h-8 w-8" />
                </div>

                <span className="mt-6 inline-flex items-center gap-2 rounded-full border border-[#8A3330]/15 bg-[#8A3330]/5 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-[#8A3330]">
                    <span className="h-1.5 w-1.5 rounded-full bg-[#8A3330]" />
                    {t('Space setup')}
                </span>

                <h2 className="mt-5 text-3xl font-bold tracking-[-0.04em] text-[#241917] sm:text-4xl">
                    {t('Create your first area')}
                </h2>

                <p className="mt-4 max-w-xl text-sm leading-7 text-[#756862] sm:text-base">
                    {t('Add an area such as Cottages, Dining Area, or Rooms, then organize the individual spaces inside it.')}
                </p>

                {canManageSpaces && (
                    <a
                        href={route('areas.create')}
                        data-turbo="false"
                        className="mt-8 inline-flex items-center justify-center gap-2 rounded-2xl bg-[#8A3330] px-5 py-3.5 text-sm font-bold text-white shadow-[0_16px_34px_-18px_rgba(138,51,48,0.9)] transition duration-200 hover:-translate-y-0.5 hover:bg-[#742927] focus:outline-none focus:ring-4 focus:ring-[#8A3330]/20"
                    >
                        <PlusIcon />
                        {t('New Area')}
                    </a>
                )}
            </div>
        </section>
    );
}

function EmptyArea({ t, area, canManageSpaces }) {
    return (
        <section className="relative overflow-hidden rounded-[1.75rem] border border-dashed border-[#D8C9B9] bg-white px-6 py-12 text-center shadow-[0_20px_55px_-46px_rgba(55,35,30,0.55)]">
            <div className="pointer-events-none absolute -right-12 -top-16 h-48 w-48 rounded-full bg-[#F3E1DC] blur-3xl" />

            <div className="relative mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#F3E1DC] text-[#8A3330]">
                <SpaceIcon className="h-7 w-7" />
            </div>

            <h3 className="relative mt-5 text-xl font-bold tracking-tight text-[#241917]">
                {t('No spaces in this area yet')}
            </h3>

            <p className="relative mx-auto mt-2 max-w-md text-sm leading-6 text-[#786B65]">
                {t('Add individual spaces so customers and orders can be assigned to the correct location.')}
            </p>

            {canManageSpaces && (
                <a
                    href={newSpaceHref(area)}
                    data-turbo="false"
                    className="relative mt-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-[#8A3330] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#742927] focus:outline-none focus:ring-4 focus:ring-[#8A3330]/20"
                >
                    <PlusIcon />
                    {t('New Space')}
                </a>
            )}
        </section>
    );
}

function NoResults({ t, onClear }) {
    return (
        <section className="rounded-[1.5rem] border border-[#E5DDD0] bg-white px-6 py-10 text-center shadow-[0_18px_45px_-40px_rgba(55,35,30,0.7)]">
            <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#F7EFE7] text-[#8A3330]">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    strokeWidth="1.8"
                    stroke="currentColor"
                    className="h-6 w-6"
                    aria-hidden="true"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0z" />
                </svg>
            </div>

            <h3 className="mt-4 text-lg font-bold text-[#241917]">{t('No matching spaces')}</h3>
            <p className="mt-1 text-sm text-[#786B65]">
                {t('Try a different search term or status filter.')}
            </p>

            <button
                type="button"
                onClick={onClear}
                className="mt-5 inline-flex items-center justify-center rounded-xl border border-[#DCCFC1] bg-white px-4 py-2.5 text-sm font-bold text-[#8A3330] transition hover:border-[#8A3330]/40 hover:bg-[#FAF6EE]"
            >
                {t('Clear filters')}
            </button>
        </section>
    );
}

export default function Index({ areas = [], activeAreaId, canManageSpaces, statusOptions = [] }) {
    const t = useTranslation();
    const [activeArea, setActiveArea] = useState(String(activeAreaId ?? areas[0]?.id ?? ''));
    const [statusOverrides, setStatusOverrides] = useState({});
    const [toasts, setToasts] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');

    const pushToast = useCallback((message) => {
        if (!message) return;

        const id = Date.now() + Math.random();
        setToasts((current) => [...current, { id, message }]);

        window.setTimeout(() => {
            setToasts((current) => current.filter((toast) => toast.id !== id));
        }, 6000);
    }, []);

    const dismissToast = (id) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    };

    const applyStatusOverrides = useCallback(
        (spaceIds, statusValue) => {
            const meta = statusOptions.find(
                (status) => String(status.value) === String(statusValue),
            );

            if (!meta || !Array.isArray(spaceIds)) return;

            setStatusOverrides((current) => {
                const next = { ...current };

                spaceIds.forEach((id) => {
                    next[id] = {
                        status: meta.value,
                        status_label: meta.label,
                        status_capsule_class:
                            meta.capsule_class ?? meta.status_capsule_class ?? '',
                    };
                });

                return next;
            });
        },
        [statusOptions],
    );

    useEffect(() => {
        if (areas.length === 0) {
            setActiveArea('');
            return;
        }

        const activeAreaStillExists = areas.some(
            (area) => String(area.id) === String(activeArea),
        );

        if (!activeAreaStillExists) {
            setActiveArea(String(activeAreaId ?? areas[0].id));
        }
    }, [areas, activeArea, activeAreaId]);

    useEffect(() => {
        if (!window.Echo?.private) return undefined;

        const channel = window.Echo.private('spaces');

        channel.listen('.SpaceOccupancyChanged', (event) => {
            pushToast(event.message);
            applyStatusOverrides(event.space_ids ?? [], event.status);
        });

        const leave = () => window.Echo?.leave('spaces');
        turboCleanup(leave);

        return leave;
    }, [applyStatusOverrides, pushToast]);

    const mergedAreas = useMemo(
        () =>
            areas.map((area) => ({
                ...area,
                spaces: (area.spaces ?? []).map((space) => ({
                    ...space,
                    ...(statusOverrides[space.id] ?? {}),
                })),
            })),
        [areas, statusOverrides],
    );

    const areaSummaries = useMemo(
        () =>
            mergedAreas.map((area) => {
                const spaces = area.spaces ?? [];
                const available = spaces.filter((space) => isAvailableStatus(space.status)).length;

                return {
                    id: String(area.id),
                    total: spaces.length,
                    available,
                };
            }),
        [mergedAreas],
    );

    const currentArea = useMemo(
        () =>
            mergedAreas.find((area) => String(area.id) === String(activeArea)) ??
            mergedAreas[0],
        [mergedAreas, activeArea],
    );

    const currentSpaces = currentArea?.spaces ?? [];
    const currentSummary = areaSummaries.find(
        (summary) => summary.id === String(currentArea?.id),
    ) ?? { total: 0, available: 0 };

    const totalSpaces = areaSummaries.reduce((sum, summary) => sum + summary.total, 0);
    const totalAvailable = areaSummaries.reduce(
        (sum, summary) => sum + summary.available,
        0,
    );
    const currentUnavailable = Math.max(
        currentSummary.total - currentSummary.available,
        0,
    );
    const availabilityRate = currentSummary.total
        ? Math.round((currentSummary.available / currentSummary.total) * 100)
        : 0;

    const filteredSpaces = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        return currentSpaces.filter((space) => {
            const statusValue = normalizeStatus(space.status);
            const statusLabel = String(space.status_label ?? '').toLowerCase();
            const matchesStatus =
                statusFilter === 'all' || statusValue === statusFilter;
            const matchesSearch =
                query === '' ||
                String(space.name ?? '').toLowerCase().includes(query) ||
                statusValue.toLowerCase().includes(query) ||
                statusLabel.includes(query);

            return matchesStatus && matchesSearch;
        });
    }, [currentSpaces, searchQuery, statusFilter]);

    const handleAreaChange = (areaId) => {
        setActiveArea(String(areaId));
        setSearchQuery('');
        setStatusFilter('all');
    };

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
    };

    const handleStatusChange = (spaceId, status) => {
        const previousSpace = currentSpaces.find(
            (space) => String(space.id) === String(spaceId),
        );
        const previousOverride = statusOverrides[spaceId];

        applyStatusOverrides([spaceId], status);

        router.patch(
            route('spaces.update-status', spaceId),
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['areas'],
                onSuccess: () => {
                    setStatusOverrides((current) => {
                        const next = { ...current };
                        delete next[spaceId];
                        return next;
                    });
                },
                onError: () => {
                    setStatusOverrides((current) => {
                        const next = { ...current };

                        if (previousOverride) {
                            next[spaceId] = previousOverride;
                        } else if (previousSpace) {
                            next[spaceId] = {
                                status: previousSpace.status,
                                status_label: previousSpace.status_label,
                                status_capsule_class:
                                    previousSpace.status_capsule_class,
                            };
                        } else {
                            delete next[spaceId];
                        }

                        return next;
                    });

                    pushToast(t('Unable to update the space status.'));
                },
            },
        );
    };

    if (areas.length === 0) {
        return (
            <AuthenticatedLayout>
                <Head title={t('Spaces')} />

                <div className="space-y-6">
                    <section className="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-7 text-white shadow-[0_28px_70px_-38px_rgba(36,25,23,0.9)] sm:px-8">
                        <div className="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-[#A84742]/35 blur-3xl" />
                        <div className="relative flex items-center gap-4">
                            <div className="grid h-14 w-14 place-items-center rounded-2xl border border-white/15 bg-white/10">
                                <BuildingIcon className="h-7 w-7" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-[-0.03em]">
                                    {t('Spaces')}
                                </h1>
                                <p className="mt-1 text-sm text-white/60">
                                    {t('Organize and monitor every guest location.')}
                                </p>
                            </div>
                        </div>
                    </section>

                    <EmptyWorkspace t={t} canManageSpaces={canManageSpaces} />
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout>
            <Head title={t('Spaces')} />

            <div className="fixed inset-x-4 bottom-4 z-[60] flex flex-col gap-3 sm:left-auto sm:right-4 sm:w-96">
                {toasts.map((toast) => (
                    <div
                        key={toast.id}
                        className="flex items-start gap-3 rounded-2xl border border-[#E5DDD0] bg-white/95 p-3.5 shadow-[0_24px_60px_-28px_rgba(55,35,30,0.7)] backdrop-blur-md animate-fade-slide-up"
                    >
                        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F3E1DC] text-[#8A3330]">
                            <SpaceIcon className="h-5 w-5" />
                        </div>

                        <p className="min-w-0 flex-1 pt-1 text-sm font-semibold leading-6 text-[#3C302B]">
                            {toast.message}
                        </p>

                        <button
                            type="button"
                            onClick={() => dismissToast(toast.id)}
                            className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[#9B8E87] transition hover:bg-[#F7F1EA] hover:text-[#241917]"
                            aria-label={t('Dismiss')}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                strokeWidth="2"
                                stroke="currentColor"
                                className="h-4 w-4"
                                aria-hidden="true"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" d="m6 6 12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>
                ))}
            </div>

            <div className="space-y-6">
                <section className="relative isolate overflow-hidden rounded-[2rem] bg-[#241917] px-6 py-7 text-white shadow-[0_32px_75px_-42px_rgba(36,25,23,0.95)] sm:px-8 sm:py-8">
                    <div
                        className="pointer-events-none absolute inset-0 opacity-[0.07]"
                        style={{
                            backgroundImage:
                                'linear-gradient(rgba(255,255,255,.75) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.75) 1px, transparent 1px)',
                            backgroundSize: '28px 28px',
                        }}
                    />
                    <div className="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full bg-[#A84742]/40 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-white/5 blur-3xl" />

                    <div className="relative flex flex-col gap-7 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-2xl">
                            <div className="flex items-center gap-4">
                                <div className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 backdrop-blur-sm sm:h-16 sm:w-16">
                                    <BuildingIcon className="h-7 w-7 sm:h-8 sm:w-8" />
                                </div>

                                <div>
                                    <div className="flex flex-wrap items-center gap-2.5">
                                        <h1 className="text-2xl font-bold tracking-[-0.035em] sm:text-3xl">
                                            {t('Spaces')}
                                        </h1>
                                        <span className="rounded-full border border-white/10 bg-white/10 px-2.5 py-1 text-[11px] font-bold text-white/75 backdrop-blur-sm">
                                            {totalSpaces} {t('total')}
                                        </span>
                                    </div>
                                    <p className="mt-1.5 text-sm leading-6 text-white/60 sm:text-base">
                                        {t('Monitor availability, update occupancy, and manage QR access from one workspace.')}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-semibold text-white/80 backdrop-blur-sm">
                                <span className="relative flex h-2.5 w-2.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-35" />
                                    <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300" />
                                </span>
                                {t('Live occupancy updates')}
                            </div>

                            {canManageSpaces && (
                                <a
                                    href={route('areas.index')}
                                    data-turbo="false"
                                    className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-bold text-[#7E302D] shadow-[0_12px_28px_-16px_rgba(0,0,0,0.65)] transition hover:-translate-y-0.5 hover:bg-[#FFF7F3] focus:outline-none focus:ring-4 focus:ring-white/20"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        strokeWidth="1.9"
                                        stroke="currentColor"
                                        className="h-4 w-4"
                                        aria-hidden="true"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18v-5.25" />
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 15 20.25 3.75m0 0H15m5.25 0V9" />
                                    </svg>
                                    {t('Manage Areas')}
                                </a>
                            )}
                        </div>
                    </div>

                    <div className="relative mt-7 grid gap-3 sm:grid-cols-3">
                        <div className="rounded-2xl border border-white/10 bg-white/[0.08] p-4 backdrop-blur-sm">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">
                                        {t('Areas')}
                                    </p>
                                    <p className="mt-1 text-2xl font-bold">{areas.length}</p>
                                </div>
                                <span className="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-white/75">
                                    <BuildingIcon className="h-5 w-5" />
                                </span>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-white/10 bg-white/[0.08] p-4 backdrop-blur-sm">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">
                                        {t('Total spaces')}
                                    </p>
                                    <p className="mt-1 text-2xl font-bold">{totalSpaces}</p>
                                </div>
                                <span className="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-white/75">
                                    <SpaceIcon className="h-5 w-5" />
                                </span>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 p-4 backdrop-blur-sm">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-100/60">
                                        {t('Available now')}
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-emerald-50">
                                        {totalAvailable}
                                    </p>
                                </div>
                                <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-300/15 text-emerald-100">
                                    <CheckIcon className="h-5 w-5" />
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-[1.75rem] border border-[#E5DDD0] bg-white p-4 shadow-[0_22px_60px_-48px_rgba(55,35,30,0.7)] sm:p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#9A8B84]">
                                {t('Property areas')}
                            </p>
                            <h2 className="mt-1 text-lg font-bold tracking-tight text-[#241917]">
                                {t('Choose an area to manage')}
                            </h2>
                        </div>

                        <div className="flex items-center gap-2 text-xs font-semibold text-[#80736D]">
                            <span className="h-2 w-2 rounded-full bg-emerald-500" />
                            {t('Counts update automatically')}
                        </div>
                    </div>

                    <div className="mt-5 flex gap-3 overflow-x-auto pt-1 pb-2">
                        {mergedAreas.map((area) => {
                            const summary = areaSummaries.find(
                                (item) => item.id === String(area.id),
                            ) ?? { total: 0, available: 0 };
                            const isActive = String(area.id) === String(currentArea?.id);

                            return (
                                <button
                                    key={area.id}
                                    type="button"
                                    onClick={() => handleAreaChange(area.id)}
                                    className={`group relative z-0 min-w-[190px] flex-1 rounded-2xl border p-4 text-left transition duration-200 focus:outline-none focus:ring-4 focus:ring-[#8A3330]/15 focus:z-10 ${
                                        isActive
                                            ? 'border-[#8A3330] bg-[#8A3330] text-white shadow-[0_16px_32px_-22px_rgba(138,51,48,0.85)]'
                                            : 'border-[#E5DDD0] bg-[#FCFAF7] text-[#241917] hover:z-10 hover:-translate-y-0.5 hover:border-[#8A3330]/35 hover:bg-[#FAF6EE]'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <span
                                            className={`grid h-10 w-10 place-items-center rounded-xl ${
                                                isActive
                                                    ? 'bg-white/15 text-white'
                                                    : 'bg-[#F3E1DC] text-[#8A3330]'
                                            }`}
                                        >
                                            <BuildingIcon className="h-5 w-5" />
                                        </span>

                                        <span
                                            className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.1em] ${
                                                isActive
                                                    ? 'bg-white/15 text-white/85'
                                                    : 'bg-white text-[#8A3330]'
                                            }`}
                                        >
                                            {summary.available}/{summary.total} {t('free')}
                                        </span>
                                    </div>

                                    <p className="mt-4 truncate text-sm font-bold">{area.name}</p>
                                    <p
                                        className={`mt-1 text-xs ${
                                            isActive ? 'text-white/60' : 'text-[#8B7E77]'
                                        }`}
                                    >
                                        {summary.total} {t('spaces')}
                                    </p>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section className="overflow-hidden rounded-[1.75rem] border border-[#E5DDD0] bg-white shadow-[0_22px_60px_-48px_rgba(55,35,30,0.7)]">
                    <div className="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center sm:p-6">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h2 className="truncate text-xl font-bold tracking-[-0.025em] text-[#241917] sm:text-2xl">
                                    {currentArea.name}
                                </h2>
                                <span className="rounded-full bg-[#F3E1DC] px-2.5 py-1 text-[11px] font-bold text-[#8A3330]">
                                    {currentSummary.total} {t('spaces')}
                                </span>
                            </div>

                            <p className="mt-1.5 text-sm text-[#7D706A]">
                                {currentSummary.available} {t('available')} - {currentUnavailable}{' '}
                                {t('currently in use or unavailable')}
                            </p>

                            <div className="mt-4 max-w-xl">
                                <div className="flex items-center justify-between text-xs font-semibold">
                                    <span className="text-[#7D706A]">{t('Availability')}</span>
                                    <span className="text-[#8A3330]">{availabilityRate}%</span>
                                </div>
                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-[#EEE7DF]">
                                    <div
                                        className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                        style={{ width: `${availabilityRate}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        {canManageSpaces && (
                            <a
                                href={newSpaceHref(currentArea)}
                                data-turbo="false"
                                className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#8A3330] px-5 py-3 text-sm font-bold text-white shadow-[0_14px_28px_-18px_rgba(138,51,48,0.85)] transition hover:-translate-y-0.5 hover:bg-[#742927] focus:outline-none focus:ring-4 focus:ring-[#8A3330]/20"
                            >
                                <PlusIcon />
                                {t('New Space')}
                            </a>
                        )}
                    </div>

                    {currentSpaces.length > 0 && (
                        <div className="border-t border-[#EEE7DF] bg-[#FCFAF7] p-4 sm:p-5">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex flex-1 flex-col gap-3 sm:flex-row">
                                    <label className="relative block min-w-0 flex-1 lg:max-w-md">
                                        <span className="sr-only">{t('Search spaces')}</span>
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            strokeWidth="1.8"
                                            stroke="currentColor"
                                            className="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-[#9A8C85]"
                                            aria-hidden="true"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0z" />
                                        </svg>
                                        <input
                                            type="search"
                                            value={searchQuery}
                                            onChange={(event) => setSearchQuery(event.target.value)}
                                            placeholder={t('Search by space name or status...')}
                                            className="h-11 w-full rounded-xl border-[#DED3C7] bg-white pl-10 pr-4 text-sm text-[#241917] placeholder:text-[#A99D96] focus:border-[#8A3330] focus:ring-[#8A3330]/15"
                                        />
                                    </label>

                                    <label className="relative block sm:w-56">
                                        <span className="sr-only">{t('Filter by status')}</span>
                                        {/* bg-none: @tailwindcss/forms already paints its own
                                            arrow into every <select> via background-image — left
                                            as-is, it sat under the custom SVG chevron below and
                                            doubled up into a broken-looking icon. */}
                                        <select
                                            value={statusFilter}
                                            onChange={(event) => setStatusFilter(event.target.value)}
                                            className="h-11 w-full appearance-none bg-none rounded-xl border-[#DED3C7] bg-white pl-4 pr-10 text-sm font-semibold text-[#51443E] focus:border-[#8A3330] focus:ring-[#8A3330]/15"
                                        >
                                            <option value="all">{t('All statuses')}</option>
                                            {statusOptions.map((status) => (
                                                <option key={status.value} value={String(status.value)}>
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
                                            className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8D8079]"
                                            aria-hidden="true"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 9-7.5 7.5L4.5 9" />
                                        </svg>
                                    </label>
                                </div>

                                <div className="flex items-center justify-between gap-3 lg:justify-end">
                                    <span className="rounded-full border border-[#E2D7CA] bg-white px-3 py-1.5 text-xs font-bold text-[#786B64]">
                                        {filteredSpaces.length} {t('shown')}
                                    </span>

                                    {(searchQuery || statusFilter !== 'all') && (
                                        <button
                                            type="button"
                                            onClick={clearFilters}
                                            className="text-xs font-bold text-[#8A3330] transition hover:text-[#692522]"
                                        >
                                            {t('Clear')}
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </section>

                {currentSpaces.length === 0 ? (
                    <EmptyArea
                        t={t}
                        area={currentArea}
                        canManageSpaces={canManageSpaces}
                    />
                ) : filteredSpaces.length === 0 ? (
                    <NoResults t={t} onClear={clearFilters} />
                ) : (
                    <ListView
                        t={t}
                        spaces={filteredSpaces}
                        canManageSpaces={canManageSpaces}
                        statusOptions={statusOptions}
                        onStatusChange={handleStatusChange}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}