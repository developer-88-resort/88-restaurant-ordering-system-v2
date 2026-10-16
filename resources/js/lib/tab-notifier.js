// Notifications on the browser tab, like "(3) Facebook": the tab title gets
// "(N) " in front and the favicon a red dot while there is something waiting —
// unread chat, new orders (Pending) and orders the kitchen has Ready — and a
// small card pops up saying what just came in and where (Chat, Orders,
// Kitchen, Assistance); clicking it goes there.
//
// The numbers come from /notifications/summary (NotificationSummaryController)
// and are refetched whenever the websocket says one of them changed, plus
// every minute and when the tab comes back into view, in case a message was
// missed. Loaded by both app.js (Blade pages) and app.jsx (React pages); it
// starts once per page load whichever loads first.

const SUMMARY_URL = '/notifications/summary';
const PREFIX = /^\(\d+\+?\)\s/;

const KINDS = {
    chat: { label: 'Chat', icon: '💬', bg: '#E0F2FE', fg: '#075985' },
    orders: { label: 'Orders', icon: '🧾', bg: '#FEF3C7', fg: '#92400E' },
    kitchen: { label: 'Kitchen', icon: '🍳', bg: '#D1FAE5', fg: '#065F46' },
    assistance: { label: 'Assistance', icon: '🙋', bg: '#FFE4E6', fg: '#9F1239' },
};

