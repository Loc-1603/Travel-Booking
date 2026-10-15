import { useEffect, useState } from 'react';
import { useParams, Link, useLocation, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Clock3, Lock, CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { useToast } from '../contexts/useToast';
import { useCountdown } from '../hooks/useCountdown';
import ErrorMessage from '../components/ErrorMessage';
import { Modal } from '../components/Modal';
import { Stepper } from '../components/Stepper';
import { formatPrice } from '../lib/utils';

function pad2(n) {
  return String(n).padStart(2, '0');
}

export default function TourCheckout() {
  const { t } = useTranslation();
  const { uuid } = useParams();
  const { user } = useAuth();
  const { toast } = useToast();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const stateBooking = location.state?.booking;
  const [isProcessing, setIsProcessing] = useState(false);
  const [isCancelling, setIsCancelling] = useState(false);
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);
  const [isInvoiceLoading, setIsInvoiceLoading] = useState(false);
  const [paymentError, setPaymentError] = useState(null);
  // UI placeholder (quyết định #3): chỉ VNPay hoạt động, PayPal disabled "sắp ra mắt".
  const [paymentMethod, setPaymentMethod] = useState('vnpay');

  const isSuccess = searchParams.get('success') === '1';
  const isPaymentFailed = searchParams.get('payment') === 'failed';

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour-booking', uuid],
    queryFn: async () => {
      const res = await api.get(`/tour-bookings/${uuid}`);
      return res.data;
    },
    enabled: !!uuid && !!user && !stateBooking,
    refetchInterval: (query) => {
      if (!query.state.data) return false;
      const b = query.state.data?.data ?? query.state.data?.booking ?? query.state.data;
      return b?.status === 'pending_payment' ? 3000 : false;
    },
  });

  const booking = stateBooking || (data?.data ?? data?.booking ?? data);
  const countdown = useCountdown(booking?.expires_at);

  // Auto refresh once the hold expires so the UI flips to the expired state (FE-3.1).
  useEffect(() => {
    if (booking?.expires_at && countdown.isExpired && booking?.status === 'pending_payment' && !stateBooking) {
      refetch();
    }
  }, [countdown.isExpired, booking?.expires_at, booking?.status, stateBooking, refetch]);

  const isConfirmed = booking?.status === 'confirmed';
  const showSuccess = isSuccess || isConfirmed;
  const isPending = booking?.status === 'pending_payment';
  const isCancelled = booking?.status === 'cancelled';
  const isExpired = Boolean(booking?.is_expired) || (Boolean(booking?.expires_at) && countdown.isExpired);
  const showExpired = isPending && isExpired;
  // Backend is source of truth when it says so; otherwise fall back to countdown.
  const canPay = booking?.can_pay ?? (isPending && !isExpired);
  const checkoutSteps = [
    t('tours.wizard.steps.slot'),
    t('tours.wizard.steps.details'),
    t('tours.wizard.steps.payment'),
  ];

  const handlePayClick = async () => {
    setPaymentError(null);
    setIsProcessing(true);
    try {
      const res = await api.post(`/tour-bookings/${uuid}/checkout-session`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to create checkout');
      const checkoutUrl = res.data?.data?.checkout_url;
      if (checkoutUrl) window.location.href = checkoutUrl;
      else throw new Error('No checkout URL received');
    } catch (err) {
      setIsProcessing(false);
      setPaymentError(err?.response?.data?.message || err?.message);
    }
  };

  const handleCancelHold = async () => {
    setIsCancelling(true);
    try {
      await api.post(`/tour-bookings/${uuid}/cancel`);
      setShowCancelConfirm(false);
      toast(t('tours.checkout.cancelSuccess'), { type: 'success' });
      await refetch();
    } catch (err) {
      toast(err?.response?.data?.message || t('tours.checkout.cancelError'), { type: 'error' });
    } finally {
      setIsCancelling(false);
    }
  };

  const handleDownloadInvoice = async () => {
    setIsInvoiceLoading(true);
    try {
      const res = await api.get(`/tour-bookings/${uuid}/invoice`, { responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([res.data], { type: 'text/html' }));
      window.open(url, '_blank', 'noopener');
    } catch (e) {
      toast(e?.response?.data?.message || t('tours.checkout.invoiceError'), { type: 'error' });
    } finally {
      setIsInvoiceLoading(false);
    }
  };

  if (!user) {
    return (
      <div className="py-12 max-w-md mx-auto text-center">
        <div className="rounded-2xl border border-neutral-200 bg-white p-8">
          <h2 className="text-xl font-semibold mb-2">{t('tours.wizard.loginRequired')}</h2>
          <Link
            to={`/login?redirect=${encodeURIComponent(`/tour-checkout/${uuid}`)}`}
            className="inline-flex px-6 py-3 rounded-xl bg-accent text-white font-medium hover:bg-accent-600 mt-4"
          >
            {t('auth.login.submit')}
          </Link>
        </div>
      </div>
    );
  }

  if (!stateBooking && !booking && isLoading) {
    return (
      <div className="py-6 flex items-center justify-center">
        <Loader2 className="w-8 h-8 animate-spin text-accent" />
      </div>
    );
  }
  if (!stateBooking && !booking && isError) {
    return (
      <div className="py-6">
        <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />
      </div>
    );
  }

  if (showSuccess) {
    return (
      <div className="py-12 max-w-lg mx-auto text-center">
        <div className="rounded-2xl border border-neutral-200 bg-white p-8 sm:p-12">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-6">
            <CheckCircle2 className="w-10 h-10" />
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold text-neutral-900 mb-2">{t('tours.checkout.successTitle')}</h1>
          <p className="text-neutral-600 mb-6">{t('tours.checkout.successHint')}</p>
          <dl className="text-left rounded-xl border border-neutral-200 bg-neutral-50 p-5 space-y-3 mb-4">
            {booking?.tour && (
              <div className="flex justify-between gap-4 text-sm">
                <dt className="text-neutral-600">{t('tours.checkout.successTour')}</dt>
                <dd className="font-medium text-neutral-900 text-right">{booking.tour.title}</dd>
              </div>
            )}
            {booking?.slot && (
              <div className="flex justify-between gap-4 text-sm">
                <dt className="text-neutral-600">{t('tours.checkout.successWhen')}</dt>
                <dd className="font-medium text-neutral-900 text-right">
                  {booking.slot.date} · {booking.slot.start_time}–{booking.slot.end_time}
                </dd>
              </div>
            )}
            {booking?.total_price != null && (
              <div className="flex justify-between gap-4 text-sm border-t border-neutral-200 pt-3">
                <dt className="text-neutral-600">{t('tours.checkout.successTotal')}</dt>
                <dd className="font-semibold text-neutral-900">{formatPrice(booking.total_price, booking.currency)}</dd>
              </div>
            )}
            <div className="text-xs font-mono text-neutral-600 break-all">#{booking?.uuid || uuid}</div>
          </dl>
          {user?.email && <p className="text-sm text-neutral-600 mb-6">{t('tours.checkout.emailNote', { email: user.email })}</p>}
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <button
              type="button"
              onClick={handleDownloadInvoice}
              disabled={isInvoiceLoading}
              className="px-6 py-3 rounded-xl border border-neutral-200 font-semibold hover:bg-neutral-50 disabled:opacity-60 inline-flex items-center justify-center gap-2"
            >
              {isInvoiceLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
              {t('tours.checkout.downloadInvoice')}
            </button>
            <Link to="/tour-bookings" className="px-6 py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600">
              {t('tours.checkout.viewBookings')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (isCancelled && !showSuccess) {
    return (
      <div className="py-12 max-w-lg mx-auto text-center">
        <div className="rounded-2xl border border-neutral-200 bg-white p-8 sm:p-12">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-stone-100 text-stone-500 mb-6">
            <XCircle className="w-10 h-10" />
          </div>
          <h1 className="text-2xl font-bold text-neutral-900 mb-2">{t('tours.checkout.cancelledTitle')}</h1>
          <p className="text-neutral-600 mb-6">{t('tours.checkout.cancelledHint')}</p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Link to="/tours" className="px-6 py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600">
              {t('tours.checkout.backToTours')}
            </Link>
            <Link to="/tour-bookings" className="px-6 py-3 rounded-xl border border-neutral-200 font-semibold hover:bg-neutral-50">
              {t('tours.checkout.viewBookings')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (showExpired) {
    return (
      <div className="py-12 max-w-lg mx-auto text-center">
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-8 sm:p-12">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 text-amber-700 mb-6">
            <Clock3 className="w-10 h-10" />
          </div>
          <h1 className="text-2xl font-bold text-neutral-900 mb-2">{t('tours.checkout.expiredTitle')}</h1>
          <p className="text-neutral-600 mb-6">{t('tours.checkout.expiredHint')}</p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Link to="/tours" className="px-6 py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600">
              {t('tours.checkout.backToTours')}
            </Link>
            <Link to="/tour-bookings" className="px-6 py-3 rounded-xl border border-neutral-200 bg-white font-semibold hover:bg-neutral-50">
              {t('tours.checkout.viewBookings')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="py-6">
      <h1 className="text-2xl sm:text-3xl font-bold text-neutral-900 mb-4">{t('tours.checkout.title')}</h1>
      <Stepper steps={checkoutSteps} current={2} ariaLabel={t('tours.checkout.stepsAria')} className="mb-6" />

      {isPaymentFailed && (
        <div role="alert" className="mb-4 rounded-2xl border border-red-200 bg-red-50 p-5 flex gap-3">
          <AlertTriangle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
          <div>
            <h2 className="font-semibold text-red-800">{t('tours.checkout.paymentFailedTitle')}</h2>
            <p className="text-sm text-red-700 mt-1">{t('tours.checkout.paymentFailedHint')}</p>
          </div>
        </div>
      )}

      {isPending && (
        <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <div className="flex items-start gap-3">
            <Clock3 className="w-5 h-5 text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
            <div className="flex-1">
              <h2 className="font-semibold text-amber-900">{t('tours.checkout.awaitingTitle')}</h2>
              <p className="text-sm text-amber-800 mt-1">{t('tours.checkout.awaitingHint')} {t('tours.checkout.holdHint')}</p>
              {booking?.expires_at && !countdown.isExpired && (
                <p data-testid="hold-countdown" className="mt-2 inline-flex items-center gap-2 rounded-lg bg-white border border-amber-200 px-3 py-1.5 font-mono font-semibold text-amber-900" aria-live="off">
                  <span aria-hidden="true">{pad2(countdown.minutes)}:{pad2(countdown.seconds)}</span>
                  <span className="sr-only">{t('tours.checkout.holdTitle')}</span>
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      <div className="flex flex-col lg:flex-row gap-8">
        <div className="flex-1">
          <div className="rounded-2xl border border-neutral-200 bg-white p-6">
            <h2 className="font-semibold text-neutral-900 mb-4">{t('tours.checkout.summary')}</h2>
            <p className="font-mono font-medium text-neutral-900 mb-1">{booking?.uuid || uuid}</p>
            {booking?.tour && <p className="text-neutral-600 mb-1">{booking.tour.title}</p>}
            {booking?.slot && (
              <p className="text-neutral-600 mb-4">{booking.slot.date} · {booking.slot.start_time}–{booking.slot.end_time}</p>
            )}
            {booking?.total_price != null && (
              <div className="border-t border-neutral-200 pt-4 space-y-2">
                {booking.subtotal != null && (
                  <div className="flex justify-between text-sm">
                    <span className="text-neutral-600">{t('tours.wizard.subtotal')}</span>
                    <span>{formatPrice(booking.subtotal, booking.currency)}</span>
                  </div>
                )}
                {(booking.transport_fee ?? 0) > 0 && (
                  <div className="flex justify-between text-sm">
                    <span className="text-neutral-600">{t('tours.wizard.transport')}</span>
                    <span>{formatPrice(booking.transport_fee, booking.currency)}</span>
                  </div>
                )}
                {(booking.discount_amount ?? 0) > 0 && (
                  <div className="flex justify-between text-sm text-green-600">
                    <span>{t('tours.wizard.discount')}</span>
                    <span>-{formatPrice(booking.discount_amount, booking.currency)}</span>
                  </div>
                )}
                {(booking.tax_amount ?? 0) > 0 && (
                  <div className="flex justify-between text-sm">
                    <span className="text-neutral-600">{t('tours.checkout.vat')}</span>
                    <span>{formatPrice(booking.tax_amount, booking.currency)}</span>
                  </div>
                )}
                <div className="flex justify-between font-semibold text-neutral-900 pt-2">
                  <span>{t('tours.wizard.total')}</span>
                  <span>{formatPrice(booking.total_price, booking.currency)}</span>
                </div>
              </div>
            )}
          </div>
        </div>
        <div className="lg:w-96 shrink-0">
          <div className="rounded-2xl border border-neutral-200 bg-white p-6 sticky top-24">
            <div className="flex items-center gap-2 text-neutral-600 text-sm mb-4">
              <Lock className="w-4 h-4" />
              <span>{t('tours.checkout.secure')}</span>
            </div>
            {paymentError && (
              <div className="mb-4">
                <ErrorMessage message={paymentError} onRetry={() => setPaymentError(null)} />
              </div>
            )}
            <fieldset className="mb-4">
              <legend className="text-sm font-semibold text-neutral-900 mb-2">
                {t('tours.checkout.paymentMethod')}
              </legend>
              <div className="space-y-2">
                <label
                  className={`flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition-colors ${paymentMethod === 'vnpay' ? 'border-accent bg-accent-100/40' : 'border-neutral-200 hover:bg-neutral-50'}`}
                >
                  <input
                    type="radio"
                    name="tour-payment-method"
                    value="vnpay"
                    checked={paymentMethod === 'vnpay'}
                    onChange={() => setPaymentMethod('vnpay')}
                    className="mt-1 accent-accent"
                  />
                  <span>
                    <span className="block text-sm font-semibold text-neutral-900">{t('tours.checkout.vnpay')}</span>
                    <span className="block text-xs text-neutral-500 mt-0.5">{t('tours.checkout.vnpayHint')}</span>
                  </span>
                </label>
                <label
                  className="flex items-start gap-3 rounded-xl border border-neutral-200 p-3 opacity-60 cursor-not-allowed"
                  aria-disabled="true"
                  title={t('tours.checkout.comingSoon')}
                >
                  <input
                    type="radio"
                    name="tour-payment-method"
                    value="paypal"
                    disabled
                    className="mt-1"
                  />
                  <span className="flex-1">
                    <span className="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                      {t('tours.checkout.paypal')}
                      <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500">
                        {t('tours.checkout.comingSoon')}
                      </span>
                    </span>
                  </span>
                </label>
              </div>
            </fieldset>
            <button
              type="button"
              onClick={handlePayClick}
              disabled={isProcessing || !canPay}
              className="w-full py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600 disabled:opacity-70 flex items-center justify-center gap-2"
            >
              {isProcessing ? <Loader2 className="w-5 h-5 animate-spin" /> : null}
              {isPaymentFailed ? t('tours.checkout.retryPayment') : t('tours.checkout.payNow')}
            </button>
            {isPending && canPay && (
              <button
                type="button"
                onClick={() => setShowCancelConfirm(true)}
                disabled={isCancelling}
                className="mt-3 w-full py-3 rounded-xl border border-red-200 text-red-700 text-sm font-medium hover:bg-red-50 disabled:opacity-60 flex items-center justify-center gap-2"
              >
                {isCancelling ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                {t('tours.checkout.cancelBooking')}
              </button>
            )}
          </div>
        </div>
      </div>

      <Modal
        open={showCancelConfirm}
        onClose={() => setShowCancelConfirm(false)}
        title={t('tours.checkout.cancelConfirmTitle')}
        footer={
          <>
            <button
              type="button"
              onClick={() => setShowCancelConfirm(false)}
              className="px-4 py-2 rounded-xl border text-sm font-medium"
            >
              {t('common.close')}
            </button>
            <button
              type="button"
              onClick={handleCancelHold}
              disabled={isCancelling}
              className="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium disabled:opacity-60 inline-flex items-center gap-2"
            >
              {isCancelling ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
              {t('common.confirm')}
            </button>
          </>
        }
      >
        <p className="text-sm text-neutral-600">{t('tours.checkout.cancelConfirmMessage')}</p>
      </Modal>
    </div>
  );
}
