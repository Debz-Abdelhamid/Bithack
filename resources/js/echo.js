import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Realtime notification transport (Claude.md §3): Laravel Reverb over the
// Pusher protocol. Loaded into the Filament panel head; Filament's database
// notifications component picks up window.Echo automatically and falls back
// to polling when it is absent.
window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
