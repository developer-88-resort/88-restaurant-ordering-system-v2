import Avatar from '@/Components/Avatar';
import Emoji from '@/Components/Chat/Emoji';
import MessageActionsMenu from '@/Components/Chat/MessageActionsMenu';
import ReactionPicker from '@/Components/Chat/ReactionPicker';
import { splitTextAndEmoji } from '@/lib/emoji';
import { formatFileSize } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { motion } from 'framer-motion';
import { useRef, useState } from 'react';

const formatTime = (iso) =>
    iso ? new Date(iso).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : '';

export default function MessageBubble({ message, isOwn, statusLabel, seenByUser, emojiBodies, onReact, onReply, onJumpTo, onEdit }) {
    const t = useTranslation();
    const pieces = message.body ? splitTextAndEmoji(message.body, emojiBodies) : [];
    const [showPicker, setShowPicker] = useState(false);
    const [anchorRect, setAnchorRect] = useState(null);
    const [showMenu, setShowMenu] = useState(false);
    const [menuAnchorRect, setMenuAnchorRect] = useState(null);
    const [isEditing, setIsEditing] = useState(false);
    const [editValue, setEditValue] = useState(message.body ?? '');
    const triggerRef = useRef(null);
    const moreTriggerRef = useRef(null);
    const reactions = message.reactions ?? [];
    const attachment = message.attachment ?? null;
    const isImageAttachment = attachment?.mime?.startsWith('image/');
    const attachmentExtension = attachment?.name?.split('.').pop()?.toUpperCase() ?? '';

    const openPicker = () => {
        setAnchorRect(triggerRef.current.getBoundingClientRect());
        setShowPicker(true);
    };

    const openMenu = () => {
        setMenuAnchorRect(moreTriggerRef.current.getBoundingClientRect());
        setShowMenu(true);
    };

    const startEdit = () => {
        setEditValue(message.body ?? '');
        setIsEditing(true);
    };

    const cancelEdit = () => setIsEditing(false);

    const saveEdit = () => {
        const trimmed = editValue.trim();
        if (!trimmed && !attachment) return; // can't edit a text-only message down to nothing
        onEdit(message.id, trimmed);
        setIsEditing(false);
    };

    return (
        <div id={`message-${message.id}`} className={`group flex items-end gap-2 transition-shadow ${isOwn ? 'flex-row-reverse' : ''}`}>
            {!isOwn && <Avatar user={message.sender} className="h-7 w-7 text-[10px] shrink-0" />}
            <div className={`flex flex-col ${isOwn ? 'items-end' : 'items-start'} max-w-[75%] sm:max-w-[60%]`}>
                {message.reply_to && (
                    <button
                        type="button"
                        onClick={() => onJumpTo(message.reply_to.id)}
                        className="mb-1 max-w-full rounded-lg bg-black/[0.04] px-2 py-1 text-left text-xs text-[#8A7B6D] hover:bg-black/[0.07]"
                    >
                        <span className="flex items-center gap-1 font-semibold text-[#6B5D52]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-3 w-3 shrink-0">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            {message.reply_to.sender_name}
                        </span>
                        <span className="block truncate">{message.reply_to.body}</span>
                    </button>
                )}
                <div className={`relative flex items-center gap-1 ${isOwn ? 'flex-row-reverse' : ''}`}>
                    <div
                        className={
                            isOwn
                                ? 'rounded-2xl rounded-br-sm bg-[#8A3330] px-3.5 py-2 text-sm text-white'
                                : 'rounded-2xl rounded-bl-sm border border-[#E5DDD0] bg-white px-3.5 py-2 text-sm text-[#251C19]'
                        }
                    >
                        {attachment && (isImageAttachment ? (
                            <a
                                href={attachment.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`block overflow-hidden rounded-lg ${message.body ? 'mb-1' : ''}`}
                            >
                                <img src={attachment.url} alt={attachment.name} className="max-h-64 w-full object-cover" />
                            </a>
                        ) : (
                            <a
                                href={attachment.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`flex items-center gap-2 rounded-lg px-2 py-2 ${message.body ? 'mb-1' : ''} ${
                                    isOwn ? 'bg-white/10 hover:bg-white/20' : 'bg-black/[0.04] hover:bg-black/[0.07]'
                                }`}
                            >
                                <span
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[9px] font-bold ${
                                        isOwn ? 'bg-white/20 text-white' : 'bg-[#8A3330]/10 text-[#8A3330]'
                                    }`}
                                >
                                    {attachmentExtension}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-xs font-semibold">{attachment.name}</span>
                                    <span className={`block text-[11px] ${isOwn ? 'text-white/70' : 'text-[#8A7B6D]'}`}>
                                        {formatFileSize(attachment.size)}
                                    </span>
                                </span>
                            </a>
                        ))}
                        {isEditing ? (
                            <input
                                type="text"
                                autoFocus
                                value={editValue}
                                onChange={(e) => setEditValue(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') saveEdit();
                                    if (e.key === 'Escape') cancelEdit();
                                }}
                                className={`min-w-[8rem] border-0 bg-transparent p-0 text-sm outline-none ring-0 focus:border-0 focus:outline-none focus:ring-0 ${
                                    isOwn ? 'text-white placeholder-white/60' : 'text-[#251C19]'
                                }`}
                            />
                        ) : (
                            message.body && (
                                <p className="whitespace-pre-wrap break-words">
                                    {pieces.map((piece, i) => (typeof piece === 'string'
                                        ? <span key={i}>{piece}</span>
                                        : <Emoji key={i} char={piece.emoji} bodies={emojiBodies} />))}
                                </p>
                            )
                        )}
                    </div>
                    {!isEditing && (
                        <>
                            <button
                                type="button"
                                onClick={() => onReply(message)}
                                className="shrink-0 rounded-full p-1 text-[#8A7B6D] opacity-0 transition-opacity hover:bg-[#F3E1DC]/70 group-hover:opacity-100"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-4 w-4">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                </svg>
                            </button>
                            <button
                                ref={triggerRef}
                                type="button"
                                onClick={openPicker}
                                className="shrink-0 rounded-full p-1 text-[#8A7B6D] opacity-0 transition-opacity hover:bg-[#F3E1DC]/70 group-hover:opacity-100"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-4 w-4">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 9.75c0-.621.504-1.125 1.125-1.125h.008c.621 0 1.125.504 1.125 1.125v.008c0 .621-.504 1.125-1.125 1.125h-.008a1.125 1.125 0 01-1.125-1.125V9.75zM13.5 9.75c0-.621.504-1.125 1.125-1.125h.008c.621 0 1.125.504 1.125 1.125v.008c0 .621-.504 1.125-1.125 1.125h-.008a1.125 1.125 0 01-1.125-1.125V9.75zM9 15.75c.856.646 1.9 1.031 3 1.031s2.144-.385 3-1.031M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" />
                                </svg>
                            </button>
                            {isOwn && (
                                <button
                                    ref={moreTriggerRef}
                                    type="button"
                                    onClick={openMenu}
                                    className="shrink-0 rounded-full p-1 text-[#8A7B6D] opacity-0 transition-opacity hover:bg-[#F3E1DC]/70 group-hover:opacity-100"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-4 w-4">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                    </svg>
                                </button>
                            )}
                        </>
                    )}
                    {showPicker && anchorRect && (
                        <ReactionPicker
                            bodies={emojiBodies}
                            align={isOwn ? 'right' : 'left'}
                            anchorRect={anchorRect}
                            onSelect={(emoji) => { onReact(message.id, emoji); setShowPicker(false); }}
                            onClose={() => setShowPicker(false)}
                        />
                    )}
                    {showMenu && menuAnchorRect && (
                        <MessageActionsMenu
                            align={isOwn ? 'right' : 'left'}
                            anchorRect={menuAnchorRect}
                            items={[{ label: t('Edit'), onClick: startEdit }]}
                            onClose={() => setShowMenu(false)}
                        />
                    )}
                </div>
                {isEditing && (
                    <p className="mt-1 px-1 text-[11px] text-[#8A7B6D]">
                        {t('Press Enter to save')} · {t('Esc to cancel')}
                    </p>
                )}

                {reactions.length > 0 && (
                    <div className={`mt-1 flex flex-wrap gap-1 ${isOwn ? 'justify-end' : 'justify-start'}`}>
                        {reactions.map((r) => (
                            <button
                                key={r.emoji}
                                type="button"
                                onClick={() => onReact(message.id, r.emoji)}
                                className={`flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs ${
                                    r.reacted_by_me ? 'border-[#8A3330] bg-[#F3E1DC]' : 'border-[#E5DDD0] bg-white'
                                }`}
                            >
                                <Emoji char={r.emoji} bodies={emojiBodies} className="h-3.5 w-3.5" />
                                {r.count > 1 && <span className="text-[#8A7B6D]">{r.count}</span>}
                            </button>
                        ))}
                    </div>
                )}

                <span className="mt-1 flex items-center gap-1 px-1 text-[11px] text-[#8A7B6D]">
                    <span>
                        {formatTime(message.created_at)}
                        {message.edited_at ? ` · ${t('Edited')}` : ''}
                        {statusLabel ? ` · ${statusLabel}` : ''}
                    </span>
                    {seenByUser && (
                        <motion.div
                            layoutId="seen-avatar"
                            transition={{ type: 'spring', stiffness: 500, damping: 32 }}
                            initial={{ scale: 0, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                        >
                            <Avatar user={seenByUser} className="h-3.5 w-3.5 text-[6px]" />
                        </motion.div>
                    )}
                </span>
            </div>
        </div>
    );
}
