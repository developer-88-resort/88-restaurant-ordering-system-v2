import Emoji from '@/Components/Chat/Emoji';
import EmojiPicker from '@/Components/Chat/EmojiPicker';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Messenger's own classic 6 — fast, recognizable, no scrolling needed for
// the common case. The "+" opens the full catalog for anything else.
const QUICK_REACTIONS = ['❤️', '😂', '😮', '😢', '😡', '👍'];

/**
 * Rendered into document.body via a portal, positioned with `fixed`
 * coordinates computed from the trigger button's own screen position
 * (anchorRect). This is deliberate: the message list is a scrollable,
 * overflow-clipped container, so a popover positioned `absolute` relative
 * to a message bubble gets silently clipped/invisible whenever that
 * bubble is near the top of the visible scroll area (e.g. the only
 * message in a fresh thread) — a portal sidesteps that entirely.
 */
export default function ReactionPicker({ onSelect, onClose, bodies, anchorRect, align = 'left' }) {
    const panelRef = useRef(null);
    const [showFullPicker, setShowFullPicker] = useState(false);

    useEffect(() => {
        const onClickOutside = (e) => {
            if (panelRef.current && !panelRef.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [onClose]);

    // Closing when the page/message-list scrolls out from under the anchor
    // is simpler and more robust than tracking/repositioning on every
    // scroll tick — but this listener fires (capture phase) for ANY scroll
    // anywhere in the document, including scrolling the emoji grid's own
    // internal list while browsing categories — that must NOT close the
    // picker, only a scroll outside of it should.
    useEffect(() => {
        const onScroll = (e) => {
            if (panelRef.current && panelRef.current.contains(e.target)) return;
            onClose();
        };
        window.addEventListener('scroll', onScroll, true);
        return () => window.removeEventListener('scroll', onScroll, true);
    }, [onClose]);

    // The quick-bar (~48px) almost always fits above the trigger, but the
    // full picker (384px) frequently doesn't — a message near the top of
    // the thread has nowhere near that much room between it and the top of
    // the browser window. Auto-flip below the trigger whenever there isn't
    // enough space above, the same way any real floating-menu library
    // would, rather than letting it render partly off-screen.
    const contentHeight = showFullPicker ? 384 : 48;
    const contentWidth = showFullPicker ? 320 : 260;
    const spaceAbove = anchorRect.top;
    const spaceBelow = window.innerHeight - anchorRect.bottom;
    const openBelow = spaceAbove < contentHeight + 16 && spaceBelow > spaceAbove;

    const style = {
        position: 'fixed',
        zIndex: 50,
        ...(openBelow
            ? { top: anchorRect.bottom + 8 }
            : { top: anchorRect.top - 8, transform: 'translateY(-100%)' }),
        ...(align === 'right'
            ? { right: Math.max(8, window.innerWidth - anchorRect.right) }
            : { left: Math.min(anchorRect.left, window.innerWidth - contentWidth - 8) }),
    };

    // Swaps between the quick-bar and the full picker — never stacks both —
    // so this box always has exactly one child and the flip-direction math
    // above always matches whichever one is actually showing, instead of
    // compounding heights from a nested second popover.
    return createPortal(
        <div ref={panelRef} style={style}>
            {showFullPicker ? (
                <EmojiPicker positioned={false} bodies={bodies} onSelect={onSelect} onClose={onClose} />
            ) : (
                <div className="flex items-center gap-0.5 rounded-full border border-[#E5DDD0] bg-white px-1.5 py-1 shadow-[0_14px_38px_-18px_rgba(55,35,30,0.45)]">
                    {QUICK_REACTIONS.map((emoji) => (
                        <button
                            key={emoji}
                            type="button"
                            onClick={() => onSelect(emoji)}
                            className="flex h-9 w-9 items-center justify-center rounded-full text-xl transition-transform hover:scale-125 hover:bg-[#F3E1DC]/70"
                        >
                            <Emoji char={emoji} bodies={bodies} className="h-6 w-6" />
                        </button>
                    ))}
                    <button
                        type="button"
                        onClick={() => setShowFullPicker(true)}
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#8A7B6D] hover:bg-[#F3E1DC]/70"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>
            )}
        </div>,
        document.body,
    );
}
