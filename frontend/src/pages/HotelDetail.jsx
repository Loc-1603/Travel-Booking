import { useParams, useSearchParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useState, useMemo, useEffect, useCallback, useRef, Suspense } from 'react';
import { MapPin, X, ChevronLeft, ChevronRight, Star, Sparkles, SlidersHorizontal, SearchX } from 'lucide-react';
import { RoomCard, RoomCardSkeletonList } from '../components/RoomCard';
import { RoomFilterPanelSkeleton } from '../components/RoomFilterPanel/RoomFilterPanelSkeleton';
import { LazyRoomFilterPanel } from '../components/RoomFilterPanel/lazy';
import { preloadRoomFilterPanel } from '../components/RoomFilterPanel/preload';
import { LazyRoomDetailModal } from '../components/RoomDetailModal/lazy';
import { preloadRoomDetailModal } from '../components/RoomDetailModal/preload';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { useWishlist } from '../hooks/useWishlist';
import { Skeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { formatPrice, getRatingLabel, calculateNights, cn } from '../lib/utils';
import {
  createDefaultRoomFilters,
  countActiveFilters,
  filterRooms,
  sortRooms,
  groupRoomsByType,
  calculatePriceSummary,
} from '../lib/rooms';
import { getAmenityLabel } from '../lib/amenities';
import { AmenityIcon } from '../components/AmenityIcon';
import { DatePicker } from '../components/DatePicker';
import { useTranslation } from 'react-i18next';

const TABS = [
  { key: 'overview', label: 'hotelDetail.tabs.overview' },
  { key: 'rooms', label: 'hotelDetail.tabs.rooms' },
  { key: 'reviews', label: 'hotelDetail.tabs.reviews' },
  { key: 'location', label: 'hotelDetail.tabs.location' },
];

const ROOM_SORT_OPTIONS = [
  { value: 'recommended', label: 'hotelDetail.rooms.sortRecommended' },
  { value: 'price_asc', label: 'hotelDetail.rooms.sortPriceAsc' },
  { value: 'price_desc', label: 'hotelDetail.rooms.sortPriceDesc' },
  { value: 'capacity', label: 'hotelDetail.rooms.sortCapacity' },
];

function WishlistHeart({ hotelId, checkIn, checkOut }) {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { isInWishlist, addToWishlist, removeFromWishlist, addPending, removePending } = useWishlist(!!user);
  if (!user) {
    return (
      <Link to="/login" className="p-2 rounded-full border border-[#e8e4dd] hover:bg-[#faf8f5] inline-flex" aria-label={t('hotels.wishlist.loginToSave')}>
        <HeartIcon filled={false} />
      </Link>
    );
  }
  const inList = isInWishlist(Number(hotelId));
  const pending = addPending || removePending;
  const handleClick = async () => {
    if (pending) return;
    try {
      if (inList) await removeFromWishlist(hotelId);
      else await addToWishlist({ hotelId: Number(hotelId), checkIn, checkOut });
    } catch { /* ignore */ }
  };
  return (
    <button type="button" onClick={handleClick} disabled={pending} className="p-2 rounded-full border border-[#e8e4dd] hover:bg-[#faf8f5] disabled:opacity-60 inline-flex" aria-label={inList ? t('hotels.wishlist.remove') : t('hotels.wishlist.add')}>
      <HeartIcon filled={inList} />
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

function StarDistribution({ reviews }) {
  const { t } = useTranslation();
  const counts = useMemo(() => {
    const c = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    reviews.forEach((r) => {
      const n = Math.round(Number(r.rating));
      if (n >= 1 && n <= 5) c[n]++;
    });
    return c;
  }, [reviews]);
  const total = reviews.length;
  if (total === 0) return null;
  return (
    <div className="space-y-2">
      {[5, 4, 3, 2, 1].map((stars) => {
        const pct = total > 0 ? (counts[stars] / total) * 100 : 0;
        return (
          <div key={stars} className="flex items-center gap-3">
            <span className="text-sm text-[#5c5852] w-16">{t('hotelDetail.reviews.starLabel', { count: stars })}</span>
            <div className="flex-1 h-2 bg-[#e8e4dd] rounded-full overflow-hidden">
              <div className="h-full bg-[#b8860b] rounded-full" style={{ width: `${pct}%` }} />
            </div>
            <span className="text-sm text-[#5c5852] w-12">{counts[stars]}</span>
          </div>
        );
      })}
    </div>
  );
}

// Composite skeleton mirroring the real HotelDetail layout (hero, title, tabs, rooms grid, booking widget)
function HotelDetailLoadingSkeleton() {
  return (
    <div className="py-4 sm:py-6" role="status" aria-label="Đang tải chi tiết khách sạn">
      <div className="mb-6">
        <Skeleton className="w-full aspect-[21/9] sm:aspect-[3/1] max-h-[400px] rounded-2xl" />
      </div>
      <div className="flex flex-col lg:flex-row gap-6 lg:gap-8">
        <div className="flex-1 min-w-0">
          <div className="mb-4 space-y-2">
            <Skeleton className="h-8 w-64" />
            <Skeleton className="h-4 w-40" />
          </div>
          <div className="border-b border-[#e8e4dd] mb-6">
            <Skeleton className="h-10 w-72 rounded-none" />
          </div>
          <div className="flex gap-6 items-start">
            <div className="hidden lg:block w-[280px] shrink-0">
              <Skeleton className="h-[480px] w-full rounded-2xl" />
            </div>
            <div className="flex-1 min-w-0 @container">
              <RoomCardSkeletonList count={6} variant="detailed" />
            </div>
          </div>
        </div>
        <div className="lg:w-96 shrink-0 hidden lg:block">
          <Skeleton className="h-[480px] w-full rounded-2xl" />
        </div>
      </div>
    </div>
  );
}

export default function HotelDetail() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { id } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();
  const checkIn = searchParams.get('check_in');
  const checkOut = searchParams.get('check_out');
  const [activeTab, setActiveTab] = useState('overview');
  const [localCheckIn, setLocalCheckIn] = useState(checkIn || '');
  const [localCheckOut, setLocalCheckOut] = useState(checkOut || '');
  const [guests, setGuests] = useState(1);
  const [selectedRoom, setSelectedRoom] = useState(null);
  const [roomQuantity, setRoomQuantity] = useState(1);
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);
  const [isGalleryOpen, setIsGalleryOpen] = useState(false);
  const [currentGalleryImages, setCurrentGalleryImages] = useState([]);
  const [reviewsPage, setReviewsPage] = useState(1);
  const [roomFilters, setRoomFilters] = useState(createDefaultRoomFilters);
  const [debouncedRoomFilters, setDebouncedRoomFilters] = useState(createDefaultRoomFilters);
  const [roomSortBy, setRoomSortBy] = useState('recommended');
  const [selectedRoomForModal, setSelectedRoomForModal] = useState(null);
  const [isRoomModalOpen, setIsRoomModalOpen] = useState(false);
  const [modalQuantity, setModalQuantity] = useState(1);
  const [isMobileFilterOpen, setIsMobileFilterOpen] = useState(false);
  const [isMobileViewport, setIsMobileViewport] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches
  );
  const [prevHotelId, setPrevHotelId] = useState(id);
  if (prevHotelId !== id) {
    setPrevHotelId(id);
    setRoomFilters(createDefaultRoomFilters());
    setDebouncedRoomFilters(createDefaultRoomFilters());
    setRoomSortBy('recommended');
    setSelectedRoomForModal(null);
    setIsRoomModalOpen(false);
    setIsMobileFilterOpen(false);
    setSelectedRoom(null);
    setRoomQuantity(1);
  }

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 1023px)');
    const handleChange = (e) => setIsMobileViewport(e.matches);
    mq.addEventListener('change', handleChange);
    return () => mq.removeEventListener('change', handleChange);
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedRoomFilters(roomFilters), 300);
    return () => clearTimeout(timer);
  }, [roomFilters]);

  const handleDateSearch = () => {
    if (localCheckIn && localCheckOut) {
      setSearchParams({ check_in: localCheckIn, check_out: localCheckOut });
    }
  };

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['hotel', id],
    queryFn: async () => {
      const res = await api.get(`/hotels/${id}`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load');
      return res.data;
    },
    enabled: !!id,
  });

  const { data: reviewsData } = useQuery({
    queryKey: ['reviews', 'hotel', id, reviewsPage],
    queryFn: async () => {
      const res = await api.get('/reviews', { params: { hotel_id: id, per_page: 10, page: reviewsPage } });
      return res.data;
    },
    enabled: !!id,
  });

  const { data: sentimentData } = useQuery({
    queryKey: ['review-sentiment', id],
    queryFn: async () => {
      const res = await api.get(`/hotels/${id}/review-sentiment`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed');
      return res.data.data ?? res.data;
    },
    enabled: !!id && activeTab === 'reviews',
  });

  const hotel = data?.data ?? data;
  const reviewsRaw = reviewsData?.data ?? reviewsData?.data?.data ?? [];
  const reviews = Array.isArray(reviewsRaw) ? reviewsRaw : [];
  const reviewsMeta = reviewsData?.meta ?? {};

  const today = useMemo(() => new Date().toISOString().split('T')[0], []);
  const tomorrow = useMemo(() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().split('T')[0];
  }, []);

  const nights = checkIn && checkOut ? calculateNights(checkIn, checkOut) : null;
  const effectiveCheckIn = checkIn || localCheckIn;
  const effectiveCheckOut = checkOut || localCheckOut;
  const effectiveNights = effectiveCheckIn && effectiveCheckOut ? calculateNights(effectiveCheckIn, effectiveCheckOut) : null;

  const priceSummary = useMemo(
    () =>
      calculatePriceSummary({
        basePrice: selectedRoom?.base_price,
        nights: effectiveNights,
        quantity: roomQuantity,
        taxRate: hotel?.tax_rate ?? 0,
        taxInclusive: hotel?.tax_inclusive,
      }),
    [selectedRoom, effectiveNights, roomQuantity, hotel?.tax_rate, hotel?.tax_inclusive]
  );

  const bookUrl = selectedRoom && effectiveCheckIn && effectiveCheckOut
    ? `/book?hotel_id=${id}&room_id=${selectedRoom.id}&check_in=${effectiveCheckIn}&check_out=${effectiveCheckOut}&quantity=${roomQuantity}`
    : null;

  const images = hotel?.images ?? [];
  const rooms = useMemo(() => hotel?.rooms ?? [], [hotel]);
  const roomsForGuests = useMemo(() => {
    const fit = rooms.filter((r) => r.capacity >= guests);
    return fit.length > 0 ? fit : rooms;
  }, [rooms, guests]);

  // Latest refs so memoized children receive stable callbacks (React.memo needs stable props)
  const selectedRoomRef = useRef(selectedRoom);
  const roomQuantityRef = useRef(roomQuantity);
  useEffect(() => {
    selectedRoomRef.current = selectedRoom;
    roomQuantityRef.current = roomQuantity;
  });

  const openRoomModal = useCallback((room) => {
    preloadRoomDetailModal();
    setSelectedRoomForModal(room);
    setModalQuantity(selectedRoomRef.current?.id === room.id ? roomQuantityRef.current : 1);
    setIsRoomModalOpen(true);
  }, []);

  const closeRoomModal = useCallback(() => {
    setIsRoomModalOpen(false);
    setSelectedRoomForModal(null);
  }, []);

  const handleModalQuantityChange = useCallback((delta) => {
    setModalQuantity((q) => {
      if (!selectedRoomForModal) return Math.max(1, q + delta);
      const maxQty = Math.min(selectedRoomForModal.total_rooms || 5, selectedRoomForModal.max_quantity_per_booking || 5, 5);
      return Math.min(Math.max(1, q + delta), maxQty);
    });
  }, [selectedRoomForModal]);

  const handleSelectRoomFromModal = useCallback(() => {
    if (!selectedRoomForModal) return;
    setSelectedRoom(selectedRoomForModal);
    setRoomQuantity(modalQuantity);
    closeRoomModal();
  }, [selectedRoomForModal, modalQuantity, closeRoomModal]);

  const handleSelectRoom = useCallback((room) => {
    if (!room || selectedRoomRef.current?.id === room.id) return;
    setSelectedRoom(room);
    setRoomQuantity(1);
  }, []);

  const clearRoomFilters = useCallback(() => {
    setRoomFilters(createDefaultRoomFilters());
  }, []);

  const closeMobileFilter = useCallback(() => setIsMobileFilterOpen(false), []);

  const activeFilterCount = useMemo(() => countActiveFilters(roomFilters), [roomFilters]);

  const filteredRooms = useMemo(
    () => filterRooms(rooms, debouncedRoomFilters),
    [rooms, debouncedRoomFilters]
  );

  const sortedRooms = useMemo(() => sortRooms(filteredRooms, roomSortBy), [filteredRooms, roomSortBy]);

  const roomsByType = useMemo(
    () => groupRoomsByType(sortedRooms, t('hotelDetail.rooms.otherType')),
    [sortedRooms, t]
  );

  if (isLoading) {
    return (
      <HotelDetailLoadingSkeleton />
    );
  }

  if (isError || !hotel) {
    return (
      <div className="py-6">
        <ErrorMessage message={error?.response?.data?.message || error?.message || t('errors.notFound')} onRetry={() => refetch()} />
      </div>
    );
  }

  return (
    <div className="py-4 sm:py-6">
      {/* Header: Image gallery + name, location, rating, wishlist */}
      <div className="mb-6">
        <div className="relative rounded-2xl overflow-hidden bg-[#e8e4dd] aspect-[21/9] sm:aspect-[3/1] max-h-[400px]">
          {images.length > 0 ? (
            <>
              <img
                src={images[selectedImageIndex]?.url || images[0]?.url}
                alt={images[selectedImageIndex]?.alt_text || hotel.name}
                className="w-full h-full object-cover cursor-pointer"
                fetchPriority="high"
                decoding="async"
                onClick={() => {
                  setCurrentGalleryImages(images);
                  setSelectedImageIndex(0);
                  setIsGalleryOpen(true);
                }}
              />
              {images.length > 1 && (
                <>
                  <div className="absolute bottom-4 right-4 bg-black/60 text-white px-3 py-1 rounded-lg text-sm">
                    {selectedImageIndex + 1} / {images.length}
                  </div>
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); setSelectedImageIndex((i) => (i - 1 + images.length) % images.length); }}
                    className="absolute left-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-white/90 hover:bg-white shadow"
                    aria-label={t('hotelDetail.gallery.prev')}
                  >
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); setSelectedImageIndex((i) => (i + 1) % images.length); }}
                    className="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-white/90 hover:bg-white shadow"
                    aria-label={t('hotelDetail.gallery.next')}
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

        {images.length > 1 && (
          <div className="flex gap-2 mt-3 overflow-x-auto pb-2">
            {images.map((img, idx) => (
              <button
                key={img.id}
                onClick={() => setSelectedImageIndex(idx)}
                className={cn(
                  'flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-all',
                  selectedImageIndex === idx ? 'border-[#b8860b]500 scale-105' : 'border-[#d4cec4] hover:border-[#e8e4dd]400'
                )}
              >
                <img src={img.url} alt={img.alt_text || ''} className="w-full h-full object-cover" />
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="flex flex-col lg:flex-row gap-6 lg:gap-8">
        {/* Main content */}
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
              <h1 className="text-2xl sm:text-3xl font-bold text-[#1a1a1a]">{hotel.name}</h1>
              <p className="text-[#5c5852] mt-1 flex items-center gap-1">
                <MapPin className="w-4 h-4" />
                {[hotel.city, hotel.country].filter(Boolean).join(', ')}
              </p>
              <div className="flex items-center gap-3 mt-2">
                {hotel.average_rating != null && (
                  <span className="inline-flex items-center gap-1 font-semibold text-[#1a1a1a]">
                    <Star className="w-5 h-5 fill-amber-400 text-[#b8860b]400" />
                    {Number(hotel.average_rating).toFixed(1)} {getRatingLabel(hotel.average_rating)}
                  </span>
                )}
                {hotel.review_count != null && hotel.review_count > 0 && (
                  <span className="text-[#5c5852] text-sm">{hotel.review_count} {t('common.reviews')}</span>
                )}
              </div>
            </div>
            <WishlistHeart hotelId={id} checkIn={checkIn} checkOut={checkOut} />
          </div>

          {/* Tabs */}
          <div className="border-b border-[#e8e4dd] mb-6">
            <nav className="flex gap-4 overflow-x-auto" aria-label={t('hotelDetail.tabs.ariaLabel')}>
              {TABS.map((tab) => (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={cn(
                    'py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors',
                    activeTab === tab.key ? 'border-[#b8860b] text-[#b8860b]600' : 'border-transparent text-[#5c5852] hover:text-[#1a1a1a]'
                  )}
                >
                  {t(tab.label)}
                </button>
              ))}
            </nav>
          </div>

          {/* Tab content */}
          {activeTab === 'overview' && (
            <section className="space-y-6">
              {hotel.description && <p className="text-[#45423d] leading-relaxed">{hotel.description}</p>}
              {hotel.amenities?.length > 0 && (
                <div>
                  <h3 className="font-semibold text-[#1a1a1a] mb-3">{t('hotelDetail.overview.amenities')}</h3>
                  <div className="flex flex-wrap gap-3">
                    {hotel.amenities.map((a) => (
                      <span key={a.id} className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#f5f2ed] text-[#45423d] text-sm">
                        <AmenityIcon slug={a.slug} className="w-4 h-4 text-[#b8860b]600" />
                        {getAmenityLabel(t, a)}
                      </span>
                    ))}
                  </div>
                </div>
              )}
              {(hotel.check_in || hotel.check_out) && (
                <div>
                  <h3 className="font-semibold text-[#1a1a1a] mb-2">{t('hotelDetail.overview.checkInOut')}</h3>
                  <p className="text-[#5c5852] text-sm">{t('hotelDetail.overview.checkIn')}: {hotel.check_in ?? '—'} · {t('hotelDetail.overview.checkOut')}: {hotel.check_out ?? '—'}</p>
                </div>
              )}
              {(hotel.cancellation_policy_summary || hotel.cancellation_policy) && (
                <div>
                  <h3 className="font-semibold text-[#1a1a1a] mb-2">{t('hotelDetail.overview.cancellationPolicy')}</h3>
                  <p className="text-[#5c5852] text-sm">{hotel.cancellation_policy_summary || t('hotelDetail.overview.seeTermsAtBooking')}</p>
                </div>
              )}
            </section>
          )}

          {activeTab === 'rooms' && (
            <section className="space-y-5">
              {rooms.length === 0 ? (
                <p className="text-[#5c5852]">{t('hotelDetail.rooms.noRooms')}</p>
              ) : (
                <>
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-[#5c5852]">
                      {t('hotelDetail.rooms.roomsCount', { count: sortedRooms.length })}
                    </p>
                    <div className="flex items-center gap-2">
                      {isMobileViewport && (
                        <button
                          type="button"
                          onClick={() => { preloadRoomFilterPanel(); setIsMobileFilterOpen(true); }}
                          className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-[#e8e4dd] bg-white text-sm font-medium text-[#1a1a1a] hover:bg-[#faf8f5] transition-colors"
                        >
                          <SlidersHorizontal className="w-4 h-4" aria-hidden />
                          {t('hotelDetail.rooms.filters')}
                          {activeFilterCount > 0 && (
                            <span className="min-w-5 h-5 px-1.5 rounded-full bg-[#b8860b] text-white text-xs inline-flex items-center justify-center">
                              {activeFilterCount}
                            </span>
                          )}
                        </button>
                      )}
                      <label htmlFor="room-sort" className="sr-only">{t('hotelDetail.rooms.sortBy')}</label>
                      <select
                        id="room-sort"
                        value={roomSortBy}
                        onChange={(e) => setRoomSortBy(e.target.value)}
                        className="rounded-xl border border-[#e8e4dd] bg-white px-3 py-2 text-sm text-[#1a1a1a]"
                      >
                        {ROOM_SORT_OPTIONS.map((option) => (
                          <option key={option.value} value={option.value}>{t(option.label)}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="flex gap-6 items-start">
                    {!isMobileViewport && (
                      <Suspense fallback={<RoomFilterPanelSkeleton />}>
                        <LazyRoomFilterPanel
                          rooms={rooms}
                          filters={roomFilters}
                          onFiltersChange={setRoomFilters}
                        />
                      </Suspense>
                    )}
                    <div className="flex-1 min-w-0 @container">
                      {sortedRooms.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-[#d4cec4] bg-[#faf8f5] px-6 py-12 text-center">
                          <SearchX className="w-12 h-12 mx-auto text-[#b8860b]/70 mb-3" aria-hidden />
                          <p className="font-semibold text-[#1a1a1a]">{t('hotelDetail.rooms.noResults')}</p>
                          <p className="text-sm text-[#5c5852] mt-1">{t('hotelDetail.rooms.noResultsHint')}</p>
                          {activeFilterCount > 0 && (
                            <button
                              type="button"
                              onClick={clearRoomFilters}
                              className="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-[#b8860b]/40 text-[#b8860b] font-medium text-sm hover:bg-[#fff7e6] transition-colors"
                            >
                              <X className="w-4 h-4" aria-hidden />
                              {t('hotelDetail.rooms.clearFilters')}
                            </button>
                          )}
                        </div>
                      ) : (
                        <div className="space-y-8">
                          {roomsByType.map(({ type, rooms: typeRooms }) => (
                            <div key={type}>
                              <div className="flex items-baseline gap-2 mb-3">
                                <h3 className="font-semibold text-[#1a1a1a] text-lg">{type}</h3>
                                <span className="text-sm text-[#5c5852]">
                                  {t('hotelDetail.rooms.roomsCount', { count: typeRooms.length })}
                                </span>
                              </div>
                              {/* Container queries (not viewport): the cards column is only ~240-432px wide
                                  on desktop (container max-w-6xl minus booking aside 384px minus filter
                                  sidebar 280px), so lg:grid-cols-3 stretched cards to ~69-133px. */}
                              <div className="grid grid-cols-1 @xl:grid-cols-2 @4xl:grid-cols-3 gap-4 items-start">
                                {typeRooms.map((room) => (
                                  <RoomCard
                                    key={room.id}
                                    room={room}
                                    variant="detailed"
                                    nights={nights}
                                    onViewDetails={openRoomModal}
                                    onSelect={handleSelectRoom}
                                    selected={selectedRoom?.id === room.id}
                                  />
                                ))}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>

                  {isMobileViewport && isMobileFilterOpen && (
                    <Suspense fallback={null}>
                      <LazyRoomFilterPanel
                        rooms={rooms}
                        filters={roomFilters}
                        onFiltersChange={setRoomFilters}
                        isMobile
                        onClose={closeMobileFilter}
                      />
                    </Suspense>
                  )}
                </>
              )}
            </section>
          )}

          {activeTab === 'reviews' && (
            <section className="space-y-6">
              {sentimentData && sentimentData.review_count > 0 && (
                <div className="rounded-2xl border border-[#e8e4dd] bg-gradient-to-br from-[#f9edd1]/35 to-white p-5 sm:p-6 shadow-sm">
                  <h3 className="font-semibold text-[#1a1a1a] mb-2 flex flex-wrap items-center gap-2">
                    <Sparkles className="w-5 h-5 text-[#b8860b] shrink-0" aria-hidden />
                    {t('hotelDetail.reviews.sentimentTitle')}
                    {sentimentData.ai_enabled && (
                      <span className="text-xs font-normal text-[#7a756d]">{t('hotelDetail.reviews.aiAssisted')}</span>
                    )}
                  </h3>
                  <p className="text-[#45423d] text-sm leading-relaxed">{sentimentData.summary}</p>
                  {sentimentData.bullets?.length > 0 && (
                    <ul className="mt-3 list-disc list-inside text-sm text-[#5c5852] space-y-1">
                      {sentimentData.bullets.map((b, i) => (
                        <li key={i}>{b}</li>
                      ))}
                    </ul>
                  )}
                  {sentimentData.review_count > 0 && (
                    <p className="text-xs text-[#7a756d] mt-3">
                      {t('hotelDetail.reviews.basedOn', { count: sentimentData.review_count })} · avg {Number(sentimentData.avg_rating).toFixed(1)}/5
                    </p>
                  )}
                </div>
              )}
              {reviews.length === 0 ? (
                <p className="text-[#5c5852]">{t('hotelDetail.reviews.noReviews')}</p>
              ) : (
                <>
                  <div className="max-w-xs">
                    <h3 className="font-semibold text-[#1a1a1a] mb-3">{t('hotelDetail.reviews.starDistribution')}</h3>
                    <StarDistribution reviews={reviews} />
                  </div>
                  <ul className="space-y-4">
                    {reviews.map((r) => (
                      <li key={r.id} className="p-4 rounded-2xl border border-[#e8e4dd]/80 bg-white shadow-sm">
                        <div className="flex items-center gap-2 mb-4">
                          <span className="font-semibold text-[#1a1a1a]">★ {r.rating}</span>
                          <span className="text-[#5c5852] text-sm">{t('hotelDetail.reviews.verifiedStay')}</span>
                        </div>
                        {r.comment && <p className="text-[#45423d]">{r.comment}</p>}
                      </li>
                    ))}
                  </ul>
                  {reviewsMeta.last_page > 1 && (
                    <nav className="flex justify-center gap-2" aria-label={t('hotelDetail.reviews.paginationAriaLabel')}>
                      <button
                        onClick={() => setReviewsPage((p) => Math.max(1, p - 1))}
                        disabled={reviewsMeta.current_page <= 1}
                        className="px-4 py-2 rounded-xl border border-[#e8e4dd] hover:bg-[#faf8f5] disabled:opacity-50 transition-colors"
                      >
                        {t('common.previous')}
                      </button>
                      <span className="px-4 py-2 text-[#5c5852] text-sm">
                        {t('hotelDetail.reviews.page', { current: reviewsMeta.current_page, total: reviewsMeta.last_page })}
                      </span>
                      <button
                        onClick={() => setReviewsPage((p) => Math.min(reviewsMeta.last_page, p + 1))}
                        disabled={reviewsMeta.current_page >= reviewsMeta.last_page}
                        className="px-4 py-2 rounded-xl border border-[#e8e4dd] hover:bg-[#faf8f5] disabled:opacity-50 transition-colors"
                      >
                        {t('common.next')}
                      </button>
                    </nav>
                  )}
                </>
              )}
            </section>
          )}

          {activeTab === 'location' && (
            <section>
              <h3 className="font-semibold text-[#1a1a1a] mb-3">{t('hotelDetail.location.title')}</h3>
              {hotel.address && <p className="text-[#5c5852] mb-2">{hotel.address}</p>}
              <p className="text-[#5c5852] mb-4">{[hotel.city, hotel.country].filter(Boolean).join(', ')}</p>
              {hotel.latitude != null && hotel.longitude != null ? (
                <div className="rounded-xl overflow-hidden border border-[#e8e4dd] shadow-sm">
                  <div className="aspect-video w-full">
                    <iframe
                      title={t('hotelDetail.location.mapTitle')}
                      src={`https://www.openstreetmap.org/export/embed.html?bbox=${Number(hotel.longitude) - 0.02},${Number(hotel.latitude) - 0.02},${Number(hotel.longitude) + 0.02},${Number(hotel.latitude) + 0.02}&layer=mapnik&marker=${hotel.latitude}%2C${hotel.longitude}`}
                      className="w-full h-full border-0"
                      loading="lazy"
                      referrerPolicy="no-referrer-when-downgrade"
                    />
                  </div>
                  <div className="px-4 py-2 bg-[#faf8f5] border-t border-[#e8e4dd] flex items-center justify-between">
                    <span className="text-sm text-[#5c5852]">
                      {Number(hotel.latitude).toFixed(6)}, {Number(hotel.longitude).toFixed(6)}
                    </span>
                    <a
                      href={`https://www.openstreetmap.org/?mlat=${hotel.latitude}&mlon=${hotel.longitude}#map=15/${hotel.latitude}/${hotel.longitude}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm font-medium text-[#b8860b]600 hover:text-[#b8860b]700"
                    >
                      {t('hotelDetail.location.openInMaps')}
                    </a>
                  </div>
                </div>
              ) : (
                <div className="aspect-video rounded-xl bg-[#e8e4dd] flex items-center justify-center text-[#5c5852]">
                  <div className="text-center">
                    <MapPin className="w-12 h-12 mx-auto mb-2" />
                    <p>{t('hotelDetail.location.coordinatesNotAvailable')}</p>
                    <p className="text-sm">{t('hotelDetail.location.addLocationNote')}</p>
                  </div>
                </div>
              )}
            </section>
          )}
        </div>

        {/* Sticky Booking Widget */}
        <aside className="lg:w-96 shrink-0">
          <div className="lg:sticky lg:top-24 rounded-2xl border border-[#e8e4dd] bg-white shadow-lg p-6">
            <h2 className="font-semibold text-[#1a1a1a] text-lg mb-4">{t('hotelDetail.booking.title')}</h2>
            <form onSubmit={(e) => { e.preventDefault(); if (!user) return; if (bookUrl) window.location.href = bookUrl; }} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label htmlFor="booking-check-in" className="block text-sm font-medium text-[#45423d] mb-1">{t('common.date.checkIn')}</label>
                  <DatePicker
                    id="booking-check-in"
                    value={localCheckIn}
                    onChange={setLocalCheckIn}
                    min={today}
                    className="w-full rounded-xl border border-[#e8e4dd] px-4 py-2.5 text-sm text-[#1a1a1a] focus:ring-2 focus:ring-[#b8860b]/30 focus:border-[#b8860b] bg-white"
                  />
                </div>
                <div>
                  <label htmlFor="booking-check-out" className="block text-sm font-medium text-[#45423d] mb-1">{t('common.date.checkOut')}</label>
                  <DatePicker
                    id="booking-check-out"
                    value={localCheckOut}
                    onChange={setLocalCheckOut}
                    min={localCheckIn || tomorrow}
                    className="w-full rounded-xl border border-[#e8e4dd] px-4 py-2.5 text-sm text-[#1a1a1a] focus:ring-2 focus:ring-[#b8860b]/30 focus:border-[#b8860b] bg-white"
                  />
                </div>
              </div>
              <div>
                <label htmlFor="booking-guests" className="block text-sm font-medium text-[#45423d] mb-1">{t('common.guests')}</label>
                <select
                  id="booking-guests"
                  value={guests}
                  onChange={(e) => setGuests(Number(e.target.value))}
                  className="w-full rounded-lg border border-[#d4cec4] px-3 py-2 text-sm text-[#1a1a1a] bg-white"
                >
                  {[1, 2, 3, 4, 5, 6].map((n) => (
                    <option key={n} value={n}>{n} {n === 1 ? t('common.guest') : t('common.guests')}</option>
                  ))}
                </select>
              </div>
              <button
                type="button"
                onClick={handleDateSearch}
                className="w-full py-2.5 text-sm font-medium text-[#b8860b]600 hover:text-[#b8860b]700 border border-[#b8860b]200 rounded-xl transition-colors"
              >
                {t('hotelDetail.booking.checkAvailability')}
              </button>

              {effectiveCheckIn && effectiveCheckOut && rooms.length > 0 && (
                <>
                  <div>
                    <span id="room-picker-label" className="block text-sm font-medium text-[#45423d] mb-2">{t('hotelDetail.booking.selectRoom')}</span>
                    <div className="grid grid-cols-2 gap-3" role="group" aria-labelledby="room-picker-label">
                      {roomsForGuests.map((r) => (
                        <RoomCard
                          key={r.id}
                          room={r}
                          variant="gallery"
                          onSelect={handleSelectRoom}
                          selected={selectedRoom?.id === r.id}
                        />
                      ))}
                    </div>
                  </div>
                  {selectedRoom && (
                    <div>
                      <label htmlFor="booking-quantity" className="block text-sm font-medium text-[#45423d] mb-1">{t('hotelDetail.booking.quantity')}</label>
                      <select
                        id="booking-quantity"
                        value={roomQuantity}
                        onChange={(e) => setRoomQuantity(Number(e.target.value))}
                        className="w-full rounded-lg border border-[#d4cec4] px-3 py-2 text-sm text-[#1a1a1a] bg-white"
                      >
                        {Array.from({ length: Math.min(selectedRoom.total_rooms || 5, 5) }, (_, i) => i + 1).map((n) => (
                          <option key={n} value={n}>{n} {t('hotelDetail.booking.rooms', { count: n })}</option>
                        ))}
                      </select>
                    </div>
                  )}
                </>
              )}

              {priceSummary && (
                <div className="border-t border-[#e8e4dd] pt-4 space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-[#5c5852]">{t('hotelDetail.booking.subtotal')}</span>
                    <span>{formatPrice(priceSummary.subTotal)}</span>
                  </div>
                  {priceSummary.taxAmount > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-[#5c5852]">{t('hotelDetail.booking.tax', { rate: priceSummary.taxRatePct })}</span>
                      <span>{formatPrice(priceSummary.taxAmount)}</span>
                    </div>
                  )}
                  <div className="flex justify-between font-semibold text-[#1a1a1a] pt-2">
                    <span>{t('hotelDetail.booking.total')}</span>
                    <span>{formatPrice(priceSummary.total)}</span>
                  </div>
                </div>
              )}

              {!user && bookUrl && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                  <p className="text-sm text-[#7a4a00]">{t('hotelDetail.booking.loginRequired')}</p>
                  <div className="flex gap-2">
                    <Link to="/login" className="flex-1 py-2.5 rounded-xl bg-[#b8860b] text-white font-semibold text-center hover:bg-[#996f09] transition-colors">
                      {t('common.login', 'Đăng nhập')}
                    </Link>
                    <Link to="/register" className="flex-1 py-2.5 rounded-xl border border-[#b8860b] text-[#b8860b] font-semibold text-center hover:bg-[#fff7e6] transition-colors">
                      {t('common.register', 'Đăng ký')}
                    </Link>
                  </div>
                </div>
              )}

              {user && (
                <button
                  type="submit"
                  disabled={!bookUrl}
                  className="w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold hover:bg-[#996f09] disabled:bg-[#d4cec4] disabled:text-[#7a756d] disabled:cursor-not-allowed transition-colors"
                >
                  {t('hotelDetail.booking.bookNow')}
                </button>
              )}

              {!user && !bookUrl && (
                <button
                  type="submit"
                  disabled
                  className="w-full py-3 rounded-xl bg-[#d4cec4] text-[#7a756d] font-semibold cursor-not-allowed transition-colors"
                >
                  {t('hotelDetail.booking.bookNow')}
                </button>
              )}
            </form>
          </div>
        </aside>
      </div>

      {/* Room Detail Modal — conditional mount + code-split (fallback=null avoids double-paint flash) */}
      {isRoomModalOpen && selectedRoomForModal && (
        <Suspense fallback={null}>
          <LazyRoomDetailModal
            room={selectedRoomForModal}
            hotel={hotel}
            isOpen={isRoomModalOpen}
            onClose={closeRoomModal}
            onSelectRoom={handleSelectRoomFromModal}
            quantity={modalQuantity}
            onQuantityChange={handleModalQuantityChange}
            isLoggedIn={!!user}
            nights={nights}
          />
        </Suspense>
      )}

      {/* Lightbox */}
      {isGalleryOpen && currentGalleryImages.length > 0 && (
        <div
          className="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4"
          onClick={() => setIsGalleryOpen(false)}
        >
          <button
            onClick={() => setIsGalleryOpen(false)}
            className="absolute top-4 right-4 p-2 text-white hover:bg-white/10 rounded-full"
            aria-label={t('hotelDetail.gallery.close')}
          >
            <X className="w-6 h-6" />
          </button>
          <div className="relative max-w-5xl max-h-full" onClick={(e) => e.stopPropagation()}>
            <img
              src={currentGalleryImages[selectedImageIndex]?.url}
              alt={currentGalleryImages[selectedImageIndex]?.alt_text || hotel.name}
              className="max-w-full max-h-[80vh] object-contain rounded-lg"
            />
            {currentGalleryImages.length > 1 && (
              <>
                <button
                  onClick={() => setSelectedImageIndex((i) => (i - 1 + currentGalleryImages.length) % currentGalleryImages.length)}
                  className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white"
                  aria-label={t('hotelDetail.gallery.prev')}
                >
                  <ChevronLeft className="w-6 h-6" />
                </button>
                <button
                  onClick={() => setSelectedImageIndex((i) => (i + 1) % currentGalleryImages.length)}
                  className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white"
                  aria-label={t('hotelDetail.gallery.next')}
                >
                  <ChevronRight className="w-6 h-6" />
                </button>
                <div className="flex justify-center gap-2 mt-4">
                  {currentGalleryImages.map((_, idx) => (
                    <button
                      key={idx}
                      onClick={() => setSelectedImageIndex(idx)}
                      className={cn(
                        'w-2 h-2 rounded-full transition-colors',
                        selectedImageIndex === idx ? 'bg-white' : 'bg-white/50 hover:bg-white/75'
                      )}
                      aria-label={t('hotelDetail.gallery.imageAriaLabel', { index: idx + 1 })}
                    />
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}