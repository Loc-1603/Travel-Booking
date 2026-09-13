import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Loader2, Star } from 'lucide-react';
import { api } from '../lib/api';

export function TourReviewList({ tourUuid, providerUuid }) {
  const { t } = useTranslation();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['tour-reviews', tourUuid ?? providerUuid],
    queryFn: async () => {
      const params = { per_page: 10 };
      if (tourUuid) params.tour_uuid = tourUuid;
      if (providerUuid) params.provider_uuid = providerUuid;
      const res = await api.get('/tour-reviews', { params });
      return res.data;
    },
    enabled: !!(tourUuid || providerUuid),
  });

  if (isLoading) return <Loader2 className="w-5 h-5 animate-spin text-[#b8860b]" />;
  if (isError) return null;

  const payload = data?.data ?? {};
  const reviews = Array.isArray(payload?.data) ? payload.data : [];
  if (reviews.length === 0) {
    return <p className="text-sm text-[#7a756d]">{t('tours.reviews.empty')}</p>;
  }

  return (
    <div className="space-y-3">
      {reviews.map((r) => (
        <div key={r.id} className="rounded-xl border border-[#e8e4dd] bg-white p-4">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-1 text-[#b8860b]" aria-label={`${r.rating}/5`}>
              {[1, 2, 3, 4, 5].map((s) => (
                <Star key={s} className={`w-4 h-4 ${s <= r.rating ? 'fill-current' : 'text-stone-300'}`} />
              ))}
            </div>
            {(r.customer_name || r.tour?.title) && (
              <p className="text-xs text-[#7a756d] truncate">
                {[r.customer_name, r.tour?.title].filter(Boolean).join(' · ')}
              </p>
            )}
          </div>
          {r.comment && <p className="text-sm text-[#45423d] mt-2">{r.comment}</p>}
        </div>
      ))}
    </div>
  );
}
