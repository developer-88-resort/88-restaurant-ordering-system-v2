import Avatar from '@/Components/Avatar';
import { useTranslation } from '@/lib/i18n';
import { useMemo, useState } from 'react';

// onlineUserIds comes from the Chat page, which keeps it current as people
// sign in and out — so the dots here update while the picker is open.
export default function NewChatPicker({ directory, onlineUserIds = [], onSelect, onClose }) {
    const t = useTranslation();
    const [search, setSearch] = useState('');

    const groups = useMemo(() => {
        const term = search.trim().toLowerCase();
        const filtered = term ? directory.filter((u) => u.name.toLowerCase().includes(term)) : directory;

        const byRole = new Map();
        for (const u of filtered) {
            if (!byRole.has(u.role_label)) byRole.set(u.role_label, []);
            byRole.get(u.role_label).push(u);
        }
        // Whoever is online now comes first in each group.
        for (const users of byRole.values()) {
            users.sort((a, b) => Number(onlineUserIds.includes(b.id)) - Number(onlineUserIds.includes(a.id)));
        }
        return Array.from(byRole.entries());
    }, [directory, search, onlineUserIds]);

    return (
        <div className="fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4" onClick={onClose}>
            <div
                className="flex max-h-[80vh] w-full sm:max-w-md flex-col rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 className="text-base font-bold text-slate-900">{t('New Chat')}</h2>
                    <button type="button" onClick={onClose} aria-label={t('Close')} className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="border-b border-slate-100 px-5 py-3">
                    <input
                        type="text"
                        autoFocus
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('Search people…')}
                        className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#8A3330] focus:outline-none focus:ring-1 focus:ring-[#8A3330]"
                    />
                </div>

                <div className="flex-1 overflow-y-auto px-2 py-2">
                    {groups.length === 0 && (
                        <p className="px-3 py-6 text-center text-sm text-gray-400">{t('No one found.')}</p>
                    )}
                    {groups.map(([roleLabel, users]) => (
                        <div key={roleLabel} className="mb-2">
                            <p className="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500">{roleLabel}</p>
                            {users.map((u) => {
                                const isOnline = onlineUserIds.includes(u.id);

                                return (
                                    <button
                                        key={u.id}
                                        type="button"
                                        onClick={() => onSelect(u.id)}
                                        className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition-colors hover:bg-slate-50"
                                    >
                                        <span className="relative shrink-0">
                                            <Avatar user={u} className="h-9 w-9 text-xs" />
                                            <span
                                                aria-hidden="true"
                                                className={`absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-2 ring-white ${isOnline ? 'bg-green-500' : 'bg-gray-300'}`}
                                            />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-slate-900">{u.name}</span>
                                            <span className={`block text-xs ${isOnline ? 'font-semibold text-green-600' : 'text-slate-400'}`}>
                                                {isOnline ? t('Online') : t('Offline')}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
