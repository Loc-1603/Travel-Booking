import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getStoredToken } from './api';

let echo = null;

/**
 * Laravel Echo singleton over Reverb (Pusher protocol).
 * Returns null when Reverb is not configured — callers fall back to REST polling.
 */
export function getEcho() {
  if (echo) return echo;
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (!key) return null;
  try {
    window.Pusher = window.Pusher || Pusher;
    echo = new Echo({
      broadcaster: 'reverb',
      key,
      wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${import.meta.env.VITE_API_URL || '/api/v1'}/broadcasting/auth`,
      auth: {
        headers: { Authorization: `Bearer ${getStoredToken() || ''}` },
      },
    });
    echo.connector.pusher.connection.bind('error', () => {
      // Socket errors are non-fatal: REST polling keeps chat working.
    });
  } catch {
    echo = null;
  }
  return echo;
}

/** Refresh the auth header after login/logout. */
export function resetEcho() {
  try {
    echo?.disconnect();
  } catch { /* non-fatal: ignore */ }
  echo = null;
}
