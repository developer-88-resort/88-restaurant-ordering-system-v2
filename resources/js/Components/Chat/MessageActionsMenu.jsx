import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

/**
 * A small "more actions" dropdown (Messenger's "•••" menu) — rendered into
 * document.body via a portal and positioned with `fixed` coordinates
 * computed from the trigger button's own screen position, same reasoning
 * as ReactionPicker.jsx: the message list is a scrollable, overflow-clipped
 * container, so a plain `absolute` popover gets silently clipped whenever
 * its trigger is near the top of the visible scroll area.
 */
export default function MessageActionsMenu({ items, anchorRect, align = 'left', onClose }) {
    const panelRef = useRef(null);

    useEffect(() => {
        const onClickOutside = (e) => {
            if (panelRef.current && !panelRef.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [onClose]);

    // Capture-phase listener fires for ANY scroll, including scrolling the
    // message list itself while this menu is open — only a scroll OUTSIDE
    // the menu should close it.
    useEffect(() => {
        const onScroll = (e) => {
            if (panelRef.current && panelRef.current.contains(e.target)) return;
            onClose();
        };
        window.addEventListener('scroll', onScroll, true);
        return () => window.removeEventListener('scroll', onScroll, true);
    }, [onClose]);

    const contentHeight = items.length * 36 + 8;
    const contentWidth = 160;
    const spaceAbove = anchorRect.top;
    const spaceBelow = window.innerHeight - anchorRect.bottom;
    const openBelow = spaceAbove < contentHeight + 16 && spaceBelow > spaceAbove;

    const style = {
        position: 'fixed',
        zIndex: 50,
        minWidth: `${contentWidth}px`,
        ...(openBelow
            ? { top: anchorRect.bottom + 6 }
            : { top: anchorRect.top - 6, transform: 'translateY(-100%)' }),
        ...(align === 'right'
            ? { right: Math.max(8, window.innerWidth - anchorRect.right) }
            : { left: Math.min(anchorRect.left, window.innerWidth - contentWidth - 8) }),
    };

    return createPortal(
        <div
            ref={panelRef}
            style={style}
            className="overflow-hidden rounded-xl border border-[#E5DDD0] bg-white py-1 shadow-[0_14px_38px_-18px_rgba(55,35,30,0.45)]"
        >
            {items.map((item) => (
                <button
                    key={item.label}
                    type="button"
                    onClick={() => { item.onClick(); onClose(); }}
                    className="flex w-full items-center px-3 py-2 text-left text-sm text-[#251C19] hover:bg-[#F3E1DC]/50"
                >
                    {item.label}
                </button>
            ))}
        </div>,
        document.body,
    );
}
