import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const root = document.documentElement;
const serverKey = root.dataset.reverbAppKey;
const host = root.dataset.reverbHost || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const port = Number(root.dataset.reverbPort || import.meta.env.VITE_REVERB_PORT || 8080);
const scheme = root.dataset.reverbScheme || import.meta.env.VITE_REVERB_SCHEME || 'http';
const key = serverKey || import.meta.env.VITE_REVERB_APP_KEY;
const wsScheme = scheme === 'https' ? 'wss' : 'ws';

if (key && !key.includes('${')) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: wsScheme === 'wss',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    window.Echo = null;
}
