import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Loader2, Send } from 'lucide-react';
import { useTourChat } from '../hooks/useTourChat';
import ErrorMessage from './ErrorMessage';
import { cn } from '../lib/utils';

export function TourChatBox({ bookingUuid }) {
  const { t } = useTranslation();
  const { messages, isLoading, isError, refetch, sendMessage, socketLive } = useTourChat(bookingUuid);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [sendError, setSendError] = useState(null);
  const bottomRef = useRef(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, [messages.length]);

  const handleSend = async (e) => {
    e.preventDefault();
    if (!draft.trim() || sending) return;
    setSending(true);
    setSendError(null);
    try {
      await sendMessage(draft);
      setDraft('');
    } catch (err) {
      setSendError(err?.response?.data?.message || err?.message);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="rounded-2xl border border-[#e8e4dd] bg-white overflow-hidden">
      <div className="px-4 py-3 border-b border-[#e8e4dd] flex items-center justify-between">
        <p className="text-sm font-semibold text-[#1a1a1a]">{t('tours.chat.title')}</p>
        <span className={cn('text-xs px-2 py-0.5 rounded-full', socketLive ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-500')}>
          {socketLive ? t('tours.chat.live') : t('tours.chat.polling')}
        </span>
      </div>

      <div className="h-72 overflow-y-auto p-4 space-y-3 bg-[#faf8f5]">
        {isLoading && (
          <div className="flex justify-center py-8">
            <Loader2 className="w-6 h-6 animate-spin text-[#b8860b]" />
          </div>
        )}
        {isError && <ErrorMessage message={t('tours.chat.couldNotLoad')} onRetry={() => refetch()} />}
        {!isLoading && !isError && messages.length === 0 && (
          <p className="text-sm text-[#7a756d] text-center py-8">{t('tours.chat.empty')}</p>
        )}
        {messages.map((m) => (
          <div key={m.id} className={cn('flex', m.is_mine ? 'justify-end' : 'justify-start')}>
            <div
              className={cn(
                'max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm',
                m.is_mine ? 'bg-[#1a1a1a] text-white rounded-br-md' : 'bg-white border border-[#e8e4dd] text-[#1a1a1a] rounded-bl-md'
              )}
            >
              {!m.is_mine && m.sender_name && (
                <p className="text-xs font-semibold text-[#b8860b] mb-0.5">{m.sender_name}</p>
              )}
              <p className="whitespace-pre-wrap break-words">{m.body}</p>
              {m.created_at && (
                <p className={cn('text-[11px] mt-1', m.is_mine ? 'text-white/60' : 'text-[#a39e94]')}>
                  {new Date(m.created_at).toLocaleString()}
                </p>
              )}
            </div>
          </div>
        ))}
        <div ref={bottomRef} />
      </div>

      <form onSubmit={handleSend} className="p-3 border-t border-[#e8e4dd] flex gap-2">
        <input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          maxLength={2000}
          placeholder={t('tours.chat.placeholder')}
          className="flex-1 rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#b8860b]/40"
        />
        <button
          type="submit"
          disabled={sending || !draft.trim()}
          className="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-[#b8860b] text-white hover:bg-[#996f09] disabled:opacity-50 shrink-0"
          aria-label={t('tours.chat.send')}
        >
          {sending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
        </button>
      </form>
      {sendError && <p className="px-4 pb-3 text-sm text-red-600">{sendError}</p>}
    </div>
  );
}
