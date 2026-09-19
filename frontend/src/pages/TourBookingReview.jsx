import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Calendar, Loader2 } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import ErrorMessage from '../components/ErrorMessage';
import { TourReviewForm } from '../components/TourReviewForm';
import { formatPrice } from '../lib/utils';

const REVIEWABLE_STATUSES = ['confirmed', 'ongoing', 'completed'];

/**
 * Trang đánh giá riêng cho 1 tour booking đã thanh toán.
 * Mỗi booking chỉ đánh giá được 1 lần (backend chặn REVIEW_EXISTS).
 */
export default function TourBookingReview() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { uuid } = useParams();

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour-booking', uuid],
    queryFn: async () => {
      const res = await api.get(`/tour-bookings/${uuid}`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load booking');
      return res.data;
    },
    enabled: !!user && !!uuid,
  });
  const booking = data?.data ?? null;

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
      <div className="max-w-2xl mx-auto">
        <Link to="/tour-bookings" className="inline-flex items-center gap-1.5 text-sm text-[#5c5852] hover:text-[#b8860b] mb-4">
          <ArrowLeft className="w-4 h-4" /> {t('tours.bookings.backToBookings')}
        </Link>
        <h1 className="text-2xl sm:text-3xl font-bold text-[#1a1a1a]">{t('tours.bookings.reviewPageTitle')}</h1>
        <p className="text-[#5c5852] mt-1 mb-6">{t('tours.bookings.reviewPageHint')}</p>

        {isLoading && <Loader2 className="w-8 h-8 animate-spin text-[#b8860b]" />}
        {isError && <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />}

        {!isLoading && !isError && booking && !REVIEWABLE_STATUSES.includes(booking.status) && (
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8 text-center">
            <p className="text-[#5c5852]">{t('tours.bookings.reviewNotEligible')}</p>
          </div>
        )}

        {!isLoading && !isError && booking && REVIEWABLE_STATUSES.includes(booking.status) && !!booking.has_review && (
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8 text-center">
            <p className="text-[#5c5852]">{t('tours.bookings.reviewDone')}</p>
          </div>
        )}

        {!isLoading && !isError && booking && REVIEWABLE_STATUSES.includes(booking.status) && !booking.has_review && (
          <div className="space-y-4">
            <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5">
              <h3 className="font-semibold text-lg">{booking.tour?.title ?? 'Tour'}</h3>
              <p className="text-sm text-[#5c5852] mt-1">
                {booking.slot?.date} · {booking.slot?.start_time}–{booking.slot?.end_time}
              </p>
              <p className="font-semibold text-lg mt-2">{formatPrice(booking.total_price, booking.currency)}</p>
            </div>
            <TourReviewForm tourBookingId={booking.id} onSubmitted={() => navigate('/tour-bookings')} />
          </div>
        )}
      </div>
    </div>
  );
}