export function initTabNotifier() {
    if (window.__tabNotifier) return;
    window.__tabNotifier = true;

    const state = {
        enabled: true,
        summary: null,
        total: 0,
        originalIcon: null,
        dottedIcon: null,
        refreshTimer: null,
        // The page's own title ("Order Management"), the last title this
        // module wrote, and what just came in ("🧾 KR - KR 3 · New order …"),
        // which takes turns with the page title in the tab, like Facebook's
        // "Juan messaged you", until the tab is looked at again.
        pageTitle: document.title.replace(PREFIX, ''),
        lastSet: null,
        flash: null,
        flashOn: false,
        flashTimer: null,
    };

    // ---- Title -----------------------------------------------------------
    const applyTitle = () => {
        const count = state.total > 0 ? `(${state.total > 99 ? '99+' : state.total}) ` : '';
        const text = state.flash && state.flashOn ? state.flash : state.pageTitle;
        const wanted = count + text;
        state.lastSet = wanted;
        if (document.title !== wanted) document.title = wanted;
    };

    const stopFlash = () => {
        clearInterval(state.flashTimer);
        state.flashTimer = null;
        state.flash = null;
        state.flashOn = false;
        applyTitle();
    };

    const lookingAtTab = () => document.visibilityState === 'visible' && document.hasFocus();

    // In the background it keeps alternating until the tab is opened; while
    // the tab is being looked at it shows once, for a few seconds.
    const flashTitle = (text) => {
        clearInterval(state.flashTimer);
        state.flash = text;
        state.flashOn = true;
        applyTitle();
        if (lookingAtTab()) {
            state.flashTimer = setTimeout(stopFlash, 5000);
        } else {
            state.flashTimer = setInterval(() => {
                state.flashOn = !state.flashOn;
                applyTitle();
            }, 1500);
        }
    };

    window.addEventListener('focus', () => {
        if (state.flash && document.visibilityState === 'visible') setTimeout(stopFlash, 1500);
    });

    // ---- Favicon dot -------------------------------------------------------
    const iconLink = () => {
        let link = document.querySelector('link[rel~="icon"]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            link.href = '/favicon.ico';
            document.head.appendChild(link);
        }
        return link;
    };

    const buildDottedIcon = (href) => new Promise((resolve) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        // The dot alone if the icon itself can't be drawn (missing / empty file).
        const draw = (withImage) => {
            const size = 64;
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            if (withImage) ctx.drawImage(image, 0, 0, size, size);
            ctx.beginPath();
            ctx.arc(size - 16, 16, 15, 0, 2 * Math.PI);
            ctx.fillStyle = '#DC2626';
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#FFFFFF';
            ctx.stroke();
            try {
                resolve(canvas.toDataURL('image/png'));
            } catch {
                resolve(null);
            }
        };
        image.onload = () => draw(true);
        image.onerror = () => draw(false);
        image.src = href;
    });

    const applyIcon = async () => {
        const link = iconLink();
        if (!link.dataset.originalHref) link.dataset.originalHref = link.getAttribute('href');
        if (state.total > 0) {
            if (!state.dottedIcon) state.dottedIcon = await buildDottedIcon(link.dataset.originalHref);
            if (state.dottedIcon && link.getAttribute('href') !== state.dottedIcon) link.setAttribute('href', state.dottedIcon);
        } else if (link.getAttribute('href') !== link.dataset.originalHref) {
            link.setAttribute('href', link.dataset.originalHref);
        }
    };

    const applyAll = () => {
        applyTitle();
        applyIcon();
    };

    // Inertia's <Head> and Turbo both rewrite the title / head on navigation.
    new MutationObserver(() => {
        if (!state.summary) return;
        // A title this module didn't write is the page's own (new page).
        if (document.title !== state.lastSet) state.pageTitle = document.title.replace(PREFIX, '');
        applyAll();
    }).observe(document.head, { childList: true, subtree: true, characterData: true });

    // ---- Pop-up cards --------------------------------------------------------
    const toastRoot = () => {
        let root = document.getElementById('tab-notifier-toasts');
        if (!root) {
            root = document.createElement('div');
            root.id = 'tab-notifier-toasts';
            // Inline styles: Tailwind only scans .jsx under resources/js.
            root.style.cssText = 'position:fixed;right:16px;top:80px;z-index:200;display:flex;flex-direction:column;gap:8px;width:320px;max-width:calc(100vw - 32px);pointer-events:none;';
            document.body.appendChild(root);
        }
        return root;
    };

    const notify = (kind, message, href, tabText) => {
        const meta = KINDS[kind];
        flashTitle(`${meta.icon} ${tabText ?? message}`);
        const card = document.createElement('div');
        card.style.cssText = 'pointer-events:auto;cursor:pointer;background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:12px;font-size:14px;line-height:1.4;box-shadow:0 10px 25px -5px rgba(15,23,42,.18);';
        card.setAttribute('role', 'status');

        const head = document.createElement('div');
        head.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;';
        const pill = document.createElement('span');
        pill.style.cssText = `display:inline-flex;align-items:center;gap:4px;border-radius:9999px;padding:2px 8px;font-size:11px;font-weight:700;background:${meta.bg};color:${meta.fg};`;
        pill.textContent = `${meta.icon} ${meta.label}`;
        const close = document.createElement('button');
        close.type = 'button';
        close.style.cssText = 'background:none;border:0;color:#94A3B8;font-size:18px;line-height:1;cursor:pointer;padding:0 2px;';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '×';
        head.append(pill, close);

        const body = document.createElement('p');
        body.style.cssText = 'margin:6px 0 0;color:#1E293B;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;';
        body.textContent = message;

        card.append(head, body);
        const remove = () => card.remove();
        close.addEventListener('click', (event) => {
            event.stopPropagation();
            remove();
        });
        card.addEventListener('click', () => {
            remove();
            if (href) window.location.href = href;
        });

        const root = toastRoot();
        root.prepend(card);
        while (root.children.length > 4) root.lastElementChild.remove();
        setTimeout(remove, 10000);
    };

    // ---- Numbers -----------------------------------------------------------
    const refresh = async () => {
        if (!state.enabled) return;
        try {
            const response = await fetch(SUMMARY_URL, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (response.status === 401 || response.status === 403 || response.status === 419) {
                state.enabled = false; // signed out — nothing to show
                return;
            }
            if (!response.ok) return;
            const summary = await response.json();
            const first = state.summary === null;
            state.summary = summary;
            state.total = summary.counts.chat + summary.counts.orders + summary.counts.kitchen;
            applyAll();
            if (first) subscribe();
        } catch {
            // offline for a moment — the next refresh catches up
        }
    };

    // Several websocket events often arrive together; one fetch covers them.
    const refreshSoon = () => {
        clearTimeout(state.refreshTimer);
        state.refreshTimer = setTimeout(refresh, 400);
    };

    const onChat = (event) => {
        const onThatChat = window.location.pathname === `/chat/${event.conversation_id}`;
        if (!onThatChat && (event.unread_count ?? 0) > (state.summary?.counts.chat ?? 0)) {
            notify('chat', `${event.sender_name ?? 'New message'}: ${event.preview ?? ''}`.trim(), `/chat/${event.conversation_id}`,
                `${event.sender_name ?? 'Someone'} messaged you`);
        }
        refreshSoon();
    };

    const onOrder = (event) => {
        // "KR - KR 3", "Take-out" — where the order is.
        const where = event.location ? `${event.location} · ` : '';
        if (event.action === 'created') {
            notify('orders', `${where}New order ${event.order_number}`, state.summary?.links.orders, `${where}New order`);
        } else if (event.action === 'status_changed' && event.status === 'ready') {
            notify('kitchen', `${where}${event.order_number} is ready to serve`, state.summary?.links.kitchen, `${where}Ready to serve`);
        }
        refreshSoon();
    };

    const onAssistance = (event) => {
        notify('assistance', event.message ?? 'A guest is asking for assistance.', state.summary?.links.orders, 'Assistance requested');
    };

    // Pages leave shared channels when they unload (turboCleanup), which
    // drops these listeners too — so they're (re)attached after every page
    // change. stopListening first keeps a still-open channel from doubling up.
    const listen = (channelName, event, handler) => {
        const channel = window.Echo.private(channelName);
        channel.stopListening(event, handler);
        channel.listen(event, handler);
    };

    const subscribe = () => {
        if (!window.Echo || !state.summary) return;
        try {
            listen(`chat-inbox.${state.summary.user_id}`, '.ChatInboxUpdated', onChat);
            if (state.summary.restaurant) {
                listen('orders', '.OrderUpdated', onOrder);
                listen('kitchen', '.KitchenUpdated', refreshSoon);
                listen('staff-alerts', '.StaffAssistanceRequested', onAssistance);
            }
        } catch {
            // websocket not ready yet — the minute refresh keeps the numbers right
        }
    };

    const afterNavigation = () => {
        if (!state.summary) return;
        applyAll();
        setTimeout(subscribe, 0);
    };
    document.addEventListener('turbo:load', afterNavigation);
    document.addEventListener('inertia:navigate', afterNavigation);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;
        refresh();
        if (state.flash) setTimeout(stopFlash, 1500);
    });
    // Also while the tab is in the background — that is when the title matters.
    setInterval(refresh, 60000);

    refresh();
}
