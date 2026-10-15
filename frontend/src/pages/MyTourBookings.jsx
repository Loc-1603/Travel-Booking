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
  Star,
} from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { useToast } from '../contexts/useToast';
import ErrorMessage from '../components/ErrorMessage';
import { Modal } from '../components/Modal';
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
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [chatUuid, setChatUuid] = useState(null);
  const [cancelTargetUuid, setCancelTargetUuid] = useState(null);
  const [disputeModalUuid, setDisputeModalUuid] = useState(null);
  const [disputeNotes, setDisputeNotes] = useState('');
  const [disputeFormError, setDisputeFormError] = useState(null);

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour-bookings', page],
    queryFn: async () => {
      const res = await api.get('/tour-bookings', { params: { page, per_page: 10 } });
      return res.data;
    },
    enabled: !!user,
  });

  const cancelMutation = useMutation({
    mutationFn: (uuid) => api.post(`/tour-bookings/${uuid}/cancel`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tour-bookings'] });
      setCancelTargetUuid(null);
      toast(t('tours.bookings.cancelSuccess'), { type: 'success' });
    },
    onError: (e) => {
      toast(e?.response?.data?.message || t('tours.bookings.cancelError'), { type: 'error' });
    },
  });

  const disputeMutation = useMutation({
    mutationFn: ({ uuid, body }) => api.post(`/tour-bookings/${uuid}/dispute`, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tour-bookings'] });
      setDisputeModalUuid(null);
      setDisputeNotes('');
      setDisputeFormError(null);
      toast(t('tours.bookings.disputeSuccess'), { type: 'success' });
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
      toast(e?.response?.data?.message || t('tours.bookings.invoiceError'), { type: 'error' });
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
        <div className="rounded-2xl border border-neutral-200 bg-white p-8 text-center max-w-md mx-auto">
          <Calendar className="w-8 h-8 mx-auto mb-4 text-neutral-500" />
          <h2 className="text-xl font-semibold mb-2">{t('tours.bookings.loginRequired')}</h2>
          <Link to="/login" className="inline-flex px-6 py-3 rounded-xl bg-accent text-white font-medium hover:bg-accent-600 mt-2">
            {t('auth.login.submit')}
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="py-6 sm:py-8">
      <div className="max-w-4xl mx-auto">
        <h1 className="text-2xl sm:text-3xl font-bold text-neutral-900">{t('tours.bookings.title')}</h1>
        <p className="text-neutral-600 mt-1 mb-8">{t('tours.bookings.subtitle')}</p>

        {isLoading && (
          <div className="space-y-4" aria-busy="true" aria-label={t('tours.bookings.loading')}>
            <span className="sr-only">{t('tours.bookings.loading')}</span>
            {[0, 1, 2].map((i) => (
              <div key={i} data-testid="booking-skeleton" className="rounded-2xl border border-neutral-200 bg-white p-5 sm:p-6 animate-pulse">
                <div className="h-5 w-2/3 rounded bg-stone-200 mb-3" />
                <div className="h-4 w-1/2 rounded bg-stone-100 mb-2" />
                <div className="h-6 w-1/4 rounded bg-stone-200 mt-3" />
                <div className="mt-4 flex gap-2">
                  <div className="h-10 w-24 rounded-xl bg-stone-100" />
                  <div className="h-10 w-24 rounded-xl bg-stone-100" />
                </div>
              </div>
            ))}
          </div>
        )}
        {isError && <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />}
        {!isLoading && !isError && bookings.length === 0 && (
          <div className="rounded-2xl border border-neutral-200 bg-white p-12 text-center">
            <p className="text-neutral-600 mb-6">{t('tours.bookings.empty')}</p>
            <Link to="/tours" className="inline-flex px-6 py-3 rounded-xl bg-neutral-900 text-white font-medium hover:bg-neutral-800">
              {t('tours.bookings.browse')}
            </Link>
          </div>
        )}

        <div className="space-y-4">
          {bookings.map((b) => (
            <article key={b.uuid} className="rounded-2xl border border-neutral-200 bg-white overflow-hidden">
              <div className="p-5 sm:p-6">
                <div className="flex flex-col lg:flex-row lg:justify-between gap-4 lg:items-center">
                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2 mb-2">
                      <h3 className="font-semibold text-lg truncate">{b.tour?.title ?? t('tours.bookings.unknownTour')}</h3>
                      <StatusBadge status={b.status} />
                    </div>
                    <p className="text-sm text-neutral-600">
                      {b.slot?.date} · {b.slot?.start_time}–{b.slot?.end_time} · {b.pricing_mode} × {b.duration_value}
                    </p>
                    <p className="font-semibold text-lg mt-2">{formatPrice(b.total_price, b.currency)}</p>
                    <p className="text-xs text-stone-500 mt-1 font-mono">#{b.uuid}</p>
                  </div>
                  <div className="shrink-0 min-w-0 max-w-full lg:max-w-[50%] flex lg:justify-end self-stretch lg:self-center py-1">
                    <div className="flex flex-nowrap items-center gap-2 overflow-x-auto max-w-full px-1 py-1">
                    <Link
                      to={`/tour-checkout/${b.uuid}`}
                      className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50 whitespace-nowrap shrink-0"
                    >
                      <ExternalLink className="w-4 h-4" /> {t('tours.bookings.view')}
                    </Link>
                    <button
                      type="button"
                      onClick={() => openInvoice(b.uuid)}
                      className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50 whitespace-nowrap shrink-0"
                    >
                      <FileDown className="w-4 h-4" /> {t('tours.bookings.invoice')}
                    </button>
                    <button
                      type="button"
                      onClick={() => setChatUuid(chatUuid === b.uuid ? null : b.uuid)}
                      className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50 whitespace-nowrap shrink-0"
                    >
                      <MessageCircle className="w-4 h-4" /> {t('tours.bookings.message')}
                    </button>
                    {['confirmed', 'ongoing', 'completed'].includes(b.status) && !b.has_review && (
                      <Link
                        to={`/tour-bookings/${b.uuid}/review`}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-green-200 text-sm font-medium text-green-700 hover:bg-green-50 whitespace-nowrap shrink-0"
                      >
                        <Star className="w-4 h-4" /> {t('tours.bookings.review')}
                      </Link>
                    )}
                    {b.can_open_dispute && (
                      <button
                        type="button"
                        onClick={() => { setDisputeModalUuid(b.uuid); setDisputeNotes(''); setDisputeFormError(null); }}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-amber-200 bg-amber-50 text-sm font-medium text-amber-900 hover:bg-amber-100 whitespace-nowrap shrink-0"
                      >
                        <AlertTriangle className="w-4 h-4" /> {t('tours.bookings.report')}
                      </button>
                    )}
                    {CANCELLABLE_STATUSES.includes(b.status) && (
                      <button
                        type="button"
                        onClick={() => setCancelTargetUuid(b.uuid)}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-red-200 text-sm font-medium text-red-700 hover:bg-red-50 whitespace-nowrap shrink-0"
                      >
                        {t('tours.bookings.cancel')}
                      </button>
                    )}
                    </div>
                  </div>
                </div>
                {chatUuid === b.uuid && (
                  <div className="mt-4">
                    <TourChatBox bookingUuid={b.uuid} />
                  </div>
                )}
                {['confirmed', 'ongoing', 'completed'].includes(b.status) && !!b.has_review && (
                  <p className="mt-4 text-sm text-neutral-500">{t('tours.bookings.reviewDone')}</p>
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
              className="inline-flex items-center gap-1 px-4 py-2.5 rounded-xl border border-neutral-200 disabled:opacity-50 text-sm font-medium"
            >
              <ChevronLeft className="w-4 h-4" /> {t('common.previous')}
            </button>
            <button
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={currentPage >= lastPage}
              className="inline-flex items-center gap-1 px-4 py-2.5 rounded-xl border border-neutral-200 disabled:opacity-50 text-sm font-medium"
            >
              {t('common.next')} <ChevronRight className="w-4 h-4" />
            </button>
          </nav>
        )}

        <Modal
          open={cancelTargetUuid !== null}
          onClose={() => setCancelTargetUuid(null)}
          title={t('tours.bookings.cancelTitle')}
          footer={
            <>
              <button
                type="button"
                onClick={() => setCancelTargetUuid(null)}
                className="px-4 py-2 rounded-xl border text-sm font-medium"
              >
                {t('common.close')}
              </button>
              <button
                type="button"
                onClick={() => cancelTargetUuid && cancelMutation.mutate(cancelTargetUuid)}
                disabled={cancelMutation.isPending}
                className="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium disabled:opacity-60 inline-flex items-center gap-2"
              >
                {cancelMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                {t('common.confirm')}
              </button>
            </>
          }
        >
          <p className="text-sm text-neutral-600">{t('tours.bookings.cancelMessage')}</p>
        </Modal>

        <Modal
          open={disputeModalUuid !== null}
          onClose={() => setDisputeModalUuid(null)}
          title={t('tours.bookings.disputeTitle')}
          footer={
            <>
              <button
                type="button"
                onClick={() => setDisputeModalUuid(null)}
                className="px-4 py-2 rounded-xl border text-sm font-medium"
              >
                {t('common.close')}
              </button>
              <button
                type="submit"
                form="tour-dispute-form"
                disabled={disputeMutation.isPending}
                className="px-4 py-2 rounded-xl bg-amber-600 text-white text-sm font-medium disabled:opacity-60"
              >
                {t('common.submit')}
              </button>
            </>
          }
        >
          <form id="tour-dispute-form" onSubmit={submitDispute} className="space-y-3">
            <label htmlFor="tour-dispute-notes" className="text-sm font-medium text-neutral-700">
              {t('tours.bookings.disputeTitle')}
            </label>
            <textarea
              id="tour-dispute-notes"
              required
              minLength={20}
              rows={5}
              value={disputeNotes}
              onChange={(e) => setDisputeNotes(e.target.value)}
              className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm"
            />
            {disputeFormError && <p className="text-sm text-red-600">{disputeFormError}</p>}
          </form>
        </Modal>
      </div>
    </div>
  );
}
