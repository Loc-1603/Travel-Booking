import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { MapPin, Loader2, MessageCircle } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { HotelDetailSkeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { TourSlotCalendar } from '../components/TourSlotCalendar';
import { TourGallery } from '../components/TourGallery';
import { TourPriceSummary } from '../components/TourPriceSummary';
import { getTourFromPrice, getTourPriceParts } from '../lib/tourSearch';
import { TourChatBox } from '../components/TourChatBox';
import { TourReviewForm } from '../components/TourReviewForm';
import { TourReviewList } from '../components/TourReviewList';
import GuideAvatar from '../components/GuideAvatar';
import { formatPrice, getRatingLabel } from '../lib/utils';

export default function TourDetail() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { uuid } = useParams();
  const [slot, setSlot] = useState(null);

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour', uuid],
    queryFn: async () => {
      const res = await api.get(`/tours/${uuid}`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load tour');
      return res.data;
    },
    enabled: !!uuid,
  });
  const tour = data?.data ?? data;

  // Customer's existing booking for this tour (to open chat without re-booking).
  const { data: myBookingsData } = useQuery({
    queryKey: ['tour-bookings-mine'],
    queryFn: async () => {
      const res = await api.get('/tour-bookings', { params: { per_page: 50 } });
      return res.data;
    },
    enabled: !!user,
  });
  const myBooking = (() => {
    const payload = myBookingsData?.data ?? {};
    const list = Array.isArray(payload?.data) ? payload.data : [];
    return list.find((b) => b.tour?.uuid === uuid || b.tour?.id === tour?.id) || null;
  })();

  // Estimated price for the picked slot (public preview — visible to guests,
  // login is only required when booking). Fixed 1-hour trip as a baseline.
  const { data: estimateData, isFetching: estimateLoading } = useQuery({
    queryKey: ['tour-estimate', uuid, slot?.id],
    queryFn: async () => {
      const res = await api.post('/tour-bookings/preview', {
        tour_id: tour.id,
        slot_id: slot.id,
        pricing_mode: 'hour',
        duration_value: 1,
      });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to preview');
      return res.data;
    },
    enabled: !!tour && !!slot,
    staleTime: 30_000,
    refetchOnWindowFocus: false,
    retry: false,
  });
  const estimate = estimateData?.data;

  if (isLoading) {
    return (
      <div className="py-6">
        <HotelDetailSkeleton />
      </div>
    );
  }
  if (isError || !tour) {
    return (
      <div className="py-6">
        <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />
      </div>
    );
  }

  const images = tour.images ?? [];
  const rating = tour.average_rating != null ? Number(tour.average_rating) : null;
  const rateParts = getTourPriceParts(tour);
  const fromPrice = getTourFromPrice(tour);

  const handleBookNow = () => {
    if (!user) {
      navigate(`/login?redirect=${encodeURIComponent(`/tours/${uuid}`)}`);
      return;
    }
    if (!slot) return;
    navigate(`/tours/book/${uuid}?slot_id=${slot.id}`);
  };

  return (
    <div className="py-4 sm:py-6">
      {/* Breadcrumb: province -> guide -> tour */}
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-neutral-600 mb-4">
        {tour.province?.slug && (
          <Link to={`/tours/province/${tour.province.slug}`} className="hover:text-accent">
            ← {tour.province.name}
          </Link>
        )}
        {tour.provider?.uuid && (
          <Link
            to={`/guides/${tour.provider.uuid}${tour.province?.slug ? `?province=${encodeURIComponent(tour.province.slug)}` : ''}`}
            className="hover:text-accent"
          >
            · {tour.provider.business_name} →
          </Link>
        )}
      </div>
      {/* Gallery */}
      <div className="mb-6" data-testid="tour-gallery">
        <TourGallery images={images} alt={tour.title} />
      </div>

      <div className="flex flex-col lg:flex-row gap-6 lg:gap-8">
        {/* Main */}
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-neutral-900">{tour.title}</h1>
              <p className="text-neutral-600 mt-1 flex items-center gap-1">
                <MapPin className="w-4 h-4" /> {tour.province?.name || t('tours.list.unknownProvince')}
              </p>
              {rating != null && rating > 0 && (
                <p className="mt-1 text-sm text-neutral-600">
                  <span className="font-semibold text-neutral-900">{rating.toFixed(1)}</span> {getRatingLabel(rating)}
                  {tour.review_count > 0 && ` · ${t('tours.reviewCount', { count: Number(tour.review_count) })}`}
                </p>
              )}
            </div>
          </div>

          {tour.description && <p className="mt-4 text-neutral-700 leading-relaxed">{tour.description}</p>}

          {/* Guide */}
          {tour.provider && (
            <div className="mt-6 rounded-2xl border border-neutral-200 bg-white p-5">
              <h2 className="font-semibold text-neutral-900 mb-2">{t('tours.detail.guide')}</h2>
              <div className="flex items-center gap-3">
                <GuideAvatar name={tour.provider.business_name} avatarUrl={tour.provider.avatar} size={48} />
                <p className="font-medium text-neutral-900">{tour.provider.business_name}</p>
              </div>
              {tour.provider.bio && <p className="text-sm text-neutral-600 mt-1">{tour.provider.bio}</p>}
              {tour.provider.languages?.length > 0 && (
                <p className="text-sm text-neutral-500 mt-1">{t('tours.detail.languages')}: {tour.provider.languages.join(', ')}</p>
              )}
            </div>
          )}

          {/* Attractions */}
          {tour.province?.attractions?.length > 0 && (
            <div className="mt-6">
              <h2 className="font-semibold text-neutral-900 mb-3">{t('tours.detail.attractions')}</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {tour.province.attractions.map((a) => (
                  <div key={a.id} className="rounded-2xl overflow-hidden border border-neutral-200 bg-white">
                    <div className="relative aspect-[16/9] bg-neutral-200 flex items-center justify-center text-neutral-500">
                      <MapPin className="w-8 h-8" />
                      {a.image && (
                        <img
                          src={a.image}
                          alt={a.name}
                          loading="lazy"
                          className="absolute inset-0 w-full h-full object-cover"
                          onError={(e) => { e.currentTarget.style.display = 'none'; }}
                        />
                      )}
                    </div>
                    <div className="p-4">
                      <p className="font-medium text-neutral-900">
                        {a.name}
                        {a.is_famous && <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-accent-100 text-accent-600">★ {t('tours.detail.famous')}</span>}
                      </p>
                      {a.description && <p className="text-sm text-neutral-600 mt-1">{a.description}</p>}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Chat (existing booking) */}
          {myBooking && (
            <div className="mt-6">
              <h2 className="font-semibold text-neutral-900 mb-3 flex items-center gap-2">
                <MessageCircle className="w-5 h-5 text-accent" /> {t('tours.chat.title')}
              </h2>
              <TourChatBox bookingUuid={myBooking.uuid} />
            </div>
          )}

          {/* Reviews */}
          <div className="mt-6 space-y-4">
            <h2 className="font-semibold text-neutral-900">{t('tours.reviews.title')}</h2>
            <TourReviewList tourUuid={uuid} />
            {myBooking && ['confirmed', 'ongoing', 'completed'].includes(myBooking.status) && (
              <TourReviewForm tourBookingId={myBooking.id} />
            )}
          </div>
        </div>

        {/* Sidebar: pricing + slots + book */}
        <div className="lg:w-96 shrink-0">
          <div className="rounded-2xl border border-neutral-200 bg-white p-5 sm:p-6 shadow-sm lg:sticky lg:top-24 space-y-4">
            <div>
              <p className="text-sm text-neutral-500">{t('tours.detail.fromPrice')}</p>
              {fromPrice != null ? (
                <>
                  <p className="text-2xl font-bold text-neutral-900" data-testid="tour-from-price">
                    {formatPrice(fromPrice, 'VND')}
                  </p>
                  <TourPriceSummary
                    subtotal={rateParts.hourTotal}
                    transport={rateParts.dayTotal}
                    total={fromPrice}
                    currency="VND"
                    labels={{
                      subtotal: t('tours.detail.rateHour'),
                      transport: t('tours.detail.rateDay'),
                      total: t('tours.detail.fromPrice'),
                    }}
                    className="mt-2"
                  />
                </>
              ) : (
                <p className="text-sm text-neutral-500">{t('tours.slots.none')}</p>
              )}
              {tour.transport_fee != null && (
                <p className="text-sm text-neutral-600 mt-2">
                  {t('tours.detail.transport')}: {formatPrice(Number(tour.transport_fee), 'VND')}
                </p>
              )}
              {tour.meeting_point && (
                <p className="text-sm text-neutral-600 mt-2">{t('tours.detail.meetingPoint')}: {tour.meeting_point}</p>
              )}
            </div>

            <TourSlotCalendar tourUuid={uuid} selectedSlotId={slot?.id} onSelect={setSlot} />

            {slot && (
              <p className="text-sm text-neutral-700">
                {t('tours.detail.selected')}: {slot.date} · {slot.start_time}–{slot.end_time}
              </p>
            )}

            {slot && (
              <div className="rounded-xl bg-neutral-50 border border-neutral-200 p-4" data-testid="tour-estimate">
                <h3 className="text-sm font-semibold text-neutral-900 mb-2">{t('tours.detail.estimateTitle')}</h3>
                {estimateLoading && <Loader2 className="w-5 h-5 animate-spin text-accent" />}
                {estimate && !estimateLoading && (
                  <TourPriceSummary
                    subtotal={estimate.subtotal ?? 0}
                    transport={estimate.add_on_amount ?? 0}
                    discount={estimate.discount ?? 0}
                    tax={estimate.tax ?? 0}
                    total={estimate.total}
                    currency={estimate.currency || 'VND'}
                    labels={estimate.tax_name ? { tax: estimate.tax_name } : undefined}
                  />
                )}
                {!estimate && !estimateLoading && (
                  <p className="text-sm text-neutral-500">{t('tours.wizard.previewHint')}</p>
                )}
                {!user && (
                  <p className="text-xs text-neutral-500 mt-2">{t('tours.detail.estimateGuestHint')}</p>
                )}
              </div>
            )}

            <button
              type="button"
              onClick={handleBookNow}
              disabled={user && !slot}
              className="w-full py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {user ? t('tours.detail.bookNow') : t('tours.detail.loginToBook')}
            </button>
            {!user && (
              <p className="text-xs text-neutral-500 text-center">{t('tours.detail.loginHint')}</p>
            )}
            {user && !slot && (
              <p className="text-xs text-neutral-500 text-center">{t('tours.detail.pickSlotHint')}</p>
            )}
          </div>
        </div>
      </div>

      {/* Spacer so the mobile sticky CTA never covers content */}
      <div className="h-20 lg:hidden" aria-hidden="true" />

      {/* Sticky CTA (mobile) — price + book action stay visible while scrolling */}
      <div
        className="fixed bottom-0 inset-x-0 z-40 lg:hidden border-t border-neutral-200 bg-white/95 backdrop-blur px-4 py-3"
        data-testid="tour-sticky-cta"
      >
        <div className="flex items-center gap-3 max-w-3xl mx-auto">
          <div className="flex-1 min-w-0">
            <p className="text-xs text-neutral-500">{t('tours.detail.fromPrice')}</p>
            <p className="font-bold text-neutral-900 truncate">
              {fromPrice != null ? formatPrice(fromPrice, 'VND') : t('tours.slots.none')}
            </p>
          </div>
          <button
            type="button"
            onClick={handleBookNow}
            disabled={user && !slot}
            className="px-6 py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shrink-0"
          >
            {user ? t('tours.detail.bookNow') : t('tours.detail.loginToBook')}
          </button>
        </div>
      </div>
    </div>
  );
}
