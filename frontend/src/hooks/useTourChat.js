import { useCallback, useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getStoredUser } from '../lib/api';
import { getEcho, getEchoSocketId } from '../lib/echo';

const ACTIVITY_EVENTS = [
  'mousemove',
  'mousedown',
  'click',
  'keydown',
  'touchstart',
  'wheel',
  'scroll',
];

function socketHeaders() {
  const sid = getEchoSocketId();
  return sid ? { 'X-Socket-ID': sid } : {};
}

function withMine(list) {
  const myId = getStoredUser()?.id;
  return (list ?? []).map((m) => ({
    ...m,
    is_mine: myId != null ? Number(m.sender_id) === Number(myId) : !!m.is_mine,
  }));
}

/**
 * Tour 1vs1 chat: realtime via Reverb socket + REST polling fallback.
 * Works even when the socket server is down (polling every 5s).
 *
 * Read receipts are ACTIVITY-GATED: a message flips unread -> read only when
 * the recipient has this thread open AND performs any interaction
 * (mouse, click, key, touch, scroll, focus) after the message arrived.
 * GET list is read-only and never marks anything as read.
 */
export function useTourChat(bookingUuid, enabled = true) {
  const queryClient = useQueryClient();
  const [socketLive, setSocketLive] = useState(false);
  const channelRef = useRef(null);
  const pendingReadRef = useRef(false);
  const markingRef = useRef(false);
  const markTimerRef = useRef(null);

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['tour-messages', bookingUuid],
    queryFn: async () => {
      const res = await api.get(`/tour-bookings/${bookingUuid}/messages`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load messages');
      return { ...res.data, data: withMine(res.data?.data) };
    },
    enabled: !!bookingUuid && enabled,
    // Always fetch fresh on open: messages sent while the chat was closed
    // (unsubscribed) would otherwise stay hidden behind a fresh cache
    // (global staleTime 60s) until the next poll tick.
    refetchOnMount: 'always',
    // Polling fallback: fast when socket is down, slow heartbeat otherwise.
    refetchInterval: socketLive ? 30_000 : 5_000,
  });

  const messages = data?.data ?? [];

  const markRead = useCallback(async () => {
    if (!bookingUuid || !enabled || markingRef.current) return null;
    markingRef.current = true;
    try {
      const res = await api.post(
        `/tour-bookings/${bookingUuid}/messages/read`,
        {},
        { headers: socketHeaders() },
      );
      if (!res.data?.success) return null;
      const readUntil = res.data?.data?.read_until;
      pendingReadRef.current = false;
      if (readUntil) {
        const myId = getStoredUser()?.id;
        queryClient.setQueryData(['tour-messages', bookingUuid], (old) => {
          const list = old?.data ?? [];
          let changed = false;
          const updated = list.map((m) => {
            const mine = myId != null ? Number(m.sender_id) === Number(myId) : !!m.is_mine;
            if (mine || m.read_at) return mine !== m.is_mine ? { ...m, is_mine: mine } : m;
            changed = true;
            return { ...m, is_mine: mine, read_at: readUntil };
          });
          return changed ? { ...old, data: updated, success: true } : old;
        });
      }
      return res.data?.data ?? null;
    } catch {
      return null;
    } finally {
      markingRef.current = false;
    }
  }, [bookingUuid, enabled, queryClient]);

  const scheduleMarkRead = useCallback(() => {
    if (!pendingReadRef.current || markingRef.current) return;
    if (document.visibilityState !== 'visible') return;
    if (markTimerRef.current) return;
    markTimerRef.current = setTimeout(() => {
      markTimerRef.current = null;
      markRead();
    }, 800);
  }, [markRead]);

  // If the initial fetch already contains unread messages from the other
  // party, they still require a user interaction before flipping to read.
  useEffect(() => {
    if (!enabled || messages.length === 0) return;
    const myId = getStoredUser()?.id;
    const hasUnread = messages.some((m) => {
      const mine = myId != null ? Number(m.sender_id) === Number(myId) : !!m.is_mine;
      return !mine && !m.read_at;
    });
    if (hasUnread) pendingReadRef.current = true;
  }, [enabled, messages.length]); // eslint-disable-line react-hooks/exhaustive-deps

  // Any user interaction while the thread is open may confirm pending reads.
  useEffect(() => {
    if (!enabled) return;
    const onActivity = () => scheduleMarkRead();
    const onFocus = () => scheduleMarkRead();
    const onVisibility = () => {
      if (document.visibilityState === 'visible') scheduleMarkRead();
    };
    ACTIVITY_EVENTS.forEach((e) => window.addEventListener(e, onActivity, { passive: true }));
    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      ACTIVITY_EVENTS.forEach((e) => window.removeEventListener(e, onActivity));
      window.removeEventListener('focus', onFocus);
      document.removeEventListener('visibilitychange', onVisibility);
      if (markTimerRef.current) clearTimeout(markTimerRef.current);
      markTimerRef.current = null;
    };
  }, [enabled, scheduleMarkRead]);

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
      const [normalized] = withMine([incoming]);
      // Incoming from the other party still needs a user interaction
      // before it flips to read — just flag it for now.
      const myId = getStoredUser()?.id;
      const fromOther = myId != null
        ? Number(normalized.sender_id) !== Number(myId)
        : !normalized.is_mine;
      if (fromOther) pendingReadRef.current = true;
      queryClient.setQueryData(['tour-messages', bookingUuid], (old) => {
        const list = old?.data ?? [];
        if (list.some((m) => m.id === normalized.id)) {
          // Refresh is_mine normalization on duplicates too.
          return {
            ...old,
            data: list.map((m) => (m.id === normalized.id ? { ...m, ...normalized } : m)),
          };
        }
        return { ...old, data: [...list, normalized], success: true };
      });
    });
    channel.listen('.message.read', (event) => {
      if (!event?.read_until || !event?.reader_id) return;
      setSocketLive(true);
      const until = new Date(event.read_until).getTime();
      queryClient.setQueryData(['tour-messages', bookingUuid], (old) => {
        const list = old?.data ?? [];
        let changed = false;
        const updated = list.map((m) => {
          if (m.sender_id === event.reader_id || m.read_at) return m;
          const createdAt = m.created_at ? new Date(m.created_at).getTime() : 0;
          if (createdAt <= until) {
            changed = true;
            return { ...m, read_at: event.read_until };
          }
          return m;
        });
        return changed ? { ...old, data: updated, success: true } : old;
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
      const res = await api.post(
        `/tour-bookings/${bookingUuid}/messages`,
        { body: text },
        { headers: socketHeaders() },
      );
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to send');
      const sent = res.data?.data;
      const [normalized] = sent ? withMine([sent]) : [null];
      // Optimistic append (deduped against socket echo by id).
      queryClient.setQueryData(['tour-messages', bookingUuid], (old) => {
        const list = old?.data ?? [];
        if (normalized && list.some((m) => m.id === normalized.id)) return old;
        return { ...old, data: normalized ? [...list, normalized] : list, success: true };
      });
      return normalized ?? sent;
    },
    [bookingUuid, queryClient]
  );

  return { messages, isLoading, isError, refetch, sendMessage, markRead, socketLive };
}
