import Avatar from '@/Components/Avatar';
import { useTranslation } from '@/lib/i18n';
import { useMemo, useState } from 'react';

export default function NewChatPicker({ directory, onSelect, onClose }) {
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
        return Array.from(byRole.entries());
    }, [directory, search]);

    return (
        <div className="fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4" onClick={onClose}>
            <div
                className="flex max-h-[80vh] w-full sm:max-w-md flex-col rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-[#E5DDD0] px-5 py-4">
                    <h2 className="text-base font-bold text-[#251C19]">{t('New Chat')}</h2>
                    <button type="button" onClick={onClose} className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="border-b border-[#E5DDD0] px-5 py-3">
                    <input
                        type="text"
                        autoFocus
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('Search people…')}
                        className="w-full rounded-lg border border-[#D9CCBA] px-3 py-2 text-sm focus:border-[#8A3330] focus:outline-none focus:ring-1 focus:ring-[#8A3330]"
                    />
                </div>

                <div className="flex-1 overflow-y-auto px-2 py-2">
                    {groups.length === 0 && (
                        <p className="px-3 py-6 text-center text-sm text-gray-400">{t('No one found.')}</p>
                    )}
                    {groups.map(([roleLabel, users]) => (
                        <div key={roleLabel} className="mb-2">
                            <p className="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-[#8A7B9E]">{roleLabel}</p>
                            {users.map((u) => (
                                <button
                                    key={u.id}
                                    type="button"
                                    onClick={() => onSelect(u.id)}
                                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-[#F3E1DC]/50"
                                >
                                    <Avatar user={u} className="h-9 w-9 text-xs" />
                                    <span className="text-sm font-medium text-[#251C19]">{u.name}</span>
                                </button>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
