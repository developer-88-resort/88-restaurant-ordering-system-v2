import EmptyState from '@/Components/EmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ListView from './ListView';

function newSpaceHref(area) {
    return area.default_category_id
        ? route('spaces.create', { category_id: area.default_category_id })
        : route('spaces.create', { area_id: area.id });
}

export default function Index({ areas, activeAreaId, canManageSpaces, statusOptions }) {
    const t = useTranslation();
    const [activeArea, setActiveArea] = useState(String(activeAreaId ?? areas[0]?.id ?? ''));
    const [statusOverrides, setStatusOverrides] = useState({});
    const [toasts, setToasts] = useState([]);

    const pushToast = (message) => {
        const id = Date.now() + Math.random();
        setToasts((current) => [...current, { id, message }]);
        setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 6000);
    };

    const applyStatusOverrides = (spaceIds, statusValue) => {
        const meta = statusOptions.find((s) => s.value === statusValue);
        if (!meta) return;
        setStatusOverrides((current) => {
            const next = { ...current };
            spaceIds.forEach((id) => {
                next[id] = {
                    status: meta.value,
                    status_label: meta.label,
                    status_capsule_class: meta.capsule_class,
                };
            });
            return next;
        });
    };

    useEffect(() => {
        window.Echo.private('spaces').listen('.SpaceOccupancyChanged', (e) => {
            pushToast(e.message);
            applyStatusOverrides(e.space_ids, e.status);
        });

        const leave = () => window.Echo.leave('spaces');
        turboCleanup(leave);

        return leave;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleStatusChange = (spaceId, status) => {
        router.patch(route('spaces.update-status', spaceId), { status }, { preserveScroll: true, preserveState: true, only: ['areas'] });
    };

    if (areas.length === 0) {
        return (
            <AuthenticatedLayout>
                <Head title={t('Spaces')} />
                <h1 className="text-2xl font-bold text-gray-900 mb-6">{t('Spaces')}</h1>
                <EmptyState
                    title={t('No areas yet')}
                    description={t('Add an area (e.g. Cottages, Dining Area, Rooms) to start organizing spaces.')}
                    actionLabel={canManageSpaces ? t('New Area') : null}
                    actionHref={canManageSpaces ? route('areas.create') : null}
                    inertia={false}
                />
            </AuthenticatedLayout>
        );
    }

    const currentArea = areas.find((area) => String(area.id) === activeArea) ?? areas[0];
    const currentSpaces = currentArea.spaces.map((s) => ({ ...s, ...(statusOverrides[s.id] ?? {}) }));

    return (
        <AuthenticatedLayout>
            <Head title={t('Spaces')} />

            <div className="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 z-[60] flex flex-col gap-3 sm:w-96">
                {toasts.map((toast) => (
                    <div key={toast.id} className="flex items-start gap-3 rounded-xl border border-[#E5DDD0] bg-white pl-4 pr-3 py-3.5 shadow-xl animate-fade-slide-up">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F3E1DC]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="#8A3330" className="h-4 w-4">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"
                                />
                            </svg>
                        </div>
                        <p className="flex-1 pt-1 text-sm font-medium text-gray-800">{toast.message}</p>
                    </div>
                ))}
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
                <h1 className="text-2xl font-bold text-gray-900">{t('Spaces')}</h1>
                {canManageSpaces && (
                    <a href={route('areas.index')} data-turbo="false" className="text-sm text-[#8A3330] hover:underline font-medium">
                        {t('Manage Areas')}
                    </a>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2 mb-8">
                {areas.map((area) => (
                    <button
                        key={area.id}
                        type="button"
                        onClick={() => setActiveArea(String(area.id))}
                        className={`px-5 py-2 text-sm font-semibold rounded-full border transition whitespace-nowrap ${
                            String(area.id) === activeArea
                                ? 'bg-[#8A3330] text-white border-[#8A3330]'
                                : 'bg-white text-gray-600 border-[#D9CCBA] hover:border-[#8A3330]'
                        }`}
                    >
                        {area.name}
                    </button>
                ))}
            </div>

            {canManageSpaces && (
                <div className="flex items-center justify-end mb-5">
                    <a href={newSpaceHref(currentArea)} data-turbo="false" className="text-sm text-[#8A3330] hover:underline font-medium">
                        + {t('New Space')}
                    </a>
                </div>
            )}

            {currentArea.spaces.length === 0 ? (
                <EmptyState
                    title={t('No spaces yet')}
                    description={t('Add individual spaces (e.g. Cottage Table 1) so customers can be assigned to them.')}
                    actionLabel={canManageSpaces ? t('New Space') : null}
                    actionHref={canManageSpaces ? newSpaceHref(currentArea) : null}
                    inertia={false}
                />
            ) : (
                <ListView t={t} spaces={currentSpaces} canManageSpaces={canManageSpaces} statusOptions={statusOptions} onStatusChange={handleStatusChange} />
            )}
        </AuthenticatedLayout>
    );
}
