import { useTranslation } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';

// Shown to a Superadmin while Maintenance Mode is on (everyone else gets the
// maintenance page), so it is not left on by mistake. Same bar as
// views/partials/maintenance-banner.blade.php on the Blade pages.
export default function MaintenanceBanner() {
    const { maintenanceMode } = usePage().props;
    const t = useTranslation();

    if (!maintenanceMode) return null;

    return (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span className="flex items-center gap-2">
                <span className="h-2 w-2 shrink-0 animate-pulse rounded-full bg-amber-500" />
                {t('Maintenance Mode is on — only Superadmin can use the system. Everyone else sees the maintenance page.')}
            </span>
            {/* A plain link: Settings is a Blade page (see app.jsx). */}
            <a href={route('superadmin.settings.edit')} className="font-semibold underline underline-offset-4 hover:no-underline">
                {t('Turn it off in Settings')}
            </a>
        </div>
    );
}
