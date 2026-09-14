import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// The app is reachable several ways at once: over the LAN
// (http://<pc-ip>:8124), a public Cloudflare demo tunnel
// (https://*.trycloudflare.com), and — in production — the real domain over
// HTTPS. A single baked-in VITE_REVERB_HOST can't match all of them, so pick
// the websocket endpoint from wherever THIS page was actually served:
//  - any HTTPS page (tunnel or the real production domain) -> Reverb is
//    reverse-proxied through Nginx on the same host, port 443, TLS on —
//    plain ws:// would be blocked as mixed content on an https:// page.
//  - plain HTTP (local LAN dev) -> Reverb's own port, no TLS, which also
//    survives the PC's LAN IP changing between sessions.
const viaTunnel = window.location.hostname.endsWith('.trycloudflare.com');
const isSecurePage = window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: viaTunnel
        ? (import.meta.env.VITE_REVERB_TUNNEL_HOST || import.meta.env.VITE_REVERB_HOST)
        : window.location.hostname,
    wsPort: isSecurePage ? 443 : (import.meta.env.VITE_REVERB_PORT ?? 8081),
    wssPort: isSecurePage ? 443 : (import.meta.env.VITE_REVERB_PORT ?? 8081),
    forceTLS: isSecurePage,
    enabledTransports: ['ws', 'wss'],
});
