import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = null;
window.JASPEST_ECHO_CONFIGURED = true;

const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    const forceTLS = scheme === 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
    });
}

// admin.js may be loaded before or after this Vite module; the event handles both orders.
window.dispatchEvent(new CustomEvent('jaspest:echo-ready'));
