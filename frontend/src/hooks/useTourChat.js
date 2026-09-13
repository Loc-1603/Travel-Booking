import { useCallback, useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../lib/api';
import { getEcho } from '../lib/echo';

/**
 * Tour 1vs1 chat: realtime via Reverb socket + REST polling fallback.
 * Works even when the socket server is down (polling every 5s).
 */
export function useTourChat(bookingUuid, enabled = true) {
  const queryClient = useQueryClient();
  const [socketLive, setSocketLive] = useState(false);
  const channelRef = useRef(null);

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['tour-messages', bookingUuid],
    queryFn: async () => {
      const res = await api.get(`/tour-bookings/${bookingUuid}/messages`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load messages');
      return res.data;
    },
    enabled: !!bookingUuid && enabled,
    // Polling fallback: fast when socket is down, slow heartbeat otherwise.
    refetchInterval: socketLive ? 30_000 : 5_000,
  });

  const messages = data?.data ?? [];

  useEffect(() => {
    if (!bookingUuid || !enabled) return;
    const echo = getEcho();
    if (!echo) return;
    const channel = echo.private(`tour.booking.${bookingUuid}`);
    channelRef.current = channel;
    channel.listen('.message.sent', (event) => {
      const incoming = event?.message;
      if (!incoming) return;
      setSocketLive(true);
      queryClient.setQueryData(['tour-messages', bookingUuid], (old) => {
        const list = old?.data ?? [];
        if (list.some((m) => m.id === incoming.id)) return old;
        return { ...old, data: [...list, incoming], success: true };
      });
    });
    const timer = setTimeout(() => {
      try {
        setSocketLive(echo.connector?.pusher?.connection?.state === 'connected');
      } catch { /* non-fatal: ignore */ }
    }, 3000);
    return () => {
      clearTimeout(timer);
      try {
        echo.leave(`tour.booking.${bookingUuid}`);
      } catch { /* non-fatal: ignore */ }
      channelRef.current = null;
      setSocketLive(false);
    };
  }, [bookingUuid, enabled, queryClient]);

  const sendMessage = useCallback(
    async (body) => {
      const text = (body || '').trim();
      if (!text) return null;
      const res = await api.post(`/tour-bookings/${bookingUuid}/messages`, { body: text });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to send');
      const sent = res.data?.data;
      // Optimistic append (deduped against socket echo by id).
      queryClient.setQueryData(['tour-messages', bookingUuid], (old) => {
        const list = old?.data ?? [];
        if (sent && list.some((m) => m.id === sent.id)) return old;
        return { ...old, data: sent ? [...list, sent] : list, success: true };
      });
      return sent;
    },
    [bookingUuid, queryClient]
  );

  return { messages, isLoading, isError, refetch, sendMessage, socketLive };
}
