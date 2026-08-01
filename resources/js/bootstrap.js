import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// The app is reachable two ways at once: over the LAN (http://<pc-ip>:8124)
// and over a public Cloudflare demo tunnel (https://*.trycloudflare.com).
// A single baked-in VITE_REVERB_HOST can only ever match one of them, so
// pick the websocket endpoint from wherever THIS page was actually served:
//  - tunnel page  -> the Reverb tunnel hostname (TLS on 443)
//  - anything else -> the same host the page came from, on Reverb's port,
//    which also survives the PC's LAN IP changing between sessions.
const viaTunnel = window.location.hostname.endsWith('.trycloudflare.com');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: viaTunnel
        ? (import.meta.env.VITE_REVERB_TUNNEL_HOST || import.meta.env.VITE_REVERB_HOST)
        : window.location.hostname,
    wsPort: viaTunnel ? 443 : (import.meta.env.VITE_REVERB_PORT ?? 8081),
    wssPort: viaTunnel ? 443 : (import.meta.env.VITE_REVERB_PORT ?? 8081),
    forceTLS: viaTunnel,
    enabledTransports: ['ws', 'wss'],
});
