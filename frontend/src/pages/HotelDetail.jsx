import { useParams, useSearchParams, Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useState, useMemo, useEffect, useCallback, Suspense } from 'react';
import { MapPin, X, ChevronLeft, ChevronRight, Star, Sparkles, SearchX } from 'lucide-react';
import { RoomCard, RoomCardSkeletonList } from '../components/RoomCard';
import { HotelRoomFilterBar } from '../components/HotelRoomFilterBar';
import { LazyRoomFilterPanel } from '../components/RoomFilterPanel/lazy';
import { preloadRoomFilterPanel } from '../components/RoomFilterPanel/preload';
import { LazyRoomDetailModal } from '../components/RoomDetailModal/lazy';
import { preloadRoomDetailModal } from '../components/RoomDetailModal/preload';
import { api } from '../lib/api';
import { useAuth } from '../contexts/useAuth';
import { useWishlist } from '../hooks/useWishlist';
import { Skeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { getRatingLabel, calculateNights, cn } from '../lib/utils';
import {
  createDefaultRoomFilters,
  countActiveFilters,
  filterRooms,
  filterRoomsByAvailabilityAndGuests,
  sortRooms,
  groupRoomsByType,
} from '../lib/rooms';
import { getAmenityLabel } from '../lib/amenities';
import { toISODateLocal, parseISODateLocal, addDaysLocal, isValidISODate } from '../lib/dates';
import { AmenityIcon } from '../components/AmenityIcon';
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

// --- Room search defaults: tomorrow → day after tomorrow (redesign FE-4) ---
/** Initial room search: URL check_in/check_out win when valid, else tomorrow/day-after. */
function resolveInitialRoomSearch(searchParams) {
  const fallbackIn = toISODateLocal(addDaysLocal(new Date(), 1));
  const fallbackOut = toISODateLocal(addDaysLocal(new Date(), 2));
  const rawIn = searchParams.get('check_in');
  const rawOut = searchParams.get('check_out');
  const checkIn = isValidISODate(rawIn) ? rawIn : fallbackIn;
  let checkOut = isValidISODate(rawOut) ? rawOut : fallbackOut;
  if (checkOut <= checkIn) {
    checkOut = toISODateLocal(addDaysLocal(parseISODateLocal(checkIn), 1));
  }
  return { checkIn, checkOut, adults: 1, rooms: 1 };
}

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
    } catch { /* rollback handled by the optimistic update in useWishlist (FX-6) */ }
  };
  return (
    <button type="button" onClick={handleClick} disabled={pending} aria-pressed={inList} className="p-2 rounded-full border border-[#e8e4dd] hover:bg-[#faf8f5] disabled:opacity-60 inline-flex" aria-label={inList ? t('hotels.wishlist.remove') : t('hotels.wishlist.add')}>
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

// Composite skeleton mirroring the real HotelDetail layout (hero, title, tabs, rooms grid)
function HotelDetailLoadingSkeleton() {
  return (
    <div className="py-4 sm:py-6" role="status" aria-label="Đang tải chi tiết khách sạn">
      <div className="mb-6">
        <Skeleton className="w-full aspect-[21/9] sm:aspect-[3/1] max-h-[400px] rounded-2xl" />
      </div>
      <div>
        <div className="min-w-0">
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
      </div>
    </div>
  );
}

export default function HotelDetail() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  // Room search (FX-3): `roomSearch` is the DRAFT edited in the filter bar
  // (editing never fetches); `appliedRoomSearch` is the last APPLIED search
  // and the ONLY thing that drives the API query + URL + client filters.
  const [roomSearch, setRoomSearch] = useState(() => resolveInitialRoomSearch(searchParams));
  const [appliedRoomSearch, setAppliedRoomSearch] = useState(roomSearch);
  const checkIn = appliedRoomSearch.checkIn;
  const checkOut = appliedRoomSearch.checkOut;
  const [activeTab, setActiveTab] = useState('overview');
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);
  // Hướng trượt ảnh hotel: 1 = tiếp theo, -1 = trước đó, 0 = mở/thumbnail (fade).
  const [galleryDirection, setGalleryDirection] = useState(0);
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
  const [isDesktopDrawerOpen, setIsDesktopDrawerOpen] = useState(false);
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
    setReviewsPage(1);
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

  // Sync APPLIED check_in/check_out into URL params (FX-2 + FX-3).
  // `replace: true` is load-bearing: every date/guest edit used to push a new
  // history entry (via the old 300ms debounce), so the browser Back button
  // only undid param changes instead of leaving the page. Applied searches
  // replace the entry; editing the draft touches neither URL nor history.
  useEffect(() => {
    setSearchParams((prev) => {
      if (
        prev.get('check_in') === appliedRoomSearch.checkIn &&
        prev.get('check_out') === appliedRoomSearch.checkOut
      ) {
        return prev;
      }
      const next = new URLSearchParams(prev);
      next.set('check_in', appliedRoomSearch.checkIn);
      next.set('check_out', appliedRoomSearch.checkOut);
      return next;
    }, { replace: true });
  }, [appliedRoomSearch, setSearchParams]);

  // Note: the desktop drawer (position="right") owns its own ESC + scroll-lock
  // handling inside RoomFilterPanel — no page-level effect needed.

  const { data, isLoading, isFetching, isError, error, refetch } = useQuery({
    queryKey: ['hotel', id, appliedRoomSearch.checkIn, appliedRoomSearch.checkOut, appliedRoomSearch.adults, appliedRoomSearch.rooms],
    queryFn: async () => {
      const res = await api.get(`/hotels/${id}`, {
        params: {
          check_in: appliedRoomSearch.checkIn,
          check_out: appliedRoomSearch.checkOut,
          adults: appliedRoomSearch.adults,
          rooms: appliedRoomSearch.rooms,
        },
      });
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
    // Lazy: only fetch when the user actually opens the reviews tab (FX-1).
    enabled: !!id && activeTab === 'reviews',
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
  // API envelope: { success, data: { data: [...], meta: {...} } } (FX-1).
  // NOTE: `reviewsData?.data` is the wrapper object — it must NOT be used as
  // the list, otherwise the tab is always empty.
  const reviewsRaw = reviewsData?.data?.data ?? [];
  const reviews = Array.isArray(reviewsRaw) ? reviewsRaw : [];
  const reviewsMeta = reviewsData?.data?.meta ?? {};

  const nights = checkIn && checkOut ? calculateNights(checkIn, checkOut) : null;

  const images = hotel?.images ?? [];
  const rooms = useMemo(() => hotel?.rooms ?? [], [hotel]);
  // Class animation tương ứng hướng chuyển ảnh hotel (replay qua key={selectedImageIndex}).
  const gallerySlideClass =
    galleryDirection > 0 ? 'animate-gallery-next' : galleryDirection < 0 ? 'animate-gallery-prev' : 'animate-gallery-fade';

  const openRoomModal = useCallback((room) => {
    preloadRoomDetailModal();
    setSelectedRoomForModal(room);
    setModalQuantity(1);
    setIsRoomModalOpen(true);
  }, []);

  // Chuyển ảnh hotel kèm hướng trượt để replay animation (key + direction).
  const goHotelImage = useCallback((delta, total) => {
    if (!total || total <= 1) return;
    setGalleryDirection(delta > 0 ? 1 : -1);
    setSelectedImageIndex((i) => (i + delta + total) % total);
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
    if (!selectedRoomForModal || !checkIn || !checkOut) return;
    closeRoomModal();
    // SPA navigation (FX-2): window.location.href would hard-reload and
    // break the browser-history stack.
    navigate(`/book?hotel_id=${id}&room_id=${selectedRoomForModal.id}&check_in=${checkIn}&check_out=${checkOut}&quantity=${modalQuantity}`);
  }, [selectedRoomForModal, modalQuantity, checkIn, checkOut, id, closeRoomModal, navigate]);

  const clearRoomFilters = useCallback(() => {
    setRoomFilters(createDefaultRoomFilters());
  }, []);

  const closeMobileFilter = useCallback(() => setIsMobileFilterOpen(false), []);

  const closeDesktopDrawer = useCallback(() => setIsDesktopDrawerOpen(false), []);

  const handleRoomSearchChange = useCallback((patch) => {
    setRoomSearch((prev) => ({ ...prev, ...patch }));
  }, []);

  // Explicit "Tìm kiếm" click: apply the draft. This is the ONLY path that
  // hits the API / URL — editing filter-bar inputs never fetches. (FX-3)
  const handleRoomSearch = useCallback(() => {
    setAppliedRoomSearch(roomSearch);
  }, [roomSearch]);

  // "Bộ lọc" button: bottom sheet on mobile, right drawer on desktop.
  const openFilterPanel = useCallback(() => {
    preloadRoomFilterPanel();
    if (isMobileViewport) setIsMobileFilterOpen(true);
    else setIsDesktopDrawerOpen(true);
  }, [isMobileViewport]);

  const activeFilterCount = useMemo(() => countActiveFilters(roomFilters), [roomFilters]);

  // Search-bar pre-filter (FE-8): guest fit + availability quantity from the
  // API response. Runs on the APPLIED search only (FX-3).
  const availabilityFilteredRooms = useMemo(
    () => filterRoomsByAvailabilityAndGuests(
      rooms,
      appliedRoomSearch.checkIn,
      appliedRoomSearch.checkOut,
      appliedRoomSearch.adults,
      appliedRoomSearch.rooms
    ),
    [rooms, appliedRoomSearch]
  );

  const filteredRooms = useMemo(
    () => filterRooms(availabilityFilteredRooms, debouncedRoomFilters),
    [availabilityFilteredRooms, debouncedRoomFilters]
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
              <div
                key={selectedImageIndex}
                className={cn('h-full w-full', gallerySlideClass)}
              >
                <img
                  src={images[selectedImageIndex]?.url || images[0]?.url}
                  alt={images[selectedImageIndex]?.alt_text || hotel.name}
                  className="w-full h-full object-cover cursor-pointer"
                  fetchPriority="high"
                  decoding="async"
                  onClick={() => {
                    setCurrentGalleryImages(images);
                    setGalleryDirection(0);
                    setSelectedImageIndex(0);
                    setIsGalleryOpen(true);
                  }}
                />
              </div>
              {images.length > 1 && (
                <>
                  <div className="absolute bottom-4 right-4 bg-black/60 text-white px-3 py-1 rounded-lg text-sm">
                    {selectedImageIndex + 1} / {images.length}
                  </div>
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); goHotelImage(-1, images.length); }}
                    className="absolute left-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-white/90 hover:bg-white shadow"
                    aria-label={t('hotelDetail.gallery.prev')}
                  >
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); goHotelImage(1, images.length); }}
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
                onClick={() => {
                  setGalleryDirection(idx > selectedImageIndex ? 1 : idx < selectedImageIndex ? -1 : 0);
                  setSelectedImageIndex(idx);
                }}
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

      <div>
        {/* Main content */}
        <div className="min-w-0">
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
                  <span className="text-[#5c5852] text-sm">{t('hotels.reviewCount', { count: Number(hotel.review_count) })}</span>
                )}
              </div>
            </div>
            <WishlistHeart hotelId={id} checkIn={checkIn} checkOut={checkOut} />
          </div>

          {/* Tabs */}
          <div className="sticky top-[var(--header-height,0px)] z-40 bg-white/95 backdrop-blur border-b border-[#e8e4dd] mb-6">
            <nav className="flex gap-4 overflow-x-auto px-4 py-3" aria-label={t('hotelDetail.tabs.ariaLabel')}>
              {TABS.map((tab) => (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={cn(
                    'py-3 px-1 border-b-2 font-semibold text-base sm:text-lg whitespace-nowrap transition-colors',
                    activeTab === tab.key ? 'border-[#b8860b] text-[#b8860b]' : 'border-transparent text-[#5c5852] hover:text-[#1a1a1a]'
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
                  <HotelRoomFilterBar
                    roomSearch={roomSearch}
                    onSearchChange={handleRoomSearchChange}
                    onSearch={handleRoomSearch}
                    onFilterClick={openFilterPanel}
                    isLoading={isLoading || isFetching}
                    activeFilterCount={activeFilterCount}
                  />
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-[#5c5852]">
                      {t('hotelDetail.rooms.roomsCount', { count: sortedRooms.length })}
                    </p>
                    <div className="flex items-center gap-2">
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

                  {/* Filters live in the drawer on desktop (FE-6) / bottom sheet on mobile */}
                  <div className="flex-1 min-w-0">
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
                                {/* h2 (not h3): keeps heading order h1 → h2 for axe */}
                                <h2 className="font-semibold text-[#1a1a1a] text-lg">{type}</h2>
                                <span className="text-sm text-[#5c5852]">
                                  {t('hotelDetail.rooms.roomsCount', { count: typeRooms.length })}
                                </span>
                              </div>
                              {/* FX-4: full-width vertical list (Booking.com style).
                                  Every group — even a single-room one — spans the
                                  same width as the filter bar above. */}
                              <div className="flex flex-col gap-4">
                                {typeRooms.map((room) => (
                                  <RoomCard
                                    key={room.id}
                                    room={room}
                                    variant="detailed"
                                    nights={nights}
                                    onViewDetails={openRoomModal}
                                    className="w-full"
                                  />
                                ))}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
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

                  {/* Desktop filters live in the panel's own right drawer (FE-6) */}
                  {!isMobileViewport && isDesktopDrawerOpen && (
                    <Suspense fallback={null}>
                      <LazyRoomFilterPanel
                        rooms={rooms}
                        filters={roomFilters}
                        onFiltersChange={setRoomFilters}
                        position="right"
                        onClose={closeDesktopDrawer}
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
            <div key={selectedImageIndex} className={cn('flex justify-center', gallerySlideClass)}>
              <img
                src={currentGalleryImages[selectedImageIndex]?.url}
                alt={currentGalleryImages[selectedImageIndex]?.alt_text || hotel.name}
                className="max-w-full max-h-[80vh] object-contain rounded-lg"
              />
            </div>
            {currentGalleryImages.length > 1 && (
              <>
                <button
                  onClick={() => goHotelImage(-1, currentGalleryImages.length)}
                  className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white"
                  aria-label={t('hotelDetail.gallery.prev')}
                >
                  <ChevronLeft className="w-6 h-6" />
                </button>
                <button
                  onClick={() => goHotelImage(1, currentGalleryImages.length)}
                  className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white"
                  aria-label={t('hotelDetail.gallery.next')}
                >
                  <ChevronRight className="w-6 h-6" />
                </button>
                <div className="flex justify-center items-center gap-1.5 mt-4">
                  {currentGalleryImages.map((_, idx) => (
                    <button
                      key={idx}
                      onClick={() => {
                        setGalleryDirection(idx > selectedImageIndex ? 1 : idx < selectedImageIndex ? -1 : 0);
                        setSelectedImageIndex(idx);
                      }}
                      // min-h-0/min-w-0/p-0: override global 44px rule so dots stay tiny.
                      className={cn(
                        'h-1.5 w-1.5 min-h-0 min-w-0 shrink-0 rounded-full p-0 transition-colors duration-200',
                        selectedImageIndex === idx ? 'scale-110 bg-white' : 'bg-white/50 hover:bg-white/75'
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