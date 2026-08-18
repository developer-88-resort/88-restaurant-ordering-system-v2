import Emoji from '@/Components/Chat/Emoji';
import { useTranslation } from '@/lib/i18n';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function EmojiPicker({ onSelect, onClose, bodies, positioned = true }) {
    const t = useTranslation();
    const panelRef = useRef(null);
    const [groups, setGroups] = useState(null);
    const [search, setSearch] = useState('');

    // The full Unicode emoji catalog (~1,900 entries, same set backing
    // Messenger/Instagram/Discord pickers) is code-split into its own chunk
    // and only fetched the first time someone actually opens the picker —
    // it never weighs down the Chat page's initial load.
    useEffect(() => {
        import('unicode-emoji-json/data-by-group.json').then((mod) => {
            // Country flags render as plain two-letter text on Windows (no
            // flag glyphs in Segoe UI Emoji) — excluded rather than shipped
            // broken, since fixing it properly needs CDN-hosted flag images.
            setGroups(mod.default.filter((group) => group.slug !== 'flags'));
        });
    }, []);

    useEffect(() => {
        const onClickOutside = (e) => {
            if (panelRef.current && !panelRef.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [onClose]);

    // Data shape is an array of { name, slug, emojis: [...] } groups —
    // NOT the object-keyed-by-name shape the package's README shows (that
    // doc is stale relative to the installed 0.9.0 data format).
    const searchResults = useMemo(() => {
        if (!groups) return [];
        const term = search.trim().toLowerCase();
        if (!term) return null;

        return groups
            .flatMap((group) => group.emojis)
            .filter((e) => e.name.includes(term) || e.slug.includes(term));
    }, [groups, search]);

    return (
        <div
            ref={panelRef}
            className={`flex h-96 w-80 flex-col overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_14px_38px_-18px_rgba(55,35,30,0.45)] ${
                positioned ? 'absolute bottom-full right-0 mb-2' : ''
            }`}
        >
            <div className="shrink-0 border-b border-[#E5DDD0] p-2">
                <input
                    type="text"
                    autoFocus
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('Search emoji…')}
                    className="w-full rounded-lg border border-[#D9CCBA] px-3 py-1.5 text-sm focus:border-[#8A3330] focus:outline-none focus:ring-1 focus:ring-[#8A3330]"
                />
            </div>

            <div className="flex-1 overflow-y-auto p-2">
                {!groups && <p className="px-2 py-6 text-center text-xs text-gray-400">{t('Loading…')}</p>}

                {groups && searchResults && (
                    searchResults.length === 0 ? (
                        <p className="px-2 py-6 text-center text-xs text-gray-400">{t('No emoji found.')}</p>
                    ) : (
                        <div className="grid grid-cols-8 gap-0.5">
                            {searchResults.map((e) => (
                                <button
                                    key={e.slug}
                                    type="button"
                                    title={e.name}
                                    onClick={() => onSelect(e.emoji)}
                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-lg hover:bg-[#F3E1DC]/70"
                                >
                                    <Emoji char={e.emoji} bodies={bodies} className="h-5 w-5" />
                                </button>
                            ))}
                        </div>
                    )
                )}

                {groups && !searchResults && groups.map((group) => (
                    <div key={group.slug} className="mb-2 last:mb-0">
                        <p className="sticky top-0 bg-white px-1 py-1 text-[10px] font-bold uppercase tracking-wide text-[#8A7B9E]">
                            {group.name}
                        </p>
                        <div className="grid grid-cols-8 gap-0.5">
                            {group.emojis.map((e) => (
                                <button
                                    key={e.slug}
                                    type="button"
                                    title={e.name}
                                    onClick={() => onSelect(e.emoji)}
                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-lg hover:bg-[#F3E1DC]/70"
                                >
                                    <Emoji char={e.emoji} bodies={bodies} className="h-5 w-5" />
                                </button>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
