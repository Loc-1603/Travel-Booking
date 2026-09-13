import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Heart, Loader2, Search } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../contexts/AuthContext';
import { TourCard } from '../components/TourCard';
import ErrorMessage from '../components/ErrorMessage';

export default function SavedTours() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const queryClient = useQueryClient();

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['saved-tours'],
    queryFn: async () => {
      const res = await api.get('/saved-tours');
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed');
      return res.data;
    },
    enabled: !!user,
  });

  const removeMutation = useMutation({
    mutationFn: (tourId) => api.delete(`/saved-tours/${tourId}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['saved-tours'] }),
  });

  const items = data?.data?.data ?? data?.data ?? [];

  if (!user) {
    return (
      <div className="py-12">
        <div className="rounded-2xl border border-[#e8e4dd] bg-white p-8 text-center max-w-md mx-auto">
          <Heart className="w-8 h-8 mx-auto mb-4 text-[#7a756d]" />
          <h2 className="text-xl font-semibold mb-2">{t('tours.saved.loginRequired')}</h2>
          <Link to="/login" className="inline-flex px-6 py-3 rounded-xl bg-[#1a1a1a] text-white font-medium mt-2">
            {t('auth.login.submit')}
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="py-6 sm:py-8">
      <div className="max-w-6xl mx-auto">
        <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[#1a1a1a]">{t('tours.saved.title')}</h1>
        <p className="text-[#5c5852] mt-1 mb-8">{t('tours.saved.subtitle', { count: items.length })}</p>

        {isLoading && (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="w-8 h-8 animate-spin text-[#b8860b]" />
          </div>
        )}
        {isError && <ErrorMessage message={error?.response?.data?.message || error?.message} onRetry={() => refetch()} />}
        {!isLoading && !isError && items.length === 0 && (
          <div className="rounded-2xl border border-[#e8e4dd] bg-white p-12 text-center">
            <Heart className="w-10 h-10 mx-auto mb-4 text-[#b8860b]" />
            <p className="text-[#5c5852] mb-6">{t('tours.saved.empty')}</p>
            <Link to="/tours" className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[#1a1a1a] text-white font-medium">
              <Search className="w-5 h-5" /> {t('tours.bookings.browse')}
            </Link>
          </div>
        )}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          {items.map((item) => {
            const tour = item.tour;
            if (!tour) return null;
            return (
              <TourCard
                key={item.id}
                tour={tour}
                imageOverlay={
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      removeMutation.mutate(tour.id);
                    }}
                    disabled={removeMutation.isPending}
                    className="flex items-center justify-center w-10 h-10 rounded-full bg-white/95 shadow-md disabled:opacity-60"
                    aria-label={t('tours.save.remove')}
                  >
                    {removeMutation.isPending ? (
                      <Loader2 className="w-5 h-5 animate-spin text-[#5c5852]" />
                    ) : (
                      <Heart className="w-5 h-5 text-[#b8860b] fill-[#b8860b]" />
                    )}
                  </button>
                }
              />
            );
          })}
        </div>
      </div>
    </div>
  );
}
