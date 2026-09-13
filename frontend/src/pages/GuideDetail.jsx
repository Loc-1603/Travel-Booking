import { useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, MessageCircle, Star } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import GuideAvatar from '../components/GuideAvatar';
import { HotelDetailSkeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { GuideAvailability } from '../components/GuideAvailability';
import { TourChatBox } from '../components/TourChatBox';
import { TourReviewList } from '../components/TourReviewList';
import { formatPrice, getRatingLabel } from '../lib/utils';
import { getGuideScore } from '../lib/guideSearch';

/**
 * Guide profile: bio, tours they can take you on (in this province),
 * merged free calendar, guest reviews, and chat after booking.
 * Booking itself reuses the tour wizard (/tours/book/:tourUuid).
 */
export default function GuideDetail() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { uuid } = useParams();
  const [searchParams] = useSearchParams();
  const provinceSlug = searchParams.get('province') || '';
  const [selection, setSelection] = useState(null);

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour-provider', uuid, provinceSlug],
    queryFn: async () => {
      const params = provinceSlug ? { province_slug: provinceSlug } : {};
      const res = await api.get(`/tour-providers/${uuid}`, { params });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load guide');
      return res.data;
    },
    enabled: !!uuid,
  });
  const guide = data?.data ?? null;
  const tours = useMemo(() => guide?.tours ?? [], [guide]);

  // Customer's bookings with this guide (chat opens without re-booking).
  const { data: myBookingsData } = useQuery({
    queryKey: ['tour-bookings-mine'],
    queryFn: async () => {
      const res = await api.get('/tour-bookings', { params: { per_page: 50 } });
      return res.data;
    },
    enabled: !!user,
  });
  const myGuideBookings = useMemo(() => {
    const payload = myBookingsData?.data ?? {};
    const list = Array.isArray(payload?.data) ? payload.data : [];
    return list.filter((b) => b.tour?.provider?.uuid === uuid || b.provider?.uuid === uuid);
  }, [myBookingsData, uuid]);
  const latestBooking = myGuideBookings[0] ?? null;

  if (isLoading) {
    return (
      <div className="py-6">
        <HotelDetailSkeleton />
      </div>
    );
  }
  if (isError || !guide) {
    return (
      <div className="py-6">
        <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />
      </div>
    );
  }

  const rating = guide.average_rating != null ? Number(guide.average_rating) : null;
  const reviewCount = guide.review_count != null ? Number(guide.review_count) : 0;
  const backTo = provinceSlug ? `/tours/province/${provinceSlug}` : '/tours';

  const goWizard = (tourUuid, slotId) => {
    if (!user) {
      const here = `/guides/${uuid}${provinceSlug ? `?province=${encodeURIComponent(provinceSlug)}` : ''}`;
      navigate(`/login?redirect=${encodeURIComponent(here)}`);
      return;
    }
    navigate(slotId ? `/tours/book/${tourUuid}?slot_id=${slotId}` : `/tours/book/${tourUuid}`);
  };

  return (
    <div className="py-4 sm:py-6">
      <div className="flex items-center justify-between gap-2 mb-4">
        <Link to={backTo} className="inline-flex items-center gap-1.5 text-sm text-[#5c5852] hover:text-[#b8860b]">
          <ArrowLeft className="w-4 h-4" /> {provinceSlug ? t('tours.guides.backToProvince') : t('tours.provinceDetail.back')}
        </Link>
        {user && (
          <Link to="/tour-bookings" className="text-sm text-[#b8860b] hover:underline">
            {t('tours.guides.viewAllBookings')}
          </Link>
        )}
      </div>

      {/* Profile header */}
      <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5 sm:p-6 flex flex-col sm:flex-row gap-5">
        <div className="shrink-0">
          <GuideAvatar name={guide.business_name} avatarUrl={guide.avatar} size={96} />
        </div>
        <div className="min-w-0 flex-1">
          <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a]">{guide.business_name}</h1>
          {guide.languages?.length > 0 && (
            <p className="text-sm text-[#7a756d] mt-1">
              {t('tours.detail.languages')}: {guide.languages.join(', ')}
            </p>
          )}
          <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            {rating != null && rating > 0 ? (
              <>
                <span className="inline-flex items-center gap-1 font-semibold text-[#1a1a1a]">
                  <Star className="w-4 h-4 text-[#b8860b] fill-current" />
                  {rating.toFixed(1)}
                </span>
                <span className="text-[#5c5852]">{getRatingLabel(rating)}</span>
                <span className="text-[#7a756d]">
                  {reviewCount} {t('tours.guides.reviewsCount')}
                </span>
                <span className="text-xs px-2 py-0.5 rounded-full bg-[#f9edd1] text-[#996f09] font-medium">
                  {getGuideScore(guide)} {t('tours.guides.score')}
                </span>
              </>
            ) : (
              <span className="text-[#7a756d]">{t('tours.reviews.empty')}</span>
            )}
          </div>
          {guide.price_from != null && (
            <p className="mt-2 text-sm">
              <span className="text-[#7a756d]">{t('tours.guides.priceFrom')} </span>
              <span className="font-semibold text-[#1a1a1a]">{formatPrice(Number(guide.price_from), 'VND')}</span>
            </p>
          )}
        </div>
      </div>

      {guide.bio && (
        <div className="mt-6">
          <h2 className="font-semibold text-[#1a1a1a] mb-2">{t('tours.guides.about')}</h2>
          <p className="text-[#45423d] leading-relaxed">{guide.bio}</p>
        </div>
      )}

      {/* Tours this guide offers here */}
      <div className="mt-6">
        <h2 className="font-semibold text-[#1a1a1a] mb-3">{t('tours.guides.offers')}</h2>
        {tours.length === 0 ? (
          <p className="text-sm text-[#7a756d]">{t('tours.guides.noTours')}</p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {tours.map((tour) => (
              <div key={tour.uuid} className="rounded-2xl border border-[#e8e4dd] bg-white p-4 flex flex-col">
                <p className="font-medium text-[#1a1a1a]">{tour.title}</p>
                {tour.description && <p className="text-sm text-[#5c5852] mt-1 line-clamp-2">{tour.description}</p>}
                <div className="mt-2 text-sm text-[#5c5852]">
                  {formatPrice(Number(tour.base_fixed) + Number(tour.base_price_hourly), 'VND')}
                  {tour.transport_fee != null && <> · {t('tours.detail.transport')}: {formatPrice(Number(tour.transport_fee), 'VND')}</>}
                </div>
                {tour.meeting_point && (
                  <p className="text-xs text-[#7a756d] mt-1">
                    {t('tours.detail.meetingPoint')}: {tour.meeting_point}
                  </p>
                )}
                <button
                  type="button"
                  onClick={() => goWizard(tour.uuid, null)}
                  className="mt-3 w-full py-2.5 rounded-xl border border-[#b8860b] text-[#996f09] text-sm font-semibold hover:bg-[#f9edd1] transition-colors"
                >
                  {user ? t('tours.guides.bookThis') : t('tours.guides.loginToBook')}
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Availability + book */}
      {tours.length > 0 && (
        <div className="mt-6">
          <GuideAvailability tours={tours} selected={selection} onSelect={setSelection} />
          {selection && (
            <p className="text-sm text-[#45423d] mt-3">
              {t('tours.detail.selected')}: {selection.slot.date} · {selection.slot.start_time}–{selection.slot.end_time} · {selection.tour.title}
            </p>
          )}
          <button
            type="button"
            onClick={() => selection && goWizard(selection.tour.uuid, selection.slot.id)}
            disabled={!selection}
            className="mt-3 w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {user ? t('tours.detail.bookNow') : t('tours.guides.loginToBook')}
          </button>
          {!selection && <p className="text-xs text-[#7a756d] text-center mt-2">{t('tours.guides.pickTourHint')}</p>}
        </div>
      )}

      {/* Chat (existing booking with this guide) */}
      {latestBooking && (
        <div className="mt-6">
          <h2 className="font-semibold text-[#1a1a1a] mb-1 flex items-center gap-2">
            <MessageCircle className="w-5 h-5 text-[#b8860b]" /> {t('tours.chat.title')}
          </h2>
          <p className="text-sm text-[#7a756d] mb-3">{t('tours.guides.myBooking')}</p>
          <TourChatBox bookingUuid={latestBooking.uuid} />
        </div>
      )}

      {/* Reviews from past customers */}
      <div className="mt-6 space-y-4">
        <h2 className="font-semibold text-[#1a1a1a]">{t('tours.reviews.title')}</h2>
        <TourReviewList providerUuid={uuid} />
      </div>
    </div>
  );
}
