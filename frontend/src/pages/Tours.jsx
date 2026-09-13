import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { MapPin } from 'lucide-react';
import { api } from '../lib/api';
import { Skeleton } from '../components/Skeleton';
import ErrorMessage from '../components/ErrorMessage';

/**
 * Tour landing: province exploration only.
 * Picking a province goes to /tours/province/:slug
 * (attractions + ranked 1vs1 guides). No inline tour search here.
 */
export default function Tours() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const { data: provincesData, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tour-provinces'],
    queryFn: async () => {
      const res = await api.get('/tour-provinces');
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed');
      return res.data;
    },
    staleTime: 5 * 60_000,
  });
  const provinces = provincesData?.data ?? [];

  return (
    <div className="py-6 sm:py-8">
      <div className="max-w-6xl mx-auto">
        <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a]">{t('tours.list.title')}</h1>
        <p className="text-[#5c5852] mt-1 mb-6">{t('tours.list.subtitle')}</p>

        <h2 className="font-semibold text-[#1a1a1a] mb-3">{t('tours.provinces.title')}</h2>

        {isLoading && (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
              <div key={i} className="rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white">
                <Skeleton className="h-28 sm:h-32 w-full rounded-none" />
                <div className="p-3 space-y-2">
                  <Skeleton className="h-4 w-3/4" />
                  <Skeleton className="h-3 w-1/2" />
                </div>
              </div>
            ))}
          </div>
        )}
        {isError && <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />}
        {!isLoading && !isError && provinces.length === 0 && (
          <p className="text-[#5c5852] py-8 text-center">{t('tours.list.empty')}</p>
        )}
        {!isLoading && !isError && provinces.length > 0 && (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            {provinces.map((p) => (
              <button
                key={p.id}
                type="button"
                onClick={() => navigate(`/tours/province/${p.slug}`)}
                className="group flex! flex-col! items-stretch! justify-start! rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white hover:border-[#b8860b] transition-colors text-left p-0!"
              >
                <div className="w-full shrink-0 aspect-[16/9] bg-[#e8e4dd] relative overflow-hidden">
                  {p.image ? (
                    <img src={p.image} alt={p.name} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-[#a39e94]">
                      <MapPin className="w-8 h-8" />
                    </div>
                  )}
                </div>
                <div className="w-full p-3">
                  <p className="font-semibold text-[#1a1a1a]">{p.name}</p>
                  <p className="text-sm text-[#7a756d]">
                    {p.tours_count ?? 0} {t('tours.provinces.tours')}
                    {p.attractions?.length > 0 && ` · ${p.attractions.slice(0, 2).map((a) => a.name).join(', ')}`}
                  </p>
                </div>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
