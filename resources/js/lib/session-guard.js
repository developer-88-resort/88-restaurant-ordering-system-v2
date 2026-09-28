// Signing out of a shared tablet has to really end what was on screen:
//
//   - Back (button, phone key or swipe) must not bring a signed-in page
//     back. The server marks those pages no-store (PreventCachingSignedInPages)
//     so Back asks it again and gets sent to the login screen; this covers a
//     browser that keeps the page in its back-forward cache anyway, by hiding
//     it before it can be seen and reloading, which the server then redirects.
//   - The next person to sign in must not find the last one's unfinished
//     order or filters, so those are cleared on the way out.

import { clearAllDrafts } from '../draft-persistence';

// Per-tab screen state that belongs to whoever was signed in.
const SESSION_KEYS = ['orders.selectedStatus'];

let signingOut = false;

function signedInPage() {
    return document.querySelector('meta[name="auth-user"]');
}

function hidePage() {
    document.documentElement.style.visibility = 'hidden';
}

function isLogoutForm(form) {
    const logoutUrl = signedInPage()?.dataset.logoutUrl;
    if (!logoutUrl || !(form instanceof HTMLFormElement)) return false;

    // Paths only: the same page is reached by LAN IP, tunnel and domain.
    try {
        return new URL(form.action, window.location.href).pathname === new URL(logoutUrl, window.location.href).pathname;
    } catch (e) {
        return false;
    }
}

// Also called by the idle timer, whose form.submit() fires no submit event.
export function noteSigningOut() {
    signingOut = true;
    clearAllDrafts();
    SESSION_KEYS.forEach((key) => {
        try {
            sessionStorage.removeItem(key);
        } catch (e) {
            // Storage unavailable: nothing was kept there.
        }
    });
}

export function initSessionGuard() {
    if (window.__sessionGuardStarted) return;
    window.__sessionGuardStarted = true;

    // Capture phase, so it runs even where a handler stops the event.
    document.addEventListener('submit', (event) => {
        if (isLogoutForm(event.target)) noteSigningOut();
    }, true);

    // The page is kept exactly as it is left: hidden, if it's being left by
    // signing out, so a cached copy has nothing to show if Back restores it.
    window.addEventListener('pagehide', () => {
        if (signingOut) hidePage();
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted || !signedInPage()) return;

        hidePage();
        window.location.reload();
    });
}
