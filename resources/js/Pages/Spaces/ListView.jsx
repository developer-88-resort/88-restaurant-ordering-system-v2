import { router } from '@inertiajs/react';

// Faithful parity port of the previous Blade list view — no visual
// redesign here, per scope (only the Floor Plan/canvas view was redesigned).
export default function ListView({ t, spaces, canManageSpaces, statusOptions, onStatusChange }) {
    return (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {spaces.map((space) => (
                <div key={space.id} className="border border-[#E5DDD0] rounded-xl p-4 bg-white hover:border-[#8A3330] transition">
                    <div className="flex items-start justify-between gap-2">
                        <span className="font-semibold text-gray-900">{space.name}</span>
                        {canManageSpaces && (
                            <a
                                href={route('spaces.print', space.id)}
                                target="_blank"
                                rel="noopener"
                                data-turbo="false"
                                className="text-xs text-[#8A3330] hover:underline shrink-0"
                            >
                                {t('QR')}
                            </a>
                        )}
                    </div>

                    <div className="mt-3 relative w-full">
                        <div className={`absolute inset-y-0 left-0 w-full -translate-x-2 rounded-full ${space.status_capsule_class}`} />
                        <select
                            value={space.status}
                            onChange={(e) => onStatusChange(space.id, e.target.value)}
                            className="relative w-full text-xs font-bold text-black text-center rounded-full px-3 py-1.5 bg-white border-2 border-gray-900 focus:ring-2 focus:ring-[#8A3330] focus:outline-none"
                        >
                            {statusOptions.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {canManageSpaces && (
                        <div className="mt-3 pt-3 border-t border-[#E5DDD0] flex items-center justify-between text-xs">
                            <a href={route('spaces.edit', space.id)} data-turbo="false" className="text-[#8A3330] hover:text-[#5f2120]">
                                {t('Edit')}
                            </a>
                            <button
                                type="button"
                                onClick={() => {
                                    if (window.confirm(`${t('Delete this space?')}\n${t('This will permanently remove :name.').replace(':name', space.name)}`)) {
                                        router.delete(route('spaces.destroy', space.id));
                                    }
                                }}
                                className="text-red-600 hover:text-red-900"
                            >
                                {t('Delete')}
                            </button>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
