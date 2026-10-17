import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, CalendarDays, Loader2, Star } from 'lucide-react';
import DOMPurify from 'dompurify';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { useSavedGuides } from '../hooks/useSavedGuides';
import GuideAvatar from '../components/GuideAvatar';
import { HotelDetailSkeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { ReviewList } from '../components/ReviewList';
import { TourPriceSummary } from '../components/TourPriceSummary';
import { formatPrice, getRatingLabel } from '../lib/utils';
import { getGuideScore } from '../lib/guideSearch';

function GuideWishlistHeart({ providerUuid }) {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { isSaved, saveGuide, unsaveGuide, addPending, removePending } = useSavedGuides(!!user);
  if (!user) {
    return (
      <Link to="/login" className="p-2 rounded-full border border-neutral-200 hover:bg-neutral-50 inline-flex" aria-label={t('tours.guides.wishlist.loginToSave')}>
        <HeartIcon filled={false} />
      </Link>
    );
  }
  const saved = isSaved(providerUuid);
  const pending = addPending || removePending;
  const handleClick = async () => {
    if (pending) return;
    try {
      if (saved) await unsaveGuide(providerUuid);
      else await saveGuide(providerUuid);
    } catch { /* rollback handled by the optimistic update in useSavedGuides */ }
  };
  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={pending}
      aria-pressed={saved}
      className="p-2 rounded-full border border-neutral-200 hover:bg-neutral-50 disabled:opacity-60 inline-flex"
      aria-label={saved ? t('tours.guides.wishlist.remove') : t('tours.guides.wishlist.add')}
    >
      <HeartIcon filled={saved} />
    </button>
  );
}

function HeartIcon({ filled }) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill={filled ? '#b8860b' : 'none'} stroke="#b8860b" strokeWidth="2" className="w-5 h-5">
      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
    </svg>
  );
}

/**
 * Guide profile — luồng đặt 1 ngày khám phá tỉnh:
 * ngày đi đã chốt từ trang tỉnh (?date=) → mô tả rich → Đặt ngay 1 ngày
 * (giá/ngày + coupon/điểm hẹn/ghi chú) → thanh toán.
 * Chat + viết đánh giá nằm ở trang /tour-bookings, không ở đây.
 */
