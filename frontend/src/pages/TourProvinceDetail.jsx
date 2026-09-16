import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, MapPin, Search } from 'lucide-react';
import { api } from '../lib/api';
import { HotelDetailSkeleton, Skeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';
import { VendorGuideCard } from '../components/VendorGuideCard';
import { parseGuideSearchResponse } from '../lib/guideSearch';
import { todayISO } from '../lib/guideSearch';

/**
 * Province detail: hero + attractions with photos, then ranked 1vs1 guides.
 * Luồng mới 1 ngày: chọn ngày đi -> lọc guide trống đúng ngày đó.
 * GIỮ NGUYÊN: hero ảnh tỉnh + Điểm nổi bật (attractions) như hiện tại.
 */
export default function TourProvinceDetail() {
  const { t } = useTranslation();
  const { slug } = useParams();
  const today = todayISO();
  // Đồng bộ bộ lọc vào URL (?date=&sort=) để từ GuideDetail quay lại
  // (nút back hoặc browser back) vẫn giữ nguyên trạng thái lọc.
  const [searchParams, setSearchParams] = useSearchParams();
  const initialDate = searchParams.get('date') || '';
  const initialSort = searchParams.get('sort') || 'score';

  const [travelDateInput, setTravelDateInput] = useState(initialDate);
  const [sort, setSort] = useState(initialSort);
  const [applied, setApplied] = useState({ travel_date: initialDate, sort: initialSort });

  const {
    data: provinceData,
    isLoading: provinceLoading,
    isError: provinceError,
    error: provinceErr,
    refetch: refetchProvince,
  } = useQuery({
    queryKey: ['tour-province', slug],
    queryFn: async () => {
      const res = await api.get(`/tour-provinces/${slug}`);
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load province');
      return res.data;
    },
    enabled: !!slug,
    staleTime: 5 * 60_000,
  });
  const province = provinceData?.data ?? null;
  const attractions = province?.attractions ?? [];

  const {
    data: guidesData,
    isLoading: guidesLoading,
    isError: guidesError,
    error: guidesErr,
    refetch: refetchGuides,
  } = useQuery({
    queryKey: ['tour-providers', slug, applied],
    queryFn: async () => {
      const params = { province_slug: slug, per_page: 24, sort: applied.sort || 'score' };
      if (applied.travel_date) params.travel_date = applied.travel_date;
      const res = await api.get('/tour-providers', { params });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load guides');
      return res.data;
    },
    enabled: !!slug && !!applied.travel_date,
  });
  const { guides, total } = parseGuideSearchResponse(guidesData);
  const hasFiltered = !!applied.travel_date;

  const applyFilter = () => {
    const next = { travel_date: travelDateInput, sort };
    setApplied(next);
    setSearchParams({ date: next.travel_date, sort: next.sort });
  };
  const clearFilter = () => {
    setTravelDateInput('');
    setSort('score');
    setApplied({ travel_date: '', sort: 'score' });
    setSearchParams({});
  };

  if (provinceLoading) {
    return (
      <div className="py-6">
        <HotelDetailSkeleton />
      </div>
    );
  }
  if (provinceError || !province) {
    return (
      <div className="py-6">
        <ErrorMessage message={provinceErr?.response?.data?.message || provinceErr?.message} onRetry={() => refetchProvince()} />
      </div>
    );
  }

  return (
    <div className="py-4 sm:py-6">
      <Link to="/tours" className="inline-flex items-center gap-1.5 text-sm text-[#5c5852] hover:text-[#b8860b] mb-4">
        <ArrowLeft className="w-4 h-4" /> {t('tours.provinceDetail.back')}
      </Link>

      {/* Hero */}
      <div className="relative rounded-2xl overflow-hidden bg-[#e8e4dd] aspect-[21/9] sm:aspect-[3/1] max-h-[360px]">
        <div className="absolute inset-0 flex items-center justify-center text-[#5c5852]">
          <MapPin className="w-16 h-16" />
        </div>
        {province.image && (
          <img
            src={province.image}
            alt={province.name}
            className="absolute inset-0 w-full h-full object-cover"
            onError={(e) => { e.currentTarget.style.display = 'none'; }}
          />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent" />
        <div className="absolute bottom-0 left-0 right-0 p-5 sm:p-8">
          <h1 className="font-serif text-2xl sm:text-4xl font-semibold text-white">{province.name}</h1>
          {province.tours_count != null && (
            <p className="text-white/80 text-sm mt-1">
              {province.tours_count} {t('tours.provinces.tours')}
            </p>
          )}
        </div>
      </div>
      {province.description && (
        <p className="mt-4 text-[#45423d] leading-relaxed max-w-4xl">{province.description}</p>
      )}

      {/* Attractions */}
      {attractions.length > 0 && (
        <div className="mt-8">
          <h2 className="font-semibold text-[#1a1a1a] text-lg mb-3">{t('tours.provinceDetail.attractions')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {attractions.map((a) => (
              <div key={a.id} className="rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white">
                <div className="relative aspect-[16/9] bg-[#e8e4dd] flex items-center justify-center text-[#a39e94]">
                  <MapPin className="w-8 h-8" />
                  {a.image && (
                    <img
                      src={a.image}
                      alt={a.name}
                      className="absolute inset-0 w-full h-full object-cover"
                      loading="lazy"
                      onError={(e) => { e.currentTarget.style.display = 'none'; }}
                    />
                  )}
                </div>
                <div className="p-4">
                  <p className="font-medium text-[#1a1a1a]">
                    {a.name}
                    {a.is_famous && (
                      <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-[#f9edd1] text-[#996f09]">
                        ★ {t('tours.detail.famous')}
                      </span>
                    )}
                  </p>
                  {a.description && <p className="text-sm text-[#5c5852] mt-1">{a.description}</p>}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Guides */}
      <div className="mt-8">
        <h2 className="font-semibold text-[#1a1a1a] text-lg">{t('tours.provinceDetail.guides')}</h2>
        <p className="text-sm text-[#7a756d] mt-0.5 mb-4">{t('tours.provinceDetail.guidesHint')}</p>

        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-4 mb-6 flex flex-col sm:flex-row gap-3 sm:items-end">
          <div>
            <label className="block text-sm font-medium text-[#45423d] mb-1">{t('tours.provinceDetail.travelDate')}</label>
            <input
              type="date"
              value={travelDateInput}
              min={today}
              onChange={(e) => setTravelDateInput(e.target.value)}
              className="rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[#45423d] mb-1">{t('tours.provinceDetail.sort')}</label>
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value)}
              className="rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            >
              <option value="score">{t('tours.provinceDetail.sortScore')}</option>
              <option value="rating">{t('tours.provinceDetail.sortRating')}</option>
              <option value="price_low">{t('tours.provinceDetail.sortPriceLow')}</option>
            </select>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={applyFilter}
              disabled={!travelDateInput}
              className="inline-flex items-center justify-center gap-2 px-5 py-2 rounded-xl bg-[#1a1a1a] text-white text-sm font-medium hover:bg-[#2d2a28] disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Search className="w-4 h-4" />
              {t('tours.provinceDetail.filter')}
            </button>
            {applied.travel_date && (
              <button
                type="button"
                onClick={clearFilter}
                className="px-4 py-2 rounded-xl border border-[#e8e4dd] text-sm font-medium hover:bg-[#faf8f5]"
              >
                {t('tours.provinceDetail.clear')}
              </button>
            )}
          </div>
        </div>

        {!hasFiltered && (
          <p className="text-[#5c5852] py-8 text-center">{t('tours.provinceDetail.emptyGuidesNoFilter')}</p>
        )}
        {hasFiltered && guidesLoading && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <Skeleton key={i} />
            ))}
          </div>
        )}
        {hasFiltered && guidesError && <ErrorMessage message={guidesErr?.response?.data?.message || guidesErr?.message} onRetry={() => refetchGuides()} />}
        {hasFiltered && !guidesLoading && !guidesError && guides.length === 0 && (
          <p className="text-[#5c5852] py-8 text-center">{t('tours.provinceDetail.emptyGuides')}</p>
        )}
        {hasFiltered && !guidesLoading && !guidesError && guides.length > 0 && (
          <>
            <p className="text-sm text-[#7a756d] mb-4">
              {total} {t('tours.provinceDetail.results')}
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              {guides.map((g) => (
                <VendorGuideCard key={g.uuid} guide={g} provinceSlug={slug} travelDate={applied.travel_date} sort={applied.sort} />
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
