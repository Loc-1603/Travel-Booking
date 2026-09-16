import { useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, CalendarDays, Loader2, Star } from 'lucide-react';
import DOMPurify from 'dompurify';
import { api } from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import GuideAvatar from '../components/GuideAvatar';
import { HotelDetailSkeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { TourReviewList } from '../components/TourReviewList';
import { formatPrice, getRatingLabel } from '../lib/utils';
import { getGuideScore } from '../lib/guideSearch';

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
  const [searchParams] = useSearchParams();
  const provinceSlug = searchParams.get('province') || '';
  // Ngày đi + sort đã chọn từ trang tỉnh — read-only, không cho sửa ở đây.
  // backTo khôi phục ?date=&sort= để giữ nguyên trạng thái lọc khi quay lại.
  const travelDate = searchParams.get('date') || '';
  const backSort = searchParams.get('sort') || '';
  const hasDate = !!travelDate;

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

  // Xem trước giá 1 ngày (cần đăng nhập). Không gửi slot_id —
  // backend tự resolve slot trống của ngày đó.
  const previewPayload = useMemo(() => {
    if (!user || !hasDate) return null;
    return {
      provider_uuid: uuid,
      travel_date: travelDate,
      ...(couponCode.trim() && { coupon_code: couponCode.trim() }),
    };
  }, [user, hasDate, uuid, travelDate, couponCode]);
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
              <span className="text-[#7a756d]"> / {t('tours.guides.perDay')}</span>
            </p>
          )}
        </div>
      </div>

      {/* Mô tả rich của guide (TipTap) — fallback text cũ */}
      {guide.bio_html ? (
        <div className="mt-6">
          <h2 className="font-semibold text-[#1a1a1a] mb-2">{t('tours.guides.about')}</h2>
          <div
            className="rich-text rounded-2xl border border-[#e8e4dd] bg-white p-5"
            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(guide.bio_html) }}
          />
        </div>
      ) : (
        guide.bio && (
          <div className="mt-6">
            <h2 className="font-semibold text-[#1a1a1a] mb-2">{t('tours.guides.about')}</h2>
            <p className="text-[#45423d] leading-relaxed">{guide.bio}</p>
          </div>
        )
      )}

      {/* Đặt guide 1 ngày — ngày đi chốt từ trang tỉnh, giá theo ngày */}
      <div className="mt-6 rounded-2xl border border-[#e8e4dd] bg-white p-5 space-y-4">
        <h2 className="font-semibold text-[#1a1a1a] flex items-center gap-2">
          <CalendarDays className="w-5 h-5 text-[#b8860b]" /> {t('tours.guides.bookDayTitle')}
        </h2>

        {hasDate ? (
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="text-[#5c5852]">{t('tours.guides.fixedDate')}:</span>
            <span className="font-semibold text-[#1a1a1a]">{travelDate}</span>
            <Link to={backTo} className="text-[#b8860b] hover:underline">
              {t('tours.guides.changeDate')}
            </Link>
          </div>
        ) : (
          <div className="rounded-xl border border-dashed border-[#e8e4dd] bg-[#faf8f5] p-4 text-sm text-[#5c5852]">
            {t('tours.guides.noDateHint')}{' '}
            <Link to={backTo} className="text-[#b8860b] font-medium hover:underline">
              {t('tours.guides.backToProvince')}
            </Link>
          </div>
        )}

        {hasDate && slotsLoading && <Loader2 className="w-5 h-5 animate-spin text-[#b8860b]" />}
        {hasDate && slotsError && <ErrorMessage message={t('tours.slots.couldNotLoad')} onRetry={() => refetchSlots()} />}
        {hasDate && !slotsLoading && !slotsError && !isFree && (
          <p className="text-sm text-[#7a756d]">{t('tours.guides.noSlots')}</p>
        )}

        <div className="space-y-3 border-t border-[#f0ede8] pt-4">
          <div>
            <label className="block text-sm font-medium text-[#45423d] mb-1">{t('tours.wizard.coupon')}</label>
            <input
              value={couponCode}
              onChange={(e) => setCouponCode(e.target.value)}
              placeholder={t('tours.wizard.couponPlaceholder')}
              className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[#45423d] mb-1">{t('tours.wizard.meetingPoint')}</label>
            <input
              value={meetingPoint}
              onChange={(e) => setMeetingPoint(e.target.value)}
              placeholder={meetingPlaceholder}
              className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[#45423d] mb-1">{t('tours.wizard.notes')}</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            />
          </div>
        </div>

        <div className="rounded-xl bg-[#faf8f5] border border-[#e8e4dd] p-4">
          {!user && <p className="text-sm text-[#7a756d]">{t('tours.guides.loginToSeePrice')}</p>}
          {user && !hasDate && <p className="text-sm text-[#7a756d]">{t('tours.guides.noDateHint')}</p>}
          {user && hasDate && previewLoading && <Loader2 className="w-5 h-5 animate-spin text-[#b8860b]" />}
          {user && hasDate && breakdown && !previewLoading && (
            <div className="space-y-1.5 text-sm">
              <div className="flex justify-between">
                <span className="text-[#5c5852]">{t('tours.wizard.subtotal')}</span>
                <span>{formatPrice(breakdown.subtotal, breakdown.currency)}</span>
              </div>
              {(breakdown.add_on_amount ?? 0) > 0 && (
                <div className="flex justify-between">
                  <span className="text-[#5c5852]">{t('tours.wizard.transport')}</span>
                  <span>{formatPrice(breakdown.add_on_amount, breakdown.currency)}</span>
                </div>
              )}
              {(breakdown.discount ?? 0) > 0 && (
                <div className="flex justify-between text-green-600">
                  <span>{t('tours.wizard.discount')}</span>
                  <span>-{formatPrice(breakdown.discount, breakdown.currency)}</span>
                </div>
              )}
              {(breakdown.tax ?? 0) > 0 && (
                <div className="flex justify-between">
                  <span className="text-[#5c5852]">{breakdown.tax_name || 'VAT'}</span>
                  <span>{formatPrice(breakdown.tax, breakdown.currency)}</span>
                </div>
              )}
              <div className="flex justify-between font-semibold text-base pt-2 border-t border-[#e8e4dd]">
                <span>{t('tours.wizard.total')}</span>
                <span>{formatPrice(breakdown.total, breakdown.currency)}</span>
              </div>
            </div>
          )}
        </div>

        {submitError && <ErrorMessage message={submitError} />}

        <button
          type="button"
          onClick={confirmBooking}
          disabled={!hasDate || !isFree || submitting || (user && !breakdown)}
          className="w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {submitting && <Loader2 className="w-5 h-5 animate-spin" />}
          {user ? t('tours.detail.bookNow') : t('tours.guides.loginToBook')}
        </button>
      </div>

      {/* Đánh giá read-only — viết review ở trang tour-bookings sau khi đi tour */}
      <div className="mt-6 space-y-4">
        <h2 className="font-semibold text-[#1a1a1a]">{t('tours.reviews.title')}</h2>
        <TourReviewList providerUuid={uuid} />
      </div>
    </div>
  );
}
