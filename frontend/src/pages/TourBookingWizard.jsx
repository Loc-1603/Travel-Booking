import { useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Loader2 } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import ErrorMessage from '../components/ErrorMessage';
import { TourSlotCalendar } from '../components/TourSlotCalendar';
import { formatPrice, cn } from '../lib/utils';

export default function TourBookingWizard() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { uuid } = useParams();
  const [searchParams] = useSearchParams();
  const [slot, setSlot] = useState(null);
  const [pricingMode, setPricingMode] = useState('hour');
  const [duration, setDuration] = useState(4);
  const [couponCode, setCouponCode] = useState('');
  const [meetingPoint, setMeetingPoint] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  const initialSlotId = searchParams.get('slot_id');

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour', uuid],
    queryFn: async () => {
      const res = await api.get(`/tours/${uuid}`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed');
      return res.data;
    },
    enabled: !!uuid,
  });
  const tour = data?.data ?? data;

  // Prefetch the pre-selected slot so price preview works immediately.
  const { data: availData } = useQuery({
    queryKey: ['tour-availability', uuid, 'wizard'],
    queryFn: async () => {
      const today = new Date().toISOString().split('T')[0];
      const res = await api.get(`/tours/${uuid}/availability`, { params: { from: today } });
      return res.data;
    },
    enabled: !!uuid && !!initialSlotId && !slot,
  });
  const preselected = useMemo(() => {
    if (slot || !initialSlotId || !availData?.data) return slot;
    return availData.data.find((s) => String(s.id) === String(initialSlotId)) || null;
  }, [slot, initialSlotId, availData]);
  const activeSlot = slot || preselected;

  const previewPayload = useMemo(() => {
    if (!tour || !activeSlot) return null;
    return {
      tour_id: tour.id,
      slot_id: activeSlot.id,
      pricing_mode: pricingMode,
      duration_value: Number(duration),
      ...(couponCode.trim() && { coupon_code: couponCode.trim() }),
    };
  }, [tour, activeSlot, pricingMode, duration, couponCode]);

  const { data: previewData, isFetching: previewLoading } = useQuery({
    queryKey: ['tour-booking-preview', tour?.id, activeSlot?.id, pricingMode, duration, couponCode.trim()],
    queryFn: async () => {
      const res = await api.post('/tour-bookings/preview', previewPayload);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to preview');
      return res.data;
    },
    enabled: !!previewPayload && !!user && Number(duration) >= 1,
    staleTime: 30_000,
    refetchOnWindowFocus: false,
    retry: false,
  });
  const breakdown = previewData?.data;

  if (!user) {
    return (
      <div className="py-12 max-w-md mx-auto text-center">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8">
          <h2 className="text-xl font-semibold mb-2">{t('tours.wizard.loginRequired')}</h2>
          <p className="text-[#5c5852] mb-6">{t('tours.wizard.loginRequiredHint')}</p>
          <Link
            to={`/login?redirect=${encodeURIComponent(`/tours/book/${uuid}${initialSlotId ? `?slot_id=${initialSlotId}` : ''}`)}`}
            className="inline-flex px-6 py-3 rounded-xl bg-[#b8860b] text-white font-medium hover:bg-[#996f09]"
          >
            {t('auth.login.submit')}
          </Link>
        </div>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="py-12 flex items-center justify-center">
        <Loader2 className="w-8 h-8 animate-spin text-[#b8860b]" />
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

  const confirmBooking = async () => {
    if (!previewPayload) return;
    setSubmitting(true);
    setSubmitError(null);
    try {
      const res = await api.post('/tour-bookings', {
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
    <div className="py-6 max-w-3xl mx-auto">
      <h1 className="text-2xl sm:text-3xl font-bold text-[#1a1a1a] mb-1">{t('tours.wizard.title')}</h1>
      <p className="text-[#5c5852] mb-6">{tour.title} · {tour.province?.name}</p>

      <div className="space-y-5">
        <TourSlotCalendar tourUuid={uuid} selectedSlotId={activeSlot?.id} onSelect={setSlot} />

        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5">
          <h2 className="font-semibold text-[#1a1a1a] mb-3">{t('tours.wizard.duration')}</h2>
          <div className="flex gap-2 mb-3">
            {['hour', 'day'].map((m) => (
              <button
                key={m}
                type="button"
                onClick={() => {
                  setPricingMode(m);
                  setDuration(m === 'hour' ? 4 : 1);
                }}
                className={cn(
                  'px-4 py-2 rounded-xl border text-sm font-medium',
                  pricingMode === m ? 'border-[#b8860b] bg-[#f9edd1]' : 'border-[#e8e4dd] hover:bg-[#faf8f5]'
                )}
              >
                {t(`tours.wizard.${m}`)}
              </button>
            ))}
          </div>
          <input
            type="number"
            min={1}
            max={pricingMode === 'hour' ? 12 : 30}
            value={duration}
            onChange={(e) => setDuration(e.target.value)}
            className="w-32 rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
          />
        </div>

        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5 space-y-3">
          <h2 className="font-semibold text-[#1a1a1a]">{t('tours.wizard.details')}</h2>
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
              placeholder={tour.meeting_point || t('tours.wizard.meetingPointPlaceholder')}
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

        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5">
          <h2 className="font-semibold text-[#1a1a1a] mb-3">{t('tours.wizard.price')}</h2>
          {previewLoading && <Loader2 className="w-5 h-5 animate-spin text-[#b8860b]" />}
          {breakdown && !previewLoading && (
            <div className="space-y-1.5 text-sm">
              <div className="flex justify-between"><span className="text-[#5c5852]">{t('tours.wizard.subtotal')}</span><span>{formatPrice(breakdown.subtotal, breakdown.currency)}</span></div>
              {(breakdown.add_on_amount ?? 0) > 0 && (
                <div className="flex justify-between"><span className="text-[#5c5852]">{t('tours.wizard.transport')}</span><span>{formatPrice(breakdown.add_on_amount, breakdown.currency)}</span></div>
              )}
              {(breakdown.discount ?? 0) > 0 && (
                <div className="flex justify-between text-green-600"><span>{t('tours.wizard.discount')}</span><span>-{formatPrice(breakdown.discount, breakdown.currency)}</span></div>
              )}
              {(breakdown.tax ?? 0) > 0 && (
                <div className="flex justify-between"><span className="text-[#5c5852]">{breakdown.tax_name || 'VAT'}</span><span>{formatPrice(breakdown.tax, breakdown.currency)}</span></div>
              )}
              <div className="flex justify-between font-semibold text-base pt-2 border-t border-[#e8e4dd]">
                <span>{t('tours.wizard.total')}</span><span>{formatPrice(breakdown.total, breakdown.currency)}</span>
              </div>
            </div>
          )}
          {!breakdown && !previewLoading && (
            <p className="text-sm text-[#7a756d]">{activeSlot ? t('tours.wizard.previewHint') : t('tours.detail.pickSlotHint')}</p>
          )}
        </div>

        {submitError && <ErrorMessage message={submitError} />}

        <button
          type="button"
          onClick={confirmBooking}
          disabled={!breakdown || submitting}
          className="w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {submitting && <Loader2 className="w-5 h-5 animate-spin" />}
          {t('tours.wizard.confirm')}
        </button>
      </div>
    </div>
  );
}
