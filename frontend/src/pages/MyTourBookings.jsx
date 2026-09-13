import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import {
  FileDown,
  Loader2,
  Calendar,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  AlertTriangle,
  MessageCircle,
} from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import ErrorMessage from '../components/ErrorMessage';
import { TourChatBox } from '../components/TourChatBox';
import { formatPrice, cn } from '../lib/utils';

const STATUS_STYLES = {
  pending_payment: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-green-100 text-green-800',
  ongoing: 'bg-blue-100 text-blue-800',
  completed: 'bg-stone-100 text-stone-700',
  cancelled: 'bg-stone-200 text-stone-600',
  disputed: 'bg-orange-100 text-orange-800',
  refunded: 'bg-purple-100 text-purple-800',
};

const CANCELLABLE_STATUSES = ['pending_payment', 'confirmed'];

function StatusBadge({ status }) {
  const { t } = useTranslation();
  const style = STATUS_STYLES[status] ?? 'bg-stone-100 text-stone-700';
  return (
    <span className={cn('inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide', style)}>
      {t(`tours.status.${status}`, status)}
    </span>
  );
}

export default function MyTourBookings() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [chatUuid, setChatUuid] = useState(null);
  const [disputeModalUuid, setDisputeModalUuid] = useState(null);
  const [disputeNotes, setDisputeNotes] = useState('');
  const [disputeFormError, setDisputeFormError] = useState(null);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['tour-bookings', page],
    queryFn: async () => {
      const res = await api.get('/tour-bookings', { params: { page, per_page: 10 } });
      return res.data;
    },
    enabled: !!user,
  });

  const cancelMutation = useMutation({
    mutationFn: (uuid) => api.post(`/tour-bookings/${uuid}/cancel`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tour-bookings'] }),
  });

  const disputeMutation = useMutation({
    mutationFn: ({ uuid, body }) => api.post(`/tour-bookings/${uuid}/dispute`, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tour-bookings'] });
      setDisputeModalUuid(null);
      setDisputeNotes('');
      setDisputeFormError(null);
    },
  });

  const payload = data?.data ?? {};
  const bookings = Array.isArray(payload?.data) ? payload.data : [];
  const meta = payload?.meta ?? {};
  const lastPage = meta.last_page ?? 1;
  const currentPage = meta.current_page ?? 1;

  const openInvoice = async (uuid) => {
    try {
      const res = await api.get(`/tour-bookings/${uuid}/invoice`, { responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([res.data], { type: 'text/html' }));
      window.open(url, '_blank', 'noopener');
    } catch (e) {
      alert(e?.response?.data?.message || t('tours.bookings.invoiceError'));
    }
  };

  const submitDispute = async (e) => {
    e.preventDefault();
    if (!disputeModalUuid || disputeNotes.trim().length < 20) {
      setDisputeFormError(t('tours.bookings.disputeMin'));
      return;
    }
    setDisputeFormError(null);
    try {
      await disputeMutation.mutateAsync({ uuid: disputeModalUuid, body: { customer_notes: disputeNotes.trim() } });
    } catch (err) {
      setDisputeFormError(err?.response?.data?.message || err?.message);
    }
  };

  if (!user) {
    return (
      <div className="py-12">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8 text-center max-w-md mx-auto">
          <Calendar className="w-8 h-8 mx-auto mb-4 text-[#7a756d]" />
          <h2 className="text-xl font-semibold mb-2">{t('tours.bookings.loginRequired')}</h2>
          <Link to="/login" className="inline-flex px-6 py-3 rounded-xl bg-[#b8860b] text-white font-medium hover:bg-[#996f09] mt-2">
            {t('auth.login.submit')}
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="py-6 sm:py-8">
      <div className="max-w-4xl mx-auto">
        <h1 className="text-2xl sm:text-3xl font-bold text-[#1a1a1a]">{t('tours.bookings.title')}</h1>
        <p className="text-[#5c5852] mt-1 mb-8">{t('tours.bookings.subtitle')}</p>

        {isLoading && <Loader2 className="w-8 h-8 animate-spin text-[#b8860b]" />}
        {isError && <ErrorMessage message={error?.response?.data?.message || error?.message} />}
        {!isLoading && !isError && bookings.length === 0 && (
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-12 text-center">
            <p className="text-[#5c5852] mb-6">{t('tours.bookings.empty')}</p>
            <Link to="/tours" className="inline-flex px-6 py-3 rounded-xl bg-[#1a1a1a] text-white font-medium hover:bg-[#2d2a28]">
              {t('tours.bookings.browse')}
            </Link>
          </div>
        )}

        <div className="space-y-4">
          {bookings.map((b) => (
            <article key={b.uuid} className="rounded-2xl border border-[#e8e4dd] bg-white overflow-hidden">
              <div className="p-5 sm:p-6">
                <div className="flex flex-col lg:flex-row lg:justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2 mb-2">
                      <h3 className="font-semibold text-lg truncate">{b.tour?.title ?? 'Tour'}</h3>
                      <StatusBadge status={b.status} />
                    </div>
                    <p className="text-sm text-[#5c5852]">
                      {b.slot?.date} · {b.slot?.start_time}–{b.slot?.end_time} · {b.pricing_mode} × {b.duration_value}
                    </p>
                    <p className="font-semibold text-lg mt-2">{formatPrice(b.total_price, b.currency)}</p>
                    <p className="text-xs text-stone-500 mt-1 font-mono">#{b.uuid}</p>
                  </div>
                  <div className="flex flex-wrap gap-2 shrink-0">
                    <Link
                      to={`/tour-checkout/${b.uuid}`}
                      className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#e8e4dd] text-sm font-medium hover:bg-[#faf8f5]"
                    >
                      <ExternalLink className="w-4 h-4" /> {t('tours.bookings.view')}
                    </Link>
                    <button
                      type="button"
                      onClick={() => openInvoice(b.uuid)}
                      className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#e8e4dd] text-sm font-medium hover:bg-[#faf8f5]"
                    >
                      <FileDown className="w-4 h-4" /> {t('tours.bookings.invoice')}
                    </button>
                    <button
                      type="button"
                      onClick={() => setChatUuid(chatUuid === b.uuid ? null : b.uuid)}
                      className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#e8e4dd] text-sm font-medium hover:bg-[#faf8f5]"
                    >
                      <MessageCircle className="w-4 h-4" /> {t('tours.chat.title')}
                    </button>
                    {b.can_open_dispute && (
                      <button
                        type="button"
                        onClick={() => { setDisputeModalUuid(b.uuid); setDisputeNotes(''); setDisputeFormError(null); }}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-amber-200 bg-amber-50 text-sm font-medium text-amber-900 hover:bg-amber-100"
                      >
                        <AlertTriangle className="w-4 h-4" /> {t('tours.bookings.report')}
                      </button>
                    )}
                    {CANCELLABLE_STATUSES.includes(b.status) && (
                      <button
                        type="button"
                        onClick={async () => {
                          if (!window.confirm(t('tours.bookings.confirmCancel'))) return;
                          try {
                            await cancelMutation.mutateAsync(b.uuid);
                          } catch (e) {
                            alert(e?.response?.data?.message);
                          }
                        }}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-red-200 text-sm font-medium text-red-700 hover:bg-red-50"
                      >
                        {t('tours.bookings.cancel')}
                      </button>
                    )}
                  </div>
                </div>
                {chatUuid === b.uuid && (
                  <div className="mt-4">
                    <TourChatBox bookingUuid={b.uuid} />
                  </div>
                )}
              </div>
            </article>
          ))}
        </div>

        {lastPage > 1 && (
          <nav className="mt-8 flex items-center justify-between">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={currentPage <= 1}
              className="inline-flex items-center gap-1 px-4 py-2.5 rounded-xl border border-[#e8e4dd] disabled:opacity-50 text-sm font-medium"
            >
              <ChevronLeft className="w-4 h-4" /> {t('common.previous')}
            </button>
            <button
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={currentPage >= lastPage}
              className="inline-flex items-center gap-1 px-4 py-2.5 rounded-xl border border-[#e8e4dd] disabled:opacity-50 text-sm font-medium"
            >
              {t('common.next')} <ChevronRight className="w-4 h-4" />
            </button>
          </nav>
        )}

        {disputeModalUuid && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" role="dialog" aria-modal="true">
            <div className="bg-white rounded-2xl max-w-lg w-full p-6">
              <h2 className="text-lg font-semibold mb-2">{t('tours.bookings.disputeTitle')}</h2>
              <form onSubmit={submitDispute} className="space-y-3">
                <textarea
                  required
                  minLength={20}
                  rows={5}
                  value={disputeNotes}
                  onChange={(e) => setDisputeNotes(e.target.value)}
                  className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
                />
                {disputeFormError && <p className="text-sm text-red-600">{disputeFormError}</p>}
                <div className="flex gap-2 justify-end">
                  <button type="button" onClick={() => setDisputeModalUuid(null)} className="px-4 py-2 rounded-xl border text-sm font-medium">
                    {t('common.close')}
                  </button>
                  <button type="submit" disabled={disputeMutation.isPending} className="px-4 py-2 rounded-xl bg-amber-600 text-white text-sm font-medium disabled:opacity-60">
                    {t('common.submit')}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
