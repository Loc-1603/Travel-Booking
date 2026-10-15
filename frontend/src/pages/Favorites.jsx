import { Link } from 'react-router-dom';
import { Heart, Search } from 'lucide-react';
import { useAuth } from '../contexts/useAuth';
import { useWishlist } from '../hooks/useWishlist';
import { useSavedGuides } from '../hooks/useSavedGuides';
import { HotelCard } from '../components/HotelCard';
import { VendorGuideCard } from '../components/VendorGuideCard';
import ErrorMessage from '../components/ErrorMessage';
import { useTranslation } from 'react-i18next';

function FavoritesCardSkeleton() {
  return (
    <div className="rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)]">
      <div className="aspect-[4/3] bg-[#e8e4dd] animate-pulse" />
      <div className="p-5 space-y-3">
        <div className="h-5 w-3/4 bg-[#e8e4dd] rounded" />
        <div className="h-4 w-1/2 bg-[#f5f2ed] rounded" />
        <div className="h-4 w-1/3 bg-[#f5f2ed] rounded" />
        <div className="h-6 w-20 bg-[#e8e4dd] rounded mt-4" />
      </div>
    </div>
  );
}

const BADGE_CLASS =
  'absolute top-3 left-3 z-10 inline-flex items-center px-2.5 py-1 rounded-full bg-white/95 border border-[#e8e4dd] shadow-sm text-xs font-semibold text-[#45423d]';

const savedAt = (item) => {
  const ts = Date.parse(item?.created_at ?? '');
  return Number.isFinite(ts) ? ts : 0;
};

/**
 * Unified favorites page: saved hotels (wishlist) + saved guides.
 * Each card carries a top-left badge to tell the two kinds apart.
 */
