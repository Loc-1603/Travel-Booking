import { useState } from 'react';
import { useParams, Link, useLocation, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Lock, CheckCircle2, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import ErrorMessage from '../components/ErrorMessage';
import { formatPrice } from '../lib/utils';

export default function TourCheckout() {
  const { t } = useTranslation();
  const { uuid } = useParams();
  const { user } = useAuth();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const stateBooking = location.state?.booking;
  const [isProcessing, setIsProcessing] = useState(false);
  const [paymentError, setPaymentError] = useState(null);

  const isSuccess = searchParams.get('success') === '1';

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
  const isConfirmed = booking?.status === 'confirmed';
  const showSuccess = isSuccess || isConfirmed;

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

  if (!user) {
    return (
      <div className="py-12 max-w-md mx-auto text-center">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8">
          <h2 className="text-xl font-semibold mb-2">{t('tours.wizard.loginRequired')}</h2>
          <Link
            to={`/login?redirect=${encodeURIComponent(`/tour-checkout/${uuid}`)}`}
            className="inline-flex px-6 py-3 rounded-xl bg-[#b8860b] text-white font-medium hover:bg-[#996f09] mt-4"
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
        <Loader2 className="w-8 h-8 animate-spin text-[#b8860b]" />
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
        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8 sm:p-12">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-6">
            <CheckCircle2 className="w-10 h-10" />
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold text-[#1a1a1a] mb-2">{t('tours.checkout.successTitle')}</h1>
          <p className="text-[#5c5852] mb-4">{t('tours.checkout.successHint')}</p>
          <div className="p-4 rounded-xl bg-[#f9edd1]/60 border border-[#e5c261]/60 mb-6">
            <p className="font-mono font-semibold text-[#1a1a1a]">{booking?.uuid || uuid}</p>
          </div>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Link to="/tour-bookings" className="px-6 py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09]">
              {t('tours.checkout.viewBookings')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="py-6">
      <h1 className="text-2xl sm:text-3xl font-bold text-[#1a1a1a] mb-6">{t('tours.checkout.title')}</h1>
      <div className="flex flex-col lg:flex-row gap-8">
        <div className="flex-1">
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-6">
            <h2 className="font-semibold text-[#1a1a1a] mb-4">{t('tours.checkout.summary')}</h2>
            <p className="font-mono font-medium text-[#1a1a1a] mb-1">{booking?.uuid || uuid}</p>
            {booking?.tour && <p className="text-[#5c5852] mb-1">{booking.tour.title}</p>}
            {booking?.slot && (
              <p className="text-[#5c5852] mb-4">{booking.slot.date} · {booking.slot.start_time}–{booking.slot.end_time}</p>
            )}
            {booking?.total_price != null && (
              <div className="border-t border-[#e8e4dd] pt-4 space-y-2">
                {booking.subtotal != null && (
                  <div className="flex justify-between text-sm">
                    <span className="text-[#5c5852]">{t('tours.wizard.subtotal')}</span>
                    <span>{formatPrice(booking.subtotal, booking.currency)}</span>
                  </div>
                )}
                {(booking.transport_fee ?? 0) > 0 && (
                  <div className="flex justify-between text-sm">
                    <span className="text-[#5c5852]">{t('tours.wizard.transport')}</span>
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
                    <span className="text-[#5c5852]">VAT</span>
                    <span>{formatPrice(booking.tax_amount, booking.currency)}</span>
                  </div>
                )}
                <div className="flex justify-between font-semibold text-[#1a1a1a] pt-2">
                  <span>{t('tours.wizard.total')}</span>
                  <span>{formatPrice(booking.total_price, booking.currency)}</span>
                </div>
              </div>
            )}
          </div>
        </div>
        <div className="lg:w-96 shrink-0">
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-6 sticky top-24">
            <div className="flex items-center gap-2 text-[#5c5852] text-sm mb-4">
              <Lock className="w-4 h-4" />
              <span>{t('tours.checkout.secure')}</span>
            </div>
            {paymentError && (
              <div className="mb-4">
                <ErrorMessage message={paymentError} onRetry={() => setPaymentError(null)} />
              </div>
            )}
            <button
              type="button"
              onClick={handlePayClick}
              disabled={isProcessing}
              className="w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09] disabled:opacity-70 flex items-center justify-center gap-2"
            >
              {isProcessing ? <Loader2 className="w-5 h-5 animate-spin" /> : null}
              {t('tours.checkout.payNow')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
