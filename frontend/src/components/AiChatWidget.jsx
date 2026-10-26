import { useState, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { MessageCircle, X, Send, Sparkles } from 'lucide-react';
import { api } from '../lib/api';

export default function AiChatWidget() {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const bottomRef = useRef(null);
  const panelRef = useRef(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, open]);

  // Close on Escape so the overlay never traps the user on mobile.
  useEffect(() => {
    if (!open) return undefined;
    const onKeyDown = (e) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open]);

  const send = async (e) => {
    e?.preventDefault();
    const text = input.trim();
    if (!text || loading) return;
    const userMsg = { role: 'user', content: text };
    const toSend = [...messages, userMsg];
    setMessages(toSend);
    setInput('');
    setLoading(true);
    setError(null);
    try {
      const res = await api.post('/ai/chat', { messages: toSend });
      const reply = res.data?.data?.message?.content;
      if (reply) {
        setMessages([...toSend, { role: 'assistant', content: reply }]);
      } else {
        setError(res.data?.message || 'Trợ lý chưa có câu trả lời. Vui lòng thử lại.');
      }
    } catch (err) {
      setError(err?.response?.data?.message || 'Trợ lý hiện chưa khả dụng. Vui lòng thử lại sau.');
    } finally {
      setLoading(false);
    }
  };

  if (typeof document === 'undefined') return null;

  return createPortal(
    <>
      {!open && (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="fixed bottom-4 right-4 z-[9999] flex h-14 w-14 items-center justify-center rounded-full bg-[#1a1a1a] text-white shadow-lg ring-2 ring-[#b8860b]/40 hover:bg-[#2d2a28] transition-colors"
          aria-label="Mở trợ lý AI"
        >
          <Sparkles className="h-6 w-6 text-[#f9edd1]" />
        </button>
      )}

      {open && (
        <>
          <button
            type="button"
            aria-label="Đóng trợ lý"
            className="fixed inset-0 z-[9998] bg-black/30 sm:bg-transparent"
            onClick={() => setOpen(false)}
          />
          <div
            ref={panelRef}
            className="fixed z-[9999] flex flex-col overflow-hidden bg-white shadow-2xl border border-[#e8e4dd]
              inset-x-0 bottom-0 rounded-t-2xl max-h-[80vh]
              sm:inset-auto sm:bottom-5 sm:right-5 sm:rounded-2xl sm:w-[min(100vw-2rem,400px)] sm:max-h-[min(85vh,520px)]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ai-chat-title"
          >
            <div className="flex items-center justify-between border-b border-[#e8e4dd] px-4 py-3 bg-[#faf8f5] rounded-t-2xl sm:rounded-t-2xl">
              <div className="flex items-center gap-2">
                <MessageCircle className="h-5 w-5 text-[#b8860b]" />
                <h2 id="ai-chat-title" className="font-semibold text-[#1a1a1a] text-sm">
                  Trợ lý đặt phòng
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="p-1.5 rounded-lg hover:bg-[#e8e4dd]/80 text-[#5c5852]"
                aria-label="Đóng"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3 min-h-[160px] max-h-[60vh] sm:max-h-[340px]">
              {messages.length === 0 && (
                <p className="text-sm text-[#5c5852]">
                  Xin chào! Tôi là trợ lý đặt phòng của TravelBooking. Tôi có thể hướng dẫn bạn tìm khách sạn, đặt
                  phòng và tìm hiểu chính sách hủy. Để xem giá và tình trạng phòng hiện tại, hãy tìm kiếm trực tiếp
                  trên trang web. Tôi có thể giúp gì cho chuyến đi của bạn?
                </p>
              )}
              {messages.map((m, i) => (
                <div
                  key={i}
                  className={`text-sm rounded-xl px-3 py-2 max-w-[95%] ${
                    m.role === 'user'
                      ? 'ml-auto bg-[#1a1a1a] text-white'
                      : 'mr-auto bg-[#f5f2ed] text-[#1a1a1a] border border-[#e8e4dd]'
                  }`}
                >
                  {m.content}
                </div>
              ))}
              {loading && <p className="text-xs text-[#7a756d]">Đang trả lời…</p>}
              {error && <p className="text-xs text-red-600">{error}</p>}
              <div ref={bottomRef} />
            </div>
            <form onSubmit={send} className="border-t border-[#e8e4dd] p-3 flex gap-2">
              <input
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder="Nhập câu hỏi..."
                className="flex-1 min-w-0 rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm text-[#1a1a1a] focus:ring-2 focus:ring-[#b8860b]/30 focus:border-[#b8860b]"
                disabled={loading}
              />
              <button
                type="submit"
                disabled={loading || !input.trim()}
                className="shrink-0 rounded-xl bg-[#b8860b] text-white p-2.5 hover:bg-[#996f09] disabled:opacity-50"
                aria-label="Gửi"
              >
                <Send className="h-5 w-5" />
              </button>
            </form>
          </div>
        </>
      )}
    </>,
    document.body
  );
}
