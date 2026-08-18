import Avatar from '@/Components/Avatar';
import Emoji from '@/Components/Chat/Emoji';
import EmojiPicker from '@/Components/Chat/EmojiPicker';
import MessageBubble from '@/Components/Chat/MessageBubble';
import NewChatPicker from '@/Components/Chat/NewChatPicker';
import TypingBubble from '@/Components/Chat/TypingBubble';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { loadEmojiBodies } from '@/lib/emoji';
import { formatFileSize } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { useEffect, useMemo, useRef, useState } from 'react';

const formatListTime = (iso) => {
    if (!iso) return '';
    const date = new Date(iso);
    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();
    return sameDay
        ? date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })
        : date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
};

export default function Index({ conversations, directory, onlineUserIds, activeConversation }) {
    const t = useTranslation();
    const { auth } = usePage().props;
    const currentUser = auth.user;

    const [conversationList, setConversationList] = useState(conversations);
    const [search, setSearch] = useState('');
    const [showPicker, setShowPicker] = useState(false);
    const [messages, setMessages] = useState(activeConversation?.messages ?? []);
    const [hasMore, setHasMore] = useState(activeConversation?.has_more ?? false);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [partnerTyping, setPartnerTyping] = useState(false);
    const [showEmojiPicker, setShowEmojiPicker] = useState(false);
    const [emojiBodies, setEmojiBodies] = useState(null);
    const [replyingTo, setReplyingTo] = useState(null);
    const [attachmentFile, setAttachmentFile] = useState(null);
    const messageListRef = useRef(null);
    const typingTimeoutRef = useRef(null);
    const lastWhisperRef = useRef(0);
    const composerInputRef = useRef(null);
    const fileInputRef = useRef(null);

    useEffect(() => {
        setConversationList(conversations);
    }, [conversations]);

    // Loaded once per page visit (cached by the browser on repeat visits
    // since it's a hashed, long-cache-lifetime asset) so both the emoji
    // picker and already-sent message bubbles can render Fluent Emoji
    // artwork instead of relying on the OS's own emoji font.
    useEffect(() => {
        loadEmojiBodies().then(setEmojiBodies);
    }, []);

    useEffect(() => {
        setMessages(activeConversation?.messages ?? []);
        setHasMore(activeConversation?.has_more ?? false);
        setPartnerTyping(false);
        setReplyingTo(null);
        setAttachmentFile(null);
        clearTimeout(typingTimeoutRef.current);
    }, [activeConversation?.id]);

    useEffect(() => {
        if (messageListRef.current) {
            messageListRef.current.scrollTop = messageListRef.current.scrollHeight;
        }
    }, [activeConversation?.id, messages.length, partnerTyping]);

    // Per-conversation channel — this component is its only ever subscriber,
    // so a plain join/leave per active-thread change is correct here.
    // Also carries the typing indicator (a client "whisper" — never touches
    // the Laravel backend, purely peer-to-peer over the socket) and message
    // status flips (Delivered → Seen) for the other participant.
    useEffect(() => {
        if (!activeConversation) return;

        const channelName = `chat.conversation.${activeConversation.id}`;
        const onMessage = (e) => {
            setMessages((current) => (current.some((m) => m.id === e.id) ? current : [...current, e]));
            setPartnerTyping(false);
        };
        const onStatus = (e) => {
            setMessages((current) => current.map((m) => (e.message_ids.includes(m.id)
                ? { ...m, read_at: e.read_at, delivered_at: m.delivered_at ?? e.read_at }
                : m)));
        };
        const onReaction = (e) => {
            setMessages((current) => current.map((m) => (m.id === e.message_id
                ? {
                    ...m,
                    reactions: e.reactions.map((r) => ({ ...r, reacted_by_me: r.user_ids.includes(currentUser.id) })),
                }
                : m)));
        };
        const onEdited = (e) => {
            setMessages((current) => current.map((m) => (m.id === e.message_id
                ? { ...m, body: e.body, edited_at: e.edited_at }
                : m)));
        };
        const onTyping = () => {
            setPartnerTyping(true);
            clearTimeout(typingTimeoutRef.current);
            typingTimeoutRef.current = setTimeout(() => setPartnerTyping(false), 3000);
        };

        const channel = window.Echo.private(channelName);
        channel.listen('.MessageSent', onMessage);
        channel.listen('.MessageStatusUpdated', onStatus);
        channel.listen('.MessageReactionUpdated', onReaction);
        channel.listen('.MessageEdited', onEdited);
        channel.listenForWhisper('typing', onTyping);

        const leave = () => {
            clearTimeout(typingTimeoutRef.current);
            window.Echo.leave(channelName);
        };
        turboCleanup(leave);
        return leave;
    }, [activeConversation?.id]);

    // Online/offline dots — someone signing in or out anywhere broadcasts a
    // payload-less ping on this channel (same event Manage Users already
    // relies on); a partial reload just refetches onlineUserIds so dots
    // update live without a full page refresh. This page is the only
    // subscriber to this channel, so a plain join/leave is correct here.
    // Login/logout alone isn't enough, though: the other participant's own
    // "online" status is itself just their sessions.last_activity column
    // (kept fresh by their own AuthenticatedLayout heartbeat), so this also
    // re-polls on an interval to pick up that quietly-changing value even
    // when nobody has actually signed in or out.
    useEffect(() => {
        const refresh = () => router.reload({ only: ['onlineUserIds'] });
        window.Echo.private('user-presence').listen('.UserPresenceChanged', refresh);
        const interval = setInterval(refresh, 30000);

        const leave = () => {
            clearInterval(interval);
            window.Echo.leave('user-presence');
        };
        turboCleanup(leave);
        return leave;
    }, []);

    // Shared inbox channel — AuthenticatedLayout owns its join/leave lifecycle
    // (it needs the same channel for the persistent sidebar badge), so this
    // effect only ever adds/removes ITS OWN callback via stopListening, never
    // Echo.leave(), or it would silently kill the sidebar badge on navigation.
    useEffect(() => {
        const channelName = `chat-inbox.${currentUser.id}`;
        const onInbox = (e) => {
            setConversationList((current) => {
                const idx = current.findIndex((c) => c.id === e.conversation_id);
                if (idx === -1) {
                    // A conversation not yet in this list (someone started a
                    // brand new thread with us) — simplest correct fix is a
                    // partial reload, same idiom Dashboard.jsx uses for pings.
                    router.reload({ only: ['conversations'] });
                    return current;
                }

                const isActiveThread = activeConversation?.id === e.conversation_id;
                const updated = [...current];
                const [item] = updated.splice(idx, 1);
                updated.unshift({
                    ...item,
                    last_message: { body: e.preview, sender_id: null, created_at: new Date().toISOString() },
                    // Set to the server's authoritative count, not a local
                    // +1 — this tab may not be the one that reads it, and a
                    // naive increment would drift out of sync in that case.
                    unread_count: isActiveThread ? 0 : e.conversation_unread_count,
                });
                return updated;
            });
        };

        window.Echo.private(channelName).listen('.ChatInboxUpdated', onInbox);
        const cleanup = () => window.Echo.private(channelName).stopListening('.ChatInboxUpdated', onInbox);
        turboCleanup(cleanup);
        return cleanup;
    }, [currentUser.id, activeConversation?.id]);

    const filteredConversations = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return conversationList;
        return conversationList.filter((c) => c.other_user.name.toLowerCase().includes(term));
    }, [conversationList, search]);

    // The latest own message always shows a Sent/Delivered/Seen label —
    // matches the Messenger/Instagram convention the design was asked to
    // follow. The "seen" avatar, though, belongs on whichever own message
    // was MOST RECENTLY actually read — if the very last message you sent
    // hasn't been opened yet, the avatar stays put on the last one that
    // was, exactly like Messenger does, rather than vanishing.
    const lastOwnMessageId = useMemo(() => {
        for (let i = messages.length - 1; i >= 0; i--) {
            if (messages[i].sender.id === currentUser.id) return messages[i].id;
        }
        return null;
    }, [messages, currentUser.id]);

    const lastSeenOwnMessageId = useMemo(() => {
        for (let i = messages.length - 1; i >= 0; i--) {
            if (messages[i].sender.id === currentUser.id && messages[i].read_at) return messages[i].id;
        }
        return null;
    }, [messages, currentUser.id]);

    const statusFor = (message) => {
        if (message.read_at) return t('Seen');
        if (message.delivered_at) return t('Delivered');
        return t('Sent');
    };

    const handleBodyChange = (e) => {
        setBody(e.target.value);
        if (!activeConversation) return;

        // Throttled — one whisper per ~2s of active typing, not per keystroke.
        const now = Date.now();
        if (now - lastWhisperRef.current > 2000) {
            lastWhisperRef.current = now;
            window.Echo.private(`chat.conversation.${activeConversation.id}`).whisper('typing', {});
        }
    };

    const handleEmojiSelect = (emoji) => {
        const input = composerInputRef.current;
        const start = input?.selectionStart ?? body.length;
        const end = input?.selectionEnd ?? body.length;
        const next = body.slice(0, start) + emoji + body.slice(end);
        setBody(next);

        requestAnimationFrame(() => {
            input?.focus();
            const cursor = start + emoji.length;
            input?.setSelectionRange(cursor, cursor);
        });
    };

    const handleStartChat = (userId) => {
        setShowPicker(false);
        router.post(route('chat.start'), { user_id: userId });
    };

    const handleAttachmentSelect = (e) => {
        const file = e.target.files?.[0];
        if (file) setAttachmentFile(file);
        e.target.value = ''; // allows re-selecting the same file later
    };

    const handleSend = async (e) => {
        e.preventDefault();
        const trimmed = body.trim();
        if ((!trimmed && !attachmentFile) || sending || !activeConversation) return;

        setSending(true);
        try {
            let payload;
            if (attachmentFile) {
                // Axios sets the multipart Content-Type automatically for a
                // FormData body — no manual header or _method spoof needed,
                // this is a plain POST (unlike the PUT+file forms elsewhere
                // in this app that spoof via _method).
                payload = new FormData();
                if (trimmed) payload.append('body', trimmed);
                if (replyingTo) payload.append('reply_to_message_id', replyingTo.id);
                payload.append('attachment', attachmentFile);
            } else {
                payload = { body: trimmed, reply_to_message_id: replyingTo?.id ?? null };
            }

            const { data } = await window.axios.post(route('chat.messages.store', activeConversation.id), payload);
            setMessages((current) => (current.some((m) => m.id === data.message.id) ? current : [...current, data.message]));
            setBody('');
            setReplyingTo(null);
            setAttachmentFile(null);
        } finally {
            setSending(false);
        }
    };

    // If the quoted message isn't in the currently-loaded window (scrolled
    // away, older/paginated-out), this is a no-op — same limitation
    // Messenger itself has for quotes that aren't loaded.
    const handleJumpTo = (messageId) => {
        const node = document.getElementById(`message-${messageId}`);
        if (!node) return;

        node.scrollIntoView({ behavior: 'smooth', block: 'center' });
        node.classList.add('ring-2', 'ring-[#8A3330]', 'rounded-2xl');
        setTimeout(() => node.classList.remove('ring-2', 'ring-[#8A3330]', 'rounded-2xl'), 1200);
    };

    const handleReact = async (messageId, emoji) => {
        if (!activeConversation) return;

        const { data } = await window.axios.post(
            route('chat.messages.react', [activeConversation.id, messageId]),
            { emoji },
        );
        setMessages((current) => current.map((m) => (m.id === messageId ? { ...m, reactions: data.reactions } : m)));
    };

    const handleEditMessage = async (messageId, body) => {
        if (!activeConversation) return;

        const { data } = await window.axios.patch(
            route('chat.messages.update', [activeConversation.id, messageId]),
            { body },
        );
        setMessages((current) => current.map((m) => (m.id === messageId ? data.message : m)));
    };

    const loadOlder = async () => {
        if (!hasMore || loadingOlder || messages.length === 0 || !activeConversation) return;

        setLoadingOlder(true);
        const oldestId = messages[0].id;
        const previousHeight = messageListRef.current?.scrollHeight ?? 0;
        try {
            const { data } = await window.axios.get(route('chat.messages', activeConversation.id), { params: { before: oldestId } });
            setMessages((current) => [...data.messages, ...current]);
            setHasMore(data.has_more);
            requestAnimationFrame(() => {
                if (messageListRef.current) {
                    messageListRef.current.scrollTop = messageListRef.current.scrollHeight - previousHeight;
                }
            });
        } finally {
            setLoadingOlder(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('Chat')} />

            <div className="flex h-[calc(100vh-8rem)] overflow-hidden rounded-2xl border border-[#E5DDD0] bg-white shadow-[0_14px_38px_-30px_rgba(55,35,30,0.55)]">
                {/* Conversation list */}
                <div className={`w-full md:w-80 shrink-0 flex-col border-r border-[#E5DDD0] ${activeConversation ? 'hidden md:flex' : 'flex'}`}>
                    <div className="flex items-center justify-between border-b border-[#E5DDD0] px-4 py-4">
                        <h1 className="text-lg font-bold text-[#251C19]">{t('Chat')}</h1>
                        <button
                            type="button"
                            onClick={() => setShowPicker(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-[#8A3330] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#742927]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-3.5 w-3.5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {t('New Chat')}
                        </button>
                    </div>

                    <div className="border-b border-[#E5DDD0] px-3 py-2.5">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search conversations…')}
                            className="w-full rounded-lg border border-[#D9CCBA] px-3 py-1.5 text-sm focus:border-[#8A3330] focus:outline-none focus:ring-1 focus:ring-[#8A3330]"
                        />
                    </div>

                    <div className="flex-1 overflow-y-auto">
                        {filteredConversations.length === 0 && (
                            <p className="px-4 py-8 text-center text-sm text-gray-400">{t('No conversations yet.')}</p>
                        )}
                        {filteredConversations.map((c) => {
                            const isOnline = onlineUserIds.includes(c.other_user.id);
                            const isActive = activeConversation?.id === c.id;
                            const preview = c.last_message
                                ? `${c.last_message.sender_id === currentUser.id ? `${t('You')}: ` : ''}${c.last_message.body}`
                                : t('No messages yet');

                            return (
                                <Link
                                    key={c.id}
                                    href={route('chat.show', c.id)}
                                    data-turbo="false"
                                    data-turbo-prefetch="false"
                                    className={`flex items-center gap-3 border-l-4 py-3 pl-3 pr-4 hover:bg-[#F3E1DC]/30 ${isActive ? 'border-l-[#8A3330] bg-[#FAF6EE]' : 'border-l-transparent'}`}
                                >
                                    <div className="relative shrink-0">
                                        <Avatar user={c.other_user} className="h-11 w-11 text-sm" />
                                        <span
                                            className={`absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-2 ring-white ${isOnline ? 'bg-green-500' : 'bg-gray-300'}`}
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-sm font-semibold text-[#251C19]">{c.other_user.name}</p>
                                            {c.last_message && (
                                                <span className="shrink-0 text-[11px] text-[#8A7B6D]">{formatListTime(c.last_message.created_at)}</span>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-xs text-gray-500">{preview}</p>
                                            {c.unread_count > 0 && (
                                                <span className="flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-[#8A3330] px-1.5 text-[11px] font-bold text-white">
                                                    {c.unread_count}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>

                {/* Active thread */}
                <div className={`flex-1 flex-col ${!activeConversation ? 'hidden md:flex' : 'flex'}`}>
                    {activeConversation ? (
                        <>
                            <div className="flex items-center gap-3 border-b border-[#E5DDD0] px-4 py-3.5">
                                <Link href={route('chat.index')} data-turbo="false" data-turbo-prefetch="false" className="md:hidden -ml-1 rounded-md p-1 text-gray-500 hover:bg-gray-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-5 w-5">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                    </svg>
                                </Link>
                                <div className="relative shrink-0">
                                    <Avatar user={activeConversation.other_user} className="h-9 w-9 text-xs" />
                                    <span
                                        className={`absolute bottom-0 right-0 h-2 w-2 rounded-full ring-2 ring-white ${
                                            onlineUserIds.includes(activeConversation.other_user.id) ? 'bg-green-500' : 'bg-gray-300'
                                        }`}
                                    />
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-[#251C19]">{activeConversation.other_user.name}</p>
                                    <p className="text-[11px] text-[#8A7B6D]">{activeConversation.other_user.role_label}</p>
                                </div>
                            </div>

                            <div ref={messageListRef} onScroll={(e) => e.target.scrollTop === 0 && loadOlder()} className="flex-1 space-y-3 overflow-y-auto bg-[#FAF6EE] px-4 py-4">
                                {hasMore && (
                                    <p className="text-center text-xs text-gray-400">{loadingOlder ? t('Loading…') : t('Scroll up for older messages')}</p>
                                )}
                                {messages.length === 0 && (
                                    <p className="pt-10 text-center text-sm text-gray-400">{t('Say hello 👋')}</p>
                                )}
                                <AnimatePresence>
                                {messages.map((m) => (
                                    <MessageBubble
                                        key={m.id}
                                        message={m}
                                        isOwn={m.sender.id === currentUser.id}
                                        statusLabel={m.id === lastOwnMessageId ? statusFor(m) : null}
                                        seenByUser={m.id === lastSeenOwnMessageId ? activeConversation.other_user : null}
                                        emojiBodies={emojiBodies}
                                        onReact={handleReact}
                                        onReply={setReplyingTo}
                                        onJumpTo={handleJumpTo}
                                        onEdit={handleEditMessage}
                                    />
                                ))}
                                </AnimatePresence>
                                {partnerTyping && <TypingBubble user={activeConversation.other_user} />}
                            </div>

                            <div className="sticky bottom-0 bg-[#F7F0E3]/95 backdrop-blur">
                                {attachmentFile && (
                                    <div className="flex items-center gap-2 border-t border-[#E5DDD0] px-4 py-2">
                                        <div className="min-w-0 flex-1 rounded-lg bg-black/[0.04] px-2 py-1">
                                            <p className="truncate text-xs font-semibold text-[#6B5D52]">{attachmentFile.name}</p>
                                            <p className="text-[11px] text-[#8A7B6D]">{formatFileSize(attachmentFile.size)}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setAttachmentFile(null)}
                                            className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[#8A7B6D] hover:bg-[#F3E1DC]/70"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-3.5 w-3.5">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                )}
                                {replyingTo && (
                                    <div className="flex items-center gap-2 border-t border-[#E5DDD0] px-4 py-2">
                                        <div className="min-w-0 flex-1 border-l-2 border-[#8A3330] pl-2">
                                            <p className="text-[11px] font-semibold text-[#8A3330]">
                                                {t('Replying to')} {replyingTo.sender.id === currentUser.id ? t('yourself') : replyingTo.sender.name}
                                            </p>
                                            <p className="truncate text-xs text-[#8A7B6D]">{replyingTo.body}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setReplyingTo(null)}
                                            className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[#8A7B6D] hover:bg-[#F3E1DC]/70"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-3.5 w-3.5">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                )}
                                <form onSubmit={handleSend} className="flex items-center gap-2 border-t border-[#E5DDD0] px-4 py-3">
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    hidden
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"
                                    onChange={handleAttachmentSelect}
                                />
                                <div className="relative flex-1">
                                    <div className="flex items-center gap-1 overflow-hidden rounded-full border border-[#D9CCBA] bg-white pl-4 pr-1.5 focus-within:border-[#8A3330] focus-within:ring-1 focus-within:ring-[#8A3330]">
                                        <input
                                            ref={composerInputRef}
                                            type="text"
                                            value={body}
                                            onChange={handleBodyChange}
                                            placeholder={t('Type a message…')}
                                            className="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm shadow-none outline-none ring-0 focus:border-0 focus:shadow-none focus:outline-none focus:ring-0"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => fileInputRef.current?.click()}
                                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[#8A7B6D] hover:bg-[#F3E1DC]/70"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-5 w-5">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowEmojiPicker((v) => !v)}
                                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-[#F3E1DC]/70"
                                        >
                                            <Emoji char="🙂" bodies={emojiBodies} className="h-5 w-5" />
                                        </button>
                                    </div>
                                    {showEmojiPicker && (
                                        <EmojiPicker
                                            onSelect={handleEmojiSelect}
                                            onClose={() => setShowEmojiPicker(false)}
                                            bodies={emojiBodies}
                                        />
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    disabled={(!body.trim() && !attachmentFile) || sending}
                                    className="flex h-10 w-10 shrink-0 items-center justify-center text-[#8A3330] hover:text-[#742927] disabled:opacity-40"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="currentColor" className="h-7 w-7">
                                        <path d="M29.919 6.163l-4.225 19.925c-0.319 1.406-1.15 1.756-2.331 1.094l-6.438-4.744-3.106 2.988c-0.344 0.344-0.631 0.631-1.294 0.631l0.463-6.556 11.931-10.781c0.519-0.462-0.113-0.719-0.806-0.256l-14.75 9.288-6.35-1.988c-1.381-0.431-1.406-1.381 0.288-2.044l24.837-9.569c1.15-0.431 2.156 0.256 1.781 2.013z" />
                                    </svg>
                                </button>
                                </form>
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-[#F3E1DC] text-[#8A3330]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-7 w-7">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                </svg>
                            </div>
                            <p className="text-sm font-medium text-gray-500">{t('Select a conversation or start a new chat')}</p>
                        </div>
                    )}
                </div>
            </div>

            {showPicker && <NewChatPicker directory={directory} onSelect={handleStartChat} onClose={() => setShowPicker(false)} />}
        </AuthenticatedLayout>
    );
}
