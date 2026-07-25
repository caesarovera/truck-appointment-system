import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getAuthToken } from '@/api/client';

/**
 * Instansi Echo tunggal (singleton) untuk seluruh SPA. Sengaja lazy: hanya
 * dibuat SETELAH login (butuh token untuk /broadcasting/auth) dan TIDAK dibuat
 * sama sekali bila `VITE_REVERB_APP_KEY` kosong → aplikasi berperilaku seperti
 * tanpa realtime (query tetap ter-refresh lewat invalidasi manual/refetch).
 * Reverb berbicara protokol Pusher, jadi pusher-js dipasang sebagai transport.
 */
let echo: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
    return echo;
}

export function connectEcho(): Echo<'reverb'> | null {
    if (echo) {
        return echo;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) {
        return null; // degradasi mulus — tanpa Reverb tak ada realtime, bukan error
    }

    // pusher-js membaca global window.Pusher.
    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 80));

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Channel privat diotorisasi lewat endpoint Sanctum (guard auth:sanctum);
        // token Bearer dikirim manual karena SPA tak memakai cookie sesi.
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: { Authorization: `Bearer ${getAuthToken() ?? ''}` },
        },
    });

    return echo;
}

export function disconnectEcho(): void {
    echo?.disconnect();
    echo = null;
}
