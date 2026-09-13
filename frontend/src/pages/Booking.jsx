import { useMemo, useState } from 'react';
import { useSearchParams, useNavigate, useLocation, Link } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { ChevronRight, MapPin } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import ErrorMessage from '../components/ErrorMessage';
import { HotelDetailSkeleton } from '../components/Skeleton';
import { AmenityIcon } from '../components/AmenityIcon';
import { formatPrice, formatDate, calculateNights } from '../lib/utils';
import { cn } from '../lib/utils';
import { useTranslation } from 'react-i18next';

const STEPS = [
  { id: 1, label: 'booking.steps.room' },
  { id: 2, label: 'booking.steps.guestDetails' },
  { id: 3, label: 'booking.steps.review' },
];

function BookingSkeleton() {
  return (
    <div className="py-6 max-w-2xl">
      <div className="h-8 w-48 bg-stone-200 rounded animate-pulse mb-6" />
      <div className="space-y-4">
        <div className="h-32 bg-stone-200 rounded-xl animate-pulse" />
        <div className="h-24 bg-stone-200 rounded-xl animate-pulse" />
        <div className="h-24 bg-stone-200 rounded-xl animate-pulse" />
      </div>
    </div>
  );
}

export default function Booking() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const isGuest = !user;
  const [loginGate, setLoginGate] = useState(false);
  const bookingRedirect = encodeURIComponent(location.pathname + location.search);
  const hotelId = searchParams.get('hotel_id') || searchParams.get('hotelId');
  const roomId = searchParams.get('room_id');
  const checkIn = searchParams.get('check_in');
  const checkOut = searchParams.get('check_out');
  const quantityParam = searchParams.get('quantity');
  const [step, setStep] = useState(1);
  const [quantity, setQuantity] = useState(quantityParam ? Math.max(1, parseInt(quantityParam, 10) || 1) : 1);
  const [couponCode, setCouponCode] = useState('');
  const [lateCheckout, setLateCheckout] = useState(false);
  const [specialRequests, setSpecialRequests] = useState('');

  const nights = checkIn && checkOut ? calculateNights(checkIn, checkOut) : 0;

  const { data: hotelData, isLoading: hotelLoading, isError: hotelError, refetch: refetchHotel } = useQuery({
    queryKey: ['hotel', hotelId],
    queryFn: async () => {
      const res = await api.get(`/hotels/${hotelId}`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load');
      return res.data;
    },
    enabled: !!hotelId,
  });

  const previewPayload = useMemo(() => {
    const base = {
      hotel_id: Number(hotelId),
      check_in: checkIn,
      check_out: checkOut,
      rooms: [{ room_id: Number(roomId), quantity }],
      coupon_code: couponCode.trim() || undefined,
      late_checkout: lateCheckout,
      currency: 'VND',
    };
    return base;
  }, [hotelId, roomId, checkIn, checkOut, quantity, couponCode, lateCheckout]);

  const canFetchPreview = hotelId && roomId && checkIn && checkOut && quantity >= 1;

  const { data: previewData, isLoading: previewLoading } = useQuery({
    queryKey: [
      'booking-preview',
      'auth',
      hotelId,
      roomId,
      checkIn,
      checkOut,
      quantity,
      couponCode.trim(),
      lateCheckout,
    ],
    queryFn: async () => {
      const res = await api.post('/bookings/preview', previewPayload);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to preview');
      return res.data;
    },
    enabled: !!user && canFetchPreview && step >= 3,
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });

  const createBooking = useMutation({
    mutationFn: async () => {
      const payload = {
        hotel_id: Number(hotelId),
        check_in: checkIn,
        check_out: checkOut,
        rooms: [{ room_id: Number(roomId), quantity }],
        currency: 'VND',
      };
      if (couponCode.trim()) payload.coupon_code = couponCode.trim();
      if (lateCheckout) payload.late_checkout = true;
      const res = await api.post('/bookings', payload);
      return res.data;
    },
    onSuccess: (data) => {
      const payload = data?.data ?? data;
      const booking = payload?.booking ?? payload;
      const uuid = booking?.uuid;
      if (uuid) {
        navigate(`/checkout/${uuid}`, {
          state: {
            booking,
          },
        });
      } else {
        navigate('/profile');
      }
    },
  });

  const handleSubmitBooking = (e) => {
    e.preventDefault();
    if (!user) {
      setLoginGate(true);
      return;
    }
    setLoginGate(false);
    createBooking.mutate();
  };

  const hotel = hotelData?.data ?? hotelData;
  const room = hotel?.rooms?.find((r) => String(r.id) === String(roomId));
  const breakdown = previewData?.data ?? previewData;

  const estimatedSubtotal = room && nights ? Number(room.base_price) * nights * quantity : 0;

  if (!hotelId || !roomId || !checkIn || !checkOut) {
    return (
      <div className="py-6">
        <ErrorMessage message={t('booking.errors.missingParams')} />
      </div>
    );
  }

  if (hotelLoading) return <BookingSkeleton />;
  if (hotelError || !hotel) {
    return (
      <div className="py-6">
        <ErrorMessage message={t('booking.errors.hotelNotFound')} onRetry={() => refetchHotel()} />
      </div>
    );
  }
  if (!room) {
    return (
      <div className="py-6">
        <ErrorMessage message={t('booking.errors.roomNotFound')} />
      </div>
    );
  }

  const canProceedStep2 = true;
  const canProceedStep3 = true;

  const loginGatePanel = (
    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-6 text-center">
      <p className="text-sm text-[#7a4a00] mb-4">{t('hotelDetail.booking.loginRequired')}</p>
      <div className="flex gap-2">
        <Link
          to={`/login?redirect=${bookingRedirect}`}
          className="flex-1 py-2.5 rounded-xl bg-[#b8860b] text-white font-semibold text-center hover:bg-[#996f09] transition-colors"
        >
          {t('common.login', 'Đăng nhập')}
        </Link>
        <Link
          to={`/register?redirect=${bookingRedirect}`}
          className="flex-1 py-2.5 rounded-xl border border-[#b8860b] text-[#b8860b] font-semibold text-center hover:bg-[#fff7e6] transition-colors"
        >
          {t('common.register', 'Đăng ký')}
        </Link>
      </div>
    </div>
  );

  return (
    <div className="py-6 max-w-3xl mx-auto">
      <h1 className="text-2xl sm:text-3xl font-bold text-stone-900 mb-6">{t('booking.title')}</h1>

      {/* Step indicator */}
      <nav className="flex items-center gap-2 mb-8" aria-label={t('booking.steps.ariaLabel')}>
        {STEPS.map((s, i) => (
          <div key={s.id} className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setStep(s.id)}
              className={cn(
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                step === s.id ? 'bg-amber-100 text-amber-800' : 'text-stone-600 hover:text-stone-900'
              )}
            >
              <span className="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold bg-amber-200 text-amber-900">
                {s.id}
              </span>
              {t(s.label)}
            </button>
            {i < STEPS.length - 1 && <ChevronRight className="w-4 h-4 text-stone-400" />}
          </div>
        ))}
      </nav>

      {/* Step 1: Room */}
      {step === 1 && (
        <section className="space-y-6">
          <h2 className="text-lg font-semibold text-stone-900">{t('booking.step1.title')}</h2>
          <div className="rounded-2xl border border-stone-200/80 bg-white shadow-sm p-4 sm:p-6">
            <div className="flex flex-col sm:flex-row gap-4">
              <div className="sm:w-40 shrink-0 aspect-[4/3] sm:aspect-square rounded-xl overflow-hidden bg-stone-200">
                {(room.images?.[0] || room.banner_image)?.url ? (
                  <img src={(room.images?.[0] || room.banner_image).url} alt={room.name} className="w-full h-full object-cover" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-stone-400">
                    <MapPin className="w-10 h-10" />
                  </div>
                )}
              </div>
              <div className="flex-1">
                <h3 className="font-semibold text-stone-900">{room.name}</h3>
                <p className="text-sm text-stone-600 mt-0.5">{hotel.name} · {[hotel.city, hotel.country].filter(Boolean).join(', ')}</p>
                <p className="text-sm text-stone-600 mt-0.5">{formatDate(checkIn)} – {formatDate(checkOut)} · {nights} {t('common.nights', { count: nights })}</p>
                {room.amenities?.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {room.amenities.slice(0, 4).map((a) => (
                      <span key={a.id} className="inline-flex items-center gap-1 text-stone-500 text-sm" title={a.name}>
                        <AmenityIcon slug={a.slug} className="w-3.5 h-3.5" />
                      </span>
                    ))}
                  </div>
                )}
                <div className="mt-4">
                  <label className="block text-sm font-medium text-stone-700 mb-1">{t('booking.step1.quantity')}</label>
                  <select
                    value={quantity}
                    onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value, 10) || 1))}
                    className="w-24 rounded-xl border border-stone-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                  >
                    {Array.from({ length: Math.min(room.total_rooms || 5, 5) }, (_, i) => i + 1).map((n) => (
                      <option key={n} value={n}>{n} {t('hotelDetail.booking.rooms', { count: n })}</option>
                    ))}
                  </select>
                </div>
                <p className="mt-2 font-semibold text-stone-900">{t('booking.step1.fromTotal', { price: formatPrice(Number(room.base_price) * nights * quantity) })}</p>
              </div>
            </div>
          </div>
          <div className="flex justify-end">
            <button
              type="button"
              onClick={() => setStep(2)}
              className="px-6 py-3 rounded-xl bg-amber-500 text-white font-semibold hover:bg-amber-600 transition-colors"
            >
              {t('common.continue')}
            </button>
          </div>
        </section>
      )}

      {/* Step 2: Contact / account */}
      {step === 2 && (
        <section className="space-y-6">
          <h2 className="text-lg font-semibold text-stone-900">{t('booking.step2.title')}</h2>
          {isGuest ? (
            loginGatePanel
          ) : (
            <div className="rounded-2xl border border-stone-200/80 bg-white shadow-sm p-4 sm:p-6 space-y-4">
              <div className="p-4 rounded-xl bg-amber-50/50 border border-amber-200/60">
                <p className="text-sm font-medium text-stone-700">{t('booking.step2.bookingAs')}</p>
                <p className="text-stone-900 font-semibold">{user.name}</p>
                <p className="text-sm text-stone-600">{user.email}</p>
              </div>
            </div>
          )}
          <div className="flex justify-between">
            <button type="button" onClick={() => setStep(1)} className="px-4 py-2 text-stone-600 hover:text-stone-900">
              {t('common.back')}
            </button>
            <button
              type="button"
              onClick={() => setStep(3)}
              disabled={!canProceedStep2}
              className="px-6 py-3 rounded-xl bg-amber-500 text-white font-semibold hover:bg-amber-600 disabled:opacity-50 transition-colors"
            >
              {t('common.continue')}
            </button>
          </div>
        </section>
      )}

      {/* Step 3: Review */}
      {step === 3 && (
        <section className="space-y-6">
          <h2 className="text-lg font-semibold text-stone-900">{t('booking.step3.title')}</h2>
          <div className="rounded-2xl border border-stone-200/80 bg-white shadow-sm p-4 sm:p-6 space-y-4">
            <div className="flex gap-4">
              <div className="w-20 shrink-0 aspect-[4/3] rounded-lg overflow-hidden bg-stone-200">
                {(room.images?.[0] || room.banner_image)?.url ? (
                  <img src={(room.images?.[0] || room.banner_image).url} alt={room.name} className="w-full h-full object-cover" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-stone-400"><MapPin className="w-8 h-8" /></div>
                )}
              </div>
              <div>
                <h3 className="font-semibold text-stone-900">{room.name}</h3>
                <p className="text-sm text-stone-600">{formatDate(checkIn)} – {formatDate(checkOut)} · {quantity} {t('hotelDetail.booking.rooms', { count: quantity })}</p>
              </div>
            </div>

            {hotel.late_checkout_price != null && hotel.late_checkout_price > 0 && (
              <label className="flex items-center gap-2 cursor-pointer p-3 rounded-lg border border-stone-200 hover:bg-stone-50">
                <input type="checkbox" checked={lateCheckout} onChange={(e) => setLateCheckout(e.target.checked)} className="rounded border-stone-300 text-amber-600" />
                <span className="text-sm font-medium text-stone-700">{t('booking.step3.addLateCheckout')}</span>
                <span className="text-sm text-stone-600">({t('booking.step3.lateCheckoutPrice', { price: formatPrice(hotel.late_checkout_price) })})</span>
              </label>
            )}

            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('booking.step3.couponCode')}</label>
              <input
                type="text"
                placeholder={t('booking.step3.couponPlaceholder')}
                value={couponCode}
                onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                className="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm uppercase focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-stone-700 mb-1">{t('booking.step3.specialRequests')}</label>
              <textarea
                placeholder={t('booking.step3.specialRequestsPlaceholder')}
                value={specialRequests}
                onChange={(e) => setSpecialRequests(e.target.value)}
                rows={3}
                className="w-full rounded-xl border border-stone-200 px-4 py-2.5 text-sm resize-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
              />
            </div>

            {room.cancellation_policy_summary && (
              <p className="text-sm text-stone-500">{room.cancellation_policy_summary}</p>
            )}

            {/* Price breakdown */}
            <div className="border-t border-stone-200 pt-4 space-y-2">
              {previewLoading ? (
                <div className="h-20 bg-stone-100 rounded-lg animate-pulse" />
              ) : breakdown ? (
                <>
                  <div className="flex justify-between text-sm">
                    <span className="text-stone-600">{t('booking.step3.roomTotal')}</span>
                    <span>{formatPrice(breakdown.subtotal, breakdown.currency)}</span>
                  </div>
                  {breakdown.discount > 0 && (
                    <div className="flex justify-between text-sm text-green-600">
                      <span>{t('booking.step3.discount')}</span>
                      <span>-{formatPrice(breakdown.discount, breakdown.currency)}</span>
                    </div>
                  )}
                  {breakdown.tax > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-stone-600">{t('booking.step3.tax', { name: breakdown.tax_name })}</span>
                      <span>{formatPrice(breakdown.tax, breakdown.currency)}</span>
                    </div>
                  )}
                  {breakdown.add_on_amount > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-stone-600">{t('booking.step3.addOns')}</span>
                      <span>{formatPrice(breakdown.add_on_amount, breakdown.currency)}</span>
                    </div>
                  )}
                  <div className="flex justify-between font-semibold text-stone-900 pt-2">
                    <span>{t('booking.step3.total')}</span>
                    <span>{formatPrice(breakdown.total, breakdown.currency)}</span>
                  </div>
                </>
              ) : (
                <>
                  <div className="flex justify-between text-sm">
                    <span className="text-stone-600">{t('booking.step3.estimatedTotal')}</span>
                    <span>{formatPrice(estimatedSubtotal)}</span>
                  </div>
                  <p className="text-xs text-stone-500">{t('booking.step3.finalPriceAtCheckout')}</p>
                </>
              )}
            </div>
          </div>

          <form
            onSubmit={handleSubmitBooking}
            className="space-y-4"
          >
            {isGuest && loginGate && loginGatePanel}
            {createBooking.isError && (
              <ErrorMessage message={createBooking.error?.response?.data?.message || createBooking.error?.message} onRetry={() => createBooking.reset()} />
            )}
            <div className="flex justify-between">
              <button type="button" onClick={() => setStep(2)} className="px-4 py-2 text-stone-600 hover:text-stone-900">
                {t('common.back')}
              </button>
              <button
                type="submit"
                disabled={createBooking.isPending || !canProceedStep3}
                className="px-6 py-3 rounded-xl bg-amber-500 text-white font-semibold hover:bg-amber-600 disabled:opacity-50 transition-colors"
              >
                {createBooking.isPending ? t('booking.step3.creating') : t('booking.step3.proceedToPayment')}
              </button>
            </div>
          </form>
        </section>
      )}
    </div>
  );
}