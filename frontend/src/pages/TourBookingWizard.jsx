import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Loader2, AlertTriangle } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import ErrorMessage from '../components/ErrorMessage';
import { Stepper } from '../components/Stepper';
import { QuantityStepper } from '../components/QuantityStepper';
import { TourPriceSummary } from '../components/TourPriceSummary';
import { TourSlotCalendar } from '../components/TourSlotCalendar';
import { addDaysISO, computeTourEnd, getMissingAvailableDays } from '../lib/tourSearch';
import { cn } from '../lib/utils';

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

  const storageKey = uuid ? `tourWizard:returnState:${uuid}` : null;
  useEffect(() => {
    if (!storageKey) return;
    try {
      const raw = sessionStorage.getItem(storageKey);
      if (raw) {
        const saved = JSON.parse(raw);
        if (typeof saved.pricingMode === 'string') setPricingMode(saved.pricingMode);
        if (typeof saved.duration === 'number') setDuration(saved.duration);
        if (typeof saved.couponCode === 'string') setCouponCode(saved.couponCode);
        if (typeof saved.meetingPoint === 'string') setMeetingPoint(saved.meetingPoint);
        if (typeof saved.notes === 'string') setNotes(saved.notes);
        if (typeof saved.calendarOpen === 'boolean') setCalendarOpen(saved.calendarOpen);
        sessionStorage.removeItem(storageKey);
      }
    } catch {
      /* ignore */
    }
  }, [storageKey]);

  const initialSlotId = searchParams.get('slot_id');
  // Calendar is collapsed once a slot is chosen (deep-link or picked);
  // it re-opens via "Change slot", or stays open when nothing is selected.
  const [calendarOpen, setCalendarOpen] = useState(!initialSlotId);

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
  const expectedEnd = computeTourEnd(activeSlot, pricingMode, duration);

  // Day-mode multi-day warning: every following day of the trip needs a free
  // slot, otherwise the booking will fail at confirm time.
  const rangeEnd = activeSlot && pricingMode === 'day' && Number(duration) > 1
    ? addDaysISO(activeSlot.date, Number(duration) - 1)
    : null;
  const { data: rangeData } = useQuery({
    queryKey: ['tour-availability', uuid, 'day-range', activeSlot?.date, rangeEnd],
    queryFn: async () => {
      const res = await api.get(`/tours/${uuid}/availability`, {
        params: { from: activeSlot.date, to: rangeEnd },
      });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed');
      return res.data;
    },
    enabled: !!uuid && !!activeSlot && !!rangeEnd,
    staleTime: 30_000,
    retry: false,
  });
  const missingDays = rangeEnd
    ? getMissingAvailableDays(rangeData?.data, activeSlot.date, rangeEnd)
    : [];
  const wizardSteps = [
    t('tours.wizard.steps.slot'),
    t('tours.wizard.steps.details'),
    t('tours.wizard.steps.payment'),
  ];
  const showCalendar = calendarOpen || !activeSlot;

  if (!user) {
    return (
      <div className="py-12 max-w-md mx-auto text-center">
        <div className="rounded-2xl border border-neutral-200 bg-white p-8">
          <h2 className="text-xl font-semibold mb-2">{t('tours.wizard.loginRequired')}</h2>
          <p className="text-neutral-600 mb-6">{t('tours.wizard.loginRequiredHint')}</p>
          <Link
            to={`/login?redirect=${encodeURIComponent(`/tours/book/${uuid}${(activeSlot?.id || initialSlotId) ? `?slot_id=${activeSlot?.id || initialSlotId}` : ''}`)}`}
            onClick={() => {
              if (!storageKey) return;
              try {
                sessionStorage.setItem(storageKey, JSON.stringify({
                  pricingMode,
                  duration,
                  couponCode,
                  meetingPoint,
                  notes,
                  calendarOpen,
                }));
              } catch {
                /* ignore */
              }
            }}
            className="inline-flex px-6 py-3 rounded-xl bg-accent text-white font-medium hover:bg-accent-600"
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
        <Loader2 className="w-8 h-8 animate-spin text-accent" />
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
      <Link
        to={`/tours/${uuid}`}
        className="inline-flex items-center gap-1.5 text-sm text-neutral-600 hover:text-accent mb-4"
      >
        <ArrowLeft className="w-4 h-4" /> {t('tours.wizard.backToTour')}
      </Link>
      <h1 className="text-2xl sm:text-3xl font-bold text-neutral-900 mb-1">{t('tours.wizard.title')}</h1>
      <p className="text-neutral-600 mb-4">{tour.title} · {tour.province?.name}</p>
      <Stepper steps={wizardSteps} current={activeSlot ? 1 : 0} ariaLabel={t('tours.wizard.stepsAria')} className="mb-6" />

      <div className="space-y-5">
        <div className="rounded-2xl border border-neutral-200 bg-white p-5">
          <div className="flex items-center justify-between gap-2 mb-3">
            <h2 className="font-semibold text-neutral-900">{t('tours.wizard.selectedSlot')}</h2>
            {activeSlot && (
              <button
                type="button"
                onClick={() => setCalendarOpen((o) => !o)}
                className="text-sm font-medium text-accent hover:underline"
              >
                {t('tours.wizard.changeSlot')}
              </button>
            )}
          </div>
          {activeSlot ? (
            <p className="text-sm text-neutral-700" data-testid="selected-slot">
              <span className="font-semibold text-neutral-900">{activeSlot.date}</span>
              {' · '}{activeSlot.start_time}–{activeSlot.end_time}
            </p>
          ) : (
            <p className="text-sm text-neutral-500">{t('tours.wizard.noSlotSelected')}</p>
          )}
        </div>

        {showCalendar && (
          <TourSlotCalendar
            tourUuid={uuid}
            selectedSlotId={activeSlot?.id}
            onSelect={(s) => {
              setSlot(s);
              setCalendarOpen(false);
            }}
          />
        )}

        <div className="rounded-2xl border border-neutral-200 bg-white p-5">
          <h2 className="font-semibold text-neutral-900 mb-3">{t('tours.wizard.duration')}</h2>
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
                  pricingMode === m ? 'border-accent bg-accent-100' : 'border-neutral-200 hover:bg-neutral-50'
                )}
              >
                {t(`tours.wizard.${m}`)}
              </button>
            ))}
          </div>
          <QuantityStepper
            value={Number(duration) || 1}
            onChange={setDuration}
            min={1}
            max={pricingMode === 'hour' ? 12 : 30}
            ariaLabel={t('tours.wizard.durationValue')}
            decreaseLabel={t('tours.wizard.decreaseDuration')}
            increaseLabel={t('tours.wizard.increaseDuration')}
          />
          {expectedEnd && (
            <p className="text-sm text-neutral-700 mt-3" data-testid="expected-end">
              {t('tours.wizard.expectedEnd')}: {expectedEnd.date} · {expectedEnd.time}
            </p>
          )}
          {missingDays.length > 0 && (
            <p role="alert" data-testid="day-range-warning" className="mt-3 flex gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
              <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
              <span>{t('tours.wizard.dayRangeWarning', { dates: missingDays.join(', ') })}</span>
            </p>
          )}
        </div>

        <div className="rounded-2xl border border-neutral-200 bg-white p-5 space-y-3">
          <h2 className="font-semibold text-neutral-900">{t('tours.wizard.details')}</h2>
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
              placeholder={tour.meeting_point || t('tours.wizard.meetingPointPlaceholder')}
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

        <div className="rounded-2xl border border-neutral-200 bg-white p-5">
          <h2 className="font-semibold text-neutral-900 mb-3">{t('tours.wizard.price')}</h2>
          {previewLoading && <Loader2 className="w-5 h-5 animate-spin text-accent" />}
          {breakdown && !previewLoading && (
            <TourPriceSummary
              subtotal={breakdown.subtotal ?? 0}
              transport={breakdown.add_on_amount ?? 0}
              discount={breakdown.discount ?? 0}
              tax={breakdown.tax ?? 0}
              total={breakdown.total}
              currency={breakdown.currency || 'VND'}
              labels={breakdown.tax_name ? { tax: breakdown.tax_name } : undefined}
            />
          )}
          {!breakdown && !previewLoading && (
            <p className="text-sm text-neutral-500">{activeSlot ? t('tours.wizard.previewHint') : t('tours.detail.pickSlotHint')}</p>
          )}
        </div>

        {submitError && <ErrorMessage message={submitError} />}

        <button
          type="button"
          onClick={confirmBooking}
          disabled={!breakdown || submitting}
          className="w-full py-3 rounded-xl bg-accent text-white font-semibold hover:bg-accent-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {submitting && <Loader2 className="w-5 h-5 animate-spin" />}
          {t('tours.wizard.confirm')}
        </button>
      </div>
    </div>
  );
}