export default function GuideDetail() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { uuid } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();
  const provinceSlug = searchParams.get('province') || '';
  // Ngày đi chỉnh được inline: DatePicker + "Cập nhật" ghi vào ?date=,
  // availability/preview refetch theo queryKey; giữ nguyên province/sort.
  const travelDate = searchParams.get('date') || '';
  const backSort = searchParams.get('sort') || '';
  const hasDate = !!travelDate;
  const [draftDate, setDraftDate] = useState(travelDate);
  useEffect(() => {
    setDraftDate(travelDate);
  }, [travelDate]);
  const todayISO = useMemo(() => new Date().toISOString().split('T')[0], []);

  const handleDateUpdate = () => {
    if (!draftDate || draftDate === travelDate) return;
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      next.set('date', draftDate);
      return next;
    });
  };

  const [couponCode, setCouponCode] = useState('');
  const [meetingPoint, setMeetingPoint] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour-provider', uuid, provinceSlug],
    queryFn: async () => {
      const params = provinceSlug ? { province_slug: provinceSlug } : {};
      const res = await api.get(`/tour-providers/${uuid}`, { params });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load guide');
      return res.data;
    },
    enabled: !!uuid,
    // Vendor sửa hồ sơ (giá, giới thiệu) → trang người dùng luôn refetch,
    // không hiện bản cache 60s (staleTime toàn cục) nữa.
    staleTime: 0,
  });
  const guide = data?.data ?? null;

  // Kiểm tra guide còn trống đúng ngày đi không (public). Chỉ để verify +
  // lấy meeting_point gợi ý, không cho chọn giờ.
  const {
    data: slotsData,
    isLoading: slotsLoading,
    isError: slotsError,
    refetch: refetchSlots,
  } = useQuery({
    queryKey: ['guide-availability', uuid, travelDate, provinceSlug],
    queryFn: async () => {
      const params = { date: travelDate };
      if (provinceSlug) params.province_slug = provinceSlug;
      const res = await api.get(`/tour-providers/${uuid}/availability`, { params });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load slots');
      return res.data?.data ?? [];
    },
    enabled: !!uuid && hasDate,
    staleTime: 30_000,
  });
  const slots = useMemo(() => (Array.isArray(slotsData) ? slotsData : []), [slotsData]);
  const isFree = slots.length > 0;
  const meetingPlaceholder = slots[0]?.tour?.meeting_point || t('tours.wizard.meetingPointPlaceholder');

  // Xem trước giá 1 ngày (public cho guest từ BE-1.4 — chỉ login khi bấm đặt).
  // Không gửi slot_id — backend tự resolve slot trống của ngày đó.
  const previewPayload = useMemo(() => {
    if (!hasDate) return null;
    return {
      provider_uuid: uuid,
      travel_date: travelDate,
      ...(couponCode.trim() && { coupon_code: couponCode.trim() }),
    };
  }, [hasDate, uuid, travelDate, couponCode]);
  const { data: previewData, isFetching: previewLoading } = useQuery({
    queryKey: ['guide-booking-preview', uuid, travelDate, couponCode.trim()],
    queryFn: async () => {
      const res = await api.post('/tour-bookings/guide-preview', previewPayload);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to preview');
      return res.data;
    },
    enabled: !!previewPayload && (slotsLoading || isFree),
    staleTime: 30_000,
    refetchOnWindowFocus: false,
    retry: false,
  });
  const breakdown = previewData?.data;

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
  const backParams = new URLSearchParams({
    ...(travelDate && { date: travelDate }),
    ...(backSort && { sort: backSort }),
  }).toString();
  const backTo = provinceSlug
    ? `/tours/province/${provinceSlug}${backParams ? `?${backParams}` : ''}`
    : '/tours';

  const confirmBooking = async () => {
    if (!previewPayload) return;
    if (!user) {
      const here = `/guides/${uuid}?${new URLSearchParams({
        ...(provinceSlug && { province: provinceSlug }),
        ...(travelDate && { date: travelDate }),
        ...(backSort && { sort: backSort }),
      }).toString()}`;
      navigate(`/login?redirect=${encodeURIComponent(here)}`);
      return;
    }
    setSubmitting(true);
    setSubmitError(null);
    try {
      const res = await api.post('/tour-bookings/guide', {
        ...previewPayload,
        ...(meetingPoint.trim() && { meeting_point: meetingPoint.trim() }),
        ...(notes.trim() && { customer_notes: notes.trim() }),
        currency: 'VND',
      });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to book');
      const booking = res.data?.data?.booking ?? res.data?.data;
      if (booking?.uuid) navigate(`/tour-checkout/${booking.uuid}`, { state: { booking } });
    } catch (err) {
      setSubmitError(err?.response?.data?.message || err?.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="py-4 sm:py-6">
      <div className="flex items-center justify-between gap-2 mb-4">
        <Link to={backTo} className="inline-flex items-center gap-1.5 text-sm text-neutral-600 hover:text-accent">
          <ArrowLeft className="w-4 h-4" /> {provinceSlug ? t('tours.guides.backToProvince') : t('tours.provinceDetail.back')}
        </Link>
        {user && (
          <Link to="/tour-bookings" className="text-sm text-accent hover:underline">
            {t('tours.guides.viewAllBookings')}
          </Link>
        )}
      </div>

      {/* Profile header */}
      <div className="rounded-2xl border border-neutral-200 bg-white p-5 sm:p-6 flex flex-col sm:flex-row gap-5">
        <div className="shrink-0">
          <GuideAvatar name={guide.business_name} avatarUrl={guide.avatar} size={96} />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-neutral-900">{guide.business_name}</h1>
            <span className="shrink-0"><GuideWishlistHeart providerUuid={guide.uuid} /></span>
          </div>
          {guide.languages?.length > 0 && (
            <p className="text-sm text-neutral-500 mt-1">
              {t('tours.detail.languages')}: {guide.languages.join(', ')}
            </p>
          )}
          <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            {rating != null && rating > 0 ? (
              <>
                <span className="inline-flex items-center gap-1 font-semibold text-neutral-900">
                  <Star className="w-4 h-4 text-accent fill-current" />
                  {rating.toFixed(1)}
                </span>
                <span className="text-neutral-600">{getRatingLabel(rating)}</span>
                <span className="text-neutral-500">
                  {reviewCount} {t('tours.guides.reviewsCount')}
                </span>
                <span className="text-xs px-2 py-0.5 rounded-full bg-accent-100 text-accent-600 font-medium">
                  {getGuideScore(guide)} {t('tours.guides.score')}
                </span>
              </>
            ) : (
              <span className="text-neutral-500">{t('tours.reviews.empty')}</span>
            )}
          </div>
          {guide.price_from != null && (
            <p className="mt-2 text-sm">
              <span className="text-neutral-500">{t('tours.guides.priceFrom')} </span>
              <span className="font-semibold text-neutral-900">{formatPrice(Number(guide.price_from), 'VND')}</span>
              <span className="text-neutral-500"> / {t('tours.guides.perDay')}</span>
            </p>
          )}
        </div>
      </div>

      {/* Mô tả rich của guide (TipTap) — fallback text cũ */}
      {guide.bio_html ? (
        <div className="mt-6">
          <h2 className="font-semibold text-neutral-900 mb-2">{t('tours.guides.about')}</h2>
          <div
            className="rich-text rounded-2xl border border-neutral-200 bg-white p-5"
            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(guide.bio_html) }}
          />
        </div>
      ) : (
        guide.bio && (
          <div className="mt-6">
            <h2 className="font-semibold text-neutral-900 mb-2">{t('tours.guides.about')}</h2>
            <p className="text-neutral-700 leading-relaxed">{guide.bio}</p>
          </div>
        )
      )}

      {/* Đặt guide 1 ngày — ngày đi chốt từ trang tỉnh, giá theo ngày */}
      <div className="mt-6 rounded-2xl border border-neutral-200 bg-white p-5 space-y-4">
        <h2 className="font-semibold text-neutral-900 flex items-center gap-2">
          <CalendarDays className="w-5 h-5 text-accent" /> {t('tours.guides.bookDayTitle')}
        </h2>

        {hasDate ? (
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <label htmlFor="guide-travel-date" className="text-neutral-600">
              {t('tours.guides.fixedDate')}:
            </label>
            <input
              id="guide-travel-date"
              type="date"
              value={draftDate}
              min={todayISO}
              onChange={(e) => setDraftDate(e.target.value)}
              className="rounded-xl border border-neutral-200 px-3 py-1.5 text-sm text-neutral-900"
            />
            <button
              type="button"
              onClick={handleDateUpdate}
              disabled={!draftDate || draftDate === travelDate}
              className="px-3 py-1.5 rounded-xl border border-accent text-accent-600 text-sm font-medium hover:bg-accent-100 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {t('tours.guides.updateDate')}
            </button>
            <Link to={backTo} className="text-accent hover:underline">
              {t('tours.guides.changeDate')}
            </Link>
          </div>
        ) : (
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <label htmlFor="guide-travel-date" className="text-neutral-600">
              {t('tours.guides.travelDateLabel')}:
            </label>
            <input
              id="guide-travel-date"
              type="date"
              value={draftDate}
              min={todayISO}
              placeholder={t('tours.guides.newDatePlaceholder')}
              onChange={(e) => setDraftDate(e.target.value)}
              className="rounded-xl border border-neutral-200 px-3 py-1.5 text-sm text-neutral-900"
            />
            <button
              type="button"
              onClick={handleDateUpdate}
              disabled={!draftDate}
              className="px-3 py-1.5 rounded-xl border border-accent text-accent-600 text-sm font-medium hover:bg-accent-100 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {t('tours.guides.updateDate')}
            </button>
            <span className="text-neutral-600">
              {t('tours.guides.noDateHint')}{' '}
              <Link to={backTo} className="text-accent font-medium hover:underline">
                {t('tours.guides.backToProvince')}
              </Link>
            </span>
          </div>
        )}

        {hasDate && slotsLoading && <Loader2 className="w-5 h-5 animate-spin text-accent" />}
        {hasDate && slotsError && <ErrorMessage message={t('tours.slots.couldNotLoad')} onRetry={() => refetchSlots()} />}
        {hasDate && !slotsLoading && !slotsError && !isFree && (
          <p className="text-sm text-neutral-500">{t('tours.guides.noSlots')}</p>
        )}

        <div className="space-y-3 border-t border-neutral-100 pt-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">{t('tours.wizard.coupon')}</label>
            <input
              value={couponCode}
              onChange={(e) => setCouponCode(e.target.value)}
              placeholder={t('tours.wizard.couponPlaceholder')}
              className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">{t('tours.wizard.meetingPoint')}</label>
            <input
              value={meetingPoint}
              onChange={(e) => setMeetingPoint(e.target.value)}
              placeholder={meetingPlaceholder}
              className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">{t('tours.wizard.notes')}</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              className="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm"
            />
          </div>
        </div>

        <div className="rounded-xl bg-neutral-50 border border-neutral-200 p-4" data-testid="guide-estimate">
          {!hasDate && <p className="text-sm text-neutral-500">{t('tours.guides.noDateHint')}</p>}
          {hasDate && previewLoading && <Loader2 className="w-5 h-5 animate-spin text-accent" />}
          {hasDate && breakdown && !previewLoading && (
            <>
              <TourPriceSummary
                subtotal={breakdown.subtotal ?? 0}
                transport={breakdown.add_on_amount ?? 0}
                discount={breakdown.discount ?? 0}
                tax={breakdown.tax ?? 0}
                total={breakdown.total}
                currency={breakdown.currency || 'VND'}
                labels={breakdown.tax_name ? { tax: breakdown.tax_name } : undefined}
              />
              {!user && (
                <p className="text-xs text-neutral-500 mt-2">{t('tours.guides.loginToBook')}</p>
              )}
            </>
          )}
        </div>

        {submitError && <ErrorMessage message={submitError} />}

        <button
          type="button"
          onClick={confirmBooking}
          disabled={!hasDate || !isFree || submitting || (user && !breakdown)}
          className="w-full py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {submitting && <Loader2 className="w-5 h-5 animate-spin" />}
          {user ? t('tours.detail.bookNow') : t('tours.guides.loginToBook')}
        </button>
      </div>

      {/* Đánh giá read-only — viết review ở trang tour-bookings sau khi đi tour */}
      <div className="mt-6 space-y-4">
        <h2 className="font-semibold text-neutral-900">{t('tours.reviews.title')}</h2>
        <ReviewList providerUuid={uuid} />
      </div>

      {/* Spacer so the mobile sticky CTA never covers content */}
      <div className="h-20 lg:hidden" aria-hidden="true" />

      {/* Sticky CTA (mobile) — price + book action stay visible while scrolling */}
      <div
        className="fixed bottom-0 inset-x-0 z-40 lg:hidden border-t border-neutral-200 bg-white/95 backdrop-blur px-4 py-3"
        data-testid="guide-sticky-cta"
      >
        <div className="flex items-center gap-3 max-w-3xl mx-auto">
          <div className="flex-1 min-w-0">
            <p className="text-xs text-neutral-500">{t('tours.guides.priceFrom')}</p>
            <p className="font-bold text-neutral-900 truncate">
              {guide.price_from != null ? `${formatPrice(Number(guide.price_from), 'VND')} / ${t('tours.guides.perDay')}` : t('tours.guides.bookDayTitle')}
            </p>
          </div>
          <button
            type="button"
            onClick={confirmBooking}
            disabled={!hasDate || !isFree || submitting || (user && !breakdown)}
            className="px-6 py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
          >
            {user ? t('tours.detail.bookNow') : t('tours.guides.loginToBook')}
          </button>
        </div>
      </div>
    </div>
  );
}
