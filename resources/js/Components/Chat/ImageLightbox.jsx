import { useTranslation } from '@/lib/i18n';
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const MIN_SCALE = 1;
const MAX_SCALE = 5;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/**
 * A chat photo opened in place, over the conversation, instead of in a new
 * tab. Zoom with the mouse wheel, the +/− buttons, a double-click / double
 * tap, or a two-finger pinch; drag to look around while zoomed in. A click
 * anywhere that is not the photo (or Esc) closes it.
 */
export default function ImageLightbox({ src, alt, onClose }) {
    const t = useTranslation();
    const [scale, setScale] = useState(1);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const pointers = useRef(new Map());
    const gesture = useRef(null);
    const moved = useRef(false);

    const zoomTo = useCallback((next) => {
        const s = clamp(next, MIN_SCALE, MAX_SCALE);
        setScale(s);
        if (s === 1) setOffset({ x: 0, y: 0 });
    }, []);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
            if (e.key === '+' || e.key === '=') setScale((s) => clamp(s * 1.25, MIN_SCALE, MAX_SCALE));
            if (e.key === '-') zoomTo(scale / 1.25);
        };
        window.addEventListener('keydown', onKey);
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.style.overflow = overflow;
        };
    }, [onClose, scale, zoomTo]);

    const onWheel = (e) => {
        zoomTo(scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15));
    };

    const distance = () => {
        const [a, b] = [...pointers.current.values()];
        return Math.hypot(a.x - b.x, a.y - b.y);
    };

    const onPointerDown = (e) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        moved.current = false;
        gesture.current = pointers.current.size === 2
            ? { pinch: distance(), scale }
            : { x: e.clientX, y: e.clientY, offset };
    };

    const onPointerMove = (e) => {
        if (!pointers.current.has(e.pointerId) || !gesture.current) return;
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (pointers.current.size === 2 && gesture.current.pinch) {
            moved.current = true;
            zoomTo(gesture.current.scale * (distance() / gesture.current.pinch));
        } else if (pointers.current.size === 1 && scale > 1 && gesture.current.offset) {
            const dx = e.clientX - gesture.current.x;
            const dy = e.clientY - gesture.current.y;
            if (Math.abs(dx) + Math.abs(dy) > 3) moved.current = true;
            setOffset({ x: gesture.current.offset.x + dx / scale, y: gesture.current.offset.y + dy / scale });
        }
    };

    const onPointerUp = (e) => {
        pointers.current.delete(e.pointerId);
        gesture.current = pointers.current.size === 1
            ? (() => {
                const [p] = [...pointers.current.values()];
                return { x: p.x, y: p.y, offset };
            })()
            : null;
    };

    return createPortal(
        <div
            role="dialog"
            aria-modal="true"
            aria-label={alt}
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4 sm:p-10"
            onClick={onClose}
            onWheel={onWheel}
        >
            <img
                src={src}
                alt={alt}
                draggable={false}
                onClick={(e) => e.stopPropagation()}
                onDoubleClick={(e) => {
                    e.stopPropagation();
                    zoomTo(scale > 1 ? 1 : 2.5);
                }}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={onPointerUp}
                style={{
                    transform: `scale(${scale}) translate(${offset.x}px, ${offset.y}px)`,
                    cursor: scale > 1 ? 'grab' : 'zoom-in',
                    touchAction: 'none',
                }}
                className="max-h-full max-w-full select-none rounded-lg object-contain shadow-2xl transition-transform duration-75"
            />

            <div
                className="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full bg-white/10 p-1 text-white backdrop-blur"
                onClick={(e) => e.stopPropagation()}
            >
                <button
                    type="button"
                    onClick={() => zoomTo(scale / 1.25)}
                    disabled={scale <= MIN_SCALE}
                    className="flex h-9 w-9 items-center justify-center rounded-full text-lg font-bold hover:bg-white/20 disabled:opacity-40"
                    aria-label={t('Zoom out')}
                >
                    −
                </button>
                <button
                    type="button"
                    onClick={() => zoomTo(1)}
                    className="min-w-[3.5rem] rounded-full px-2 py-1.5 text-xs font-semibold tabular-nums hover:bg-white/20"
                    aria-label={t('Reset zoom')}
                >
                    {Math.round(scale * 100)}%
                </button>
                <button
                    type="button"
                    onClick={() => zoomTo(scale * 1.25)}
                    disabled={scale >= MAX_SCALE}
                    className="flex h-9 w-9 items-center justify-center rounded-full text-lg font-bold hover:bg-white/20 disabled:opacity-40"
                    aria-label={t('Zoom in')}
                >
                    +
                </button>
            </div>

            <button
                type="button"
                onClick={onClose}
                className="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label={t('Close')}
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>,
        document.body,
    );
}
