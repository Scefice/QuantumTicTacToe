import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
const wsScheme = scheme === 'https' ? 'wss' : 'ws';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: wsScheme === 'wss',
    enabledTransports: ['ws', 'wss'],
});
