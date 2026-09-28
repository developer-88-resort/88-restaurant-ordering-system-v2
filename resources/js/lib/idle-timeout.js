// Signs a Staff/Admin out of a shared tablet after a stretch with no taps or
// typing, so the next person doesn't work under the last one's name.
//
// Only runs where the page carries <meta name="idle-timeout"> (both root
// templates add it for PIN users only), and does nothing on a page marked
// [data-idle-exempt] — the Kitchen Display, a wall screen nobody touches.
//
// "Last activity" lives in localStorage, keyed to this sign-in's session, so:
//   - every open tab shares it (typing in one keeps the others alive), and
//   - a page that reloads itself (Order Management, the Kitchen board) picks
//     up where it was instead of counting its own reload as activity.
// The server keeps its own clock too (EnforceIdleTimeout); the heartbeat ping
// below tells it about taps that never made a request.

import { noteSigningOut } from './session-guard';

const CHECK_EVERY_MS = 5000;
const PING_EVERY_MS = 60000;
const WARN_BEFORE_MS = 30000;

let lastActivity = 0;
let activeSinceLastPing = false;
let signingOut = false;
let warning = null;

function settings() {
    const meta = document.querySelector('meta[name="idle-timeout"]');
    if (!meta) return null;

    return {
        timeoutMs: Number(meta.content) * 1000,
        storageKey: `idle:last-activity:${meta.dataset.sessionKey}`,
        logoutUrl: meta.dataset.logoutUrl,
        heartbeatUrl: meta.dataset.heartbeatUrl,
        warningText: meta.dataset.warningText,
    };
}

function readStored(key) {
    try {
        return Number(localStorage.getItem(key)) || 0;
    } catch {
        return 0;
    }
}

function store(key, time) {
    try {
        localStorage.setItem(key, String(time));
    } catch {
        // Private mode or storage blocked: this tab's own clock still works.
    }
}

function noteActivity() {
    const config = settings();
    if (!config || signingOut) return;

    const now = Date.now();
    // Writing on every pointer move would be wasteful; once a second is plenty.
    if (now - lastActivity > 1000) store(config.storageKey, now);
    lastActivity = now;
    activeSinceLastPing = true;
    hideWarning();
}

function check() {
    const config = settings();
    if (!config || signingOut) {
        hideWarning();
        return;
    }

    if (document.querySelector('[data-idle-exempt]')) {
        noteActivity();
        return;
    }

    const idleFor = Date.now() - Math.max(lastActivity, readStored(config.storageKey));

    if (idleFor >= config.timeoutMs) {
        signOut(config);
    } else if (idleFor >= config.timeoutMs - WARN_BEFORE_MS) {
        showWarning(config, Math.ceil((config.timeoutMs - idleFor) / 1000));
    } else {
        hideWarning();
    }
}

function ping() {
    const config = settings();
    if (!config || !activeSinceLastPing || !window.axios) return;

    activeSinceLastPing = false;
    window.axios.post(config.heartbeatUrl, { active: 1 }).catch(() => {});
}

function signOut(config) {
    signingOut = true;
    noteSigningOut();

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = config.logoutUrl;
    form.dataset.turbo = 'false';
    form.hidden = true;

    const fields = {
        _token: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        reason: 'idle',
    };
    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

function showWarning(config, seconds) {
    if (!warning) {
        warning = document.createElement('div');
        warning.setAttribute('role', 'status');
        // Inline, not Tailwind classes: tailwind.config.js doesn't scan .js files.
        warning.style.cssText =
            'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:100;max-width:calc(100vw - 32px);' +
            'border-radius:16px;background:#241917;color:#fff;padding:12px 20px;font-size:14px;font-weight:600;' +
            'box-shadow:0 25px 50px -12px rgba(0,0,0,.45);text-align:center;';
        document.body.appendChild(warning);
    }
    warning.textContent = config.warningText.replace(':seconds', seconds);
}

function hideWarning() {
    warning?.remove();
    warning = null;
}

export function initIdleTimeout() {
    if (window.__idleTimeoutStarted) return;
    window.__idleTimeoutStarted = true;

    const config = settings();
    if (config) {
        // First page of this sign-in: the clock starts now. A reload later in
        // the same sign-in keeps the stored time.
        lastActivity = readStored(config.storageKey);
        if (!lastActivity) {
            lastActivity = Date.now();
            store(config.storageKey, lastActivity);
        }
    }

    ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((type) =>
        window.addEventListener(type, noteActivity, { capture: true, passive: true }),
    );

    // A tablet that was asleep gets checked the moment it wakes, not 5s later.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') check();
    });

    setInterval(check, CHECK_EVERY_MS);
    setInterval(ping, PING_EVERY_MS);
}