export default function Favorites() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const {
    wishlist,
    removeFromWishlist,
    removePending: hotelRemovePending,
    isLoading: hotelsLoading,
    isError: hotelsError,
    error: hotelsErrorObj,
    refetch: refetchHotels,
  } = useWishlist(!!user);
  const {
    savedGuides,
    unsaveGuide,
    removePending: guideRemovePending,
    isLoading: guidesLoading,
    isError: guidesError,
    error: guidesErrorObj,
    refetch: refetchGuides,
  } = useSavedGuides(!!user);

  if (!user) {
    return (
      <div className="py-12 sm:py-16">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] p-8 sm:p-12 text-center max-w-md mx-auto">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#f5f2ed] text-[#7a756d] mb-4">
            <Heart className="w-8 h-8" />
          </div>
          <h2 className="font-serif text-xl font-semibold text-[#1a1a1a] mb-2">{t('favorites.guests.signInToView')}</h2>
          <p className="text-[#5c5852] mb-6">{t('favorites.guests.signInToSave')}</p>
          <Link
            to="/login"
            className="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-[#1a1a1a] text-white font-medium hover:bg-[#2d2a28] transition-colors"
          >
            {t('auth.login.submit')}
          </Link>
        </div>
      </div>
    );
  }

  const isLoading = hotelsLoading || guidesLoading;
  const isError = hotelsError || guidesError;
  const refetchAll = () => {
    refetchHotels();
    refetchGuides();
  };

  // Merge hotels + guides into one list, most recently saved first.
  const entries = [
    ...(Array.isArray(wishlist) ? wishlist : []).map((item) => {
      const hotel = item.hotel || item;
      const hotelId = item.hotel_id ?? hotel?.id;
      if (!hotel || !hotelId) return null;
      return { type: 'hotel', key: `hotel-${item.id ?? hotelId}`, at: savedAt(item), item, hotel, hotelId };
    }),
    ...(Array.isArray(savedGuides) ? savedGuides : []).map((item) => {
      if (!item?.guide?.uuid) return null;
      return { type: 'guide', key: `guide-${item.id ?? item.guide.uuid}`, at: savedAt(item), item };
    }),
  ].filter(Boolean).sort((a, b) => b.at - a.at);

  const handleRemoveHotel = async (hotelId) => {
    try {
      await removeFromWishlist(hotelId);
    } catch { /* rollback handled by the optimistic update in useWishlist */ }
  };

  const handleRemoveGuide = async (providerUuid) => {
    try {
      await unsaveGuide(providerUuid);
    } catch { /* rollback handled by the optimistic update in useSavedGuides */ }
  };

  if (isLoading) {
    return (
      <div className="py-6 sm:py-8 lg:py-10">
        <div className="max-w-6xl mx-auto">
          <div className="mb-8">
            <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a] tracking-tight">{t('favorites.title')}</h1>
            <p className="text-[#5c5852] mt-1">{t('favorites.subtitle', { count: 0 })}</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 sm:gap-6">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <FavoritesCardSkeleton key={i} />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="py-6 sm:py-8">
        <div className="max-w-6xl mx-auto">
          <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a] mb-6">{t('favorites.title')}</h1>
          <div className="rounded-2xl border border-red-200 bg-red-50/50 p-6">
            <ErrorMessage
              message={
                hotelsErrorObj?.response?.data?.message ||
                hotelsErrorObj?.message ||
                guidesErrorObj?.response?.data?.message ||
                guidesErrorObj?.message ||
                t('favorites.errors.couldNotLoad')
              }
              onRetry={refetchAll}
            />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="py-6 sm:py-8 lg:py-10">
      <div className="max-w-6xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
          <div>
            <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a] tracking-tight">{t('favorites.title')}</h1>
            <p className="text-[#5c5852] mt-1">
              {entries.length === 0
                ? t('favorites.empty.description')
                : t('favorites.subtitle', { count: entries.length })}
            </p>
          </div>
          {entries.length > 0 && (
            <Link
              to="/hotels"
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-[#d4cec4] bg-white hover:bg-[#faf8f5] text-sm font-medium text-[#45423d] transition-colors shrink-0"
            >
              <Search className="w-4 h-4" />
              {t('favorites.actions.discoverMore')}
            </Link>
          )}
        </div>

        {entries.length === 0 ? (
          <div className="rounded-2xl border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] p-12 sm:p-16 text-center">
            <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#f9edd1] text-[#b8860b] mb-6">
              <Heart className="w-10 h-10" />
            </div>
            <h3 className="font-serif text-lg font-semibold text-[#1a1a1a] mb-2">{t('favorites.empty.title')}</h3>
            <p className="text-[#5c5852] mb-8 max-w-sm mx-auto">{t('favorites.empty.description')}</p>
            <div className="flex flex-wrap items-center justify-center gap-3">
              <Link
                to="/hotels"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-[#1a1a1a] text-white font-medium hover:bg-[#2d2a28] transition-colors"
              >
                <Search className="w-5 h-5" />
                {t('favorites.empty.searchHotels')}
              </Link>
              <Link
                to="/tours"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-[#d4cec4] bg-white font-medium text-[#45423d] hover:bg-[#faf8f5] transition-colors"
              >
                {t('favorites.empty.browseTours')}
              </Link>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 sm:gap-6">
            {entries.map((entry) => {
              if (entry.type === 'hotel') {
                const { item, hotel, hotelId } = entry;
                const nights = item.check_in && item.check_out
                  ? Math.ceil((new Date(item.check_out) - new Date(item.check_in)) / (1000 * 60 * 60 * 24))
                  : null;
                return (
                  <div key={entry.key} className="relative">
                    <span className={BADGE_CLASS}>{t('favorites.badges.hotel')}</span>
                    <HotelCard
                      hotel={hotel}
                      nights={nights ?? undefined}
                      to={`/hotels/${hotelId}${item.check_in && item.check_out ? `?check_in=${item.check_in}&check_out=${item.check_out}` : ''}`}
                      imageOverlay={
                        <button
                          type="button"
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            handleRemoveHotel(hotelId);
                          }}
                          disabled={hotelRemovePending}
                          className="flex items-center justify-center w-10 h-10 rounded-full bg-white/95 shadow-md hover:bg-white hover:shadow-lg transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#b8860b]/50 focus:ring-offset-2"
                          aria-label={t('favorites.actions.remove')}
                          title={t('favorites.actions.remove')}
                        >
                          <Heart className="w-5 h-5 text-[#b8860b] fill-[#b8860b]" />
                        </button>
                      }
                    >
                      <span className="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-[#b8860b] group-hover:text-[#996f09]">
                        {t('favorites.actions.viewDetails')}
                      </span>
                    </HotelCard>
                  </div>
                );
              }

              return (
                <div key={entry.key} className="relative">
                  <span className={BADGE_CLASS}>{t('favorites.badges.guide')}</span>
                  <VendorGuideCard guide={entry.item.guide} />
                  <button
                    type="button"
                    onClick={() => handleRemoveGuide(entry.item.guide.uuid)}
                    disabled={guideRemovePending}
                    className="absolute top-3 right-3 z-10 flex items-center justify-center w-10 h-10 rounded-full bg-white/95 shadow-md hover:bg-white hover:shadow-lg transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#b8860b]/50 focus:ring-offset-2"
                    aria-label={t('favorites.actions.remove')}
                    title={t('favorites.actions.remove')}
                  >
                    <Heart className="w-5 h-5 text-[#b8860b] fill-[#b8860b]" />
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
