import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/*
 * Admin vendor live client for tour messages.
 * Config is injected as window.TourMessagesLive by admin/layouts/javascript.blade.php.
 * All socket failures are non-fatal: pages keep working via REST polling/reload.
 *
 * Read receipts are ACTIVITY-GATED: unread -> read only when the vendor has
 * the thread open AND performs any interaction (mouse, click, key, touch,
 * scroll, focus) after the message arrived. Opening the page alone never
 * marks anything as read (the show endpoint is read-only).
 */
const cfg = window.TourMessagesLive;

if (cfg && cfg.vendorId) {
  try {
    window.Pusher = window.Pusher || Pusher;

    const echo = new Echo({
      broadcaster: 'reverb',
      key: cfg.reverb.key,
      wsHost: cfg.reverb.host,
      wsPort: Number(cfg.reverb.port),
      wssPort: Number(cfg.reverb.port),
      forceTLS: cfg.reverb.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: cfg.authEndpoint || '/broadcasting/auth',
    });

    echo.connector.pusher.connection.bind('error', () => {
      // Socket errors are non-fatal.
    });

    function socketId() {
      try {
        return echo.socketId() || '';
      } catch {
        return '';
      }
    }

    function csrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // ---- Sidebar unread badge ----
    const badge = document.getElementById('tour-messages-unread-badge');

    function setBadge(n) {
      if (!badge) return;
      const count = Number(n) || 0;
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = count > 0 ? '' : 'none';
    }

    // ---- Conversation list (index page) ----
    const listBody = document.querySelector('[data-tour-messages-index]');
    const unreadLabel = cfg.labels?.unread || 'unread';
    const openLabel = cfg.labels?.open || 'Open';

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    function updateRow(event) {
      if (!listBody || !event || !event.booking_uuid) return;
      const uuid = event.booking_uuid;
      const row = listBody.querySelector(`tr[data-booking-uuid="${uuid}"]`);

      if (row) {
        const unreadCell = row.querySelector('[data-row-unread]');
        const countCell = row.querySelector('[data-row-messages]');
        if (unreadCell) {
          const current = Number(unreadCell.dataset.count) || 0;
          unreadCell.dataset.count = current + 1;
          unreadCell.textContent = (current + 1) + ' ' + unreadLabel;
          unreadCell.style.display = '';
        }
        if (countCell) {
          countCell.textContent = (Number(countCell.textContent) || 0) + 1;
        }
        row.classList.add('table-warning');
        listBody.prepend(row);
        return;
      }

      const preview = event.last_message?.body ? escapeHtml(event.last_message.body) : '';
      const sender = event.last_message?.sender_name
        ? escapeHtml(event.last_message.sender_name)
        : escapeHtml(uuid.slice(0, 8));

      const tr = document.createElement('tr');
      tr.className = 'table-warning';
      tr.dataset.bookingUuid = uuid;
      tr.innerHTML = `
        <td><code>${escapeHtml(uuid.slice(0, 8))}</code></td>
        <td>&mdash;</td>
        <td>${sender}</td>
        <td>1 <span data-row-unread data-count="1" class="badge bg-danger rounded-pill">1 ${unreadLabel}</span></td>
        <td><a href="${cfg.indexUrl.replace('__UUID__', encodeURIComponent(uuid))}" class="btn btn-sm btn-primary">${openLabel}</a></td>`;
      if (preview) {
        tr.querySelector('td:nth-child(4)').insertAdjacentText('afterbegin', `${preview} · `);
      }
      listBody.prepend(tr);
    }

    // ---- "Đã xem" labels (show page) ----
    const readLabel = cfg.labels?.read || 'Read';

    function applyRead(event) {
      if (!event || !event.read_until || !event.reader_id) return;
      const until = new Date(event.read_until).getTime();
      document.querySelectorAll('[data-message]').forEach((el) => {
        const senderId = el.dataset.senderId;
        const createdAt = new Date(el.dataset.createdAt).getTime();
        if (String(senderId) !== String(event.reader_id) && createdAt <= until) {
          el.dataset.readAt = event.read_until;
          const label = el.querySelector('.message-read-label');
          if (label) {
            label.textContent = readLabel;
            label.classList.add('opacity-75');
            label.classList.remove('text-danger', 'fw-semibold');
          }
        }
      });
    }

    function markThreadReadLocal(readUntil) {
      document.querySelectorAll('[data-message]').forEach((el) => {
        if (String(el.dataset.senderId) === String(cfg.vendorId)) return;
        if (el.dataset.readAt) return;
        el.dataset.readAt = readUntil;
        const label = el.querySelector('.message-read-label');
        if (label) {
          label.textContent = readLabel;
          label.classList.add('opacity-75');
          label.classList.remove('text-danger', 'fw-semibold');
        }
      });
    }

    // ---- Activity-gated read receipts (show page) ----
    let pendingRead = false;
    let marking = false;
    let markTimer = null;

    function readUrl() {
      if (cfg.readUrl) return cfg.readUrl;
      if (cfg.bookingUuid && cfg.readUrlTemplate) {
        return cfg.readUrlTemplate.replace('__UUID__', encodeURIComponent(cfg.bookingUuid));
      }
      return null;
    }

    async function markRead() {
      const url = readUrl();
      if (!url || marking || !pendingRead) return;
      if (document.visibilityState !== 'visible') return;
      marking = true;
      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Socket-ID': socketId(),
          },
          credentials: 'same-origin',
        });
        if (!res.ok) return;
        const json = await res.json().catch(() => null);
        const data = json?.data ?? json;
        if (Number(data?.marked) > 0 && data?.read_until) {
          markThreadReadLocal(data.read_until);
        }
        pendingRead = false;
      } catch {
        // Non-fatal: keep pendingRead so the next interaction retries.
      } finally {
        marking = false;
      }
    }

    function scheduleMarkRead() {
      if (!pendingRead || marking || markTimer) return;
      if (document.visibilityState !== 'visible') return;
      markTimer = setTimeout(() => {
        markTimer = null;
        markRead();
      }, 800);
    }

    function flagPendingRead() {
      pendingRead = true;
    }

    // Existing unread messages still need an interaction before flipping.
    function scanInitialUnread() {
      const hasUnread = Array.from(document.querySelectorAll('[data-message]')).some(
        (el) => String(el.dataset.senderId) !== String(cfg.vendorId) && !el.dataset.readAt,
      );
      if (hasUnread) pendingRead = true;
    }

    if (cfg.bookingUuid) {
      scanInitialUnread();
      ['mousemove', 'mousedown', 'click', 'keydown', 'touchstart', 'wheel', 'scroll'].forEach((name) => {
        window.addEventListener(name, scheduleMarkRead, { passive: true });
      });
      window.addEventListener('focus', scheduleMarkRead);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') scheduleMarkRead();
      });
    }

    // ---- Thread bubbles (show page) ----
    function scrollThread() {
      const list = document.querySelector('[data-tour-messages-thread]');
      if (list) list.scrollTop = list.scrollHeight;
    }

    function bubbleHtml({ senderName, date, body, isMine, read }) {
      const name = escapeHtml(senderName || '');
      const when = escapeHtml(date || '');
      const text = escapeHtml(body || '');
      // Read receipt only on the vendor's own messages (same as customer chat).
      if (!isMine) {
        return `<div class="small opacity-75">${name} · ${when}</div><div>${text}</div>`;
      }
      const label = read ? readLabel : unreadLabel;
      const labelCls = read
        ? 'small mt-1 message-read-label opacity-75'
        : 'small mt-1 message-read-label text-danger fw-semibold';
      return `<div class="small opacity-75">${name} · ${when}</div><div>${text}</div><div class="${labelCls}">${escapeHtml(label)}</div>`;
    }

    function appendBubble(msg, isMine) {
      const list = document.querySelector('[data-tour-messages-thread]');
      if (!list || !msg || !msg.body) return;
      if (msg.id && list.querySelector(`[data-message-id="${msg.id}"]`)) return;
      list.querySelector('[data-empty-hint]')?.remove();
      const bubble = document.createElement('div');
      bubble.className = isMine ? 'mb-2 p-2 rounded bg-primary text-white ms-5' : 'mb-2 p-2 rounded bg-light me-5';
      bubble.dataset.message = '';
      if (msg.id) bubble.dataset.messageId = msg.id;
      bubble.dataset.senderId = msg.sender_id;
      bubble.dataset.createdAt = msg.created_at || '';
      bubble.dataset.readAt = msg.read_at || '';
      const date = msg.created_at ? new Date(msg.created_at).toLocaleString() : '';
      bubble.innerHTML = bubbleHtml({
        senderName: msg.sender_name || '',
        date,
        body: msg.body,
        isMine,
        read: !!msg.read_at,
      });
      list.appendChild(bubble);
      scrollThread();
    }

    // ---- Live incoming customer message (show page) ----
    function appendIncoming(event) {
      const list = document.querySelector('[data-tour-messages-thread]');
      if (!list || !event?.message || !event.message.body) return;
      const msg = event.message;
      // Own socket echo (when X-Socket-ID missing) is deduped by id; never
      // render own messages on the left.
      if (String(msg.sender_id) === String(cfg.vendorId)) {
        if (msg.id) {
          const existing = list.querySelector(`[data-message-id="${msg.id}"]`);
          if (existing && msg.read_at) {
            existing.dataset.readAt = msg.read_at;
            const label = existing.querySelector('.message-read-label');
            if (label) {
              label.textContent = readLabel;
              label.classList.add('opacity-75');
              label.classList.remove('text-danger', 'fw-semibold');
            }
          }
        }
        return;
      }
      appendBubble(msg, false);
      // Still unread until the vendor interacts with the page.
      flagPendingRead();
    }

    // ---- AJAX reply (show page, no full reload) ----
    const replyForm = document.getElementById('tour-message-reply-form');
    if (replyForm) {
      const input = replyForm.querySelector('input[name="body"]');
      const submitBtn = replyForm.querySelector('button[type="submit"]');
      const errorBox = replyForm.querySelector('[data-reply-error]');
      const sentAlert = document.getElementById('tour-message-sent-alert');

      replyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = (input?.value || '').trim();
        if (!body || submitBtn?.disabled) return;
        if (errorBox) {
          errorBox.textContent = '';
          errorBox.classList.add('d-none');
        }
        submitBtn.disabled = true;
        try {
          const res = await fetch(replyForm.action, {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken(),
              'X-Socket-ID': socketId(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
          });
          const json = await res.json().catch(() => null);
          if (!res.ok || !json?.success) {
            throw new Error(json?.message || `Gửi thất bại (HTTP ${res.status})`);
          }
          const saved = json.data ?? null;
          // Own message renders on the RIGHT immediately (no reload).
          appendBubble(
            saved ?? { body, sender_id: cfg.vendorId, sender_name: '', created_at: new Date().toISOString(), read_at: null },
            true,
          );
          if (input) input.value = '';
          input?.focus();
        } catch (err) {
          if (errorBox) {
            errorBox.textContent = err?.message || 'Gửi thất bại.';
            errorBox.classList.remove('d-none');
          }
        } finally {
          submitBtn.disabled = false;
        }
      });
    }

    // Vendor inbox channel
    const inbox = echo.private(`vendor.${cfg.vendorId}.tour-messages`);
    inbox.listen('.inbox.updated', (event) => {
      setBadge(event?.unread_count);
      updateRow(event);
    });

    // Active booking thread (show page)
    if (cfg.bookingUuid) {
      const thread = echo.private(`tour.booking.${cfg.bookingUuid}`);
      thread.listen('.message.read', applyRead);
      thread.listen('.message.sent', appendIncoming);
    }
  } catch (error) {
    // Socket wiring failed — page still works without realtime.
    console.warn('Tour messages live disabled:', error);
  }
}
