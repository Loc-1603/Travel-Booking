import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { MapPin, ChevronLeft, ChevronRight, Loader2, MessageCircle } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { useSavedTours } from '../hooks/useSavedTours';
import { HotelDetailSkeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { TourSlotCalendar } from '../components/TourSlotCalendar';
import { TourChatBox } from '../components/TourChatBox';
import { TourReviewForm } from '../components/TourReviewForm';
import { TourReviewList } from '../components/TourReviewList';
import GuideAvatar from '../components/GuideAvatar';
import { formatPrice, getRatingLabel, cn } from '../lib/utils';

export default function TourDetail() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { uuid } = useParams();
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);
  const [slot, setSlot] = useState(null);
  const { isSaved, saveTour, unsaveTour } = useSavedTours(!!user);

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
  const saved = isSaved(tour.id);

  const handleBookNow = () => {
    if (!user) {
      navigate(`/login?redirect=${encodeURIComponent(`/tours/${uuid}`)}`);
      return;
    }
    if (!slot) return;
    navigate(`/tours/book/${uuid}?slot_id=${slot.id}`);
  };

  const toggleSave = async () => {
    try {
      if (saved) await unsaveTour(tour.id);
      else await saveTour(tour.id);
    } catch { /* non-fatal: ignore */ }
  };

  return (
    <div className="py-4 sm:py-6">
      {/* Breadcrumb: province -> guide -> tour */}
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[#5c5852] mb-4">
        {tour.province?.slug && (
          <Link to={`/tours/province/${tour.province.slug}`} className="hover:text-[#b8860b]">
            ← {tour.province.name}
          </Link>
        )}
        {tour.provider?.uuid && (
          <Link
            to={`/guides/${tour.provider.uuid}${tour.province?.slug ? `?province=${encodeURIComponent(tour.province.slug)}` : ''}`}
            className="hover:text-[#b8860b]"
          >
            · {tour.provider.business_name} →
          </Link>
        )}
      </div>
      {/* Gallery */}
      <div className="mb-6">
        <div className="relative rounded-2xl overflow-hidden bg-[#e8e4dd] aspect-[21/9] sm:aspect-[3/1] max-h-[400px]">
          {images.length > 0 ? (
            <>
              <img
                src={images[selectedImageIndex]?.url || images[0]?.url}
                alt={images[selectedImageIndex]?.alt_text || tour.title}
                className="w-full h-full object-cover"
              />
              {images.length > 1 && (
                <>
                  <div className="absolute bottom-4 right-4 bg-black/60 text-white px-3 py-1 rounded-lg text-sm">
                    {selectedImageIndex + 1} / {images.length}
                  </div>
                  <button
                    type="button"
                    onClick={() => setSelectedImageIndex((i) => (i - 1 + images.length) % images.length)}
                    className="absolute left-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-white/90 hover:bg-white shadow"
                    aria-label="Previous"
                  >
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => setSelectedImageIndex((i) => (i + 1) % images.length)}
                    className="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-white/90 hover:bg-white shadow"
                    aria-label="Next"
                  >
                    <ChevronRight className="w-5 h-5" />
                  </button>
                </>
              )}
            </>
          ) : (
            <div className="w-full h-full flex items-center justify-center text-[#5c5852]">
              <MapPin className="w-16 h-16" />
            </div>
          )}
        </div>
      </div>

      <div className="flex flex-col lg:flex-row gap-6 lg:gap-8">
        {/* Main */}
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a]">{tour.title}</h1>
              <p className="text-[#5c5852] mt-1 flex items-center gap-1">
                <MapPin className="w-4 h-4" /> {tour.province?.name || '—'}
              </p>
              {rating != null && rating > 0 && (
                <p className="mt-1 text-sm text-[#5c5852]">
                  <span className="font-semibold text-[#1a1a1a]">{rating.toFixed(1)}</span> {getRatingLabel(rating)}
                  {tour.review_count > 0 && ` · ${tour.review_count} reviews`}
                </p>
              )}
            </div>
            {user && (
              <button
                type="button"
                onClick={toggleSave}
                className={cn(
                  'shrink-0 px-4 py-2 rounded-xl border text-sm font-medium transition-colors',
                  saved ? 'border-[#b8860b] bg-[#f9edd1]' : 'border-[#e8e4dd] hover:bg-[#faf8f5]'
                )}
              >
                {saved ? t('tours.save.saved') : t('tours.save.save')}
              </button>
            )}
          </div>

          {tour.description && <p className="mt-4 text-[#45423d] leading-relaxed">{tour.description}</p>}

          {/* Guide */}
          {tour.provider && (
            <div className="mt-6 rounded-2xl border border-[#e8e4dd] bg-white p-5">
              <h2 className="font-semibold text-[#1a1a1a] mb-2">{t('tours.detail.guide')}</h2>
              <div className="flex items-center gap-3">
                <GuideAvatar name={tour.provider.business_name} avatarUrl={tour.provider.avatar} size={48} />
                <p className="font-medium text-[#1a1a1a]">{tour.provider.business_name}</p>
              </div>
              {tour.provider.bio && <p className="text-sm text-[#5c5852] mt-1">{tour.provider.bio}</p>}
              {tour.provider.languages?.length > 0 && (
                <p className="text-sm text-[#7a756d] mt-1">{t('tours.detail.languages')}: {tour.provider.languages.join(', ')}</p>
              )}
            </div>
          )}

          {/* Attractions */}
          {tour.province?.attractions?.length > 0 && (
            <div className="mt-6">
              <h2 className="font-semibold text-[#1a1a1a] mb-3">{t('tours.detail.attractions')}</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {tour.province.attractions.map((a) => (
                  <div key={a.id} className="rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white">
                    <div className="relative aspect-[16/9] bg-[#e8e4dd] flex items-center justify-center text-[#a39e94]">
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
                      <p className="font-medium text-[#1a1a1a]">
                        {a.name}
                        {a.is_famous && <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-[#f9edd1] text-[#996f09]">★ {t('tours.detail.famous')}</span>}
                      </p>
                      {a.description && <p className="text-sm text-[#5c5852] mt-1">{a.description}</p>}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Chat (existing booking) */}
          {myBooking && (
            <div className="mt-6">
              <h2 className="font-semibold text-[#1a1a1a] mb-3 flex items-center gap-2">
                <MessageCircle className="w-5 h-5 text-[#b8860b]" /> {t('tours.chat.title')}
              </h2>
              <TourChatBox bookingUuid={myBooking.uuid} />
            </div>
          )}

          {/* Reviews */}
          <div className="mt-6 space-y-4">
            <h2 className="font-semibold text-[#1a1a1a]">{t('tours.reviews.title')}</h2>
            <TourReviewList tourUuid={uuid} />
            {myBooking && ['confirmed', 'ongoing', 'completed'].includes(myBooking.status) && (
              <TourReviewForm tourBookingId={myBooking.id} />
            )}
          </div>
        </div>

        {/* Sidebar: pricing + slots + book */}
        <div className="lg:w-96 shrink-0">
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5 sm:p-6 shadow-sm lg:sticky lg:top-24 space-y-4">
            <div>
              <p className="text-sm text-[#7a756d]">{t('tours.detail.fromPrice')}</p>
              <p className="text-2xl font-bold text-[#1a1a1a]">{formatPrice(Number(tour.base_fixed) + Number(tour.base_price_hourly), 'VND')}</p>
              <ul className="text-sm text-[#5c5852] mt-2 space-y-1">
                <li>{t('tours.detail.fixedFee')}: {formatPrice(Number(tour.base_fixed), 'VND')}</li>
                <li>{t('tours.detail.hourly')}: {formatPrice(Number(tour.base_price_hourly), 'VND')}</li>
                <li>{t('tours.detail.daily')}: {formatPrice(Number(tour.base_price_daily), 'VND')}</li>
                {tour.transport_fee != null && (
                  <li>{t('tours.detail.transport')}: {formatPrice(Number(tour.transport_fee), 'VND')}</li>
                )}
              </ul>
              {tour.meeting_point && (
                <p className="text-sm text-[#5c5852] mt-2">{t('tours.detail.meetingPoint')}: {tour.meeting_point}</p>
              )}
            </div>

            <TourSlotCalendar tourUuid={uuid} selectedSlotId={slot?.id} onSelect={setSlot} />

            {slot && (
              <p className="text-sm text-[#45423d]">
                {t('tours.detail.selected')}: {slot.date} · {slot.start_time}–{slot.end_time}
              </p>
            )}

            <button
              type="button"
              onClick={handleBookNow}
              disabled={user && !slot}
              className="w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {user ? t('tours.detail.bookNow') : t('tours.detail.loginToBook')}
            </button>
            {!user && (
              <p className="text-xs text-[#7a756d] text-center">{t('tours.detail.loginHint')}</p>
            )}
            {user && !slot && (
              <p className="text-xs text-[#7a756d] text-center">{t('tours.detail.pickSlotHint')}</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
